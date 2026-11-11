<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Address>
 */
class AddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => fake()->randomElement(['Entrepôt', 'Bureau', 'Boutique']),
            'recipient_name' => fake()->name(),
            'address' => fake()->streetAddress(),
            'city' => fake()->randomElement(['Cotonou', 'Porto-Novo', 'Parakou']),
            'country' => 'Bénin',
            'phone' => '90' . fake()->numerify('######'),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => ['is_default' => true]);
    }
}
