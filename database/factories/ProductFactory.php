<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        $moq = fake()->randomElement([1, 5, 10, 20]);

        return [
            'vendor_id' => Vendor::factory(),
            'category_id' => Category::factory(),
            'name' => ucfirst($name),
            'slug' => str($name)->slug() . '-' . fake()->unique()->numberBetween(100, 999999),
            'description' => fake()->paragraph(),
            'unit' => fake()->randomElement(['pièce', 'carton', 'sac', 'lot']),
            'min_order_qty' => $moq,
            'stock_qty' => fake()->numberBetween(50, 1000),
            'base_price' => fake()->randomFloat(2, 1000, 50000),
            'discount_percent' => null,
            'promo_ends_at' => null,
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        // Chaque produit créé via cette factory reçoit automatiquement un
        // palier de prix de base couvrant sa quantité minimale de commande,
        // pour que Product::priceForQty() ait toujours un palier à trouver.
        return $this->afterCreating(function (\App\Models\Product $product) {
            $product->priceTiers()->create([
                'min_qty' => $product->min_order_qty,
                'max_qty' => null,
                'unit_price' => $product->base_price,
            ]);
        });
    }

    public function withDiscount(int $percent): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_percent' => $percent,
            'promo_ends_at' => now()->addDays(7),
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => ['stock_qty' => 0]);
    }
}
