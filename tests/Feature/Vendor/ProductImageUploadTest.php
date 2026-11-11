<?php

namespace Tests\Feature\Vendor;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_images_are_resized_and_converted_to_webp(): void
    {
        Storage::fake('public');

        $vendor = Vendor::factory()->create();
        $category = Category::factory()->create();

        // Une photo volontairement plus large que la limite (1600px) pour
        // vérifier que ImageOptimizer la redimensionne bien.
        $photo = UploadedFile::fake()->image('photo.jpg', 3000, 2000);

        $response = $this->actingAs($vendor->user)->post(route('vendor.products.store'), [
            'category_id' => $category->id,
            'name' => 'Produit avec photo',
            'unit' => 'pièce',
            'min_order_qty' => 1,
            'stock_qty' => 10,
            'base_price' => 1000,
            'images' => [$photo],
        ]);

        $response->assertRedirect(route('vendor.products.index'));

        $product = Product::whereName('Produit avec photo')->firstOrFail();
        $image = $product->images()->firstOrFail();

        $this->assertStringEndsWith('.webp', $image->path);
        Storage::disk('public')->assertExists($image->path);

        [$width] = getimagesizefromstring(Storage::disk('public')->get($image->path));
        $this->assertLessThanOrEqual(1600, $width);
    }

    public function test_a_vendor_can_delete_an_existing_product_photo(): void
    {
        Storage::fake('public');

        $vendor = Vendor::factory()->create();
        $product = Product::factory()->for($vendor)->create();
        $image = $product->images()->create(['path' => 'products/existing.webp', 'position' => 0]);
        Storage::disk('public')->put('products/existing.webp', 'contenu-factice');

        $response = $this->actingAs($vendor->user)->put(route('vendor.products.update', $product), [
            'category_id' => $product->category_id,
            'name' => $product->name,
            'unit' => $product->unit,
            'min_order_qty' => $product->min_order_qty,
            'stock_qty' => $product->stock_qty,
            'base_price' => $product->base_price,
            'delete_images' => [$image->id],
        ]);

        $response->assertRedirect(route('vendor.products.index'));
        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
        Storage::disk('public')->assertMissing('products/existing.webp');
    }
}
