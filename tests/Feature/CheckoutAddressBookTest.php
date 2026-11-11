<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckoutAddressBookTest extends TestCase
{
    use RefreshDatabase;

    private function addItemToCart(User $user): void
    {
        $product = Product::factory()->create(['min_order_qty' => 1, 'stock_qty' => 100, 'base_price' => 1000]);
        $this->actingAs($user)->post('/panier', ['product_id' => $product->id, 'quantity' => 1]);
    }

    public function test_checking_out_with_a_saved_address_uses_its_fields(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $address = Address::factory()->create([
            'user_id' => $user->id, 'address' => '5 avenue du Port', 'city' => 'Cotonou', 'country' => 'Bénin', 'phone' => '90111111',
        ]);
        $this->addItemToCart($user);

        $this->actingAs($user)->post('/commande', ['address_id' => $address->id])->assertSessionHasNoErrors();

        $order = Order::first();
        $this->assertSame('5 avenue du Port', $order->shipping_address);
        $this->assertSame('90111111', $order->shipping_phone);
    }

    public function test_choosing_someone_elses_address_is_refused(): void
    {
        $user = User::factory()->create();
        $foreign = Address::factory()->create();
        $this->addItemToCart($user);

        $this->actingAs($user)->post('/commande', ['address_id' => $foreign->id])
            ->assertSessionHasErrors('address_id');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_manual_entry_without_saving_does_not_create_an_address(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->addItemToCart($user);

        $this->actingAs($user)->post('/commande', [
            'shipping_address' => '12 rue du Marché', 'shipping_city' => 'Cotonou',
            'shipping_country' => 'Bénin', 'shipping_phone' => '90000000',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_checking_save_address_stores_it_and_makes_it_default(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->addItemToCart($user);

        $this->actingAs($user)->post('/commande', [
            'shipping_address' => '12 rue du Marché', 'shipping_city' => 'Cotonou',
            'shipping_country' => 'Bénin', 'shipping_phone' => '90000000',
            'save_address' => '1', 'address_label' => 'Boutique',
        ])->assertSessionHasNoErrors();

        $saved = Address::where('user_id', $user->id)->first();
        $this->assertNotNull($saved);
        $this->assertSame('Boutique', $saved->label);
        $this->assertTrue($saved->is_default);
    }

    public function test_neither_address_id_nor_manual_fields_fails_validation(): void
    {
        $user = User::factory()->create();
        $this->addItemToCart($user);

        $this->actingAs($user)->post('/commande', [])->assertSessionHasErrors('shipping_address');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_accepting_a_quote_with_a_saved_address_uses_its_fields(): void
    {
        Mail::fake();
        $buyer = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $buyer->id, 'city' => 'Parakou']);

        $product = Product::factory()->create(['is_active' => true, 'stock_qty' => 500, 'unit' => 'sac']);
        $quote = \App\Models\QuoteRequest::factory()->forProduct($product)->quoted()->create(['buyer_id' => $buyer->id]);

        $this->actingAs($buyer)->post(route('quotes.accept', $quote), [
            'address_id' => $address->id,
            'quoted_total' => $quote->quotedTotal(),
        ])->assertSessionHasNoErrors();

        $order = Order::first();
        $this->assertSame('Parakou', $order->shipping_city);
    }
}
