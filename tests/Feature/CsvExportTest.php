<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsvExportTest extends TestCase
{
    use RefreshDatabase;

    private function readCsv($response): array
    {
        $content = $response->streamedContent();
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = array_filter(explode("\n", trim($content)));

        return array_map(fn ($line) => str_getcsv($line, ';'), $lines);
    }

    // ---------- Accès ----------

    public function test_only_admins_can_export_orders_or_payouts(): void
    {
        $this->get(route('admin.orders.export'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.orders.export'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('admin.payouts.export'))->assertForbidden();
    }

    public function test_a_customer_cannot_export_vendor_data(): void
    {
        $this->actingAs(User::factory()->create())->get(route('vendor.orders.export'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('vendor.payouts.export'))->assertForbidden();
    }

    // ---------- Contenu : commandes admin ----------

    public function test_the_admin_orders_export_contains_a_header_and_one_row_per_order(): void
    {
        $admin = User::factory()->admin()->create();
        Order::factory()->create(['reference' => 'CMD-EXPORT1', 'total_amount' => 15000]);
        Order::factory()->create(['reference' => 'CMD-EXPORT2', 'total_amount' => 25000]);

        $rows = $this->readCsv($this->actingAs($admin)->get(route('admin.orders.export')));

        $this->assertCount(3, $rows); // en-tête + 2 lignes
        $this->assertSame('Référence', $rows[0][0]);
        $this->assertContains('CMD-EXPORT1', array_column($rows, 0));
        $this->assertContains('CMD-EXPORT2', array_column($rows, 0));
    }

    public function test_the_admin_orders_export_is_filtered_by_date_range(): void
    {
        $admin = User::factory()->admin()->create();
        $old = Order::factory()->create(['reference' => 'CMD-OLD00001']);
        Order::whereKey($old->id)->update(['created_at' => now()->subMonths(2)]);
        $recent = Order::factory()->create(['reference' => 'CMD-RECENT01']);

        $rows = $this->readCsv($this->actingAs($admin)->get(route('admin.orders.export', ['from' => now()->subWeek()->toDateString()])));

        $refs = array_column(array_slice($rows, 1), 0);
        $this->assertContains('CMD-RECENT01', $refs);
        $this->assertNotContains('CMD-OLD00001', $refs);
    }

    // ---------- Contenu : reversements admin ----------

    public function test_the_admin_payouts_export_lists_every_vendors_payouts(): void
    {
        $admin = User::factory()->admin()->create();
        $vendorA = Vendor::factory()->create(['store_name' => 'Boutique Alpha']);
        $vendorB = Vendor::factory()->create(['store_name' => 'Boutique Beta']);
        $vendorA->payouts()->create(['amount' => 5000, 'status' => 'pending', 'payout_method' => 'MTN 90000000']);
        $vendorB->payouts()->create(['amount' => 7000, 'status' => 'paid', 'payout_method' => 'Moov 91000000']);

        $rows = $this->readCsv($this->actingAs($admin)->get(route('admin.payouts.export')));

        $vendors = array_column(array_slice($rows, 1), 0);
        $this->assertContains('Boutique Alpha', $vendors);
        $this->assertContains('Boutique Beta', $vendors);
    }

    // ---------- Contenu : commandes vendeur ----------

    public function test_a_vendor_export_only_contains_their_own_items(): void
    {
        $vendorA = Vendor::factory()->create();
        $vendorB = Vendor::factory()->create();

        $orderA = Order::factory()->create();
        $orderA->items()->create(['vendor_id' => $vendorA->id, 'product_name' => 'Produit A', 'quantity' => 2, 'unit_price' => 1000]);

        $orderB = Order::factory()->create();
        $orderB->items()->create(['vendor_id' => $vendorB->id, 'product_name' => 'Produit B', 'quantity' => 1, 'unit_price' => 2000]);

        $rows = $this->readCsv($this->actingAs($vendorA->user)->get(route('vendor.orders.export')));

        $products = array_column(array_slice($rows, 1), 3);
        $this->assertContains('Produit A', $products);
        $this->assertNotContains('Produit B', $products);
    }

    public function test_a_vendor_export_lists_one_row_per_item_not_per_order(): void
    {
        $vendor = Vendor::factory()->create();
        $order = Order::factory()->create();
        $order->items()->create(['vendor_id' => $vendor->id, 'product_name' => 'Sac de riz', 'quantity' => 1, 'unit_price' => 5000]);
        $order->items()->create(['vendor_id' => $vendor->id, 'product_name' => 'Huile de palme', 'quantity' => 2, 'unit_price' => 3000]);

        $rows = $this->readCsv($this->actingAs($vendor->user)->get(route('vendor.orders.export')));

        $this->assertCount(3, $rows); // en-tête + 2 articles de la même commande
    }

    // ---------- Contenu : reversements vendeur ----------

    public function test_a_vendor_payouts_export_only_contains_their_own_payouts(): void
    {
        $vendorA = Vendor::factory()->create();
        $vendorB = Vendor::factory()->create();
        $vendorA->payouts()->create(['amount' => 5000, 'status' => 'pending', 'payout_method' => 'MTN 90000000']);
        $vendorB->payouts()->create(['amount' => 9999, 'status' => 'pending', 'payout_method' => 'MTN 90000001']);

        $rows = $this->readCsv($this->actingAs($vendorA->user)->get(route('vendor.payouts.export')));

        $amounts = array_column(array_slice($rows, 1), 1);
        $this->assertContains('5000', $amounts);
        $this->assertNotContains('9999', $amounts);
    }

    // ---------- Format ----------

    public function test_the_export_is_served_as_csv_with_a_bom_and_a_filename(): void
    {
        $admin = User::factory()->admin()->create();
        Order::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.orders.export'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('commandes.csv', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith("\xEF\xBB\xBF", $response->streamedContent());
    }

    public function test_an_export_with_no_matching_rows_still_returns_just_the_header(): void
    {
        $admin = User::factory()->admin()->create();
        Order::factory()->create();

        $rows = $this->readCsv($this->actingAs($admin)->get(route('admin.orders.export', ['from' => now()->addYear()->toDateString()])));

        $this->assertCount(1, $rows);
    }
}
