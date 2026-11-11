<?php

namespace Tests\Feature\Vendor;

use App\Models\Order;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_status_becomes_shipped_once_every_item_is_shipped(): void
    {
        $vendorA = Vendor::factory()->create();
        $vendorB = Vendor::factory()->create();
        $order = Order::factory()->create(['status' => 'paid', 'payment_status' => 'paid']);

        $itemA = $order->items()->create(['vendor_id' => $vendorA->id, 'product_name' => 'A', 'quantity' => 1, 'unit_price' => 1000]);
        $itemB = $order->items()->create(['vendor_id' => $vendorB->id, 'product_name' => 'B', 'quantity' => 1, 'unit_price' => 1000]);

        // Un seul vendeur a expédié sa part : la commande reste "payée" globalement.
        $this->actingAs($vendorA->user)->patch(route('vendor.orders.update-status', $itemA), ['fulfillment_status' => 'shipped']);
        $this->assertSame('paid', $order->fresh()->status);

        // Le second vendeur expédie à son tour : la commande passe "expédiée".
        $this->actingAs($vendorB->user)->patch(route('vendor.orders.update-status', $itemB), ['fulfillment_status' => 'shipped']);
        $this->assertSame('shipped', $order->fresh()->status);
    }

    public function test_order_status_becomes_delivered_once_every_item_is_delivered(): void
    {
        $vendor = Vendor::factory()->create();
        $order = Order::factory()->create(['status' => 'paid', 'payment_status' => 'paid']);
        $item = $order->items()->create(['vendor_id' => $vendor->id, 'product_name' => 'A', 'quantity' => 1, 'unit_price' => 1000]);

        $this->actingAs($vendor->user)->patch(route('vendor.orders.update-status', $item), ['fulfillment_status' => 'delivered']);

        $this->assertSame('delivered', $order->fresh()->status);
    }

    public function test_a_cancelled_order_is_never_overwritten_by_the_sync(): void
    {
        $vendor = Vendor::factory()->create();
        $order = Order::factory()->create(['status' => 'cancelled', 'payment_status' => 'paid']);
        $item = $order->items()->create(['vendor_id' => $vendor->id, 'product_name' => 'A', 'quantity' => 1, 'unit_price' => 1000]);

        $this->actingAs($vendor->user)->patch(route('vendor.orders.update-status', $item), ['fulfillment_status' => 'delivered']);

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_a_manually_advanced_status_is_never_downgraded_by_the_sync(): void
    {
        $vendor = Vendor::factory()->create();
        // Un admin a corrigé manuellement la commande à "Livrée" (ex. commande
        // gérée hors-ligne), alors qu'aucun article n'a encore de statut
        // d'exécution avancé.
        $order = Order::factory()->create(['status' => 'delivered', 'payment_status' => 'paid']);
        $item = $order->items()->create(['vendor_id' => $vendor->id, 'product_name' => 'A', 'quantity' => 1, 'unit_price' => 1000]);

        // Le vendeur ne fait que passer son article à "expédiée" (rang
        // inférieur à "livrée") : la commande doit rester "livrée".
        $this->actingAs($vendor->user)->patch(route('vendor.orders.update-status', $item), ['fulfillment_status' => 'shipped']);

        $this->assertSame('delivered', $order->fresh()->status);
    }

    public function test_a_vendor_cannot_update_another_vendors_order_item(): void
    {
        $owner = Vendor::factory()->create();
        $intruder = Vendor::factory()->create();
        $order = Order::factory()->create();
        $item = $order->items()->create(['vendor_id' => $owner->id, 'product_name' => 'A', 'quantity' => 1, 'unit_price' => 1000]);

        $response = $this->actingAs($intruder->user)->patch(route('vendor.orders.update-status', $item), ['fulfillment_status' => 'shipped']);

        $response->assertForbidden();
        $this->assertSame('pending', $item->fresh()->fulfillment_status);
    }

    public function test_a_vendor_cannot_ship_an_unpaid_order(): void
    {
        $vendor = Vendor::factory()->create();
        $order = Order::factory()->create(['status' => 'pending', 'payment_status' => 'pending']);
        $item = $order->items()->create(['vendor_id' => $vendor->id, 'product_name' => 'A', 'quantity' => 1, 'unit_price' => 1000]);

        $this->actingAs($vendor->user)->patch(route('vendor.orders.update-status', $item), ['fulfillment_status' => 'shipped'])
            ->assertSessionHasErrors('fulfillment_status');

        $this->assertSame('pending', $item->fresh()->fulfillment_status);
    }

    public function test_a_vendor_cannot_ship_a_cancelled_order(): void
    {
        $vendor = Vendor::factory()->create();
        $order = Order::factory()->create(['status' => 'cancelled', 'payment_status' => 'paid']);
        $item = $order->items()->create(['vendor_id' => $vendor->id, 'product_name' => 'A', 'quantity' => 1, 'unit_price' => 1000]);

        $this->actingAs($vendor->user)->patch(route('vendor.orders.update-status', $item), ['fulfillment_status' => 'shipped'])
            ->assertSessionHasErrors('fulfillment_status');

        $this->assertSame('pending', $item->fresh()->fulfillment_status);
    }
}
