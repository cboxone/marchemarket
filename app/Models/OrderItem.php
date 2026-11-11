<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    public const FULFILLMENT_PENDING = 'pending';
    public const FULFILLMENT_SHIPPED = 'shipped';
    public const FULFILLMENT_DELIVERED = 'delivered';

    protected $fillable = [
        'order_id',
        'vendor_id',
        'product_id',
        'product_name',
        'quantity',
        'unit_price',
        'commission_rate',
        'fulfillment_status',
        'shipped_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function subtotal(): float
    {
        return (float) $this->unit_price * $this->quantity;
    }

    /**
     * Part de cet article réellement encaissée : son sous-total, proratisé
     * si un code promo a réduit le total de la commande (voir
     * Order::vendorRevenueRatio()). Nécessite que `order.items` soit chargé
     * pour éviter une requête par article.
     */
    public function collectedAmount(): float
    {
        return $this->subtotal() * $this->order->vendorRevenueRatio();
    }

    /**
     * Commission de la plateforme sur cet article, au taux figé à la
     * commande (et non au taux courant de la configuration).
     */
    public function commissionAmount(): float
    {
        return round($this->collectedAmount() * (float) $this->commission_rate / 100, 2);
    }

    /** Montant net revenant au vendeur : encaissé moins commission. */
    public function netAmount(): float
    {
        return $this->collectedAmount() - $this->commissionAmount();
    }

    /**
     * Date à partir de laquelle le montant encaissé pour cette ligne devient
     * retirable par le vendeur (voir Vendor::revenueBreakdown()) :
     * priorité à la livraison constatée, puis à l'expédition, puis — si le
     * vendeur ne met jamais sa commande à jour — au paiement, en filet de
     * sécurité pour ne pas bloquer indéfiniment un solde légitime.
     */
    public function releaseDate(): ?\Illuminate\Support\Carbon
    {
        $days = config('marketplace.payout_release_days');

        if ($this->delivered_at) {
            return $this->delivered_at->copy()->addDays($days['after_delivery']);
        }

        if ($this->shipped_at) {
            return $this->shipped_at->copy()->addDays($days['after_shipping']);
        }

        $paidAt = $this->order?->invoiced_at ?? $this->order?->created_at;

        return $paidAt?->copy()->addDays($days['after_payment']);
    }

    public function isReleased(): bool
    {
        return ($date = $this->releaseDate()) !== null && $date->isPast();
    }

    public function fulfillmentLabel(): string
    {
        return match ($this->fulfillment_status) {
            self::FULFILLMENT_SHIPPED => __('Expédiée'),
            self::FULFILLMENT_DELIVERED => __('Livrée'),
            default => __('En attente'),
        };
    }
}
