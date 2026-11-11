<?php

namespace Tests\Feature\Vendor;

use App\Models\Order;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorRevenueWithCouponTest extends TestCase
{
    use RefreshDatabase;

    public function test_gross_revenue_is_prorated_when_a_coupon_reduced_the_order_total(): void
    {
        $vendor = Vendor::factory()->create();

        // Commande de 10 000 FCFA d'articles, réduite de 20% (code promo) et
        // sans frais de livraison pour simplifier le calcul : le vendeur ne
        // doit être crédité que de 8 000 FCFA, pas des 10 000 FCFA pleins.
        $order = Order::factory()->create([
            'payment_status' => 'paid',
            'status' => 'paid',
            'shipping_fee' => 0,
            'discount_amount' => 2000,
            'total_amount' => 8000,
        ]);
        $order->items()->create([
            'vendor_id' => $vendor->id,
            'product_name' => 'Produit test',
            'quantity' => 1,
            'unit_price' => 10000,
        ]);

        $this->assertSame(8000.0, $vendor->grossRevenue());
    }

    public function test_gross_revenue_is_split_proportionally_between_vendors_on_the_same_discounted_order(): void
    {
        $vendorA = Vendor::factory()->create();
        $vendorB = Vendor::factory()->create();

        // Panier de 10 000 FCFA (6 000 + 4 000), remisé de 10% -> 9 000 FCFA
        // réellement encaissés, sans frais de livraison.
        $order = Order::factory()->create([
            'payment_status' => 'paid',
            'status' => 'paid',
            'shipping_fee' => 0,
            'discount_amount' => 1000,
            'total_amount' => 9000,
        ]);
        $order->items()->create(['vendor_id' => $vendorA->id, 'product_name' => 'A', 'quantity' => 1, 'unit_price' => 6000]);
        $order->items()->create(['vendor_id' => $vendorB->id, 'product_name' => 'B', 'quantity' => 1, 'unit_price' => 4000]);

        // Chacun est crédité au prorata de sa part dans le panier (90% de son prix plein).
        $this->assertEqualsWithDelta(5400.0, $vendorA->grossRevenue(), 0.01);
        $this->assertEqualsWithDelta(3600.0, $vendorB->grossRevenue(), 0.01);

        // La somme des deux ne dépasse jamais ce qui a été réellement encaissé.
        $this->assertEqualsWithDelta(9000.0, $vendorA->grossRevenue() + $vendorB->grossRevenue(), 0.01);
    }

    public function test_gross_revenue_is_unaffected_when_no_coupon_was_used(): void
    {
        $vendor = Vendor::factory()->create();

        $order = Order::factory()->create([
            'payment_status' => 'paid',
            'status' => 'paid',
            'shipping_fee' => 2000,
            'discount_amount' => 0,
            'total_amount' => 12000,
        ]);
        $order->items()->create(['vendor_id' => $vendor->id, 'product_name' => 'Produit test', 'quantity' => 1, 'unit_price' => 10000]);

        $this->assertSame(10000.0, $vendor->grossRevenue());
    }
}
