<?php

namespace Tests\Feature;

use App\Models\Pack;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackAddToCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_a_pack_adds_every_product_with_the_pack_quantities(): void
    {
        $vendor = Vendor::factory()->create();
        $productA = Product::factory()->for($vendor)->create(['min_order_qty' => 1]);
        $productB = Product::factory()->for($vendor)->create(['min_order_qty' => 1]);

        $pack = Pack::factory()->for($vendor)->create();
        $pack->items()->create(['product_id' => $productA->id, 'quantity' => 3]);
        $pack->items()->create(['product_id' => $productB->id, 'quantity' => 2]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('packs.add-to-cart', $pack));

        $response->assertRedirect(route('cart.index'));
        $this->assertDatabaseHas('cart_items', ['product_id' => $productA->id, 'quantity' => 3]);
        $this->assertDatabaseHas('cart_items', ['product_id' => $productB->id, 'quantity' => 2]);
    }

    public function test_the_pack_quantity_is_bumped_up_to_the_products_current_moq_if_needed(): void
    {
        $vendor = Vendor::factory()->create();
        $product = Product::factory()->for($vendor)->create(['min_order_qty' => 10]);

        $pack = Pack::factory()->for($vendor)->create();
        // Le pack a été créé quand le MOQ était plus bas ; il a depuis augmenté.
        $pack->items()->create(['product_id' => $product->id, 'quantity' => 2]);

        $user = User::factory()->create();
        $this->actingAs($user)->post(route('packs.add-to-cart', $pack));

        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 10]);
    }
}
