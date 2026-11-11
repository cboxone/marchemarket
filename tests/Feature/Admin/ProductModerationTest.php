<?php

namespace Tests\Feature\Admin;

use App\Mail\ProductModeratedMail;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductModerationTest extends TestCase
{
    use RefreshDatabase;

    private function suspended(Product $product, string $reason = 'Contrefaçon'): Product
    {
        $product->forceFill(['is_active' => false, 'admin_suspended_at' => now(), 'admin_suspension_reason' => $reason])->save();

        return $product;
    }

    public function test_only_admins_can_access_product_moderation(): void
    {
        $product = Product::factory()->create();

        $this->get(route('admin.products.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.products.index'))->assertForbidden();

        $vendor = Vendor::factory()->create();
        $this->actingAs($vendor->user)->patch(route('admin.products.suspend', $product), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($vendor->user)->delete(route('admin.products.destroy', $product))->assertForbidden();
    }

    public function test_the_index_lists_products_and_filters_by_status_and_search(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Vendor::factory()->create(['store_name' => 'Boutique Zébulon']);

        Product::factory()->create(['name' => 'Article Actif Alpha']);
        Product::factory()->create(['name' => 'Article Masque Beta', 'is_active' => false]);
        $this->suspended(Product::factory()->create(['name' => 'Article Suspendu Gamma']));
        Product::factory()->for($shop)->create(['name' => 'Article Zébulon Delta']);

        $this->actingAs($admin)->get(route('admin.products.index'))
            ->assertOk()->assertSee('Article Actif Alpha')->assertSee('Article Masque Beta')->assertSee('Article Suspendu Gamma');

        $this->actingAs($admin)->get(route('admin.products.index', ['status' => 'suspended']))
            ->assertSee('Article Suspendu Gamma')->assertDontSee('Article Actif Alpha')->assertDontSee('Article Masque Beta');

        $this->actingAs($admin)->get(route('admin.products.index', ['status' => 'inactive']))
            ->assertSee('Article Masque Beta')->assertDontSee('Article Suspendu Gamma')->assertDontSee('Article Actif Alpha');

        $this->actingAs($admin)->get(route('admin.products.index', ['status' => 'active']))
            ->assertSee('Article Actif Alpha')->assertDontSee('Article Masque Beta')->assertDontSee('Article Suspendu Gamma');

        $this->actingAs($admin)->get(route('admin.products.index', ['q' => 'Zébulon']))
            ->assertSee('Article Zébulon Delta')->assertDontSee('Article Actif Alpha');
    }

    public function test_suspending_a_product_hides_it_notifies_the_vendor_and_is_audited(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $vendor = Vendor::factory()->create();
        $product = Product::factory()->for($vendor)->create(['is_active' => true, 'min_order_qty' => 1, 'stock_qty' => 50]);

        $this->actingAs($admin)->patch(route('admin.products.suspend', $product), ['reason' => 'Produit contrefait'])
            ->assertSessionHasNoErrors();

        $fresh = $product->fresh();
        $this->assertFalse($fresh->is_active);
        $this->assertTrue($fresh->isSuspended());
        $this->assertSame('Produit contrefait', $fresh->admin_suspension_reason);

        $this->assertSame(1, AuditLog::where('action', 'product.suspended')->count());
        Mail::assertQueued(ProductModeratedMail::class, fn ($m) => $m->hasTo($vendor->user->email) && $m->action === 'suspended' && $m->reason === 'Produit contrefait');

        // Disparu du catalogue et de la vente.
        auth()->logout();
        $this->get(route('products.show', $product))->assertNotFound();

        $buyer = User::factory()->create();
        $this->actingAs($buyer)->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1])
            ->assertSessionHasErrors('product_id');
    }

    public function test_a_reason_is_required_to_suspend(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $this->actingAs($admin)->patch(route('admin.products.suspend', $product), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertFalse($product->fresh()->isSuspended());
    }

    public function test_an_already_suspended_product_cannot_be_suspended_twice(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $product = $this->suspended(Product::factory()->create());

        $this->actingAs($admin)->patch(route('admin.products.suspend', $product), ['reason' => 'Encore'])
            ->assertSessionHasErrors('product');

        $this->assertSame('Contrefaçon', $product->fresh()->admin_suspension_reason);
        Mail::assertNothingQueued();
    }

    public function test_the_vendor_cannot_put_a_suspended_product_back_online(): void
    {
        $vendor = Vendor::factory()->create();
        $product = $this->suspended(Product::factory()->for($vendor)->create());

        $this->actingAs($vendor->user)->put(route('vendor.products.update', $product), [
            'category_id' => Category::factory()->create()->id,
            'name' => 'Nom corrigé',
            'unit' => 'pièce',
            'min_order_qty' => 1,
            'stock_qty' => 10,
            'base_price' => 1000,
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $fresh = $product->fresh();
        $this->assertSame('Nom corrigé', $fresh->name);   // il peut corriger son annonce
        $this->assertFalse($fresh->is_active);            // mais pas la remettre en ligne
        $this->assertTrue($fresh->isSuspended());
    }

    public function test_the_vendor_sees_the_suspension_and_its_reason(): void
    {
        $vendor = Vendor::factory()->create();
        $product = $this->suspended(Product::factory()->for($vendor)->create(), 'Photos volées');

        $this->actingAs($vendor->user)->get(route('vendor.products.index'))
            ->assertOk()->assertSee("Suspendu par l'administration");

        $this->actingAs($vendor->user)->get(route('vendor.products.edit', $product))
            ->assertOk()->assertSee('Photos volées')->assertDontSee('id="is_active"', false);
    }

    public function test_the_product_form_sends_an_explicit_zero_when_the_checkbox_is_unchecked(): void
    {
        $vendor = Vendor::factory()->create();
        $product = Product::factory()->for($vendor)->create();

        $this->actingAs($vendor->user)->get(route('vendor.products.edit', $product))
            ->assertOk()
            ->assertSee('<input type="hidden" name="is_active" value="0">', false);
    }

    public function test_unchecking_visibility_actually_hides_the_product(): void
    {
        $vendor = Vendor::factory()->create();
        $product = Product::factory()->for($vendor)->create(['is_active' => true]);

        $this->actingAs($vendor->user)->put(route('vendor.products.update', $product), [
            'category_id' => $product->category_id,
            'name' => $product->name,
            'unit' => $product->unit,
            'min_order_qty' => 1,
            'stock_qty' => 10,
            'base_price' => 1000,
            'is_active' => '0',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($product->fresh()->is_active);
        $this->assertFalse($product->fresh()->isSuspended());
    }

    public function test_restoring_a_product_puts_it_back_online_and_is_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->suspended(Product::factory()->create());

        $this->actingAs($admin)->patch(route('admin.products.restore', $product))->assertSessionHasNoErrors();

        $fresh = $product->fresh();
        $this->assertTrue($fresh->is_active);
        $this->assertFalse($fresh->isSuspended());
        $this->assertNull($fresh->admin_suspension_reason);
        $this->assertSame(1, AuditLog::where('action', 'product.restored')->count());

        $this->get(route('products.show', $product))->assertOk();
    }

    public function test_restoring_a_product_that_is_not_suspended_is_refused(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['is_active' => false]); // masqué par le vendeur

        $this->actingAs($admin)->patch(route('admin.products.restore', $product))->assertSessionHasErrors('product');

        // Un produit masqué par son vendeur ne doit pas être remis en ligne par ce biais.
        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_deleting_a_product_removes_it_and_its_image_files_and_keeps_order_history(): void
    {
        Storage::fake('public');
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $vendor = Vendor::factory()->create();
        $product = Product::factory()->for($vendor)->create();

        Storage::disk('public')->put('products/photo.webp', 'binaire');
        ProductImage::create(['product_id' => $product->id, 'path' => 'products/photo.webp', 'position' => 0]);

        $order = Order::factory()->create();
        $item = $order->items()->create([
            'vendor_id' => $vendor->id, 'product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 1, 'unit_price' => 1000,
        ]);

        $this->actingAs($admin)->delete(route('admin.products.destroy', $product), ['reason' => 'Arnaque'])
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($product);
        Storage::disk('public')->assertMissing('products/photo.webp');

        // L'historique des commandes est conservé.
        $this->assertModelExists($item);
        $this->assertNull($item->fresh()->product_id);
        $this->assertSame($product->name, $item->fresh()->product_name);

        $this->assertSame(1, AuditLog::where('action', 'product.deleted')->count());
        Mail::assertQueued(ProductModeratedMail::class, fn ($m) => $m->hasTo($vendor->user->email) && $m->action === 'removed' && $m->reason === 'Arnaque');
    }

    public function test_the_vendor_deleting_his_own_product_also_removes_the_image_files(): void
    {
        Storage::fake('public');

        $vendor = Vendor::factory()->create();
        $product = Product::factory()->for($vendor)->create();

        Storage::disk('public')->put('products/mine.webp', 'binaire');
        ProductImage::create(['product_id' => $product->id, 'path' => 'products/mine.webp', 'position' => 0]);

        $this->actingAs($vendor->user)->delete(route('vendor.products.destroy', $product))->assertSessionHasNoErrors();

        $this->assertModelMissing($product);
        Storage::disk('public')->assertMissing('products/mine.webp');
    }
}
