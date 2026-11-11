<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    public function definition(): array
    {
        $storeName = fake()->unique()->company();

        return [
            'user_id' => User::factory()->vendor(),
            'store_name' => $storeName,
            'slug' => str($storeName)->slug() . '-' . fake()->unique()->numberBetween(100, 99999),
            'description' => fake()->sentence(),
            'country' => 'Bénin',
            'city' => fake()->randomElement(['Cotonou', 'Porto-Novo', 'Parakou']),
            'payout_provider' => 'mtn',
            'payout_phone' => '+22996000000',
            'is_verified' => false,
            'trade_assurance' => false,
            'response_rate' => fake()->numberBetween(60, 99),
            'years_active' => fake()->numberBetween(1, 10),
        ];
    }

    public function withoutPayoutAccount(): static
    {
        return $this->state(fn (array $attributes) => ['payout_provider' => null, 'payout_phone' => null]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => ['is_verified' => true]);
    }
}
