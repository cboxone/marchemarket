<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function shippingPayload(): array
    {
        return [
            'shipping_address' => '12 rue du Marché',
            'shipping_city' => 'Cotonou',
            'shipping_country' => 'Bénin',
            'shipping_phone' => '90000000',
        ];
    }

    public function test_placing_an_order_decrements_stock_and_creates_an_order(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $product = Product::factory()->create(['min_order_qty' => 10, 'stock_qty' => 100, 'base_price' => 1000]);

        $this->actingAs($user)->post('/panier', ['product_id' => $product->id, 'quantity' => 10]);

        $response = $this->actingAs($user)->post('/commande', $this->shippingPayload());

        $order = Order::first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('payment.show', $order));

        $this->assertSame(90, $product->fresh()->stock_qty);
        $this->assertSame(1, $order->items()->count());
        $this->assertDatabaseCount('cart_items', 0);

        Mail::assertSent(OrderConfirmationMail::class, fn ($mail) => $mail->order->is($order));
    }

    public function test_the_shipping_fee_is_added_to_the_order_total(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $product = Product::factory()->create(['min_order_qty' => 1, 'stock_qty' => 100, 'base_price' => 5000]);

        $this->actingAs($user)->post('/panier', ['product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($user)->post('/commande', $this->shippingPayload());

        $order = Order::first();

        $this->assertSame((float) Order::SHIPPING_FEE, (float) $order->shipping_fee);
        $this->assertSame(5000.0 + Order::SHIPPING_FEE, (float) $order->total_amount);
    }

    public function test_it_refuses_an_order_that_exceeds_available_stock(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $product = Product::factory()->create(['min_order_qty' => 5, 'stock_qty' => 8]);

        // Le panier autorise l'ajout, mais le stock a pu bouger entre-temps
        // (autre commande) : on simule ce cas en forçant une quantité au-delà
        // du stock restant directement sur la ligne de panier.
        $this->actingAs($user)->post('/panier', ['product_id' => $product->id, 'quantity' => 5]);
        $cartItem = $user->cart->items()->first();
        $cartItem->update(['quantity' => 20]);

        $response = $this->actingAs($user)->post('/commande', $this->shippingPayload());

        $response->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(8, $product->fresh()->stock_qty);
        Mail::assertNothingSent();
    }

    public function test_an_unverified_buyer_cannot_place_an_order(): void
    {
        $user = User::factory()->unverified()->create();
        $product = Product::factory()->create(['min_order_qty' => 1, 'stock_qty' => 10]);

        $this->actingAs($user)->post('/panier', ['product_id' => $product->id, 'quantity' => 1]);
        $response = $this->actingAs($user)->post('/commande', $this->shippingPayload());

        $response->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('orders', 0);
    }
}
