<?php

namespace Tests\Feature\Vendor;

use App\Mail\QuoteUpdatedMail;
use App\Models\Order;
use App\Models\Product;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class QuoteResponseTest extends TestCase
{
    use RefreshDatabase;

    private function pendingFor(Vendor $vendor, array $attributes = []): QuoteRequest
    {
        $product = Product::factory()->for($vendor)->create(['is_active' => true, 'stock_qty' => 500, 'base_price' => 10000, 'unit' => 'sac']);

        return QuoteRequest::factory()->forProduct($product)->create($attributes);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'unit_price' => 9000,
            'quantity' => 200,
            'shipping_fee' => 5000,
            'valid_days' => 7,
            'note' => 'Prix valable pour un enlèvement groupé.',
        ], $overrides);
    }

    public function test_the_index_lists_only_my_requests_and_can_be_filtered(): void
    {
        $vendor = Vendor::factory()->create();
        $this->pendingFor($vendor, ['product_name' => 'Mon produit en attente']);
        $this->pendingFor($vendor, ['product_name' => 'Mon produit décliné', 'status' => 'declined']);
        QuoteRequest::factory()->create(['product_name' => 'Produit du voisin']);

        $this->actingAs($vendor->user)->get(route('vendor.quotes.index'))
            ->assertOk()->assertSee('Mon produit en attente')->assertSee('Mon produit décliné')->assertDontSee('Produit du voisin');

        $this->actingAs($vendor->user)->get(route('vendor.quotes.index', ['status' => 'declined']))
            ->assertSee('Mon produit décliné')->assertDontSee('Mon produit en attente');
    }

    public function test_a_vendor_cannot_open_or_answer_another_vendors_request(): void
    {
        $other = Vendor::factory()->create();
        $quote = $this->pendingFor(Vendor::factory()->create());

        $this->actingAs($other->user)->get(route('vendor.quotes.show', $quote))->assertNotFound();
        $this->actingAs($other->user)->post(route('vendor.quotes.respond', $quote), $this->payload())->assertNotFound();
        $this->actingAs($other->user)->post(route('vendor.quotes.decline', $quote))->assertNotFound();

        $this->assertSame('pending', $quote->fresh()->status);
    }

    public function test_a_customer_cannot_access_the_vendor_quote_pages(): void
    {
        $quote = QuoteRequest::factory()->create();

        $this->actingAs(User::factory()->create())->get(route('vendor.quotes.index'))->assertForbidden();
        $this->actingAs(User::factory()->create())->post(route('vendor.quotes.respond', $quote), $this->payload())->assertForbidden();
    }

    public function test_responding_records_the_quote_and_notifies_the_buyer(): void
    {
        Mail::fake();
        $vendor = Vendor::factory()->create();
        $quote = $this->pendingFor($vendor);

        $this->actingAs($vendor->user)->post(route('vendor.quotes.respond', $quote), $this->payload())
            ->assertRedirect(route('vendor.quotes.show', $quote))->assertSessionHasNoErrors();

        $fresh = $quote->fresh();
        $this->assertSame('quoted', $fresh->status);
        $this->assertEqualsWithDelta(9000.0, (float) $fresh->quoted_unit_price, 0.01);
        $this->assertSame(200, $fresh->quoted_quantity);
        $this->assertEqualsWithDelta(5000.0, (float) $fresh->quoted_shipping_fee, 0.01);
        $this->assertSame('Prix valable pour un enlèvement groupé.', $fresh->vendor_note);
        $this->assertNotNull($fresh->quoted_at);
        $this->assertTrue($fresh->expires_at->isSameDay(now()->addDays(7)));
        $this->assertEqualsWithDelta(1805000.0, $fresh->quotedTotal(), 0.01);

        $this->assertMailed(QuoteUpdatedMail::class, fn ($m) => $m->event === 'quoted' && $m->hasTo($quote->buyer->email));
    }

    public function test_the_shipping_fee_defaults_to_the_standard_fee_but_can_be_zero(): void
    {
        Mail::fake();
        $vendor = Vendor::factory()->create();
        $withDefault = $this->pendingFor($vendor);
        $free = $this->pendingFor($vendor);

        $this->actingAs($vendor->user)->post(route('vendor.quotes.respond', $withDefault), $this->payload(['shipping_fee' => '']));
        $this->actingAs($vendor->user)->post(route('vendor.quotes.respond', $free), $this->payload(['shipping_fee' => 0]));

        $this->assertEqualsWithDelta((float) Order::SHIPPING_FEE, (float) $withDefault->fresh()->quoted_shipping_fee, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $free->fresh()->quoted_shipping_fee, 0.01);
    }

    public function test_the_quote_is_validated(): void
    {
        $vendor = Vendor::factory()->create();
        $quote = $this->pendingFor($vendor);

        foreach ([
            ['unit_price' => 0],
            ['unit_price' => ''],
            ['quantity' => 0],
            ['shipping_fee' => -1],
            ['valid_days' => 0],
            ['valid_days' => 61],
            ['note' => str_repeat('a', 2001)],
        ] as $overrides) {
            $this->actingAs($vendor->user)->post(route('vendor.quotes.respond', $quote), $this->payload($overrides))->assertSessionHasErrors();
        }

        $this->assertSame('pending', $quote->fresh()->status);
    }

    public function test_a_quote_can_be_revised_until_it_is_accepted(): void
    {
        Mail::fake();
        $vendor = Vendor::factory()->create();
        $quote = $this->pendingFor($vendor);

        $this->actingAs($vendor->user)->post(route('vendor.quotes.respond', $quote), $this->payload());
        $this->actingAs($vendor->user)->post(route('vendor.quotes.respond', $quote), $this->payload(['unit_price' => 8500]))
            ->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(8500.0, (float) $quote->fresh()->quoted_unit_price, 0.01);
        $this->assertSame('quoted', $quote->fresh()->status);
    }

    public function test_a_closed_request_cannot_be_answered_or_declined(): void
    {
        Mail::fake();
        $vendor = Vendor::factory()->create();

        foreach (['accepted', 'declined', 'rejected', 'cancelled', 'expired'] as $status) {
            $quote = $this->pendingFor($vendor, ['status' => $status]);

            $this->actingAs($vendor->user)->post(route('vendor.quotes.respond', $quote), $this->payload())->assertSessionHasErrors('quote');
            $this->actingAs($vendor->user)->post(route('vendor.quotes.decline', $quote))->assertSessionHasErrors('quote');

            $this->assertSame($status, $quote->fresh()->status);
            $this->assertNull($quote->fresh()->quoted_unit_price);
        }

        Mail::assertNothingQueued();
    }

    public function test_an_overdue_request_cannot_be_answered_before_the_scheduled_command_ran(): void
    {
        $vendor = Vendor::factory()->create();
        $quote = $this->pendingFor($vendor, ['expires_at' => now()->subHour()]);

        $this->actingAs($vendor->user)->post(route('vendor.quotes.respond', $quote), $this->payload())->assertSessionHasErrors('quote');

        $this->assertSame('pending', $quote->fresh()->status);
    }

    public function test_declining_records_the_reason_and_notifies_the_buyer(): void
    {
        Mail::fake();
        $vendor = Vendor::factory()->create();
        $quote = $this->pendingFor($vendor);

        $this->actingAs($vendor->user)->post(route('vendor.quotes.decline', $quote), ['reason' => 'Rupture de stock prolongée'])
            ->assertSessionHasNoErrors();

        $fresh = $quote->fresh();
        $this->assertSame('declined', $fresh->status);
        $this->assertSame('Rupture de stock prolongée', $fresh->decline_reason);
        $this->assertMailed(QuoteUpdatedMail::class, fn ($m) => $m->event === 'declined' && $m->hasTo($quote->buyer->email));
    }

    public function test_a_revised_quote_cannot_be_accepted_at_the_old_price(): void
    {
        Mail::fake();
        $vendor = Vendor::factory()->create();
        $quote = $this->pendingFor($vendor);

        $this->actingAs($vendor->user)->post(route('vendor.quotes.respond', $quote), $this->payload(['unit_price' => 9000]));
        $seenTotal = $quote->fresh()->quotedTotal(); // ce que l'acheteur a sous les yeux

        // Le fournisseur augmente son prix avant que l'acheteur ne clique.
        $this->actingAs($vendor->user)->post(route('vendor.quotes.respond', $quote), $this->payload(['unit_price' => 11000]));

        $this->actingAs($quote->buyer)->post(route('quotes.accept', $quote), [
            'shipping_address' => '12 rue du Marché',
            'shipping_city' => 'Cotonou',
            'shipping_country' => 'Bénin',
            'shipping_phone' => '90000000',
            'quoted_total' => $seenTotal,
        ])->assertSessionHasErrors('quote');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_the_request_page_shows_the_buyer_and_the_catalogue_reference(): void
    {
        $vendor = Vendor::factory()->create();
        $quote = $this->pendingFor($vendor, ['message' => 'Livraison mensuelle souhaitée.']);

        $this->actingAs($vendor->user)->get(route('vendor.quotes.show', $quote))
            ->assertOk()
            ->assertSee($quote->buyer->name)
            ->assertSee('Livraison mensuelle souhaitée.')
            ->assertSee('Envoyer le devis');
    }

    public function test_the_dashboard_counts_requests_awaiting_an_answer(): void
    {
        $vendor = Vendor::factory()->create();
        $this->pendingFor($vendor);
        $this->pendingFor($vendor);
        $this->pendingFor($vendor, ['status' => 'quoted']);                          // déjà chiffrée
        $this->pendingFor($vendor, ['expires_at' => now()->subDay()]);               // échue
        QuoteRequest::factory()->create();                                           // autre fournisseur

        $this->actingAs($vendor->user)->get(route('vendor.dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['pending_quotes'] === 2);
    }
}
