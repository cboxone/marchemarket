<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Coupon>
 */
class CouponFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('PROMO####')),
            'discount_percent' => fake()->numberBetween(5, 30),
            'min_order_amount' => null,
            'expires_at' => null,
            'usage_limit' => null,
            'times_used' => 0,
            'is_active' => true,
        ];
    }
}
