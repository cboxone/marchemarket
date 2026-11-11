<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Dispute>
 */
class DisputeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'vendor_id' => Vendor::factory(),
            'buyer_id' => User::factory(),
            'reason' => 'not_conforming',
            'description' => "Le produit reçu ne correspond pas à la description de l'annonce.",
        ];
    }

    public function escalated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'escalated',
            'escalated_at' => now(),
            'admin_target_at' => now()->addDays(config('marketplace.disputes.admin_target_days')),
        ]);
    }

    public function resolved(string $decision = 'refund_full', float $refundAmount = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'resolved',
            'escalated_at' => now()->subDay(),
            'decision' => $decision,
            'refund_amount' => $refundAmount,
            'resolved_at' => now(),
            'refund_status' => $refundAmount > 0 ? 'pending_manual' : 'not_applicable',
        ]);
    }
}
