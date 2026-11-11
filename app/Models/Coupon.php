<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'discount_percent',
        'min_order_amount',
        'expires_at',
        'usage_limit',
        'times_used',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_order_amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Vérifie qu'un code promo est utilisable pour un montant de panier
     * donné, à cet instant précis. Retourne un message d'erreur traduit si
     * le code n'est pas valide, ou null s'il peut être appliqué.
     */
    public function validationError(float $cartTotal): ?string
    {
        if (! $this->is_active) {
            return __('Ce code promo n\'est plus actif.');
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return __('Ce code promo a expiré.');
        }

        if ($this->usage_limit !== null && $this->times_used >= $this->usage_limit) {
            return __('Ce code promo a atteint sa limite d\'utilisation.');
        }

        if ($this->min_order_amount !== null && $cartTotal < (float) $this->min_order_amount) {
            return __('Ce code promo nécessite un panier d\'au moins :amount FCFA.', [
                'amount' => number_format((float) $this->min_order_amount, 0, ',', ' '),
            ]);
        }

        return null;
    }

    public function discountFor(float $cartTotal): float
    {
        return round($cartTotal * $this->discount_percent / 100, 2);
    }
}
