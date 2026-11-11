<?php

namespace App\Models;

use App\Services\KkiapayService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Frais de livraison forfaitaires appliqués à chaque commande.
     * Simplification volontaire : un vrai calcul par zone/poids serait la
     * suite logique (voir README, section « Pistes d'évolution »).
     */
    public const SHIPPING_FEE = 2000;

    /**
     * Taux de TVA en vigueur au Bénin. Les prix affichés sur le site sont
     * TTC (toutes taxes comprises) : ce taux ne s'ajoute donc PAS au total
     * facturé, il sert uniquement à afficher le détail de la taxe déjà
     * incluse dans le prix, à titre informatif (facture, e-mail, etc.).
     */
    public const TVA_RATE = 18;

    public function taxAmount(): float
    {
        return round((float) $this->total_amount * self::TVA_RATE / (100 + self::TVA_RATE), 2);
    }

    protected $fillable = [
        'buyer_id',
        'coupon_id',
        'reference',
        'status',
        'cancellation_reason',
        'cancelled_at',
        'refunded_at',
        'payment_status',
        'payment_method',
        'payment_reference',
        'shipping_address',
        'shipping_city',
        'shipping_country',
        'shipping_phone',
        'shipping_fee',
        'discount_amount',
        'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
            'invoiced_at' => 'datetime',
            'bank_transfer_chosen_at' => 'datetime',
            'bank_transfer_declared_at' => 'datetime',
            'bank_transfer_rejected_at' => 'datetime',
        ];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->reference ??= 'CMD-' . strtoupper(Str::random(8));
        });
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Part du montant des articles (hors frais de livraison) réellement
     * encaissée après une éventuelle remise de code promo — 1.0 s'il n'y a
     * pas eu de remise, moins sinon.
     *
     * Sert à ce qu'un vendeur ne soit jamais crédité de plus que ce que la
     * plateforme a réellement encaissé pour sa part d'une commande (voir
     * Vendor::grossRevenue()) : le prix unitaire enregistré sur chaque
     * OrderItem est le prix plein, la remise du code promo n'existant qu'au
     * niveau de la commande entière, pas répartie ligne par ligne au moment
     * de la commande.
     */
    public function vendorRevenueRatio(): float
    {
        $itemsSubtotal = (float) $this->items->sum(fn (OrderItem $item) => $item->subtotal());

        if ($itemsSubtotal <= 0) {
            return 1.0;
        }

        $collectedForItems = (float) $this->total_amount - (float) $this->shipping_fee;

        return max(0.0, min(1.0, $collectedForItems / $itemsSubtotal));
    }

    /**
     * Annule la commande : restitue le stock, libère l'usage du code promo
     * et passe le statut à « annulée ». Point d'entrée unique, partagé par
     * l'annulation acheteur (OrderController) et le changement de statut
     * admin (Admin\OrderController), pour qu'aucun des deux chemins ne
     * puisse annuler sans restituer le stock.
     *
     * Idempotent : la ligne est verrouillée puis re-vérifiée, donc deux
     * annulations simultanées ne restituent jamais le stock deux fois.
     * Retourne false si la commande était déjà annulée.
     *
     * Avec $onlyIfUnpaid (annulation automatique des commandes impayées),
     * la commande n'est annulée que si, une fois verrouillée, elle est
     * toujours « en attente » ET sans paiement confirmé.
     */
    public function cancel(?string $reason = null, bool $onlyIfUnpaid = false): bool
    {
        return DB::transaction(function () use ($reason, $onlyIfUnpaid) {
            $locked = self::whereKey($this->id)->lockForUpdate()->first();

            if (! $locked || $locked->status === self::STATUS_CANCELLED) {
                return false;
            }

            if ($onlyIfUnpaid && ($locked->status !== self::STATUS_PENDING || $locked->payment_status === 'paid')) {
                return false;
            }

            foreach ($locked->items()->with('product')->get() as $item) {
                if ($item->product) {
                    $item->product->increment('stock_qty', $item->quantity);
                }
            }

            if ($locked->coupon_id) {
                $locked->coupon()->decrement('times_used');
            }

            $locked->update([
                'status' => self::STATUS_CANCELLED,
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
            ]);

            $this->refresh();

            return true;
        });
    }

    /**
     * Tente le remboursement KkiaPay d'une commande payée et annulée.
     * Ne lève jamais d'exception : en cas d'échec, `refunded_at` reste à
     * null (remboursement à traiter à la main) et l'erreur est journalisée.
     *
     * Ne s'applique qu'aux paiements KkiaPay : un virement bancaire n'a pas
     * de transaction à rembourser via cette API, `payment_reference` reste
     * d'ailleurs toujours vide pour ce moyen de paiement (voir
     * bank_transfer_reference, distinct, pour la référence donnée par
     * l'acheteur). Un remboursement de virement se fait entièrement à la
     * main, hors application.
     */
    public function attemptRefund(): bool
    {
        if ($this->payment_method !== 'kkiapay' || $this->payment_status !== 'paid' || ! $this->payment_reference || $this->refunded_at) {
            return false;
        }

        try {
            if (app(KkiapayService::class)->refundTransaction($this->payment_reference)) {
                $this->update(['refunded_at' => now()]);

                return true;
            }
        } catch (\Throwable $e) {
            Log::warning('Échec du remboursement KkiaPay pour la commande ' . $this->reference, [
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /** Une facture n'existe que pour une commande payée et non annulée. */
    public function isInvoiceable(): bool
    {
        return $this->payment_status === 'paid' && $this->status !== self::STATUS_CANCELLED;
    }

    // ---------- Virement bancaire manuel ----------

    public function hasChosenBankTransfer(): bool
    {
        return $this->payment_method === 'bank_transfer' || $this->bank_transfer_chosen_at !== null;
    }

    /** Une preuve a été déposée et attend une décision de l'admin. */
    public function hasPendingBankTransferDeclaration(): bool
    {
        return $this->hasChosenBankTransfer()
            && $this->bank_transfer_declared_at !== null
            && $this->payment_status !== 'paid'
            && $this->status !== self::STATUS_CANCELLED;
    }

    /**
     * Date après laquelle une commande encore impayée doit être annulée
     * automatiquement (voir la commande orders:cancel-unpaid) :
     * - un virement déjà déclaré (preuve déposée) n'est JAMAIS annulé tout
     *   seul — seul un admin le confirme ou le rejette, pour ne jamais faire
     *   disparaître une commande en cours d'examen ;
     * - un virement choisi mais jamais déclaré a une fenêtre longue
     *   (`marketplace.bank_transfer.window_hours`), un virement prenant
     *   parfois plusieurs jours ouvrés ;
     * - tout le reste (KkiaPay, ou aucun choix encore fait) garde le délai
     *   court habituel (`marketplace.unpaid_order_ttl_minutes`).
     *
     * Retourne null quand aucune annulation automatique ne doit avoir lieu.
     */
    public function unpaidCancelDeadline(): ?\Illuminate\Support\Carbon
    {
        if ($this->hasChosenBankTransfer()) {
            if ($this->bank_transfer_declared_at !== null) {
                return null;
            }

            $since = $this->bank_transfer_chosen_at ?? $this->created_at;

            return $since->copy()->addHours(config('marketplace.bank_transfer.window_hours'));
        }

        return $this->created_at->copy()->addMinutes(config('marketplace.unpaid_order_ttl_minutes'));
    }

    /**
     * Attribue le numéro de facture (FAC-AAAA-NNNNNN) s'il n'existe pas déjà.
     *
     * Séquentiel et sans trou : le compteur de l'année est verrouillé le
     * temps de l'attribution, et fait partie de la même transaction que la
     * mise à jour de la commande — si l'une échoue, l'autre est annulée, donc
     * aucun numéro n'est « consommé » sans facture. Idempotent : une commande
     * déjà numérotée garde son numéro.
     */
    public function assignInvoiceNumber(): void
    {
        if ($this->invoice_number) {
            return;
        }

        DB::transaction(function () {
            $locked = self::whereKey($this->id)->lockForUpdate()->first();

            if ($locked->invoice_number) {
                return;
            }

            $year = (int) now()->format('Y');

            DB::table('invoice_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0]);

            $next = DB::table('invoice_sequences')->where('year', $year)->lockForUpdate()->value('last_number') + 1;

            DB::table('invoice_sequences')->where('year', $year)->update(['last_number' => $next]);

            $locked->forceFill([
                'invoice_number' => sprintf('FAC-%d-%06d', $year, $next),
                'invoiced_at' => now(),
            ])->save();
        });

        $this->refresh();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => __('En attente'),
            self::STATUS_PAID => __('Payée'),
            self::STATUS_SHIPPED => __('Expédiée'),
            self::STATUS_DELIVERED => __('Livrée'),
            self::STATUS_CANCELLED => __('Annulée'),
            default => ucfirst($this->status),
        };
    }

    /**
     * Une commande n'est annulable par l'acheteur que si elle n'a pas encore
     * été expédiée : dès qu'un seul vendeur a marqué son article « expédiée »
     * ou « livrée », il est trop tard pour annuler en un clic (l'acheteur
     * doit alors passer par la messagerie pour s'arranger avec le fournisseur).
     */
    public function isCancellable(): bool
    {
        if (in_array($this->status, [self::STATUS_SHIPPED, self::STATUS_DELIVERED, self::STATUS_CANCELLED], true)) {
            return false;
        }

        return $this->items()->where('fulfillment_status', '!=', 'pending')->doesntExist();
    }

    /**
     * Recalcule le statut global de la commande à partir du statut
     * d'exécution de chaque article (renseigné par les vendeurs concernés).
     *
     * Une commande multi-vendeurs n'est « Expédiée » que lorsque TOUS les
     * vendeurs ont expédié leur part, et « Livrée » que lorsque tous ont
     * livré. Appelée après chaque mise à jour de statut par un vendeur
     * (voir Vendor\OrderController::updateStatus) pour que le statut visible
     * par l'acheteur et par l'admin reste le reflet fidèle de la réalité
     * terrain, plutôt que deux informations qui peuvent se contredire.
     *
     * Une commande annulée n'est jamais réécrasée par ce calcul. Le statut ne
     * peut également que progresser (jamais régresser) : si un admin a par
     * exemple corrigé manuellement une commande à « Livrée », la mise à jour
     * d'un article par un vendeur ne doit pas la faire redescendre à
     * « Expédiée ».
     */
    public function syncStatusFromItems(): void
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return;
        }

        $statuses = $this->items()->pluck('fulfillment_status');

        if ($statuses->isEmpty()) {
            return;
        }

        $newStatus = match (true) {
            $statuses->every(fn ($s) => $s === OrderItem::FULFILLMENT_DELIVERED) => self::STATUS_DELIVERED,
            $statuses->every(fn ($s) => in_array($s, [OrderItem::FULFILLMENT_SHIPPED, OrderItem::FULFILLMENT_DELIVERED], true)) => self::STATUS_SHIPPED,
            $this->payment_status === 'paid' => self::STATUS_PAID,
            default => $this->status,
        };

        $rank = [self::STATUS_PENDING => 0, self::STATUS_PAID => 1, self::STATUS_SHIPPED => 2, self::STATUS_DELIVERED => 3];
        $currentRank = $rank[$this->status] ?? -1;
        $newRank = $rank[$newStatus] ?? -1;

        if ($newRank > $currentRank) {
            $this->update(['status' => $newStatus]);
        }
    }
}
