<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\KkiapayService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Affiche la page de paiement avec le widget KkiaPay pour une commande
     * en attente de règlement (Mobile Money : MTN MoMo, Moov Money, ou carte).
     *
     * KkiapayService n'est volontairement PAS injecté dans la signature de
     * la méthode : le construire échoue tant que les clés KkiaPay ne sont
     * pas configurées (voir son constructeur), et l'injection de méthode le
     * construirait AVANT même les vérifications ci-dessous — cassant par
     * exemple la simple redirection d'une commande déjà annulée dans un
     * environnement sans clés KkiaPay. On ne le résout qu'une fois ces
     * vérifications passées, quand il est réellement nécessaire.
     */
    public function show(Request $request, Order $order)
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);

        if ($order->status === Order::STATUS_CANCELLED) {
            return redirect()->route('orders.show', $order)->with('status', __('Cette commande a été annulée, elle ne peut plus être payée.'));
        }

        if ($order->payment_status === 'paid') {
            return redirect()->route('orders.show', $order)->with('status', __('Cette commande est déjà payée.'));
        }

        $kkiapay = app(KkiapayService::class);
        $bankTransfer = config('marketplace.bank_transfer');

        return view('checkout.payment', [
            'order' => $order,
            'kkiapayPublicKey' => $kkiapay->publicKey(),
            'sandbox' => $kkiapay->isSandbox(),
            // L'option de virement n'est proposée que si un compte est
            // réellement configuré : mieux vaut la masquer que d'offrir un
            // moyen de paiement qui ne mène nulle part.
            'bankTransferAvailable' => filled($bankTransfer['account_number']) || filled($bankTransfer['iban']),
            'bankTransfer' => $bankTransfer,
        ]);
    }

    /**
     * Premier pas du virement bancaire : signale l'intention de l'acheteur
     * AVANT même qu'il ait pu se rendre à sa banque, pour déclencher
     * immédiatement la fenêtre de grâce longue de
     * marketplace.bank_transfer.window_hours (voir
     * Order::unpaidCancelDeadline()) — sans ce geste explicite, le délai
     * court habituel annulerait la commande bien avant qu'un virement, qui
     * prend parfois plusieurs jours ouvrés, ait pu aboutir.
     *
     * Idempotent : un second appel ne repousse pas une échéance déjà posée.
     */
    public function chooseBankTransfer(Request $request, Order $order)
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);

        if ($order->status === Order::STATUS_CANCELLED || $order->payment_status === 'paid') {
            return redirect()->route('payment.show', $order);
        }

        if (! $order->bank_transfer_chosen_at) {
            $order->forceFill(['payment_method' => 'bank_transfer', 'bank_transfer_chosen_at' => now()])->save();
        }

        return redirect()->route('payment.show', $order);
    }

    /**
     * Second pas : l'acheteur dépose la preuve de son virement (montant visé
     * sur son relevé, référence libre). Ceci ne marque JAMAIS la commande
     * payée — un admin rapproche la preuve de son relevé bancaire à la main
     * avant de confirmer (voir Admin\BankTransferController).
     */
    public function declareBankTransfer(Request $request, Order $order)
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);

        if ($order->status === Order::STATUS_CANCELLED || $order->payment_status === 'paid') {
            return redirect()->route('payment.show', $order);
        }

        if (! $order->hasChosenBankTransfer()) {
            return back()->withErrors(['bank_transfer' => __("Choisissez d'abord le virement bancaire comme moyen de paiement.")]);
        }

        if ($order->bank_transfer_declared_at) {
            return back()->withErrors(['bank_transfer' => __('Une preuve a déjà été déposée : elle est en cours de vérification.')]);
        }

        // Une échéance dépassée est respectée immédiatement, sans attendre
        // le passage de la commande planifiée orders:cancel-unpaid (même
        // principe qu'ailleurs dans l'application).
        $deadline = $order->unpaidCancelDeadline();
        if ($deadline !== null && $deadline->isPast()) {
            return back()->withErrors(['bank_transfer' => __('Le délai pour ce virement est dépassé ; cette commande a probablement déjà été annulée.')]);
        }

        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:120'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        // Disque `local` : privé, jamais exposé publiquement.
        $path = $request->file('proof')->store('bank-transfers', 'local');

        $order->forceFill([
            'bank_transfer_reference' => $data['reference'] ?? null,
            'bank_transfer_proof_path' => $path,
            'bank_transfer_declared_at' => now(),
            // Une nouvelle déclaration efface un rejet précédent : elle
            // repart sur un dossier propre pour l'admin.
            'bank_transfer_rejected_reason' => null,
            'bank_transfer_rejected_at' => null,
        ])->save();

        return redirect()->route('payment.show', $order)
            ->with('status', __('Preuve de virement envoyée. Nous la vérifions et vous confirmons par e-mail.'));
    }

    /**
     * Appelé en AJAX par le widget KkiaPay juste après un paiement "réussi"
     * côté navigateur. On revérifie systématiquement côté serveur avant de
     * marquer la commande payée : ne jamais faire confiance au seul callback JS.
     */
    public function verify(Request $request, Order $order)
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);

        // Déjà payée : rien à revérifier, et surtout rien à écraser (un
        // identifiant bidon envoyé après coup ne doit pas la repasser en
        // « échec »). Si elle a été annulée (et donc remboursée), on le dit.
        if ($order->payment_status === 'paid') {
            return $order->status === Order::STATUS_CANCELLED
                ? response()->json(['ok' => false, 'message' => __('Cette commande a été annulée, elle ne peut plus être payée.')], 422)
                : response()->json(['ok' => true, 'redirect' => route('orders.show', $order)]);
        }

        $data = $request->validate(['transaction_id' => ['required', 'string']]);

        $result = app(KkiapayService::class)->verifyTransaction($data['transaction_id']);

        return match ($this->settle($order, $result)) {
            'paid', 'already_paid' => response()->json(['ok' => true, 'redirect' => route('orders.show', $order)]),
            'cancelled' => response()->json(['ok' => false, 'message' => __('Cette commande a été annulée, elle ne peut plus être payée.')], 422),
            'late_payment' => response()->json(['ok' => false, 'message' => __('Cette commande avait été annulée avant la réception de votre paiement : il va vous être remboursé automatiquement.')], 422),
            'failed' => response()->json(['ok' => false, 'message' => __('Le paiement n\'a pas pu être confirmé (statut : :status).', ['status' => $result['status']])], 422),
            default => response()->json(['ok' => false, 'message' => __('Cette transaction ne correspond pas à cette commande.')], 422),
        };
    }

    /**
     * Point d'entrée webhook KkiaPay (à configurer dans le tableau de bord
     * KkiaPay). Sert de filet de sécurité si l'utilisateur ferme l'onglet
     * avant que le callback JS n'ait pu s'exécuter.
     */
    public function webhook(Request $request)
    {
        $expected = (string) config('services.kkiapay.webhook_secret');
        $signature = (string) $request->header('x-kkiapay-secret');

        // Un secret non configuré doit fermer le webhook, pas l'ouvrir :
        // sans ce contrôle, `null !== null` laissait passer toute requête
        // sans en-tête tant que KKIAPAY_WEBHOOK_SECRET était vide.
        if ($expected === '' || ! hash_equals($expected, $signature)) {
            Log::warning('Signature webhook KkiaPay invalide ou secret non configuré.');
            abort(401);
        }

        $transactionId = $request->input('transactionId');
        $reference = $request->input('data'); // référence de commande transmise au widget

        if (! $transactionId || ! $reference) {
            return response()->noContent();
        }

        $order = Order::where('reference', $reference)->first();

        if (! $order || $order->payment_status === 'paid') {
            return response()->noContent();
        }

        $result = app(KkiapayService::class)->verifyTransaction((string) $transactionId);

        $this->settle($order, $result);

        return response()->noContent();
    }

    /**
     * Point unique où une commande passe à « payée », partagé par verify()
     * et webhook() pour que les deux appliquent exactement les mêmes
     * contrôles. Une transaction n'est acceptée que si :
     *  - son statut KkiaPay est SUCCESS ;
     *  - son montant est présent et égal au total de la commande ;
     *  - la référence transmise au widget est celle de CETTE commande
     *    (sinon, une petite transaction réussie d'un autre achat pourrait
     *    servir à solder n'importe quelle commande) ;
     *  - elle n'a pas déjà servi à payer une autre commande.
     *
     * Paiement tardif : si la commande a été annulée entre-temps
     * (annulation automatique des impayées, ou par l'acheteur) mais qu'une
     * transaction valide arrive quand même, la commande n'est JAMAIS
     * rouverte — l'argent est enregistré (payment_status = paid) puis
     * remboursé automatiquement, pour que l'acheteur ne perde pas son
     * paiement sans commande en face. Si le remboursement automatique
     * échoue, `refunded_at` reste à null et l'échec est journalisé : à
     * traiter à la main.
     *
     * La ligne de commande est verrouillée le temps du contrôle pour que
     * le webhook et le callback navigateur, souvent simultanés, ne se
     * marchent pas dessus.
     *
     * @param  array{status: string, amount: float|null, transactionId: string|null, reference: string|null}  $result
     * @return 'paid'|'already_paid'|'cancelled'|'late_payment'|'failed'|'mismatch'
     */
    private function settle(Order $order, array $result): string
    {
        try {
            $outcome = DB::transaction(function () use ($order, $result) {
                $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

                if ($locked->payment_status === 'paid') {
                    return 'already_paid';
                }

                $isCancelled = $locked->status === Order::STATUS_CANCELLED;

                if ($result['status'] !== 'SUCCESS') {
                    if ($isCancelled) {
                        return 'cancelled';
                    }

                    $locked->update(['payment_status' => 'failed']);

                    return 'failed';
                }

                $transactionId = $result['transactionId'];

                $amountMatches = $result['amount'] !== null
                    && (int) round((float) $result['amount'] * 100) === (int) round((float) $locked->total_amount * 100);

                $referenceMatches = $result['reference'] !== null
                    && hash_equals((string) $locked->reference, $result['reference']);

                $alreadyUsed = $transactionId === null
                    || Order::where('payment_reference', $transactionId)->where('id', '!=', $locked->id)->exists();

                if (! $amountMatches || ! $referenceMatches || $alreadyUsed) {
                    Log::warning('Transaction KkiaPay refusée pour la commande '.$locked->reference, [
                        'transaction' => $transactionId,
                        'amount_matches' => $amountMatches,
                        'reference_matches' => $referenceMatches,
                        'already_used' => $alreadyUsed,
                    ]);

                    return $isCancelled ? 'cancelled' : 'mismatch';
                }

                if ($isCancelled) {
                    $locked->update([
                        'payment_status' => 'paid',
                        'payment_method' => 'kkiapay',
                        'payment_reference' => $transactionId,
                    ]);

                    return 'late_payment';
                }

                $locked->update([
                    'payment_status' => 'paid',
                    'status' => Order::STATUS_PAID,
                    'payment_method' => 'kkiapay',
                    'payment_reference' => $transactionId,
                ]);

                // La facture est numérotée à l'encaissement, dans la même
                // transaction : date d'émission = date du paiement.
                $locked->assignInvoiceNumber();

                return 'paid';
            });
        } catch (UniqueConstraintViolationException) {
            // Filet de sécurité : l'index unique sur payment_reference
            // a rejeté une transaction déjà utilisée par une commande
            // concurrente.
            return 'mismatch';
        }

        // Appel API externe : fait hors transaction, verrou relâché.
        if ($outcome === 'late_payment') {
            $order->refresh()->attemptRefund();
        }

        return $outcome;
    }
}
