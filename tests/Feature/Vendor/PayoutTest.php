<?php

namespace Tests\Feature\Vendor;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutTest extends TestCase
{
    use RefreshDatabase;

    private function paidOrderItemFor(Vendor $vendor, float $unitPrice, int $quantity = 1): OrderItem
    {
        $order = Order::factory()->create(['payment_status' => 'paid']);

        return $order->items()->create([
            'vendor_id' => $vendor->id,
            'product_name' => 'Produit test',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
        ]);
    }

    public function test_available_balance_reflects_paid_orders_only(): void
    {
        $vendor = Vendor::factory()->create();
        $this->paidOrderItemFor($vendor, 10000);

        $unpaidOrder = Order::factory()->create(['payment_status' => 'pending']);
        $unpaidOrder->items()->create([
            'vendor_id' => $vendor->id,
            'product_name' => 'Non payé',
            'quantity' => 1,
            'unit_price' => 99999,
        ]);

        $this->assertSame(10000.0, $vendor->availableBalance());
    }

    public function test_a_vendor_can_request_a_payout_within_their_balance(): void
    {
        $vendor = Vendor::factory()->create();
        $this->paidOrderItemFor($vendor, 20000);

        $response = $this->actingAs($vendor->user)->post(route('vendor.payouts.store'), [
            'amount' => 15000,
            'payout_method' => 'MTN Mobile Money 90000000',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payouts', ['vendor_id' => $vendor->id, 'amount' => 15000, 'status' => 'pending']);
    }

    public function test_a_vendor_cannot_request_more_than_their_available_balance(): void
    {
        $vendor = Vendor::factory()->create();
        $this->paidOrderItemFor($vendor, 5000);

        $response = $this->actingAs($vendor->user)->post(route('vendor.payouts.store'), [
            'amount' => 50000,
            'payout_method' => 'MTN Mobile Money 90000000',
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('payouts', 0);
    }

    public function test_pending_payouts_reduce_the_available_balance(): void
    {
        $vendor = Vendor::factory()->create();
        $this->paidOrderItemFor($vendor, 20000);

        $vendor->payouts()->create(['amount' => 12000, 'status' => 'pending', 'payout_method' => 'test']);

        $this->assertSame(8000.0, $vendor->fresh()->availableBalance());
    }

    public function test_an_admin_can_mark_a_payout_as_paid(): void
    {
        $admin = User::factory()->admin()->create();
        $vendor = Vendor::factory()->create();
        $payout = $vendor->payouts()->create(['amount' => 5000, 'status' => 'pending', 'payout_method' => 'test']);

        $response = $this->actingAs($admin)->patch(route('admin.payouts.mark-paid', $payout), [
            'payout_reference' => 'TX-12345',
        ]);

        $response->assertRedirect();
        $this->assertSame('paid', $payout->fresh()->status);
        $this->assertSame('TX-12345', $payout->fresh()->payout_reference);
    }

    public function test_two_successive_requests_cannot_together_exceed_the_balance(): void
    {
        $vendor = Vendor::factory()->create();
        $this->paidOrderItemFor($vendor, 10000);

        $this->actingAs($vendor->user)->post(route('vendor.payouts.store'), ['amount' => 7000, 'payout_method' => 'MTN 90000000'])
            ->assertSessionHasNoErrors();

        $this->actingAs($vendor->user)->post(route('vendor.payouts.store'), ['amount' => 7000, 'payout_method' => 'MTN 90000000'])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('payouts', 1);
    }
}
