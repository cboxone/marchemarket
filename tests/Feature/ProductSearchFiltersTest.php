<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSearchFiltersTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Recherche élargie à la description ----------

    public function test_search_matches_the_description_as_well_as_the_name(): void
    {
        Product::factory()->create(['name' => 'Riz parfumé', 'description' => 'Sac de 25kg, import Thaïlande.']);
        Product::factory()->create(['name' => 'Huile de palme', 'description' => 'Bidon de 20 litres.']);

        $this->get('/produits?q=25kg')
            ->assertSee('Riz parfumé')->assertDontSee('Huile de palme');
    }

    public function test_search_still_matches_the_name_when_the_description_does_not_contain_the_term(): void
    {
        Product::factory()->create(['name' => 'Ciment CEM II', 'description' => 'Sac de 50kg.']);
        Product::factory()->create(['name' => 'Chaises de bureau', 'description' => 'Lot de 4.']);

        $this->get('/produits?q=Ciment')
            ->assertSee('Ciment CEM II')->assertDontSee('Chaises de bureau');
    }

    // ---------- Filtre ville ----------

    public function test_the_city_filter_only_shows_products_from_that_city(): void
    {
        $cotonou = Vendor::factory()->create(['city' => 'Cotonou']);
        $parakou = Vendor::factory()->create(['city' => 'Parakou']);
        Product::factory()->for($cotonou)->create(['name' => 'Produit de Cotonou']);
        Product::factory()->for($parakou)->create(['name' => 'Produit de Parakou']);

        $this->get('/produits?ville=Cotonou')
            ->assertSee('Produit de Cotonou')->assertDontSee('Produit de Parakou');
    }

    public function test_the_city_dropdown_only_lists_cities_with_active_products(): void
    {
        $withActive = Vendor::factory()->create(['city' => 'Cotonou']);
        $withInactiveOnly = Vendor::factory()->create(['city' => 'Ouidah']);
        Product::factory()->for($withActive)->create(['is_active' => true]);
        Product::factory()->for($withInactiveOnly)->create(['is_active' => false]);

        $response = $this->get('/produits');

        $response->assertSee('Cotonou');
        $response->assertDontSee('Ouidah');
    }

    // ---------- Filtre stock ----------

    public function test_the_in_stock_filter_hides_products_with_no_stock(): void
    {
        Product::factory()->create(['name' => 'Produit disponible', 'stock_qty' => 10]);
        Product::factory()->create(['name' => 'Produit épuisé', 'stock_qty' => 0]);

        $this->get('/produits?en_stock=1')
            ->assertSee('Produit disponible')->assertDontSee('Produit épuisé');
    }

    public function test_without_the_filter_out_of_stock_products_still_appear(): void
    {
        Product::factory()->create(['name' => 'Produit épuisé', 'stock_qty' => 0]);

        $this->get('/produits')->assertSee('Produit épuisé');
    }

    // ---------- Filtre fournisseurs vérifiés ----------

    public function test_the_verified_filter_only_shows_products_from_verified_vendors(): void
    {
        $verified = Vendor::factory()->verified()->create();
        $unverified = Vendor::factory()->create(['is_verified' => false]);
        Product::factory()->for($verified)->create(['name' => 'Produit vérifié']);
        Product::factory()->for($unverified)->create(['name' => 'Produit non vérifié']);

        $this->get('/produits?verifies=1')
            ->assertSee('Produit vérifié')->assertDontSee('Produit non vérifié');
    }

    // ---------- Combinaison de filtres ----------

    public function test_filters_can_be_combined(): void
    {
        $vendor = Vendor::factory()->verified()->create(['city' => 'Cotonou']);
        $match = Product::factory()->for($vendor)->create(['name' => 'Sac de riz', 'base_price' => 9000, 'stock_qty' => 20]);
        $wrongCity = Product::factory()->for(Vendor::factory()->verified()->create(['city' => 'Parakou']))
            ->create(['name' => 'Sac de riz aussi', 'base_price' => 9000, 'stock_qty' => 20]);

        $this->get('/produits?ville=Cotonou&verifies=1&en_stock=1&prix_min=5000&prix_max=15000')
            ->assertSee($match->name)->assertDontSee($wrongCity->name);
    }

    // ---------- Réinitialisation ----------

    public function test_the_reset_link_appears_only_when_a_filter_is_active(): void
    {
        Product::factory()->create();

        $this->get('/produits')->assertDontSee(__('Réinitialiser les filtres'));
        $this->get('/produits?en_stock=1')->assertSee(__('Réinitialiser les filtres'));
    }
}
