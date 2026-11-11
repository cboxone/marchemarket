<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'buyer_id' => User::factory(),
            'reference' => 'CMD-' . strtoupper(Str::random(8)),
            'status' => 'pending',
            'payment_status' => 'pending',
            'shipping_address' => fake()->streetAddress(),
            'shipping_city' => 'Cotonou',
            'shipping_country' => 'Bénin',
            'shipping_phone' => '90' . fake()->numerify('######'),
            'shipping_fee' => 2000,
            'total_amount' => 0,
        ];
    }
}
