<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBrowsingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_catalog_lists_active_products(): void
    {
        $active = Product::factory()->create(['name' => 'Riz étuvé 25kg', 'is_active' => true]);
        Product::factory()->create(['name' => 'Produit retiré', 'is_active' => false]);

        $response = $this->get('/produits');

        $response->assertOk();
        $response->assertSee('Riz étuvé 25kg');
        $response->assertDontSee('Produit retiré');
    }

    public function test_the_catalog_can_be_filtered_by_search_term(): void
    {
        Product::factory()->create(['name' => 'Ciment CEM II']);
        Product::factory()->create(['name' => 'Chaises de bureau']);

        $response = $this->get('/produits?q=Ciment');

        $response->assertSee('Ciment CEM II');
        $response->assertDontSee('Chaises de bureau');
    }

    public function test_the_catalog_can_be_filtered_by_category(): void
    {
        $textile = Category::factory()->create(['name' => 'Textile']);
        $btp = Category::factory()->create(['name' => 'BTP']);

        Product::factory()->for($textile)->create(['name' => 'Tissu wax']);
        Product::factory()->for($btp)->create(['name' => 'Sac de ciment']);

        $response = $this->get('/produits?categorie=' . $textile->slug);

        $response->assertSee('Tissu wax');
        $response->assertDontSee('Sac de ciment');
    }

    public function test_a_product_page_shows_its_price_tiers(): void
    {
        $product = Product::factory()->create(['name' => 'Huile de palme 20L', 'base_price' => 22000]);
        $product->priceTiers()->create(['min_qty' => 50, 'max_qty' => null, 'unit_price' => 19000]);

        $response = $this->get(route('products.show', $product));

        $response->assertOk();
        $response->assertSee('Huile de palme 20L');
        $response->assertSee('19 000 FCFA');
    }
}
