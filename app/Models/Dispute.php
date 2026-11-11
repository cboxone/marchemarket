<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Dispute extends Model
{
    use HasFactory;

    // Motifs
    public const REASON_NOT_RECEIVED = 'not_received';

    public const REASON_MISSING_QTY = 'missing_qty';

    public const REASON_NOT_CONFORMING = 'not_conforming';

    public const REASON_DAMAGED = 'damaged';

    public const REASON_OTHER = 'other';

    /** Motifs où la faute revient au fournisseur : seuls ceux-ci ouvrent droit au remboursement des frais de livraison. */
    public const VENDOR_FAULT_REASONS = [self::REASON_NOT_RECEIVED, self::REASON_NOT_CONFORMING, self::REASON_DAMAGED];

    /** Motifs pour lesquels des photos sont exigées à l'ouverture du litige. */
    public const EVIDENCE_REQUIRED_REASONS = [self::REASON_NOT_CONFORMING, self::REASON_DAMAGED];

    // Statuts
    public const STATUS_OPEN = 'open';           // en attente de réponse du fournisseur

    public const STATUS_ESCALATED = 'escalated'; // en attente d'arbitrage admin

    public const STATUS_RESOLVED = 'resolved';   // décision finale rendue

    public const STATUS_WITHDRAWN = 'withdrawn'; // retiré par l'acheteur

    // Réponse du fournisseur
    public const VENDOR_ACCEPT_FULL = 'accept_full';

    public const VENDOR_ACCEPT_PARTIAL = 'accept_partial';

    public const VENDOR_CONTEST = 'contest';

    // Décision finale
    public const DECISION_REFUND_FULL = 'refund_full';

    public const DECISION_REFUND_PARTIAL = 'refund_partial';

    public const DECISION_REJECT = 'reject';

    // État du remboursement associé à la décision
    public const REFUND_NOT_APPLICABLE = 'not_applicable';   // aucun remboursement décidé

    public const REFUND_PENDING_MANUAL = 'pending_manual';   // à traiter à la main (montant partiel, ou commande multi-fournisseurs)

    public const REFUND_AUTOMATIC = 'refunded_automatic';    // remboursement KkiaPay intégral réussi

    public const REFUND_MANUAL_DONE = 'refunded_manual';     // remboursement effectué à la main, marqué par un admin

    protected $fillable = [
        'order_id',
        'vendor_id',
        'buyer_id',
        'status',
        'reason',
        'description',
        'evidence_paths',
        'vendor_deadline_at',
        'vendor_response_type',
        'vendor_response_amount',
        'vendor_response_note',
        'vendor_responded_at',
        'escalated_at',
        'admin_target_at',
        'decision',
        'refund_amount',
        'resolution_note',
        'resolved_by',
        'resolved_at',
        'refund_status',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence_paths' => 'array',
            'vendor_deadline_at' => 'datetime',
            'vendor_response_amount' => 'decimal:2',
            'vendor_responded_at' => 'datetime',
            'escalated_at' => 'datetime',
            'admin_target_at' => 'datetime',
            'refund_amount' => 'decimal:2',
            'resolved_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Dispute $dispute) {
            $dispute->status ??= self::STATUS_OPEN;
            $dispute->active_dispute_key = in_array($dispute->status, [self::STATUS_OPEN, self::STATUS_ESCALATED], true)
                ? $dispute->order_id.':'.$dispute->vendor_id
                : null;
        });

        static::creating(function (Dispute $dispute) {
            if (! $dispute->reference) {
                do {
                    $reference = 'LIT-'.strtoupper(Str::random(8));
                } while (self::where('reference', $reference)->exists());

                $dispute->reference = $reference;
            }

            $dispute->vendor_deadline_at ??= now()->addHours(config('marketplace.disputes.vendor_response_hours'));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpenForVendorResponse(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function vendorResponseOverdue(): bool
    {
        return $this->status === self::STATUS_OPEN && $this->vendor_deadline_at->isPast();
    }

    public function isAwaitingAdmin(): bool
    {
        return $this->status === self::STATUS_ESCALATED;
    }

    public function adminOverdue(): bool
    {
        return $this->status === self::STATUS_ESCALATED
            && $this->admin_target_at !== null
            && $this->admin_target_at->isPast();
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_WITHDRAWN], true);
    }

    /** Vrai tant qu'un litige gèle le solde du fournisseur pour cette commande (voir Vendor::revenueBreakdown()). */
    public function freezesPayout(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_ESCALATED], true);
    }

    /**
     * Plafond du litige : le montant encaissé pour les articles concernés
     * (avant commission), plus les frais de livraison si ce fournisseur est
     * le seul de la commande et que le motif lui est imputable — au-delà,
     * on ne sait pas répartir équitablement des frais de livraison partagés
     * entre plusieurs fournisseurs.
     */
    public function ceiling(): float
    {
        $order = $this->relationLoaded('order') ? $this->order : $this->order()->with('items')->first();
        $items = $order->items->where('vendor_id', $this->vendor_id);

        $amount = (float) $items->sum(fn (OrderItem $item) => $item->collectedAmount());

        $isSingleVendorOrder = $order->items->pluck('vendor_id')->unique()->count() === 1;

        if ($isSingleVendorOrder && in_array($this->reason, self::VENDOR_FAULT_REASONS, true)) {
            $amount += (float) $order->shipping_fee;
        }

        return round($amount, 2);
    }

    /**
     * Taux de commission appliqué au remboursement : celui du premier
     * article du fournisseur sur cette commande. En pratique tous les
     * articles d'une même commande partagent le même taux (figé au même
     * instant, au checkout) ; cette approximation documentée évite une
     * répartition au prorata inutilement complexe pour un cas qui ne se
     * produit pas dans le fonctionnement normal de l'application.
     */
    public function refundCommissionRate(): float
    {
        $order = $this->relationLoaded('order') ? $this->order : $this->order()->with('items')->first();
        $item = $order->items->firstWhere('vendor_id', $this->vendor_id);

        return $item ? (float) $item->commission_rate : 0.0;
    }

    public function refundCommissionAmount(): float
    {
        if (! $this->refund_amount) {
            return 0.0;
        }

        return round((float) $this->refund_amount * $this->refundCommissionRate() / 100, 2);
    }

    /**
     * Remboursement KkiaPay automatique possible seulement si la commande a
     * été payée par KkiaPay (un virement bancaire se rembourse entièrement à
     * la main), que le fournisseur est seul sur la commande et que tout est
     * remboursé.
     */
    public function isAutomaticRefundEligible(): bool
    {
        $order = $this->relationLoaded('order') ? $this->order : $this->order()->first();

        if ($order->payment_method !== 'kkiapay' || ! $order->payment_reference || (float) $this->refund_amount <= 0) {
            return false;
        }

        $isSingleVendorOrder = $order->items()->distinct('vendor_id')->count('vendor_id') === 1;

        return $isSingleVendorOrder
            && (int) round((float) $this->refund_amount * 100) === (int) round((float) $order->total_amount * 100);
    }

    public function reasonLabel(): string
    {
        return match ($this->reason) {
            self::REASON_NOT_RECEIVED => __('Commande non reçue'),
            self::REASON_MISSING_QTY => __('Quantité manquante'),
            self::REASON_NOT_CONFORMING => __('Produit non conforme'),
            self::REASON_DAMAGED => __('Produit endommagé'),
            default => __('Autre motif'),
        };
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->status === self::STATUS_OPEN => __('En attente du fournisseur'),
            $this->status === self::STATUS_ESCALATED => __("En cours d'arbitrage"),
            $this->status === self::STATUS_WITHDRAWN => __('Retiré'),
            $this->decision === self::DECISION_REFUND_FULL => __('Remboursé intégralement'),
            $this->decision === self::DECISION_REFUND_PARTIAL => __('Remboursé partiellement'),
            $this->decision === self::DECISION_REJECT => __('Rejeté'),
            default => __('Résolu'),
        };
    }
}
