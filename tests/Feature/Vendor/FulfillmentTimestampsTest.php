<?php

namespace Tests\Feature\Vendor;

use App\Models\Order;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FulfillmentTimestampsTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_shipped_records_the_date_once(): void
    {
        $vendor = Vendor::factory()->create();
        $order = Order::factory()->create(['status' => 'paid', 'payment_status' => 'paid']);
        $item = $order->items()->create(['vendor_id' => $vendor->id, 'product_name' => 'X', 'quantity' => 1, 'unit_price' => 1000]);

        $this->actingAs($vendor->user)->patch(route('vendor.orders.update-status', $item), ['fulfillment_status' => 'shipped']);

        $shippedAt = $item->fresh()->shipped_at;
        $this->assertNotNull($shippedAt);

        // Re-soumettre le même statut ne doit pas repousser la date.
        $this->travel(2)->hours();
        $this->actingAs($vendor->user)->patch(route('vendor.orders.update-status', $item), ['fulfillment_status' => 'shipped']);
        $this->assertTrue($shippedAt->equalTo($item->fresh()->shipped_at));
    }

    public function test_marking_delivered_directly_records_both_dates(): void
    {
        $vendor = Vendor::factory()->create();
        $order = Order::factory()->create(['status' => 'paid', 'payment_status' => 'paid']);
        $item = $order->items()->create(['vendor_id' => $vendor->id, 'product_name' => 'X', 'quantity' => 1, 'unit_price' => 1000]);

        $this->actingAs($vendor->user)->patch(route('vendor.orders.update-status', $item), ['fulfillment_status' => 'delivered']);

        $fresh = $item->fresh();
        $this->assertNotNull($fresh->shipped_at);
        $this->assertNotNull($fresh->delivered_at);
    }

    public function test_marking_delivered_after_shipped_keeps_the_original_shipped_date(): void
    {
        $vendor = Vendor::factory()->create();
        $order = Order::factory()->create(['status' => 'paid', 'payment_status' => 'paid']);
        $item = $order->items()->create(['vendor_id' => $vendor->id, 'product_name' => 'X', 'quantity' => 1, 'unit_price' => 1000]);

        $this->actingAs($vendor->user)->patch(route('vendor.orders.update-status', $item), ['fulfillment_status' => 'shipped']);
        $shippedAt = $item->fresh()->shipped_at;

        $this->travel(3)->days();
        $this->actingAs($vendor->user)->patch(route('vendor.orders.update-status', $item), ['fulfillment_status' => 'delivered']);

        $fresh = $item->fresh();
        $this->assertTrue($shippedAt->equalTo($fresh->shipped_at));
        $this->assertFalse($shippedAt->equalTo($fresh->delivered_at));
    }
}
