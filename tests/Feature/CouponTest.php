<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmationMail;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    private function shippingPayload(): array
    {
        return [
            'shipping_address' => '12 rue du Marché',
            'shipping_city' => 'Cotonou',
            'shipping_country' => 'Bénin',
            'shipping_phone' => '90000000',
        ];
    }

    public function test_a_valid_coupon_can_be_applied_to_the_cart(): void
    {
        $user = User::factory()->create();
        $coupon = Coupon::factory()->create(['code' => 'PROMO10', 'discount_percent' => 10]);

        $response = $this->actingAs($user)->post(route('checkout.apply-coupon'), ['code' => 'promo10']);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');
    }

    public function test_an_unknown_coupon_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('checkout.apply-coupon'), ['code' => 'INEXISTANT']);

        $response->assertSessionHasErrors('code');
    }

    public function test_an_expired_coupon_is_rejected(): void
    {
        $user = User::factory()->create();
        Coupon::factory()->create(['code' => 'EXPIRE', 'expires_at' => now()->subDay()]);

        $response = $this->actingAs($user)->post(route('checkout.apply-coupon'), ['code' => 'EXPIRE']);

        $response->assertSessionHasErrors('code');
    }

    public function test_a_coupon_below_its_usage_limit_is_accepted_but_not_beyond(): void
    {
        $user = User::factory()->create();
        Coupon::factory()->create(['code' => 'LIMITE', 'usage_limit' => 1, 'times_used' => 1]);

        $response = $this->actingAs($user)->post(route('checkout.apply-coupon'), ['code' => 'LIMITE']);

        $response->assertSessionHasErrors('code');
    }

    public function test_placing_an_order_applies_the_discount_and_increments_usage(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $coupon = Coupon::factory()->create(['code' => 'PROMO20', 'discount_percent' => 20]);
        $product = Product::factory()->create(['min_order_qty' => 1, 'stock_qty' => 10, 'base_price' => 10000]);

        $this->actingAs($user)->post('/panier', ['product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($user)->post(route('checkout.apply-coupon'), ['code' => 'PROMO20']);
        $this->actingAs($user)->post('/commande', $this->shippingPayload());

        $order = Order::first();

        $this->assertSame($coupon->id, $order->coupon_id);
        $this->assertSame(2000.0, (float) $order->discount_amount); // 20% de 10000
        $this->assertSame(10000.0 - 2000.0 + Order::SHIPPING_FEE, (float) $order->total_amount);
        $this->assertSame(1, $coupon->fresh()->times_used);

        Mail::assertSent(OrderConfirmationMail::class);
    }

    public function test_the_coupon_is_cleared_from_the_session_after_checkout(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        Coupon::factory()->create(['code' => 'UNIQUE1']);
        $product = Product::factory()->create(['min_order_qty' => 1, 'stock_qty' => 10]);

        $this->actingAs($user)->post('/panier', ['product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($user)->post(route('checkout.apply-coupon'), ['code' => 'UNIQUE1']);
        $this->actingAs($user)->post('/commande', $this->shippingPayload());

        $this->assertNull(session('coupon_code'));
    }

    public function test_a_coupon_can_be_removed_before_checkout(): void
    {
        $user = User::factory()->create();
        Coupon::factory()->create(['code' => 'RETRAIT']);

        $this->actingAs($user)->post(route('checkout.apply-coupon'), ['code' => 'RETRAIT']);
        $this->actingAs($user)->delete(route('checkout.remove-coupon'));

        $this->assertNull(session('coupon_code'));
    }
}
