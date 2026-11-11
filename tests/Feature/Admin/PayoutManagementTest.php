<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_paid_payout_cannot_be_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $vendor = Vendor::factory()->create();
        $payout = $vendor->payouts()->create(['amount' => 5000, 'status' => 'paid', 'payout_method' => 'test']);

        $response = $this->actingAs($admin)->patch(route('admin.payouts.reject', $payout));

        $response->assertSessionHasErrors('payout');
        $this->assertSame('paid', $payout->fresh()->status);
    }

    public function test_a_rejected_payout_cannot_be_marked_as_paid(): void
    {
        $admin = User::factory()->admin()->create();
        $vendor = Vendor::factory()->create();
        $payout = $vendor->payouts()->create(['amount' => 5000, 'status' => 'rejected', 'payout_method' => 'test']);

        $response = $this->actingAs($admin)->patch(route('admin.payouts.mark-paid', $payout), ['payout_reference' => 'TX-1']);

        $response->assertSessionHasErrors('payout');
        $this->assertSame('rejected', $payout->fresh()->status);
        $this->assertNull($payout->fresh()->payout_reference);
    }

    public function test_a_paid_payout_cannot_be_marked_as_paid_twice(): void
    {
        $admin = User::factory()->admin()->create();
        $vendor = Vendor::factory()->create();
        $payout = $vendor->payouts()->create(['amount' => 5000, 'status' => 'paid', 'payout_reference' => 'TX-FIRST', 'payout_method' => 'test']);

        $this->actingAs($admin)->patch(route('admin.payouts.mark-paid', $payout), ['payout_reference' => 'TX-SECOND'])
            ->assertSessionHasErrors('payout');

        $this->assertSame('TX-FIRST', $payout->fresh()->payout_reference);
    }

    public function test_a_pending_payout_can_still_be_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $vendor = Vendor::factory()->create();
        $payout = $vendor->payouts()->create(['amount' => 5000, 'status' => 'pending', 'payout_method' => 'test']);

        $this->actingAs($admin)->patch(route('admin.payouts.reject', $payout), ['admin_note' => 'IBAN invalide'])
            ->assertSessionHasNoErrors();

        $this->assertSame('rejected', $payout->fresh()->status);
        $this->assertSame('IBAN invalide', $payout->fresh()->admin_note);
    }

    public function test_rejecting_a_paid_payout_does_not_free_the_vendor_balance(): void
    {
        $admin = User::factory()->admin()->create();
        $vendor = Vendor::factory()->create();

        $order = Order::factory()->create(['payment_status' => 'paid', 'status' => 'paid', 'total_amount' => 12000, 'shipping_fee' => 2000]);
        $order->items()->create(['vendor_id' => $vendor->id, 'product_name' => 'X', 'quantity' => 1, 'unit_price' => 10000]);
        $payout = $vendor->payouts()->create(['amount' => 10000, 'status' => 'paid', 'payout_method' => 'test']);

        $this->actingAs($admin)->patch(route('admin.payouts.reject', $payout));

        $this->assertSame(0.0, $vendor->fresh()->availableBalance());
    }
}
