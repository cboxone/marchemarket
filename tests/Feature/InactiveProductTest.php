<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Pack;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Services\KkiapayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InactiveProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_inactive_product_page_is_a_404_for_visitors(): void
    {
        $product = Product::factory()->create(['is_active' => false]);

        $this->get(route('products.show', $product))->assertNotFound();
    }

    public function test_an_inactive_product_page_is_a_404_for_other_customers_and_vendors(): void
    {
        $product = Product::factory()->create(['is_active' => false]);
        $otherVendor = Vendor::factory()->create();

        $this->actingAs(User::factory()->create())->get(route('products.show', $product))->assertNotFound();
        $this->actingAs($otherVendor->user)->get(route('products.show', $product))->assertNotFound();
    }

    public function test_the_owner_vendor_and_admins_can_still_preview_an_inactive_product(): void
    {
        $vendor = Vendor::factory()->create();
        $product = Product::factory()->for($vendor)->create(['is_active' => false]);

        $this->actingAs($vendor->user)->get(route('products.show', $product))->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get(route('products.show', $product))->assertOk();
    }

    public function test_an_inactive_product_cannot_be_added_to_the_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => false, 'min_order_qty' => 1, 'stock_qty' => 100]);

        $this->actingAs($user)->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1])
            ->assertSessionHasErrors('product_id');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_a_quantity_above_the_available_stock_cannot_be_added_to_the_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['min_order_qty' => 1, 'stock_qty' => 8, 'max_order_qty' => null]);

        $this->actingAs($user)->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 9])
            ->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_the_cumulative_cart_quantity_cannot_exceed_the_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['min_order_qty' => 1, 'stock_qty' => 8, 'max_order_qty' => null]);

        $this->actingAs($user)->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 5])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 5])->assertSessionHasErrors('quantity');

        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 5]);
    }

    public function test_updating_a_cart_line_above_the_stock_is_refused(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['min_order_qty' => 1, 'stock_qty' => 8, 'max_order_qty' => null]);

        $this->actingAs($user)->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2]);
        $item = $user->cart->items()->first();

        $this->actingAs($user)->patch(route('cart.update', $item), ['quantity' => 20])->assertSessionHasErrors('quantity');

        $this->assertSame(2, $item->fresh()->quantity);
    }

    public function test_a_pack_skips_products_that_are_no_longer_active(): void
    {
        $vendor = Vendor::factory()->create();
        $active = Product::factory()->for($vendor)->create(['min_order_qty' => 1, 'stock_qty' => 100]);
        $inactive = Product::factory()->for($vendor)->create(['min_order_qty' => 1, 'stock_qty' => 100, 'is_active' => false]);

        $pack = Pack::factory()->for($vendor)->create();
        $pack->items()->create(['product_id' => $active->id, 'quantity' => 2]);
        $pack->items()->create(['product_id' => $inactive->id, 'quantity' => 2]);

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('packs.add-to-cart', $pack))->assertRedirect(route('cart.index'));

        $this->assertDatabaseHas('cart_items', ['product_id' => $active->id]);
        $this->assertDatabaseMissing('cart_items', ['product_id' => $inactive->id]);
    }

    public function test_a_pack_made_only_of_inactive_products_adds_nothing(): void
    {
        $vendor = Vendor::factory()->create();
        $inactive = Product::factory()->for($vendor)->create(['min_order_qty' => 1, 'is_active' => false]);

        $pack = Pack::factory()->for($vendor)->create();
        $pack->items()->create(['product_id' => $inactive->id, 'quantity' => 2]);

        $this->actingAs(User::factory()->create())->post(route('packs.add-to-cart', $pack))
            ->assertSessionHasErrors('pack');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_checkout_refuses_a_product_deactivated_after_it_was_added_to_the_cart(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $product = Product::factory()->create(['min_order_qty' => 1, 'stock_qty' => 100, 'base_price' => 5000]);

        $this->actingAs($user)->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2]);
        $product->update(['is_active' => false]);

        $this->actingAs($user)->post('/commande', [
            'shipping_address' => '12 rue du Marché',
            'shipping_city' => 'Cotonou',
            'shipping_country' => 'Bénin',
            'shipping_phone' => '90000000',
        ])->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(100, $product->fresh()->stock_qty);
        Mail::assertNothingSent();
    }

    public function test_the_payment_page_escapes_the_shipping_phone_in_the_widget_call(): void
    {
        $this->mock(KkiapayService::class, function ($mock) {
            $mock->shouldReceive('publicKey')->andReturn('pk_test');
            $mock->shouldReceive('isSandbox')->andReturn(true);
        });

        $user = User::factory()->create();
        $order = Order::factory()->create([
            'buyer_id' => $user->id,
            'status' => 'pending',
            'payment_status' => 'pending',
            'shipping_phone' => "'});alert(document.cookie);//",
        ]);

        $response = $this->actingAs($user)->get(route('payment.show', $order));

        $response->assertOk();
        // La valeur ne doit jamais apparaître brute : l'apostrophe est échappée.
        $response->assertDontSee("'});alert(document.cookie);//", false);
    }
}
