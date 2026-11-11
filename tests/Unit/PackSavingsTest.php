<?php

namespace Tests\Unit;

use App\Models\Pack;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackSavingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_computes_the_individual_total_and_savings(): void
    {
        $vendor = Vendor::factory()->create();
        $productA = Product::factory()->for($vendor)->create(['base_price' => 1000]);
        $productB = Product::factory()->for($vendor)->create(['base_price' => 2000]);

        $pack = Pack::factory()->for($vendor)->create(['pack_price' => 2500]);
        $pack->items()->create(['product_id' => $productA->id, 'quantity' => 1]);
        $pack->items()->create(['product_id' => $productB->id, 'quantity' => 1]);

        $pack->load('items.product');

        $this->assertSame(3000.0, $pack->individualTotal());
        $this->assertSame(500.0, $pack->savingsAmount());
        $this->assertSame(17, $pack->savingsPercent());
    }

    public function test_savings_are_never_negative_even_if_the_pack_is_not_actually_cheaper(): void
    {
        $vendor = Vendor::factory()->create();
        $product = Product::factory()->for($vendor)->create(['base_price' => 1000]);

        $pack = Pack::factory()->for($vendor)->create(['pack_price' => 5000]);
        $pack->items()->create(['product_id' => $product->id, 'quantity' => 1]);
        $pack->load('items.product');

        $this->assertSame(0.0, $pack->savingsAmount());
        $this->assertSame(0, $pack->savingsPercent());
    }
}
