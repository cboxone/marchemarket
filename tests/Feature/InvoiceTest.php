<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use App\Services\KkiapayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function paidOrder(?User $buyer = null, array $attributes = []): Order
    {
        return Order::factory()->create($attributes + [
            'buyer_id' => ($buyer ?? User::factory()->create())->id,
            'status' => 'paid',
            'payment_status' => 'paid',
            'payment_method' => 'kkiapay',
            'payment_reference' => 'txn_' . uniqid(),
            'shipping_fee' => 2000,
            'total_amount' => 12000,
        ]);
    }

    private function addItem(Order $order, Vendor $vendor, string $name, float $price, int $quantity = 1): void
    {
        $order->items()->create([
            'vendor_id' => $vendor->id,
            'product_name' => $name,
            'quantity' => $quantity,
            'unit_price' => $price,
        ]);
    }

    // ---------- Numérotation ----------

    public function test_a_confirmed_payment_assigns_an_invoice_number(): void
    {
        $this->mock(KkiapayService::class, function ($mock) {
            $mock->shouldReceive('verifyTransaction')->andReturn([
                'status' => 'SUCCESS', 'amount' => 10000.0, 'transactionId' => 'txn_inv', 'reference' => 'CMD-INV00001',
            ]);
        });

        $user = User::factory()->create();
        $order = Order::factory()->create([
            'buyer_id' => $user->id, 'reference' => 'CMD-INV00001', 'status' => 'pending', 'payment_status' => 'pending', 'total_amount' => 10000,
        ]);

        $this->actingAs($user)->postJson(route('payment.verify', $order), ['transaction_id' => 'txn_inv'])->assertOk();

        $fresh = $order->fresh();
        $this->assertMatchesRegularExpression('/^FAC-\d{4}-\d{6}$/', $fresh->invoice_number);
        $this->assertNotNull($fresh->invoiced_at);
    }

    public function test_invoice_numbers_are_sequential_and_idempotent(): void
    {
        $a = $this->paidOrder();
        $b = $this->paidOrder();
        $year = now()->format('Y');

        $a->assignInvoiceNumber();
        $b->assignInvoiceNumber();
        $a->assignInvoiceNumber(); // deuxième appel : ne change rien

        $this->assertSame("FAC-{$year}-000001", $a->fresh()->invoice_number);
        $this->assertSame("FAC-{$year}-000002", $b->fresh()->invoice_number);
    }

    public function test_the_sequence_restarts_each_year(): void
    {
        Carbon::setTestNow('2026-12-31 10:00:00');
        $a = $this->paidOrder();
        $a->assignInvoiceNumber();

        Carbon::setTestNow('2027-01-01 09:00:00');
        $b = $this->paidOrder();
        $c = $this->paidOrder();
        $b->assignInvoiceNumber();
        $c->assignInvoiceNumber();

        $this->assertSame('FAC-2026-000001', $a->fresh()->invoice_number);
        $this->assertSame('FAC-2027-000001', $b->fresh()->invoice_number);
        $this->assertSame('FAC-2027-000002', $c->fresh()->invoice_number);
    }

    public function test_a_late_payment_on_a_cancelled_order_gets_no_invoice(): void
    {
        $this->mock(KkiapayService::class, function ($mock) {
            $mock->shouldReceive('verifyTransaction')->andReturn([
                'status' => 'SUCCESS', 'amount' => 10000.0, 'transactionId' => 'txn_late', 'reference' => 'CMD-INV00002',
            ]);
            $mock->shouldReceive('refundTransaction')->andReturn(true);
        });

        $user = User::factory()->create();
        $order = Order::factory()->create([
            'buyer_id' => $user->id, 'reference' => 'CMD-INV00002', 'status' => 'cancelled', 'payment_status' => 'pending', 'total_amount' => 10000,
        ]);

        $this->actingAs($user)->postJson(route('payment.verify', $order), ['transaction_id' => 'txn_late'])->assertStatus(422);

        $this->assertNull($order->fresh()->invoice_number);
    }

    // ---------- Accès ----------

    public function test_the_buyer_can_view_the_invoice(): void
    {
        $buyer = User::factory()->create(['company_name' => 'Épicerie du Marché']);
        $order = $this->paidOrder($buyer);
        $this->addItem($order, Vendor::factory()->create(), 'Sac de riz 25kg', 10000);

        $response = $this->actingAs($buyer)->get(route('orders.invoice', $order));

        $response->assertOk()
            ->assertSee('FAC-')
            ->assertSee($order->reference)
            ->assertSee('Épicerie du Marché')
            ->assertSee('Sac de riz 25kg');

        $this->assertNotNull($order->fresh()->invoice_number);
    }

    public function test_another_customer_cannot_view_the_invoice(): void
    {
        $order = $this->paidOrder();

        $this->actingAs(User::factory()->create())->get(route('orders.invoice', $order))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('orders.invoice.download', $order))->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $order = $this->paidOrder();

        $this->get(route('orders.invoice', $order))->assertRedirect(route('login'));
    }

    public function test_an_admin_can_view_any_paid_orders_invoice_but_gets_404_when_unpaid(): void
    {
        $admin = User::factory()->admin()->create();
        $paid = $this->paidOrder();
        $unpaid = Order::factory()->create(['status' => 'pending', 'payment_status' => 'pending']);

        $this->actingAs($admin)->get(route('orders.invoice', $paid))->assertOk();
        $this->actingAs($admin)->get(route('orders.invoice', $unpaid))->assertNotFound();
    }

    public function test_an_unpaid_order_has_no_invoice(): void
    {
        $buyer = User::factory()->create();
        $order = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => 'pending', 'payment_status' => 'pending']);

        $this->actingAs($buyer)->get(route('orders.invoice', $order))
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHasErrors('invoice');

        $this->assertNull($order->fresh()->invoice_number);
    }

    public function test_a_cancelled_order_has_no_invoice(): void
    {
        $buyer = User::factory()->create();
        $order = $this->paidOrder($buyer, ['status' => 'cancelled']);

        $this->actingAs($buyer)->get(route('orders.invoice', $order))
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHasErrors('invoice');

        $this->assertNull($order->fresh()->invoice_number);
    }

    public function test_an_order_paid_before_invoices_existed_is_numbered_on_first_view_and_keeps_its_number(): void
    {
        $buyer = User::factory()->create();
        $order = $this->paidOrder($buyer);
        $this->assertNull($order->invoice_number);

        $this->actingAs($buyer)->get(route('orders.invoice', $order))->assertOk();
        $number = $order->fresh()->invoice_number;
        $this->assertNotNull($number);

        $this->actingAs($buyer)->get(route('orders.invoice', $order))->assertOk()->assertSee($number);
        $this->assertSame($number, $order->fresh()->invoice_number);
    }

    // ---------- Contenu ----------

    public function test_the_invoice_shows_vendors_discount_shipping_and_the_vat_detail(): void
    {
        $buyer = User::factory()->create();
        $coupon = Coupon::factory()->create(['code' => 'PROMO10']);
        $order = $this->paidOrder($buyer, ['coupon_id' => $coupon->id, 'discount_amount' => 1000, 'shipping_fee' => 2000, 'total_amount' => 11000]);

        $this->addItem($order, Vendor::factory()->create(['store_name' => 'Alpha SARL']), 'Sac de riz', 5000);
        $this->addItem($order, Vendor::factory()->create(['store_name' => 'Bêta Négoce']), 'Huile de palme', 5000);

        $this->actingAs($buyer)->get(route('orders.invoice', $order))
            ->assertOk()
            ->assertSee('Alpha SARL')
            ->assertSee('Bêta Négoce')
            ->assertSee('PROMO10')
            ->assertSee('11 000 FCFA')   // total TTC
            ->assertSee('1 678 FCFA')    // TVA 18 % incluse : 11 000 × 18 / 118
            ->assertSee('9 322 FCFA');   // total HT
    }

    public function test_the_platform_identity_and_footer_come_from_the_configuration(): void
    {
        config([
            'marketplace.company.name' => 'MarcheMarket SARL',
            'marketplace.company.tax_id' => 'IFU-9999',
            'marketplace.company.address' => '12 avenue de la Marina, Cotonou',
            'marketplace.invoice_footer' => 'Paiement à réception, pas de retour sans accord.',
        ]);

        $buyer = User::factory()->create();
        $order = $this->paidOrder($buyer);

        $this->actingAs($buyer)->get(route('orders.invoice', $order))
            ->assertOk()
            ->assertSee('MarcheMarket SARL')
            ->assertSee('IFU-9999')
            ->assertSee('12 avenue de la Marina, Cotonou')
            ->assertSee('Paiement à réception, pas de retour sans accord.');
    }

    public function test_the_order_page_links_to_the_invoice_only_when_it_exists(): void
    {
        $buyer = User::factory()->create();
        $paid = $this->paidOrder($buyer);
        $pending = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => 'pending', 'payment_status' => 'pending']);

        $this->actingAs($buyer)->get(route('orders.show', $paid))->assertOk()->assertSee(route('orders.invoice', $paid), false);
        $this->actingAs($buyer)->get(route('orders.show', $pending))->assertOk()->assertDontSee(route('orders.invoice', $pending), false);
    }

    // ---------- PDF ----------

    public function test_the_invoice_can_be_downloaded_as_a_pdf(): void
    {
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $this->markTestSkipped('barryvdh/laravel-dompdf n\'est pas installé (composer install).');
        }

        $buyer = User::factory()->create();
        $order = $this->paidOrder($buyer);
        $this->addItem($order, Vendor::factory()->create(), 'Sac de riz', 10000);

        $response = $this->actingAs($buyer)->get(route('orders.invoice.download', $order));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('Facture-FAC-', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    // ---------- Rattrapage ----------

    public function test_the_backfill_command_numbers_old_paid_orders_in_chronological_order(): void
    {
        $first = $this->paidOrder();
        $second = $this->paidOrder();
        $third = $this->paidOrder();
        $cancelled = $this->paidOrder(null, ['status' => 'cancelled']);
        $pending = Order::factory()->create(['status' => 'pending', 'payment_status' => 'pending']);
        $year = now()->format('Y');

        $this->artisan('invoices:backfill')->expectsOutput('3 commande(s) numérotée(s).')->assertSuccessful();

        $this->assertSame("FAC-{$year}-000001", $first->fresh()->invoice_number);
        $this->assertSame("FAC-{$year}-000002", $second->fresh()->invoice_number);
        $this->assertSame("FAC-{$year}-000003", $third->fresh()->invoice_number);
        $this->assertNull($cancelled->fresh()->invoice_number);
        $this->assertNull($pending->fresh()->invoice_number);

        // Relancer la commande ne renumérote rien.
        $this->artisan('invoices:backfill')->expectsOutput('0 commande(s) numérotée(s).')->assertSuccessful();
        $this->assertSame("FAC-{$year}-000001", $first->fresh()->invoice_number);
    }
}
