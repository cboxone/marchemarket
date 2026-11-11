<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_buyer_can_add_a_product_to_their_wishlist(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($user)->post(route('wishlist.toggle', $product));

        $response->assertRedirect();
        $this->assertDatabaseHas('wishlists', ['user_id' => $user->id, 'product_id' => $product->id]);
    }

    public function test_toggling_twice_removes_it_again(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)->post(route('wishlist.toggle', $product));
        $this->actingAs($user)->post(route('wishlist.toggle', $product));

        $this->assertDatabaseMissing('wishlists', ['user_id' => $user->id, 'product_id' => $product->id]);
    }

    public function test_the_wishlist_page_lists_saved_products(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Produit favori']);
        $user->wishlistedProducts()->attach($product->id);

        $response = $this->actingAs($user)->get(route('wishlist.index'));

        $response->assertOk();
        $response->assertSee('Produit favori');
    }

    public function test_a_guest_cannot_access_the_wishlist(): void
    {
        $response = $this->get(route('wishlist.index'));

        $response->assertRedirect('/connexion');
    }
}
