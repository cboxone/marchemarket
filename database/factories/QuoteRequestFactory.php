<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\QuoteRequest>
 */
class QuoteRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'buyer_id' => User::factory(),
            'vendor_id' => Vendor::factory(),
            'product_id' => null,
            'product_name' => 'Sac de riz 25kg',
            'unit' => 'sac',
            'quantity' => 200,
            'status' => 'pending',
            'expires_at' => now()->addDays(14),
        ];
    }

    /** Rattache la demande à un produit réel (et à son fournisseur). */
    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
            'vendor_id' => $product->vendor_id,
            'product_name' => $product->name,
            'unit' => $product->unit,
        ]);
    }

    /** Devis déjà envoyé par le fournisseur. */
    public function quoted(float $unitPrice = 9000, int $quantity = 200, ?float $shipping = 5000): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'quoted',
            'quoted_unit_price' => $unitPrice,
            'quoted_quantity' => $quantity,
            'quoted_shipping_fee' => $shipping,
            'quoted_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);
    }
}
