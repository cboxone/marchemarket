<?php

namespace Tests\Feature\Vendor;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private function vendorUser(): User
    {
        $vendor = Vendor::factory()->create();

        return $vendor->user;
    }

    public function test_a_vendor_can_publish_a_product_with_price_tiers(): void
    {
        $user = $this->vendorUser();
        $category = Category::factory()->create();

        $response = $this->actingAs($user)->post(route('vendor.products.store'), [
            'category_id' => $category->id,
            'name' => 'Sacs de riz 25kg',
            'unit' => 'sac',
            'min_order_qty' => 20,
            'stock_qty' => 500,
            'base_price' => 15000,
            'tier2_min_qty' => 100,
            'tier2_price' => 14000,
        ]);

        $response->assertRedirect(route('vendor.products.index'));
        $this->assertDatabaseHas('products', ['name' => 'Sacs de riz 25kg', 'vendor_id' => $user->vendor->id]);

        $product = Product::whereName('Sacs de riz 25kg')->firstOrFail();
        $this->assertSame(2, $product->priceTiers()->count());
    }

    public function test_a_vendor_cannot_edit_another_vendors_product(): void
    {
        $owner = $this->vendorUser();
        $intruder = $this->vendorUser();
        $product = Product::factory()->for($owner->vendor)->create();

        $response = $this->actingAs($intruder)->put(route('vendor.products.update', $product), [
            'category_id' => Category::factory()->create()->id,
            'name' => 'Nom modifié',
            'unit' => 'pièce',
            'min_order_qty' => 1,
            'stock_qty' => 1,
            'base_price' => 1,
        ]);

        $response->assertForbidden();
        $this->assertSame($product->name, $product->fresh()->name);
    }

    public function test_a_customer_cannot_access_the_vendor_area(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->get(route('vendor.dashboard'));

        $response->assertForbidden();
    }
}
