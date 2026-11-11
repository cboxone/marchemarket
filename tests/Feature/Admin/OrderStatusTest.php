<?php

namespace Tests\Feature\Admin;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Services\KkiapayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unpaid_order_cannot_be_marked_as_paid_by_hand(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create(['status' => 'pending', 'payment_status' => 'pending']);

        $response = $this->actingAs($admin)->patch(route('admin.orders.update-status', $order), ['status' => 'paid']);

        $response->assertSessionHasErrors('status');
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_an_unpaid_order_cannot_be_shipped_or_delivered_by_hand(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create(['status' => 'pending', 'payment_status' => 'pending']);

        foreach (['shipped', 'delivered'] as $status) {
            $this->actingAs($admin)->patch(route('admin.orders.update-status', $order), ['status' => $status])
                ->assertSessionHasErrors('status');
        }

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_a_paid_order_can_progress_to_shipped(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create(['status' => 'paid', 'payment_status' => 'paid']);

        $this->actingAs($admin)->patch(route('admin.orders.update-status', $order), ['status' => 'shipped'])
            ->assertSessionHasNoErrors();

        $this->assertSame('shipped', $order->fresh()->status);
    }

    public function test_a_paid_order_cannot_go_back_to_pending(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create(['status' => 'paid', 'payment_status' => 'paid']);

        $this->actingAs($admin)->patch(route('admin.orders.update-status', $order), ['status' => 'pending'])
            ->assertSessionHasErrors('status');

        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_a_cancelled_order_cannot_be_reopened(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create(['status' => 'cancelled', 'payment_status' => 'pending']);

        $this->actingAs($admin)->patch(route('admin.orders.update-status', $order), ['status' => 'pending'])
            ->assertSessionHasErrors('status');

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_cancelling_from_the_back_office_restores_stock_and_releases_the_coupon(): void
    {
        $admin = User::factory()->admin()->create();
        $vendor = Vendor::factory()->create();
        $product = Product::factory()->for($vendor)->create(['stock_qty' => 50]);
        $coupon = Coupon::factory()->create(['times_used' => 1]);

        $order = Order::factory()->create(['status' => 'pending', 'payment_status' => 'pending', 'coupon_id' => $coupon->id]);
        $order->items()->create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 5,
            'unit_price' => $product->base_price,
        ]);

        $this->actingAs($admin)->patch(route('admin.orders.update-status', $order), ['status' => 'cancelled'])
            ->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->cancelled_at);
        $this->assertSame(55, $product->fresh()->stock_qty);
        $this->assertSame(0, $coupon->fresh()->times_used);
    }

    public function test_cancelling_twice_restores_the_stock_only_once(): void
    {
        $admin = User::factory()->admin()->create();
        $vendor = Vendor::factory()->create();
        $product = Product::factory()->for($vendor)->create(['stock_qty' => 50]);

        $order = Order::factory()->create(['status' => 'pending', 'payment_status' => 'pending']);
        $order->items()->create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 5,
            'unit_price' => $product->base_price,
        ]);

        $this->actingAs($admin)->patch(route('admin.orders.update-status', $order), ['status' => 'cancelled']);
        $this->actingAs($admin)->patch(route('admin.orders.update-status', $order), ['status' => 'cancelled']);

        $this->assertSame(55, $product->fresh()->stock_qty);
    }

    public function test_cancelling_a_paid_order_from_the_back_office_attempts_a_refund(): void
    {
        $this->mock(KkiapayService::class, function ($mock) {
            $mock->shouldReceive('refundTransaction')->once()->with('txn_123')->andReturn(true);
        });

        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create(['status' => 'paid', 'payment_status' => 'paid', 'payment_reference' => 'txn_123']);

        $this->actingAs($admin)->patch(route('admin.orders.update-status', $order), ['status' => 'cancelled']);

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->refunded_at);
    }
}
