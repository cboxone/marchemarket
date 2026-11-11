<?php

namespace Tests\Feature\Vendor;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    private function csvFile(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import') . '.csv';
        file_put_contents($path, $content);

        return new UploadedFile($path, 'produits.csv', 'text/csv', null, true);
    }

    public function test_a_vendor_can_bulk_import_valid_products(): void
    {
        $vendor = Vendor::factory()->create();
        $category = Category::factory()->create(['name' => 'Textile']);

        $csv = "name,category,unit,min_order_qty,stock_qty,base_price,description\n"
            . "Tissu wax,Textile,lot,10,200,12000,Tissu imprimé\n"
            . "Chemises coton,Textile,carton,5,50,45000,Lot de 12 chemises\n";

        $response = $this->actingAs($vendor->user)->post(route('vendor.products.import-store'), [
            'file' => $this->csvFile($csv),
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseHas('products', ['name' => 'Tissu wax', 'vendor_id' => $vendor->id, 'base_price' => 12000]);

        $product = Product::whereName('Tissu wax')->firstOrFail();
        $this->assertSame(1, $product->priceTiers()->count());
    }

    public function test_rows_with_an_unknown_category_are_reported_and_skipped(): void
    {
        $vendor = Vendor::factory()->create();
        Category::factory()->create(['name' => 'Textile']);

        $csv = "name,category,unit,min_order_qty,stock_qty,base_price,description\n"
            . "Produit valide,Textile,pièce,1,10,1000,\n"
            . "Produit invalide,CategorieInexistante,pièce,1,10,1000,\n";

        $response = $this->actingAs($vendor->user)->post(route('vendor.products.import-store'), [
            'file' => $this->csvFile($csv),
        ]);

        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseHas('products', ['name' => 'Produit valide']);
        $this->assertNotEmpty($response->getSession()->get('import_errors'));
    }

    public function test_the_import_only_creates_products_for_the_authenticated_vendor(): void
    {
        $vendorA = Vendor::factory()->create();
        $vendorB = Vendor::factory()->create();
        $category = Category::factory()->create(['name' => 'Textile']);

        $csv = "name,category,unit,min_order_qty,stock_qty,base_price,description\n"
            . "Produit de A,Textile,pièce,1,10,1000,\n";

        $this->actingAs($vendorA->user)->post(route('vendor.products.import-store'), [
            'file' => $this->csvFile($csv),
        ]);

        $product = Product::whereName('Produit de A')->firstOrFail();
        $this->assertSame($vendorA->id, $product->vendor_id);
        $this->assertNotEquals($vendorB->id, $product->vendor_id);
    }
}
