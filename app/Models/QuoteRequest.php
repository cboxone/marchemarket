<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QuoteRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';     // en attente de réponse du fournisseur
    public const STATUS_QUOTED = 'quoted';       // devis envoyé, en attente de l'acheteur
    public const STATUS_ACCEPTED = 'accepted';   // devenu une commande
    public const STATUS_DECLINED = 'declined';   // fournisseur : pas de devis
    public const STATUS_REJECTED = 'rejected';   // acheteur : devis refusé
    public const STATUS_CANCELLED = 'cancelled'; // acheteur : demande retirée
    public const STATUS_EXPIRED = 'expired';

    /**
     * Seuls les champs saisis par l'acheteur sont assignables en masse. La
     * réponse du fournisseur, le statut et le lien vers la commande ne sont
     * écrits que par le serveur (forceFill).
     */
    protected $fillable = [
        'buyer_id',
        'vendor_id',
        'product_id',
        'product_name',
        'unit',
        'quantity',
        'target_price',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'target_price' => 'decimal:2',
            'quoted_unit_price' => 'decimal:2',
            'quoted_shipping_fee' => 'decimal:2',
            'quoted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (QuoteRequest $quote) {
            if (! $quote->reference) {
                do {
                    $reference = 'DEV-' . strtoupper(Str::random(8));
                } while (self::where('reference', $reference)->exists());

                $quote->reference = $reference;
            }
        });
    }

    /** Références non devinables dans les URL, plutôt que des identifiants séquentiels. */
    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Vrai si l'échéance est passée alors que la demande attendait encore une réponse. */
    public function hasExpired(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_QUOTED], true)
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    /**
     * Statut à afficher et à faire respecter : une échéance dépassée compte
     * comme « expirée » immédiatement, sans attendre le passage de la commande
     * planifiée `quotes:expire`.
     */
    public function effectiveStatus(): string
    {
        return $this->hasExpired() ? self::STATUS_EXPIRED : $this->status;
    }

    /** Le fournisseur peut (re)chiffrer ou décliner, l'acheteur peut retirer sa demande. */
    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_QUOTED], true) && ! $this->hasExpired();
    }

    public function canBeAccepted(): bool
    {
        return $this->status === self::STATUS_QUOTED && ! $this->hasExpired();
    }

    public function quotedShippingFee(): float
    {
        return $this->quoted_shipping_fee !== null ? (float) $this->quoted_shipping_fee : (float) Order::SHIPPING_FEE;
    }

    public function quotedSubtotal(): float
    {
        return (float) $this->quoted_unit_price * (int) $this->quoted_quantity;
    }

    /** Montant TTC que paiera l'acheteur : articles + livraison. */
    public function quotedTotal(): float
    {
        return $this->quotedSubtotal() + $this->quotedShippingFee();
    }

    public function statusLabel(): string
    {
        return match ($this->effectiveStatus()) {
            self::STATUS_PENDING => __('En attente de réponse'),
            self::STATUS_QUOTED => __('Devis reçu'),
            self::STATUS_ACCEPTED => __('Accepté'),
            self::STATUS_DECLINED => __('Décliné par le fournisseur'),
            self::STATUS_REJECTED => __('Refusé'),
            self::STATUS_CANCELLED => __('Annulé'),
            self::STATUS_EXPIRED => __('Expiré'),
            default => $this->status,
        };
    }
}
