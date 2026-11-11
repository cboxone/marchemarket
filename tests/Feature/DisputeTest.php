<?php

namespace Tests\Feature;

use App\Mail\DisputeUpdatedMail;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DisputeTest extends TestCase
{
    use RefreshDatabase;

    private function paidOrder(User $buyer, array $attributes = []): Order
    {
        return Order::factory()->create($attributes + [
            'buyer_id' => $buyer->id, 'status' => 'paid', 'payment_status' => 'paid',
            'payment_reference' => 'txn_' . uniqid(), 'shipping_fee' => 2000, 'total_amount' => 12000,
        ]);
    }

    private function item(Order $order, Vendor $vendor, array $attributes = [])
    {
        return $order->items()->create($attributes + [
            'vendor_id' => $vendor->id, 'product_name' => 'Sac de riz', 'quantity' => 1, 'unit_price' => 10000,
        ]);
    }

    // ---------- Accès et éligibilité ----------

    public function test_a_guest_is_redirected_to_login(): void
    {
        $order = $this->paidOrder(User::factory()->create());

        $this->get(route('disputes.create', $order))->assertRedirect(route('login'));
    }

    public function test_another_customer_cannot_open_a_dispute_on_someone_elses_order(): void
    {
        $order = $this->paidOrder(User::factory()->create());
        $this->item($order, Vendor::factory()->create());

        $this->actingAs(User::factory()->create())->get(route('disputes.create', $order))->assertForbidden();
    }

    public function test_an_unpaid_or_cancelled_order_cannot_be_disputed(): void
    {
        $buyer = User::factory()->create();
        $unpaid = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => 'pending', 'payment_status' => 'pending']);
        $cancelled = $this->paidOrder($buyer, ['status' => 'cancelled']);

        $this->actingAs($buyer)->get(route('disputes.create', $unpaid))->assertRedirect(route('orders.show', $unpaid))->assertSessionHasErrors('dispute');
        $this->actingAs($buyer)->get(route('disputes.create', $cancelled))->assertRedirect(route('orders.show', $cancelled))->assertSessionHasErrors('dispute');
    }

    public function test_the_window_is_open_shortly_after_delivery_and_closed_later(): void
    {
        $buyer = User::factory()->create();

        $recent = $this->paidOrder($buyer);
        $this->item($recent, Vendor::factory()->create(), ['delivered_at' => now()->subDays(2)]);

        $old = $this->paidOrder($buyer);
        $this->item($old, Vendor::factory()->create(), ['delivered_at' => now()->subDays(10)]);

        $this->actingAs($buyer)->get(route('disputes.create', $recent))->assertOk();
        $this->actingAs($buyer)->get(route('disputes.create', $old))->assertRedirect(route('orders.show', $old))->assertSessionHasErrors('dispute');
    }

    public function test_without_a_delivery_date_the_window_is_based_on_payment(): void
    {
        $buyer = User::factory()->create();

        $recent = $this->paidOrder($buyer, ['invoiced_at' => now()->subDays(5)]);
        $this->item($recent, Vendor::factory()->create());

        $old = $this->paidOrder($buyer, ['invoiced_at' => now()->subDays(20)]);
        $this->item($old, Vendor::factory()->create());

        $this->actingAs($buyer)->get(route('disputes.create', $recent))->assertOk();
        $this->actingAs($buyer)->get(route('disputes.create', $old))->assertRedirect(route('orders.show', $old))->assertSessionHasErrors('dispute');
    }

    public function test_a_vendor_with_an_already_open_dispute_is_excluded_from_the_choices(): void
    {
        $buyer = User::factory()->create();
        $order = $this->paidOrder($buyer);
        $vendorA = Vendor::factory()->create(['store_name' => 'Fournisseur A']);
        $vendorB = Vendor::factory()->create(['store_name' => 'Fournisseur B']);
        $this->item($order, $vendorA, ['delivered_at' => now()->subDay()]);
        $this->item($order, $vendorB, ['delivered_at' => now()->subDay()]);

        Dispute::factory()->create(['order_id' => $order->id, 'vendor_id' => $vendorA->id, 'buyer_id' => $buyer->id]);

        $this->actingAs($buyer)->get(route('disputes.create', $order))
            ->assertOk()->assertSee('Fournisseur B')->assertDontSee('Fournisseur A');
    }

    // ---------- Ouverture ----------

    public function test_opening_a_dispute_notifies_the_vendor(): void
    {
        Mail::fake();
        $buyer = User::factory()->create();
        $vendor = Vendor::factory()->create();
        $order = $this->paidOrder($buyer);
        $this->item($order, $vendor, ['delivered_at' => now()->subDay()]);

        $this->actingAs($buyer)->post(route('disputes.store', $order), [
            'vendor_id' => $vendor->id, 'reason' => 'not_received', 'description' => "Rien n'est arrivé après 10 jours.",
        ])->assertSessionHasNoErrors();

        $dispute = Dispute::first();
        $this->assertMatchesRegularExpression('/^LIT-[A-Z0-9]{8}$/', $dispute->reference);
        $this->assertSame('open', $dispute->status);
        $this->assertTrue($dispute->vendor_deadline_at->isSameDay(now()->addHours(72)));

        $this->assertMailed(DisputeUpdatedMail::class, fn ($m) => $m->hasTo($vendor->user->email) && $m->event === 'opened');
    }

    public function test_evidence_is_required_for_not_conforming_and_damaged(): void
    {
        $buyer = User::factory()->create();
        $vendor = Vendor::factory()->create();
        $order = $this->paidOrder($buyer);
        $this->item($order, $vendor, ['delivered_at' => now()->subDay()]);

        foreach (['not_conforming', 'damaged'] as $reason) {
            $this->actingAs($buyer)->post(route('disputes.store', $order), [
                'vendor_id' => $vendor->id, 'reason' => $reason, 'description' => 'Problème constaté.',
            ])->assertSessionHasErrors('evidence');
        }

        $this->assertDatabaseCount('disputes', 0);
    }

    public function test_evidence_is_stored_and_capped_at_five_photos(): void
    {
        Storage::fake('local');
        $buyer = User::factory()->create();
        $vendor = Vendor::factory()->create();
        $order = $this->paidOrder($buyer);
        $this->item($order, $vendor, ['delivered_at' => now()->subDay()]);

        $six = array_map(fn ($i) => UploadedFile::fake()->image("photo{$i}.jpg"), range(1, 6));

        $this->actingAs($buyer)->post(route('disputes.store', $order), [
            'vendor_id' => $vendor->id, 'reason' => 'damaged', 'description' => 'Cassé à la livraison.', 'evidence' => $six,
        ])->assertSessionHasErrors('evidence');

        $three = array_map(fn ($i) => UploadedFile::fake()->image("ok{$i}.jpg"), range(1, 3));

        $this->actingAs($buyer)->post(route('disputes.store', $order), [
            'vendor_id' => $vendor->id, 'reason' => 'damaged', 'description' => 'Cassé à la livraison.', 'evidence' => $three,
        ])->assertSessionHasNoErrors();

        $dispute = Dispute::first();
        $this->assertCount(3, $dispute->evidence_paths);
        Storage::disk('local')->assertExists($dispute->evidence_paths[0]);
    }

    public function test_a_second_dispute_on_the_same_order_and_vendor_is_refused_while_one_is_open(): void
    {
        Mail::fake();
        $buyer = User::factory()->create();
        $vendor = Vendor::factory()->create();
        $order = $this->paidOrder($buyer);
        $this->item($order, $vendor, ['delivered_at' => now()->subDay()]);

        $this->actingAs($buyer)->post(route('disputes.store', $order), ['vendor_id' => $vendor->id, 'reason' => 'not_received', 'description' => 'x']);

        $this->actingAs($buyer)->post(route('disputes.store', $order), ['vendor_id' => $vendor->id, 'reason' => 'not_received', 'description' => 'y'])
            ->assertSessionHasErrors('dispute');

        $this->assertDatabaseCount('disputes', 1);
    }

    public function test_disputes_are_rate_limited(): void
    {
        Mail::fake();
        $buyer = User::factory()->create();
        $order = $this->paidOrder($buyer);

        for ($i = 0; $i < 10; $i++) {
            $vendor = Vendor::factory()->create();
            $this->item($order, $vendor, ['delivered_at' => now()->subDay()]);
            $this->actingAs($buyer)->post(route('disputes.store', $order), ['vendor_id' => $vendor->id, 'reason' => 'not_received', 'description' => 'x'])
                ->assertStatus(302);
        }

        $vendor = Vendor::factory()->create();
        $this->item($order, $vendor, ['delivered_at' => now()->subDay()]);
        $this->actingAs($buyer)->post(route('disputes.store', $order), ['vendor_id' => $vendor->id, 'reason' => 'not_received', 'description' => 'x'])
            ->assertStatus(429);
    }

    // ---------- Consultation et retrait ----------

    public function test_another_customer_cannot_view_or_withdraw_my_dispute(): void
    {
        $dispute = Dispute::factory()->create();

        $this->actingAs(User::factory()->create())->get(route('disputes.show', $dispute))->assertForbidden();
        $this->actingAs(User::factory()->create())->post(route('disputes.withdraw', $dispute))->assertForbidden();
    }

    public function test_withdrawing_before_any_vendor_response_notifies_the_vendor(): void
    {
        Mail::fake();
        $buyer = User::factory()->create();
        $dispute = Dispute::factory()->create(['buyer_id' => $buyer->id]);

        $this->actingAs($buyer)->post(route('disputes.withdraw', $dispute))->assertSessionHasNoErrors();

        $this->assertSame('withdrawn', $dispute->fresh()->status);
        $this->assertMailed(DisputeUpdatedMail::class, fn ($m) => $m->event === 'withdrawn');
    }

    public function test_a_dispute_cannot_be_withdrawn_once_escalated_or_resolved(): void
    {
        $buyer = User::factory()->create();

        foreach (['escalated', 'resolved', 'withdrawn'] as $status) {
            $dispute = Dispute::factory()->create(['buyer_id' => $buyer->id, 'status' => $status]);
            $this->actingAs($buyer)->post(route('disputes.withdraw', $dispute))->assertSessionHasErrors('dispute');
        }
    }
}
