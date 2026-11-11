<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_can_be_sorted_by_price_ascending(): void
    {
        $expensive = Product::factory()->create(['name' => 'Cher', 'base_price' => 90000]);
        $cheap = Product::factory()->create(['name' => 'Pas cher', 'base_price' => 1000]);

        $response = $this->get('/produits?tri=prix_asc');

        $content = $response->getContent();
        $this->assertTrue(strpos($content, 'Pas cher') < strpos($content, 'Cher'));
    }

    public function test_products_can_be_sorted_by_price_descending(): void
    {
        $expensive = Product::factory()->create(['name' => 'Cher', 'base_price' => 90000]);
        $cheap = Product::factory()->create(['name' => 'Pas cher', 'base_price' => 1000]);

        $response = $this->get('/produits?tri=prix_desc');

        $content = $response->getContent();
        $this->assertTrue(strpos($content, 'Cher') < strpos($content, 'Pas cher'));
    }

    public function test_autocomplete_returns_matching_products(): void
    {
        Product::factory()->create(['name' => 'Riz étuvé 25kg']);
        Product::factory()->create(['name' => 'Chaises de bureau']);

        $response = $this->getJson(route('products.autocomplete', ['q' => 'Riz']));

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['name' => 'Riz étuvé 25kg']);
    }

    public function test_autocomplete_returns_nothing_for_very_short_queries(): void
    {
        Product::factory()->create(['name' => 'Riz étuvé 25kg']);

        $response = $this->getJson(route('products.autocomplete', ['q' => 'R']));

        $response->assertOk();
        $response->assertJsonCount(0);
    }
}
