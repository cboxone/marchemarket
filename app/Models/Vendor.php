<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use HasFactory;

    /** Opérateurs Mobile Money proposés pour les retraits. */
    public const PAYOUT_PROVIDERS = [
        'mtn' => 'MTN Mobile Money',
        'moov' => 'Moov Money',
        'celtiis' => 'Celtiis Cash',
    ];

    protected $fillable = [
        'user_id',
        'store_name',
        'slug',
        'description',
        'logo_path',
        'country',
        'city',
        'payout_provider',
        'payout_phone',
        'is_verified',
        'trade_assurance',
        'response_rate',
        'years_active',
    ];

    public const KYC_NONE = 'none';
    public const KYC_PENDING = 'pending';
    public const KYC_APPROVED = 'approved';
    public const KYC_REJECTED = 'rejected';

    /** Le vendeur peut (re)soumettre un dossier tant qu'il n'est ni en attente ni déjà approuvé. */
    public function canSubmitKyc(): bool
    {
        return in_array($this->kyc_status, [self::KYC_NONE, self::KYC_REJECTED], true);
    }

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'trade_assurance' => 'boolean',
            'response_rate' => 'integer',
            'years_active' => 'integer',
        ];
    }

    public function quoteRequests(): HasMany
    {
        return $this->hasMany(QuoteRequest::class);
    }

    public function hasPayoutAccount(): bool
    {
        return isset(self::PAYOUT_PROVIDERS[$this->payout_provider]) && filled($this->payout_phone);
    }

    /** Ex : « MTN Mobile Money · +22996000000 » — figé sur chaque demande de reversement. */
    public function payoutAccountLabel(): ?string
    {
        if (! $this->hasPayoutAccount()) {
            return null;
        }

        return self::PAYOUT_PROVIDERS[$this->payout_provider] . ' · ' . $this->payout_phone;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function packs(): HasMany
    {
        return $this->hasMany(Pack::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    /**
     * Articles de commandes dont le paiement a été confirmé ET qui n'ont pas
     * été annulées depuis — un remboursement après annulation ne doit pas
     * rester comptabilisé dans le solde disponible pour retrait.
     * `order.items` est chargé pour le calcul proratisé (voir OrderItem).
     */
    private function paidOrderItems()
    {
        return $this->orderItems()
            ->whereHas('order', fn ($q) => $q->where('payment_status', 'paid')->where('status', '!=', 'cancelled'))
            ->with('order.items')
            ->get();
    }

    /**
     * Litiges dont la décision réduit définitivement les revenus du
     * fournisseur (remboursement décidé, quel que soit l'état d'exécution
     * réel du remboursement KkiaPay) — déduits aussi bien du chiffre
     * d'affaires affiché que du solde retirable, pour que le vendeur ne
     * garde jamais le bénéfice d'une vente remboursée à l'acheteur.
     *
     * @return array{gross: float, commission: float}
     */
    private function resolvedDisputeRefunds(): array
    {
        $refunded = $this->disputes()
            ->where('status', Dispute::STATUS_RESOLVED)
            ->where('refund_amount', '>', 0)
            ->with('order.items')
            ->get();

        return [
            'gross' => (float) $refunded->sum(fn (Dispute $d) => (float) $d->refund_amount),
            'commission' => (float) $refunded->sum(fn (Dispute $d) => $d->refundCommissionAmount()),
        ];
    }

    /**
     * Répartition du chiffre d'affaires encaissé : brut (avant commission),
     * commission de la plateforme, et net revenant au vendeur.
     *
     * Chaque article est prorata-temporisé par Order::vendorRevenueRatio() :
     * si un code promo a réduit le montant réellement payé pour la
     * commande, ce vendeur ne doit pas être crédité du prix plein de ses
     * articles — sans quoi la somme des reversements de tous les vendeurs
     * d'une commande dépasserait ce qui a réellement été encaissé auprès de
     * l'acheteur. La commission s'applique ensuite sur ce montant encaissé,
     * au taux figé sur chaque article à la commande.
     *
     * Vision d'ensemble (« chiffre d'affaires ») : toutes les ventes
     * encaissées, remboursements de litiges déduits — sans tenir compte du
     * délai de retrait ni des litiges encore ouverts (voir availableBalance()
     * pour le montant réellement retirable aujourd'hui).
     *
     * @return array{gross: float, commission: float, net: float}
     */
    public function revenueBreakdown(): array
    {
        $items = $this->paidOrderItems();

        $gross = (float) $items->sum(fn (OrderItem $item) => $item->collectedAmount());
        $commission = (float) $items->sum(fn (OrderItem $item) => $item->commissionAmount());

        $refunds = $this->resolvedDisputeRefunds();
        $gross = max(0.0, $gross - $refunds['gross']);
        $commission = max(0.0, $commission - $refunds['commission']);

        return ['gross' => $gross, 'commission' => $commission, 'net' => max(0.0, $gross - $commission)];
    }

    /** Chiffre d'affaires encaissé, avant commission de la plateforme. */
    public function grossRevenue(): float
    {
        return $this->revenueBreakdown()['gross'];
    }

    /**
     * Articles dont le montant est effectivement retirable aujourd'hui :
     * réservé au-delà de la date de déblocage de chaque ligne
     * (OrderItem::releaseDate() — livraison, puis expédition, puis paiement
     * en filet de sécurité), et jamais tant qu'un litige ouvert ou en
     * arbitrage porte sur cette commande pour ce fournisseur (le gel
     * s'applique à toute la commande côté fournisseur, pas seulement à
     * l'article concerné : on ne sait pas isoler la part contestée avant la
     * décision).
     */
    private function releasedOrderItems()
    {
        $frozenOrderIds = $this->disputes()
            ->whereIn('status', [Dispute::STATUS_OPEN, Dispute::STATUS_ESCALATED])
            ->pluck('order_id');

        return $this->paidOrderItems()
            ->reject(fn (OrderItem $item) => $frozenOrderIds->contains($item->order_id))
            ->filter(fn (OrderItem $item) => $item->isReleased());
    }

    /**
     * Solde disponible pour un retrait : chiffre d'affaires NET de
     * commission parmi les seules lignes déjà déblocables, remboursements de
     * litiges déduits (voir resolvedDisputeRefunds()), moins tout ce qui a
     * déjà été payé ou est en attente de traitement.
     *
     * Limite assumée : si un vendeur a déjà retiré un montant avant qu'un
     * litige tardif ne soit résolu à la défaveur de son solde disponible, la
     * différence est simplement plafonnée à 0 ci-dessous plutôt que reprise
     * sur les fonds déjà versés — la fenêtre de litige et le délai de
     * déblocage sont volontairement alignés pour limiter ce risque, sans
     * l'éliminer complètement.
     */
    public function availableBalance(): float
    {
        $items = $this->releasedOrderItems();

        $gross = (float) $items->sum(fn (OrderItem $item) => $item->collectedAmount());
        $commission = (float) $items->sum(fn (OrderItem $item) => $item->commissionAmount());

        $refunds = $this->resolvedDisputeRefunds();
        $net = max(0.0, ($gross - $refunds['gross']) - ($commission - $refunds['commission']));

        $alreadyClaimed = $this->payouts()
            ->whereIn('status', [Payout::STATUS_PENDING, Payout::STATUS_PAID])
            ->sum('amount');

        return max(0.0, $net - (float) $alreadyClaimed);
    }

    /**
     * Somme encaissée (nette de commission) mais pas encore retirable —
     * délai de déblocage non atteint ou litige ouvert/en arbitrage —
     * uniquement pour l'affichage vendeur (« bientôt disponible »).
     */
    public function pendingReleaseBalance(): float
    {
        $releasedIds = $this->releasedOrderItems()->pluck('id');
        $pending = $this->paidOrderItems()->reject(fn (OrderItem $item) => $releasedIds->contains($item->id));

        $gross = (float) $pending->sum(fn (OrderItem $item) => $item->collectedAmount());
        $commission = (float) $pending->sum(fn (OrderItem $item) => $item->commissionAmount());

        return max(0.0, $gross - $commission);
    }
}
