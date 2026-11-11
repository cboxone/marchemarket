<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_buyer_can_leave_a_review(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($user)->post(route('reviews.store', $product), [
            'rating' => 4,
            'comment' => 'Bon produit, livraison rapide.',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reviews', [
            'product_id' => $product->id,
            'user_id' => $user->id,
            'rating' => 4,
        ]);
    }

    public function test_submitting_a_second_review_updates_the_first_instead_of_duplicating(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)->post(route('reviews.store', $product), ['rating' => 2, 'comment' => 'Moyen.']);
        $this->actingAs($user)->post(route('reviews.store', $product), ['rating' => 5, 'comment' => 'Finalement excellent.']);

        $this->assertDatabaseCount('reviews', 1);
        $this->assertDatabaseHas('reviews', ['rating' => 5, 'comment' => 'Finalement excellent.']);
    }

    public function test_a_vendor_cannot_leave_a_review(): void
    {
        $vendorUser = User::factory()->vendor()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($vendorUser)->post(route('reviews.store', $product), ['rating' => 5]);

        $response->assertForbidden();
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_an_admin_can_delete_a_review(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $review = $product->reviews()->create(['user_id' => $user->id, 'rating' => 1, 'comment' => 'Abus.']);

        $response = $this->actingAs($admin)->delete(route('admin.reviews.destroy', $review));

        $response->assertRedirect();
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }
}
