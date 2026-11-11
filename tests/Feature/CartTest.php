<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_logged_in_buyer_can_add_a_product_to_the_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['min_order_qty' => 10]);

        $response = $this->actingAs($user)->post('/panier', [
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 10]);
    }

    public function test_it_rejects_a_quantity_below_the_minimum_order_quantity(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['min_order_qty' => 10]);

        $response = $this->actingAs($user)->post('/panier', [
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $response->assertSessionHasErrors('quantity');
        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
    }

    public function test_adding_the_same_product_twice_increments_the_quantity(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['min_order_qty' => 5]);

        $this->actingAs($user)->post('/panier', ['product_id' => $product->id, 'quantity' => 5]);
        $this->actingAs($user)->post('/panier', ['product_id' => $product->id, 'quantity' => 5]);

        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 10]);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_a_guest_cannot_add_to_cart(): void
    {
        $product = Product::factory()->create();

        $response = $this->post('/panier', ['product_id' => $product->id, 'quantity' => $product->min_order_qty]);

        $response->assertRedirect('/connexion');
    }

    public function test_it_rejects_a_quantity_above_the_maximum_order_quantity(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['min_order_qty' => 1, 'max_order_qty' => 50]);

        $response = $this->actingAs($user)->post('/panier', ['product_id' => $product->id, 'quantity' => 100]);

        $response->assertSessionHasErrors('quantity');
        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
    }

    public function test_adding_twice_is_rejected_once_the_cumulative_quantity_exceeds_the_maximum(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['min_order_qty' => 1, 'max_order_qty' => 15]);

        $this->actingAs($user)->post('/panier', ['product_id' => $product->id, 'quantity' => 10]);
        $response = $this->actingAs($user)->post('/panier', ['product_id' => $product->id, 'quantity' => 10]);

        $response->assertSessionHasErrors('quantity');
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 10]);
    }
}
