<?php

namespace Tests\Feature\Vendor;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PlatformCommissionTest extends TestCase
{
    use RefreshDatabase;

    private function paidItem(Vendor $vendor, float $unitPrice, float $rate, array $orderAttributes = []): \App\Models\OrderItem
    {
        $order = Order::factory()->create($orderAttributes + [
            'payment_status' => 'paid',
            'status' => 'paid',
            'shipping_fee' => 0,
            'total_amount' => $unitPrice,
        ]);

        return $order->items()->create([
            'vendor_id' => $vendor->id,
            'product_name' => 'Produit test',
            'quantity' => 1,
            'unit_price' => $unitPrice,
            'commission_rate' => $rate,
            'delivered_at' => now()->subDays(8),
        ]);
    }

    public function test_the_available_balance_is_net_of_commission(): void
    {
        $vendor = Vendor::factory()->create();
        $this->paidItem($vendor, 10000, 10);

        $breakdown = $vendor->revenueBreakdown();

        $this->assertEqualsWithDelta(10000.0, $breakdown['gross'], 0.01);
        $this->assertEqualsWithDelta(1000.0, $breakdown['commission'], 0.01);
        $this->assertEqualsWithDelta(9000.0, $breakdown['net'], 0.01);
        $this->assertEqualsWithDelta(9000.0, $vendor->availableBalance(), 0.01);
        $this->assertEqualsWithDelta(10000.0, $vendor->grossRevenue(), 0.01);
    }

    public function test_a_zero_commission_leaves_the_balance_untouched(): void
    {
        $vendor = Vendor::factory()->create();
        $this->paidItem($vendor, 10000, 0);

        $this->assertSame(10000.0, $vendor->availableBalance());
    }

    public function test_the_rate_is_frozen_per_item_and_later_config_changes_do_not_apply(): void
    {
        $vendor = Vendor::factory()->create();
        $this->paidItem($vendor, 10000, 10);

        config(['marketplace.commission_percent' => 50]);

        $this->assertEqualsWithDelta(9000.0, $vendor->availableBalance(), 0.01);
    }

    public function test_commission_applies_to_the_amount_actually_collected_after_a_coupon(): void
    {
        $vendor = Vendor::factory()->create();

        // 10 000 d'articles, remise de 20 % => 8 000 encaissés ; commission 10 % => 800.
        $this->paidItem($vendor, 10000, 10, ['discount_amount' => 2000, 'total_amount' => 8000]);

        $breakdown = $vendor->revenueBreakdown();

        $this->assertEqualsWithDelta(8000.0, $breakdown['gross'], 0.01);
        $this->assertEqualsWithDelta(800.0, $breakdown['commission'], 0.01);
        $this->assertEqualsWithDelta(7200.0, $breakdown['net'], 0.01);
    }

    public function test_shipping_fees_are_never_commissioned(): void
    {
        $vendor = Vendor::factory()->create();

        $this->paidItem($vendor, 10000, 10, ['shipping_fee' => 2000, 'total_amount' => 12000]);

        $this->assertEqualsWithDelta(9000.0, $vendor->availableBalance(), 0.01);
    }

    public function test_cancelled_orders_carry_no_commission(): void
    {
        $vendor = Vendor::factory()->create();
        $this->paidItem($vendor, 10000, 10, ['status' => 'cancelled']);

        $breakdown = $vendor->revenueBreakdown();

        $this->assertSame(0.0, $breakdown['commission']);
        $this->assertSame(0.0, $breakdown['net']);
    }

    public function test_old_items_without_a_stored_rate_are_not_commissioned(): void
    {
        $vendor = Vendor::factory()->create();
        $order = Order::factory()->create(['payment_status' => 'paid', 'status' => 'paid', 'shipping_fee' => 0, 'total_amount' => 10000]);
        // Pas de commission_rate fourni => valeur par défaut 0 de la migration.
        $order->items()->create([
            'vendor_id' => $vendor->id,
            'product_name' => 'Ancien',
            'quantity' => 1,
            'unit_price' => 10000,
            'delivered_at' => now()->subDays(8),
        ]);

        $this->assertSame(10000.0, $vendor->availableBalance());
    }

    public function test_a_vendor_cannot_withdraw_more_than_the_net_balance(): void
    {
        $vendor = Vendor::factory()->create();
        $this->paidItem($vendor, 10000, 10);

        $this->actingAs($vendor->user)->post(route('vendor.payouts.store'), ['amount' => 9500, 'payout_method' => 'MTN 90000000'])
            ->assertSessionHasErrors('amount');

        $this->actingAs($vendor->user)->post(route('vendor.payouts.store'), ['amount' => 9000, 'payout_method' => 'MTN 90000000'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('payouts', ['vendor_id' => $vendor->id, 'amount' => 9000]);
    }

    public function test_checkout_freezes_the_current_commission_rate_on_each_item(): void
    {
        Mail::fake();
        config(['marketplace.commission_percent' => 12.5]);

        $user = User::factory()->create();
        $product = Product::factory()->create(['min_order_qty' => 1, 'stock_qty' => 100, 'base_price' => 5000]);

        $this->actingAs($user)->post('/panier', ['product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($user)->post('/commande', [
            'shipping_address' => '12 rue du Marché',
            'shipping_city' => 'Cotonou',
            'shipping_country' => 'Bénin',
            'shipping_phone' => '90000000',
        ]);

        $item = Order::first()->items()->first();

        $this->assertEqualsWithDelta(12.5, (float) $item->commission_rate, 0.001);

        // Le taux change ensuite : l'article déjà commandé garde 12.5 %.
        config(['marketplace.commission_percent' => 30]);
        $this->assertEqualsWithDelta(12.5, (float) $item->fresh()->commission_rate, 0.001);
    }

    public function test_the_admin_dashboard_shows_the_platform_commission(): void
    {
        $admin = User::factory()->admin()->create();
        $vendor = Vendor::factory()->create();
        $this->paidItem($vendor, 10000, 10);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('1 000');
    }
}
