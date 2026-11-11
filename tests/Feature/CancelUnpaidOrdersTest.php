<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelUnpaidOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function orderOfAge(int $minutesOld, array $attributes = [], ?Product $product = null, int $qty = 5): Order
    {
        $order = Order::factory()->create($attributes + ['status' => 'pending', 'payment_status' => 'pending']);

        // created_at n'est pas mass-assignable : on passe par le query builder.
        Order::whereKey($order->id)->update(['created_at' => now()->subMinutes($minutesOld)]);

        if ($product) {
            $order->items()->create([
                'vendor_id' => $product->vendor_id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $qty,
                'unit_price' => $product->base_price,
            ]);
        }

        return $order;
    }

    public function test_an_old_unpaid_order_is_cancelled_and_its_stock_restored(): void
    {
        $product = Product::factory()->for(Vendor::factory())->create(['stock_qty' => 50]);
        $order = $this->orderOfAge(120, [], $product, 5);

        $this->artisan('orders:cancel-unpaid')->assertSuccessful();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->cancelled_at);
        $this->assertSame(55, $product->fresh()->stock_qty);
    }

    public function test_the_coupon_usage_is_released(): void
    {
        $coupon = Coupon::factory()->create(['times_used' => 1]);
        $this->orderOfAge(120, ['coupon_id' => $coupon->id]);

        $this->artisan('orders:cancel-unpaid')->assertSuccessful();

        $this->assertSame(0, $coupon->fresh()->times_used);
    }

    public function test_a_recent_unpaid_order_is_left_alone(): void
    {
        $product = Product::factory()->for(Vendor::factory())->create(['stock_qty' => 50]);
        $order = $this->orderOfAge(5, [], $product);

        $this->artisan('orders:cancel-unpaid')->assertSuccessful();

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(50, $product->fresh()->stock_qty);
    }

    public function test_a_paid_order_is_never_cancelled_however_old(): void
    {
        $order = $this->orderOfAge(10000, ['status' => 'paid', 'payment_status' => 'paid']);

        $this->artisan('orders:cancel-unpaid')->assertSuccessful();

        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_an_order_whose_payment_was_confirmed_but_status_still_pending_is_not_cancelled(): void
    {
        // Cas de course : le paiement vient d'être enregistré, le statut n'a
        // pas encore été rattrapé. L'annulation automatique doit s'abstenir.
        $order = $this->orderOfAge(120, ['status' => 'pending', 'payment_status' => 'paid']);

        $this->artisan('orders:cancel-unpaid')->assertSuccessful();

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_an_already_cancelled_order_does_not_restore_stock_twice(): void
    {
        $product = Product::factory()->for(Vendor::factory())->create(['stock_qty' => 50]);
        $order = $this->orderOfAge(120, ['status' => 'cancelled'], $product);

        $this->artisan('orders:cancel-unpaid')->assertSuccessful();

        $this->assertSame(50, $product->fresh()->stock_qty);
    }

    public function test_a_failed_payment_order_is_cancelled_too(): void
    {
        $order = $this->orderOfAge(120, ['payment_status' => 'failed']);

        $this->artisan('orders:cancel-unpaid')->assertSuccessful();

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_running_twice_restores_stock_only_once(): void
    {
        $product = Product::factory()->for(Vendor::factory())->create(['stock_qty' => 50]);
        $this->orderOfAge(120, [], $product, 5);

        $this->artisan('orders:cancel-unpaid')->assertSuccessful();
        $this->artisan('orders:cancel-unpaid')->assertSuccessful();

        $this->assertSame(55, $product->fresh()->stock_qty);
    }

    public function test_the_delay_can_be_overridden_from_the_command_line(): void
    {
        $order = $this->orderOfAge(30);

        $this->artisan('orders:cancel-unpaid')->assertSuccessful();
        $this->assertSame('pending', $order->fresh()->status); // 30 min < 60 min par défaut

        $this->artisan('orders:cancel-unpaid', ['--minutes' => 15])->assertSuccessful();
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_the_command_is_scheduled(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('orders:cancel-unpaid')->assertSuccessful();
    }
}
