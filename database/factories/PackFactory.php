<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Pack>
 */
class PackFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true) . ' pack';

        return [
            'vendor_id' => Vendor::factory(),
            'name' => ucfirst($name),
            'slug' => str($name)->slug() . '-' . fake()->unique()->numberBetween(100, 999999),
            'description' => fake()->sentence(),
            'pack_price' => fake()->randomFloat(2, 1000, 20000),
            'is_active' => true,
        ];
    }
}
