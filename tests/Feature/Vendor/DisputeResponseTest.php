<?php

namespace Tests\Feature\Vendor;

use App\Mail\DisputeUpdatedMail;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use App\Services\KkiapayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DisputeResponseTest extends TestCase
{
    use RefreshDatabase;

    private function disputeFor(Vendor $vendor, array $orderAttributes = [], array $itemAttributes = []): Dispute
    {
        $order = Order::factory()->create($orderAttributes + [
            'status' => 'paid', 'payment_status' => 'paid', 'payment_reference' => 'txn_' . uniqid(),
            'shipping_fee' => 2000, 'total_amount' => 12000,
        ]);
        $order->items()->create($itemAttributes + [
            'vendor_id' => $vendor->id, 'product_name' => 'Sac de riz', 'quantity' => 1, 'unit_price' => 10000,
        ]);

        return Dispute::factory()->create(['order_id' => $order->id, 'vendor_id' => $vendor->id]);
    }

    // ---------- Accès ----------

    public function test_a_customer_cannot_access_vendor_dispute_pages(): void
    {
        $dispute = Dispute::factory()->create();

        $this->actingAs(User::factory()->create())->get(route('vendor.disputes.index'))->assertForbidden();
    }

    public function test_a_vendor_cannot_see_or_answer_another_vendors_dispute(): void
    {
        $dispute = Dispute::factory()->create();
        $other = Vendor::factory()->create();

        $this->actingAs($other->user)->get(route('vendor.disputes.show', $dispute))->assertNotFound();
        $this->actingAs($other->user)->post(route('vendor.disputes.respond', $dispute), ['response_type' => 'accept_full'])->assertNotFound();
    }

    // ---------- Plafond ----------

    public function test_the_ceiling_includes_shipping_only_for_a_single_vendor_order_with_a_vendor_fault_reason(): void
    {
        $vendor = Vendor::factory()->create();

        $faultSingle = $this->disputeFor($vendor, [], []); // not_conforming par défaut dans la factory
        $this->assertEqualsWithDelta(12000.0, $faultSingle->ceiling(), 0.01); // 10000 + 2000 livraison

        $other = Dispute::factory()->create(['reason' => 'missing_qty']);
        $order = Order::factory()->create(['status' => 'paid', 'payment_status' => 'paid', 'shipping_fee' => 2000, 'total_amount' => 12000]);
        $order->items()->create(['vendor_id' => $other->vendor_id, 'product_name' => 'X', 'quantity' => 1, 'unit_price' => 10000]);
        $other->update(['order_id' => $order->id]);
        $this->assertEqualsWithDelta(10000.0, $other->fresh()->ceiling(), 0.01); // pas de livraison : motif non imputable au vendeur
    }

    public function test_the_ceiling_excludes_shipping_on_a_multi_vendor_order(): void
    {
        $vendorA = Vendor::factory()->create();
        $vendorB = Vendor::factory()->create();
        $order = Order::factory()->create(['status' => 'paid', 'payment_status' => 'paid', 'shipping_fee' => 2000, 'total_amount' => 22000]);
        $order->items()->create(['vendor_id' => $vendorA->id, 'product_name' => 'A', 'quantity' => 1, 'unit_price' => 10000]);
        $order->items()->create(['vendor_id' => $vendorB->id, 'product_name' => 'B', 'quantity' => 1, 'unit_price' => 10000]);

        $dispute = Dispute::factory()->create(['order_id' => $order->id, 'vendor_id' => $vendorA->id, 'reason' => 'not_received']);

        $this->assertEqualsWithDelta(10000.0, $dispute->ceiling(), 0.01); // pas de part de livraison
    }

    // ---------- Réponse : acceptation totale ----------

    public function test_accepting_in_full_resolves_immediately_and_notifies_the_buyer(): void
    {
        Mail::fake();
        $vendor = Vendor::factory()->create();
        $dispute = $this->disputeFor($vendor);

        $this->actingAs($vendor->user)->post(route('vendor.disputes.respond', $dispute), ['response_type' => 'accept_full'])
            ->assertSessionHasNoErrors();

        $fresh = $dispute->fresh();
        $this->assertSame('resolved', $fresh->status);
        $this->assertSame('refund_full', $fresh->decision);
        $this->assertEqualsWithDelta(12000.0, (float) $fresh->refund_amount, 0.01);
        $this->assertMailed(DisputeUpdatedMail::class, fn ($m) => $m->event === 'resolved' && $m->hasTo($dispute->buyer->email));
    }

    public function test_full_acceptance_on_a_single_vendor_order_attempts_an_automatic_kkiapay_refund(): void
    {
        Mail::fake();
        $this->mock(KkiapayService::class, function ($mock) {
            $mock->shouldReceive('refundTransaction')->once()->andReturn(true);
        });

        $vendor = Vendor::factory()->create();
        $dispute = $this->disputeFor($vendor);

        $this->actingAs($vendor->user)->post(route('vendor.disputes.respond', $dispute), ['response_type' => 'accept_full']);

        $fresh = $dispute->fresh();
        $this->assertSame('refunded_automatic', $fresh->refund_status);
        $this->assertNotNull($fresh->refunded_at);
    }

    public function test_a_failed_automatic_refund_is_left_for_manual_processing(): void
    {
        Mail::fake();
        $this->mock(KkiapayService::class, function ($mock) {
            $mock->shouldReceive('refundTransaction')->once()->andThrow(new \RuntimeException('API down'));
        });

        $vendor = Vendor::factory()->create();
        $dispute = $this->disputeFor($vendor);

        $this->actingAs($vendor->user)->post(route('vendor.disputes.respond', $dispute), ['response_type' => 'accept_full']);

        $fresh = $dispute->fresh();
        $this->assertSame('pending_manual', $fresh->refund_status);
        $this->assertNull($fresh->refunded_at);
    }

    // ---------- Réponse : partielle / contestation → arbitrage ----------

    public function test_a_partial_offer_escalates_to_admin_rather_than_resolving_directly(): void
    {
        Mail::fake();
        $vendor = Vendor::factory()->create();
        $dispute = $this->disputeFor($vendor);

        $this->actingAs($vendor->user)->post(route('vendor.disputes.respond', $dispute), [
            'response_type' => 'accept_partial', 'amount' => 5000,
        ])->assertSessionHasNoErrors();

        $fresh = $dispute->fresh();
        $this->assertSame('escalated', $fresh->status);
        $this->assertNull($fresh->decision); // pas encore tranché
        $this->assertEqualsWithDelta(5000.0, (float) $fresh->vendor_response_amount, 0.01);
        $this->assertMailed(DisputeUpdatedMail::class, fn ($m) => $m->event === 'vendor_responded');
    }

    public function test_a_partial_offer_above_the_ceiling_is_rejected(): void
    {
        $vendor = Vendor::factory()->create();
        $dispute = $this->disputeFor($vendor);

        $this->actingAs($vendor->user)->post(route('vendor.disputes.respond', $dispute), [
            'response_type' => 'accept_partial', 'amount' => 999999,
        ])->assertSessionHasErrors('amount');

        $this->assertSame('open', $dispute->fresh()->status);
    }

    public function test_contesting_requires_a_note_and_escalates(): void
    {
        Mail::fake();
        $vendor = Vendor::factory()->create();
        $dispute = $this->disputeFor($vendor);

        $this->actingAs($vendor->user)->post(route('vendor.disputes.respond', $dispute), ['response_type' => 'contest'])
            ->assertSessionHasErrors('note');

        $this->actingAs($vendor->user)->post(route('vendor.disputes.respond', $dispute), [
            'response_type' => 'contest', 'note' => "L'acheteur a signé le bon de livraison en bon état.",
        ])->assertSessionHasNoErrors();

        $this->assertSame('escalated', $dispute->fresh()->status);
    }

    // ---------- Échéance et double réponse ----------

    public function test_the_vendor_cannot_respond_twice(): void
    {
        Mail::fake();
        $vendor = Vendor::factory()->create();
        $dispute = $this->disputeFor($vendor);

        $this->actingAs($vendor->user)->post(route('vendor.disputes.respond', $dispute), ['response_type' => 'contest', 'note' => 'x']);
        $this->actingAs($vendor->user)->post(route('vendor.disputes.respond', $dispute), ['response_type' => 'accept_full'])
            ->assertSessionHasErrors('dispute');

        $this->assertSame('escalated', $dispute->fresh()->status);
    }

    public function test_an_overdue_dispute_cannot_be_answered_before_the_scheduled_command_ran(): void
    {
        $vendor = Vendor::factory()->create();
        $dispute = $this->disputeFor($vendor);
        $dispute->update(['vendor_deadline_at' => now()->subHour()]);

        $this->actingAs($vendor->user)->post(route('vendor.disputes.respond', $dispute), ['response_type' => 'accept_full'])
            ->assertSessionHasErrors('dispute');

        $this->assertSame('open', $dispute->fresh()->status);
    }

    public function test_the_dashboard_counts_only_open_disputes(): void
    {
        $vendor = Vendor::factory()->create();
        $this->disputeFor($vendor);
        $this->disputeFor($vendor)->update(['status' => 'escalated']);
        $this->disputeFor($vendor)->update(['status' => 'resolved']);

        $this->actingAs($vendor->user)->get(route('vendor.dashboard'))
            ->assertOk()->assertViewHas('stats', fn ($stats) => $stats['open_disputes'] === 1);
    }
}
