<?php

namespace Tests\Unit;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_the_base_price_below_any_tier_threshold(): void
    {
        $product = Product::factory()->create(['base_price' => 1000, 'min_order_qty' => 10]);

        $this->assertSame(1000.0, $product->priceForQty(10));
    }

    public function test_it_applies_the_matching_price_tier(): void
    {
        $product = Product::factory()->create(['base_price' => 1000, 'min_order_qty' => 10]);
        $product->priceTiers()->create(['min_qty' => 50, 'max_qty' => null, 'unit_price' => 800]);

        $this->assertSame(1000.0, $product->fresh()->load('priceTiers')->priceForQty(20));
        $this->assertSame(800.0, $product->fresh()->load('priceTiers')->priceForQty(50));
    }

    public function test_it_applies_an_active_discount_on_top_of_the_tier_price(): void
    {
        $product = Product::factory()->withDiscount(10)->create(['base_price' => 1000, 'min_order_qty' => 10]);

        $this->assertTrue($product->hasActivePromo());
        $this->assertSame(900.0, $product->promoPrice());
        $this->assertSame(900.0, $product->fresh()->load('priceTiers')->priceForQty(10));
    }

    public function test_an_expired_promotion_is_not_applied(): void
    {
        $product = Product::factory()->create([
            'base_price' => 1000,
            'min_order_qty' => 10,
            'discount_percent' => 20,
            'promo_ends_at' => now()->subDay(),
        ]);

        $this->assertFalse($product->hasActivePromo());
        $this->assertSame(1000.0, $product->fresh()->load('priceTiers')->priceForQty(10));
    }
}
