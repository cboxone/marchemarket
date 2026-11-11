<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmationMail;
use App\Mail\QuoteUpdatedMail;
use App\Models\Order;
use App\Models\Product;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class QuoteRequestTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = []): Product
    {
        return Product::factory()->for(Vendor::factory()->create())->create($attributes + [
            'is_active' => true,
            'stock_qty' => 500,
            'min_order_qty' => 1,
            'base_price' => 10000,
            'unit' => 'sac',
        ]);
    }

    private function quotedFor(User $buyer, Product $product, array $attributes = []): QuoteRequest
    {
        return QuoteRequest::factory()->forProduct($product)->quoted()->create($attributes + ['buyer_id' => $buyer->id]);
    }

    private function acceptPayload(QuoteRequest $quote, array $overrides = []): array
    {
        return array_merge([
            'shipping_address' => '12 rue du Marché',
            'shipping_city' => 'Cotonou',
            'shipping_country' => 'Bénin',
            'shipping_phone' => '90000000',
            'quoted_total' => $quote->quotedTotal(),
        ], $overrides);
    }

    // ---------- Création de la demande ----------

    public function test_guests_are_redirected_to_login(): void
    {
        $product = $this->product();

        $this->get(route('quotes.create', $product))->assertRedirect(route('login'));
        $this->get(route('quotes.index'))->assertRedirect(route('login'));
    }

    public function test_a_customer_sees_the_request_form(): void
    {
        $product = $this->product(['name' => 'Riz parfumé 25kg']);

        $this->actingAs(User::factory()->create())->get(route('quotes.create', $product))
            ->assertOk()->assertSee('Riz parfumé 25kg');
    }

    public function test_an_inactive_product_cannot_be_quoted(): void
    {
        $product = $this->product(['is_active' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('quotes.create', $product))->assertNotFound();
        $this->actingAs($user)->post(route('quotes.store', $product), ['quantity' => 10])->assertNotFound();
    }

    public function test_a_vendor_cannot_request_a_quote_on_their_own_product(): void
    {
        $product = $this->product();

        $this->actingAs($product->vendor->user)->get(route('quotes.create', $product))->assertForbidden();
        $this->actingAs($product->vendor->user)->post(route('quotes.store', $product), ['quantity' => 10])->assertForbidden();
        $this->assertDatabaseCount('quote_requests', 0);
    }

    public function test_a_quote_request_is_created_with_a_snapshot_and_the_vendor_is_notified(): void
    {
        Mail::fake();
        $product = $this->product(['name' => 'Riz parfumé 25kg']);
        $buyer = User::factory()->create();

        $this->actingAs($buyer)->post(route('quotes.store', $product), [
            'quantity' => 200,
            'target_price' => 8500,
            'message' => 'Livraison mensuelle souhaitée.',
        ])->assertSessionHasNoErrors();

        $quote = QuoteRequest::first();

        $this->assertMatchesRegularExpression('/^DEV-[A-Z0-9]{8}$/', $quote->reference);
        $this->assertSame($buyer->id, $quote->buyer_id);
        $this->assertSame($product->vendor_id, $quote->vendor_id);
        $this->assertSame('Riz parfumé 25kg', $quote->product_name);
        $this->assertSame('sac', $quote->unit);
        $this->assertSame(200, $quote->quantity);
        $this->assertSame('pending', $quote->status);
        $this->assertTrue($quote->expires_at->isSameDay(now()->addDays(14)));

        $this->assertMailed(QuoteUpdatedMail::class, fn ($m) => $m->hasTo($product->vendor->user->email) && $m->event === 'requested');
    }

    public function test_the_request_is_validated(): void
    {
        $product = $this->product();
        $user = User::factory()->create();

        foreach ([
            ['quantity' => ''],
            ['quantity' => 0],
            ['quantity' => 1000001],
            ['quantity' => 10, 'target_price' => -5],
            ['quantity' => 10, 'message' => str_repeat('a', 2001)],
        ] as $payload) {
            $this->actingAs($user)->post(route('quotes.store', $product), $payload)->assertSessionHasErrors();
        }

        $this->assertDatabaseCount('quote_requests', 0);
    }

    public function test_an_unverified_user_cannot_request_a_quote(): void
    {
        $product = $this->product();

        $this->actingAs(User::factory()->unverified()->create())
            ->post(route('quotes.store', $product), ['quantity' => 10])
            ->assertStatus(302);

        $this->assertDatabaseCount('quote_requests', 0);
    }

    public function test_only_one_open_request_per_product(): void
    {
        Mail::fake();
        $product = $this->product();
        $buyer = User::factory()->create();

        $this->actingAs($buyer)->post(route('quotes.store', $product), ['quantity' => 10]);
        $first = QuoteRequest::first();

        $this->actingAs($buyer)->post(route('quotes.store', $product), ['quantity' => 20])
            ->assertRedirect(route('quotes.show', $first));

        $this->assertDatabaseCount('quote_requests', 1);
    }

    public function test_a_new_request_is_allowed_once_the_previous_one_is_closed(): void
    {
        Mail::fake();
        $product = $this->product();
        $buyer = User::factory()->create();

        QuoteRequest::factory()->forProduct($product)->create(['buyer_id' => $buyer->id, 'status' => 'cancelled']);

        $this->actingAs($buyer)->post(route('quotes.store', $product), ['quantity' => 10])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('quote_requests', 2);
    }

    public function test_quote_requests_are_rate_limited(): void
    {
        Mail::fake();
        $product = $this->product();
        $buyer = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($buyer)->post(route('quotes.store', $product), ['quantity' => 10])->assertStatus(302);
        }

        $this->actingAs($buyer)->post(route('quotes.store', $product), ['quantity' => 10])->assertStatus(429);
    }

    // ---------- Consultation ----------

    public function test_the_index_lists_only_my_requests(): void
    {
        $mine = QuoteRequest::factory()->create(['buyer_id' => ($me = User::factory()->create())->id, 'product_name' => 'Mon produit']);
        QuoteRequest::factory()->create(['product_name' => 'Produit étranger']);

        $this->actingAs($me)->get(route('quotes.index'))
            ->assertOk()->assertSee('Mon produit')->assertDontSee('Produit étranger');
    }

    public function test_another_customer_cannot_see_my_request(): void
    {
        $quote = QuoteRequest::factory()->create();

        $this->actingAs(User::factory()->create())->get(route('quotes.show', $quote))->assertForbidden();
        $this->actingAs(User::factory()->create())->post(route('quotes.cancel', $quote))->assertForbidden();
        $this->actingAs(User::factory()->create())->post(route('quotes.accept', $quote), [])->assertForbidden();
    }

    public function test_a_quoted_request_shows_the_offer_and_the_accept_form(): void
    {
        $buyer = User::factory()->create();
        $quote = $this->quotedFor($buyer, $this->product()); // 9 000 × 200 + 5 000

        $this->actingAs($buyer)->get(route('quotes.show', $quote))
            ->assertOk()
            ->assertSee('1 805 000 FCFA')
            ->assertSee('Accepter et payer');
    }

    // ---------- Annulation / refus ----------

    public function test_cancelling_a_pending_request_notifies_the_vendor(): void
    {
        Mail::fake();
        $buyer = User::factory()->create();
        $quote = QuoteRequest::factory()->create(['buyer_id' => $buyer->id]);

        $this->actingAs($buyer)->post(route('quotes.cancel', $quote))->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $quote->fresh()->status);
        $this->assertMailed(QuoteUpdatedMail::class, fn ($m) => $m->event === 'cancelled' && $m->hasTo($quote->vendor->user->email));
    }

    public function test_refusing_a_received_quote_marks_it_rejected(): void
    {
        Mail::fake();
        $buyer = User::factory()->create();
        $quote = $this->quotedFor($buyer, $this->product());

        $this->actingAs($buyer)->post(route('quotes.cancel', $quote))->assertSessionHasNoErrors();

        $this->assertSame('rejected', $quote->fresh()->status);
        $this->assertMailed(QuoteUpdatedMail::class, fn ($m) => $m->event === 'rejected');
    }

    public function test_a_closed_request_cannot_be_cancelled(): void
    {
        Mail::fake();
        $buyer = User::factory()->create();

        foreach (['accepted', 'declined', 'rejected', 'expired'] as $status) {
            $quote = QuoteRequest::factory()->create(['buyer_id' => $buyer->id, 'status' => $status]);

            $this->actingAs($buyer)->post(route('quotes.cancel', $quote))->assertSessionHasErrors('quote');
            $this->assertSame($status, $quote->fresh()->status);
        }

        Mail::assertNothingQueued();
    }

    // ---------- Acceptation → commande ----------

    public function test_accepting_a_quote_creates_a_reserved_order_at_the_negotiated_price(): void
    {
        Mail::fake();
        config(['marketplace.commission_percent' => 10]);

        $buyer = User::factory()->create();
        $product = $this->product(['stock_qty' => 500, 'base_price' => 12000]);
        $quote = $this->quotedFor($buyer, $product); // 9 000 × 200 + 5 000 de livraison

        $response = $this->actingAs($buyer)->post(route('quotes.accept', $quote), $this->acceptPayload($quote));

        $order = Order::first();
        $response->assertRedirect(route('payment.show', $order));

        $this->assertSame($buyer->id, $order->buyer_id);
        $this->assertEqualsWithDelta(1805000.0, (float) $order->total_amount, 0.01);
        $this->assertEqualsWithDelta(5000.0, (float) $order->shipping_fee, 0.01);
        $this->assertSame('Cotonou', $order->shipping_city);
        $this->assertNull($order->coupon_id);

        $item = $order->items()->first();
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame($product->vendor_id, $item->vendor_id);
        $this->assertSame(200, $item->quantity);
        $this->assertEqualsWithDelta(9000.0, (float) $item->unit_price, 0.01);   // prix du devis, pas du catalogue
        $this->assertEqualsWithDelta(10.0, (float) $item->commission_rate, 0.001);

        $this->assertSame(300, $product->fresh()->stock_qty);                    // stock réservé

        $fresh = $quote->fresh();
        $this->assertSame('accepted', $fresh->status);
        $this->assertSame($order->id, $fresh->order_id);

        $this->assertMailed(OrderConfirmationMail::class, fn ($m) => $m->hasTo($buyer->email));
        $this->assertMailed(QuoteUpdatedMail::class, fn ($m) => $m->event === 'accepted' && $m->hasTo($product->vendor->user->email));
    }

    public function test_the_shipping_address_is_required_to_accept(): void
    {
        $buyer = User::factory()->create();
        $quote = $this->quotedFor($buyer, $this->product());

        $this->actingAs($buyer)->post(route('quotes.accept', $quote), $this->acceptPayload($quote, ['shipping_address' => '']))
            ->assertSessionHasErrors('shipping_address');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_pending_quote_cannot_be_accepted(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();
        $quote = QuoteRequest::factory()->forProduct($product)->create(['buyer_id' => $buyer->id]);

        $this->actingAs($buyer)->post(route('quotes.accept', $quote), $this->acceptPayload($quote, ['quoted_total' => 100]))
            ->assertSessionHasErrors('quote');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_an_expired_quote_cannot_be_accepted_even_before_the_scheduled_command_ran(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();
        $quote = $this->quotedFor($buyer, $product, ['expires_at' => now()->subDay()]);

        $this->assertSame('quoted', $quote->status); // la commande planifiée n'a pas encore tourné

        $this->actingAs($buyer)->get(route('quotes.show', $quote))->assertOk()->assertSee('Expiré')->assertDontSee('Accepter et payer');

        $this->actingAs($buyer)->post(route('quotes.accept', $quote), $this->acceptPayload($quote))
            ->assertSessionHasErrors('quote');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(500, $product->fresh()->stock_qty);
    }

    public function test_accepting_with_a_stale_total_is_refused(): void
    {
        $buyer = User::factory()->create();
        $quote = $this->quotedFor($buyer, $this->product());

        // Le fournisseur a révisé son devis après que l'acheteur l'a lu.
        $quote->forceFill(['quoted_unit_price' => 12000])->save();

        $this->actingAs($buyer)->post(route('quotes.accept', $quote), $this->acceptPayload($quote, ['quoted_total' => 1805000]))
            ->assertSessionHasErrors('quote');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_accepting_is_refused_when_the_stock_is_insufficient(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product(['stock_qty' => 150]);
        $quote = $this->quotedFor($buyer, $product); // 200 demandés

        $this->actingAs($buyer)->post(route('quotes.accept', $quote), $this->acceptPayload($quote))
            ->assertSessionHasErrors('quote');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(150, $product->fresh()->stock_qty);
        $this->assertSame('quoted', $quote->fresh()->status);
    }

    public function test_accepting_is_refused_when_the_product_is_no_longer_for_sale(): void
    {
        $buyer = User::factory()->create();
        $inactive = $this->product();
        $deleted = $this->product();
        $quoteA = $this->quotedFor($buyer, $inactive);
        $quoteB = $this->quotedFor($buyer, $deleted);

        $inactive->update(['is_active' => false]);
        $deleted->delete();

        $this->actingAs($buyer)->post(route('quotes.accept', $quoteA), $this->acceptPayload($quoteA))->assertSessionHasErrors('quote');
        $this->actingAs($buyer)->post(route('quotes.accept', $quoteB), $this->acceptPayload($quoteB))->assertSessionHasErrors('quote');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_quote_cannot_be_accepted_twice(): void
    {
        Mail::fake();
        $buyer = User::factory()->create();
        $product = $this->product();
        $quote = $this->quotedFor($buyer, $product);

        $this->actingAs($buyer)->post(route('quotes.accept', $quote), $this->acceptPayload($quote))->assertSessionHasNoErrors();
        $this->actingAs($buyer)->post(route('quotes.accept', $quote), $this->acceptPayload($quote))->assertSessionHasErrors('quote');

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(300, $product->fresh()->stock_qty); // réservé une seule fois
    }

    public function test_an_unpaid_quote_order_is_cancelled_automatically_and_the_stock_released(): void
    {
        Mail::fake();
        $buyer = User::factory()->create();
        $product = $this->product();
        $quote = $this->quotedFor($buyer, $product);

        $this->actingAs($buyer)->post(route('quotes.accept', $quote), $this->acceptPayload($quote));
        $order = Order::first();
        Order::whereKey($order->id)->update(['created_at' => now()->subHours(2)]);

        $this->artisan('orders:cancel-unpaid')->assertSuccessful();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(500, $product->fresh()->stock_qty);
        $this->assertSame('accepted', $quote->fresh()->status); // l'historique du devis reste
    }

    // ---------- Expiration ----------

    public function test_the_expire_command_only_closes_overdue_open_requests(): void
    {
        $overduePending = QuoteRequest::factory()->create(['expires_at' => now()->subDay()]);
        $overdueQuoted = QuoteRequest::factory()->quoted()->create(['expires_at' => now()->subHour()]);
        $futurePending = QuoteRequest::factory()->create(['expires_at' => now()->addDay()]);
        $accepted = QuoteRequest::factory()->create(['status' => 'accepted', 'expires_at' => now()->subDay()]);
        $declined = QuoteRequest::factory()->create(['status' => 'declined', 'expires_at' => now()->subDay()]);

        $this->artisan('quotes:expire')->expectsOutput('2 demande(s) de devis expirée(s).')->assertSuccessful();

        $this->assertSame('expired', $overduePending->fresh()->status);
        $this->assertSame('expired', $overdueQuoted->fresh()->status);
        $this->assertSame('pending', $futurePending->fresh()->status);
        $this->assertSame('accepted', $accepted->fresh()->status);
        $this->assertSame('declined', $declined->fresh()->status);
    }

    public function test_the_expire_command_is_scheduled(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('quotes:expire')->assertSuccessful();
    }

    // ---------- Fiche produit ----------

    public function test_the_product_page_offers_a_quote_to_customers_but_not_to_vendors(): void
    {
        $product = $this->product();

        $this->actingAs(User::factory()->create())->get(route('products.show', $product))
            ->assertOk()->assertSee(route('quotes.create', $product), false);

        $this->actingAs(Vendor::factory()->create()->user)->get(route('products.show', $product))
            ->assertOk()->assertDontSee(route('quotes.create', $product), false);
    }

    // ---------- E-mails ----------

    public function test_every_quote_email_renders_with_its_link_and_reference(): void
    {
        $product = $this->product();
        $buyer = User::factory()->create();
        $quote = $this->quotedFor($buyer, $product);
        $quote->forceFill(['decline_reason' => 'Rupture de stock'])->save();
        $vendorUser = $product->vendor->user;

        foreach ([
            ['requested', $vendorUser, route('vendor.quotes.show', $quote)],
            ['quoted', $buyer, route('quotes.show', $quote)],
            ['declined', $buyer, route('quotes.show', $quote)],
            ['accepted', $vendorUser, route('vendor.quotes.show', $quote)],
            ['rejected', $vendorUser, route('vendor.quotes.show', $quote)],
            ['cancelled', $vendorUser, route('vendor.quotes.show', $quote)],
        ] as [$event, $recipient, $url]) {
            $html = (new QuoteUpdatedMail($recipient, $quote->fresh(), $event))->render();

            $this->assertStringContainsString($quote->reference, $html, "événement {$event}");
            $this->assertStringContainsString($url, $html, "événement {$event}");
        }
    }
}
