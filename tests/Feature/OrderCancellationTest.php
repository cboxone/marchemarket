<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Services\KkiapayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class OrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    private function orderWithItem(User $buyer, Vendor $vendor, Product $product, int $quantity = 5): Order
    {
        $order = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => 'paid', 'payment_status' => 'pending']);
        $order->items()->create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'unit_price' => $product->base_price,
        ]);

        return $order;
    }

    public function test_a_buyer_can_cancel_a_pending_order_and_stock_is_restored(): void
    {
        $buyer = User::factory()->create();
        $vendor = Vendor::factory()->create();
        $product = Product::factory()->for($vendor)->create(['stock_qty' => 50]);
        $order = $this->orderWithItem($buyer, $vendor, $product, 5);

        $response = $this->actingAs($buyer)->post(route('orders.cancel', $order), [
            'cancellation_reason' => 'Changement de plan',
        ]);

        $response->assertRedirect(route('orders.show', $order));
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('Changement de plan', $order->fresh()->cancellation_reason);
        $this->assertNotNull($order->fresh()->cancelled_at);
        $this->assertSame(55, $product->fresh()->stock_qty);
    }

    public function test_a_paid_order_triggers_a_refund_attempt(): void
    {
        $this->mock(KkiapayService::class, function ($mock) {
            $mock->shouldReceive('refundTransaction')->once()->andReturn(true);
        });

        $buyer = User::factory()->create();
        $vendor = Vendor::factory()->create();
        $product = Product::factory()->for($vendor)->create();
        $order = $this->orderWithItem($buyer, $vendor, $product);
        $order->update(['payment_status' => 'paid', 'payment_reference' => 'txn_123']);

        $this->actingAs($buyer)->post(route('orders.cancel', $order));

        $this->assertNotNull($order->fresh()->refunded_at);
    }

    public function test_a_failed_refund_still_cancels_the_order(): void
    {
        $this->mock(KkiapayService::class, function ($mock) {
            $mock->shouldReceive('refundTransaction')->once()->andThrow(new \RuntimeException('API indisponible'));
        });

        $buyer = User::factory()->create();
        $vendor = Vendor::factory()->create();
        $product = Product::factory()->for($vendor)->create();
        $order = $this->orderWithItem($buyer, $vendor, $product);
        $order->update(['payment_status' => 'paid', 'payment_reference' => 'txn_123']);

        $response = $this->actingAs($buyer)->post(route('orders.cancel', $order));

        $response->assertRedirect(route('orders.show', $order));
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertNull($order->fresh()->refunded_at);
    }

    public function test_an_order_already_shipped_cannot_be_cancelled(): void
    {
        $buyer = User::factory()->create();
        $vendor = Vendor::factory()->create();
        $product = Product::factory()->for($vendor)->create();
        $order = $this->orderWithItem($buyer, $vendor, $product);
        $order->items()->first()->update(['fulfillment_status' => 'shipped']);

        $response = $this->actingAs($buyer)->post(route('orders.cancel', $order));

        $response->assertStatus(422);
        $this->assertNotEquals('cancelled', $order->fresh()->status);
    }

    public function test_a_buyer_cannot_cancel_another_buyers_order(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $vendor = Vendor::factory()->create();
        $product = Product::factory()->for($vendor)->create();
        $order = $this->orderWithItem($owner, $vendor, $product);

        $response = $this->actingAs($intruder)->post(route('orders.cancel', $order));

        $response->assertForbidden();
    }

    public function test_cancelling_an_order_releases_its_coupon_usage(): void
    {
        $buyer = User::factory()->create();
        $vendor = Vendor::factory()->create();
        $product = Product::factory()->for($vendor)->create();
        $coupon = \App\Models\Coupon::factory()->create(['times_used' => 1]);
        $order = $this->orderWithItem($buyer, $vendor, $product);
        $order->update(['coupon_id' => $coupon->id]);

        $this->actingAs($buyer)->post(route('orders.cancel', $order));

        $this->assertSame(0, $coupon->fresh()->times_used);
    }
}
