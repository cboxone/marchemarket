<?php

namespace Tests\Feature\Admin;

use App\Mail\DisputeUpdatedMail;
use App\Models\AuditLog;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use App\Services\KkiapayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DisputeResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function escalatedDispute(array $orderAttributes = []): Dispute
    {
        $vendor = Vendor::factory()->create();
        $order = Order::factory()->create($orderAttributes + [
            'status' => 'paid', 'payment_status' => 'paid', 'payment_reference' => 'txn_' . uniqid(),
            'shipping_fee' => 2000, 'total_amount' => 12000,
        ]);
        $order->items()->create(['vendor_id' => $vendor->id, 'product_name' => 'X', 'quantity' => 1, 'unit_price' => 10000]);

        return Dispute::factory()->escalated()->create(['order_id' => $order->id, 'vendor_id' => $vendor->id]);
    }

    // ---------- Accès ----------

    public function test_only_admins_can_access_dispute_arbitration(): void
    {
        $dispute = $this->escalatedDispute();

        $this->get(route('admin.disputes.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.disputes.index'))->assertForbidden();
        $this->actingAs($dispute->vendor->user)->post(route('admin.disputes.resolve', $dispute), ['decision' => 'reject', 'note' => 'x'])->assertForbidden();
    }

    // ---------- Éligibilité ----------

    public function test_only_an_escalated_dispute_can_be_resolved(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['open', 'resolved', 'withdrawn'] as $status) {
            $dispute = Dispute::factory()->create(['status' => $status]);
            $this->actingAs($admin)->post(route('admin.disputes.resolve', $dispute), ['decision' => 'reject', 'note' => 'x'])->assertNotFound();
        }
    }

    // ---------- Décisions ----------

    public function test_rejecting_notifies_both_parties_and_is_audited(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $dispute = $this->escalatedDispute();

        $this->actingAs($admin)->post(route('admin.disputes.resolve', $dispute), ['decision' => 'reject', 'note' => 'Livraison confirmée signée.'])
            ->assertSessionHasNoErrors();

        $fresh = $dispute->fresh();
        $this->assertSame('resolved', $fresh->status);
        $this->assertSame('reject', $fresh->decision);
        $this->assertEqualsWithDelta(0.0, (float) $fresh->refund_amount, 0.01);
        $this->assertSame('not_applicable', $fresh->refund_status);
        $this->assertSame($admin->id, $fresh->resolved_by);

        $this->assertSame(1, AuditLog::where('action', 'dispute.resolved')->count());
        $this->assertMailed(DisputeUpdatedMail::class, fn ($m) => $m->hasTo($dispute->buyer->email) && $m->event === 'resolved');
        $this->assertMailed(DisputeUpdatedMail::class, fn ($m) => $m->hasTo($dispute->vendor->user->email) && $m->event === 'resolved');
    }

    public function test_a_full_refund_on_a_single_vendor_order_is_attempted_automatically(): void
    {
        Mail::fake();
        $this->mock(KkiapayService::class, function ($mock) {
            $mock->shouldReceive('refundTransaction')->once()->andReturn(true);
        });

        $admin = User::factory()->admin()->create();
        $dispute = $this->escalatedDispute();

        $this->actingAs($admin)->post(route('admin.disputes.resolve', $dispute), ['decision' => 'refund_full', 'note' => 'Preuves convaincantes.']);

        $fresh = $dispute->fresh();
        $this->assertEqualsWithDelta(12000.0, (float) $fresh->refund_amount, 0.01);
        $this->assertSame('refunded_automatic', $fresh->refund_status);
    }

    public function test_a_partial_refund_amount_is_capped_at_the_ceiling(): void
    {
        $admin = User::factory()->admin()->create();
        $dispute = $this->escalatedDispute();

        $this->actingAs($admin)->post(route('admin.disputes.resolve', $dispute), ['decision' => 'refund_partial', 'amount' => 999999, 'note' => 'x'])
            ->assertSessionHasErrors('amount');

        $this->assertSame('escalated', $dispute->fresh()->status);
    }

    public function test_a_partial_refund_is_left_for_manual_processing(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $dispute = $this->escalatedDispute();

        $this->actingAs($admin)->post(route('admin.disputes.resolve', $dispute), ['decision' => 'refund_partial', 'amount' => 3000, 'note' => 'Article partiellement conforme.'])
            ->assertSessionHasNoErrors();

        $fresh = $dispute->fresh();
        $this->assertEqualsWithDelta(3000.0, (float) $fresh->refund_amount, 0.01);
        $this->assertSame('pending_manual', $fresh->refund_status); // pas 12 000, donc pas éligible au remboursement KkiaPay automatique
    }

    public function test_a_note_is_required(): void
    {
        $admin = User::factory()->admin()->create();
        $dispute = $this->escalatedDispute();

        $this->actingAs($admin)->post(route('admin.disputes.resolve', $dispute), ['decision' => 'reject'])->assertSessionHasErrors('note');
    }

    public function test_a_resolved_dispute_cannot_be_resolved_twice(): void
    {
        $admin = User::factory()->admin()->create();
        $dispute = $this->escalatedDispute();

        $this->actingAs($admin)->post(route('admin.disputes.resolve', $dispute), ['decision' => 'reject', 'note' => 'x']);
        $this->actingAs($admin)->post(route('admin.disputes.resolve', $dispute), ['decision' => 'refund_full', 'note' => 'y'])->assertNotFound();

        $this->assertSame('reject', $dispute->fresh()->decision);
    }

    // ---------- Remboursement manuel ----------

    public function test_marking_a_pending_manual_refund_as_done_is_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $dispute = $this->escalatedDispute();
        $dispute->update(['status' => 'resolved', 'decision' => 'refund_partial', 'refund_amount' => 3000, 'refund_status' => 'pending_manual']);

        $this->actingAs($admin)->post(route('admin.disputes.mark-refunded', $dispute))->assertSessionHasNoErrors();

        $fresh = $dispute->fresh();
        $this->assertSame('refunded_manual', $fresh->refund_status);
        $this->assertNotNull($fresh->refunded_at);
        $this->assertSame(1, AuditLog::where('action', 'dispute.refund_marked_manual')->count());
    }

    public function test_marking_as_refunded_twice_is_refused(): void
    {
        $admin = User::factory()->admin()->create();
        $dispute = $this->escalatedDispute();
        $dispute->update(['status' => 'resolved', 'decision' => 'refund_partial', 'refund_amount' => 3000, 'refund_status' => 'refunded_manual', 'refunded_at' => now()]);

        $this->actingAs($admin)->post(route('admin.disputes.mark-refunded', $dispute))->assertSessionHasErrors('dispute');
    }

    // ---------- Filtrage et retard ----------

    public function test_the_index_defaults_to_open_and_escalated_disputes(): void
    {
        $admin = User::factory()->admin()->create();
        $open = Dispute::factory()->create(['description' => 'Litige ouvert distinctif']);
        $escalated = $this->escalatedDispute();
        $resolved = Dispute::factory()->resolved('reject')->create(['description' => 'Litige résolu distinctif']);

        $this->actingAs($admin)->get(route('admin.disputes.index'))
            ->assertSee($open->reference)->assertSee($escalated->reference)->assertDontSee($resolved->reference);
    }

    public function test_an_overdue_escalated_dispute_is_flagged(): void
    {
        $admin = User::factory()->admin()->create();
        $dispute = $this->escalatedDispute();
        $dispute->update(['admin_target_at' => now()->subDay()]);

        $this->assertTrue($dispute->fresh()->adminOverdue());

        $this->actingAs($admin)->get(route('admin.disputes.index'))->assertOk()->assertSee('en retard');
    }
}
