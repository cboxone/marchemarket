<?php

namespace Tests\Feature\Vendor;

use App\Models\Dispute;
use App\Models\Order;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutReleaseTest extends TestCase
{
    use RefreshDatabase;

    private function paidOrder(array $attributes = []): Order
    {
        return Order::factory()->create($attributes + [
            'status' => 'paid', 'payment_status' => 'paid', 'shipping_fee' => 0, 'total_amount' => 10000,
        ]);
    }

    private function item(Vendor $vendor, Order $order, array $attributes = [])
    {
        return $order->items()->create($attributes + [
            'vendor_id' => $vendor->id, 'product_name' => 'X', 'quantity' => 1, 'unit_price' => 10000,
        ]);
    }

    // ---------- OrderItem::releaseDate / isReleased ----------

    public function test_release_date_prioritises_delivery_then_shipping_then_payment(): void
    {
        $vendor = Vendor::factory()->create();
        $order = $this->paidOrder(['invoiced_at' => now()->subDays(100)]);

        $noDates = $this->item($vendor, $order);
        $this->assertTrue($noDates->releaseDate()->isSameDay(now()->subDays(100)->addDays(45)));

        $shippedOnly = $this->item($vendor, $order, ['shipped_at' => now()->subDays(10)]);
        $this->assertTrue($shippedOnly->releaseDate()->isSameDay(now()->subDays(10)->addDays(21)));

        $delivered = $this->item($vendor, $order, ['shipped_at' => now()->subDays(10), 'delivered_at' => now()->subDays(2)]);
        $this->assertTrue($delivered->releaseDate()->isSameDay(now()->subDays(2)->addDays(7)));
    }

    public function test_is_released_reflects_the_release_date(): void
    {
        $vendor = Vendor::factory()->create();
        $order = $this->paidOrder();

        $notYet = $this->item($vendor, $order, ['delivered_at' => now()->subDays(3)]);
        $released = $this->item($vendor, $order, ['delivered_at' => now()->subDays(8)]);

        $this->assertFalse($notYet->isReleased());
        $this->assertTrue($released->isReleased());
    }

    // ---------- Vendor::availableBalance / pendingReleaseBalance ----------

    public function test_a_released_item_is_available_and_an_unreleased_one_is_pending(): void
    {
        $vendor = Vendor::factory()->create();

        $released = $this->paidOrder();
        $this->item($vendor, $released, ['delivered_at' => now()->subDays(8), 'total_amount' => 10000]);

        $pending = $this->paidOrder();
        $this->item($vendor, $pending, ['delivered_at' => now()->subDays(1), 'total_amount' => 10000]);

        $this->assertEqualsWithDelta(10000.0, $vendor->availableBalance(), 0.01);
        $this->assertEqualsWithDelta(10000.0, $vendor->pendingReleaseBalance(), 0.01);
        $this->assertEqualsWithDelta(20000.0, $vendor->grossRevenue(), 0.01); // le total, lui, ne dépend pas du délai
    }

    public function test_an_open_dispute_freezes_the_whole_order_for_that_vendor_even_past_the_release_date(): void
    {
        $vendor = Vendor::factory()->create();
        $order = $this->paidOrder();
        $this->item($vendor, $order, ['delivered_at' => now()->subDays(30)]); // largement libéré sans le litige

        Dispute::factory()->create(['order_id' => $order->id, 'vendor_id' => $vendor->id, 'status' => 'open']);

        $this->assertSame(0.0, $vendor->availableBalance());
    }

    public function test_an_escalated_dispute_also_freezes_the_balance(): void
    {
        $vendor = Vendor::factory()->create();
        $order = $this->paidOrder();
        $this->item($vendor, $order, ['delivered_at' => now()->subDays(30)]);

        Dispute::factory()->escalated()->create(['order_id' => $order->id, 'vendor_id' => $vendor->id]);

        $this->assertSame(0.0, $vendor->availableBalance());
    }

    public function test_a_withdrawn_dispute_does_not_freeze_anything(): void
    {
        $vendor = Vendor::factory()->create();
        $order = $this->paidOrder();
        $this->item($vendor, $order, ['delivered_at' => now()->subDays(30), 'total_amount' => 10000]);

        Dispute::factory()->create(['order_id' => $order->id, 'vendor_id' => $vendor->id, 'status' => 'withdrawn']);

        $this->assertEqualsWithDelta(10000.0, $vendor->availableBalance(), 0.01);
    }

    public function test_a_resolved_refund_permanently_reduces_revenue_and_available_balance(): void
    {
        config(['marketplace.commission_percent' => 10]);
        $vendor = Vendor::factory()->create();
        $order = $this->paidOrder();
        $this->item($vendor, $order, ['delivered_at' => now()->subDays(30), 'total_amount' => 10000, 'commission_rate' => 10]);

        Dispute::factory()->resolved('refund_full', 4000)->create(['order_id' => $order->id, 'vendor_id' => $vendor->id]);

        $breakdown = $vendor->revenueBreakdown();
        $this->assertEqualsWithDelta(6000.0, $breakdown['gross'], 0.01);          // 10000 - 4000
        $this->assertEqualsWithDelta(600.0, $breakdown['commission'], 0.01);      // 10% de 6000
        $this->assertEqualsWithDelta(5400.0, $breakdown['net'], 0.01);
        $this->assertEqualsWithDelta(5400.0, $vendor->availableBalance(), 0.01);
    }

    public function test_a_resolved_reject_does_not_reduce_anything(): void
    {
        $vendor = Vendor::factory()->create();
        $order = $this->paidOrder();
        $this->item($vendor, $order, ['delivered_at' => now()->subDays(30), 'total_amount' => 10000]);

        Dispute::factory()->resolved('reject', 0)->create(['order_id' => $order->id, 'vendor_id' => $vendor->id]);

        $this->assertEqualsWithDelta(10000.0, $vendor->availableBalance(), 0.01);
    }

    public function test_a_refund_larger_than_the_unreleased_balance_clamps_to_zero_rather_than_going_negative(): void
    {
        $vendor = Vendor::factory()->create();
        $order = $this->paidOrder();
        $this->item($vendor, $order, ['delivered_at' => now()->subDays(30), 'total_amount' => 5000]);

        Dispute::factory()->resolved('refund_full', 5000)->create(['order_id' => $order->id, 'vendor_id' => $vendor->id]);

        $this->assertSame(0.0, $vendor->availableBalance());
        $this->assertSame(0.0, $vendor->revenueBreakdown()['net']);
    }

    public function test_a_pending_payout_still_reduces_the_available_balance_as_before(): void
    {
        $vendor = Vendor::factory()->create(['payout_provider' => 'mtn', 'payout_phone' => '+22996000000']);
        $order = $this->paidOrder();
        $this->item($vendor, $order, ['delivered_at' => now()->subDays(30), 'total_amount' => 10000]);

        $vendor->payouts()->create(['amount' => 3000, 'status' => 'pending', 'payout_method' => 'x']);

        $this->assertEqualsWithDelta(7000.0, $vendor->availableBalance(), 0.01);
    }
}
