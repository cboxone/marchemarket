<?php

namespace App\Services;

use Kkiapay\Kkiapay;
use RuntimeException;

/**
 * Fine couche au-dessus du SDK officiel kkiapay/kkiapay-php.
 *
 * KkiaPay est un agrégateur de paiement basé à Cotonou qui permet
 * d'encaisser via Mobile Money (MTN MoMo, Moov Money) et carte bancaire,
 * sans avoir à négocier séparément avec chaque opérateur télécom.
 *
 * Doc officielle : https://docs.kkiapay.me
 * SDK PHP        : https://github.com/kkiapay/php-sdk (composer require kkiapay/kkiapay-php)
 */
class KkiapayService
{
    private Kkiapay $client;

    public function __construct()
    {
        $public = config('services.kkiapay.public_key');
        $private = config('services.kkiapay.private_key');
        $secret = config('services.kkiapay.secret_key');

        if (! $public || ! $private || ! $secret) {
            throw new RuntimeException(
                'Clés KkiaPay manquantes. Renseignez KKIAPAY_PUBLIC_KEY, KKIAPAY_PRIVATE_KEY et KKIAPAY_SECRET_KEY dans le fichier .env.'
            );
        }

        $this->client = new Kkiapay($public, $private, $secret, config('services.kkiapay.sandbox', true));
    }

    public function publicKey(): string
    {
        return config('services.kkiapay.public_key');
    }

    public function isSandbox(): bool
    {
        return (bool) config('services.kkiapay.sandbox', true);
    }

    /**
     * Interroge l'API KkiaPay pour connaître le statut réel d'une transaction.
     * Ne JAMAIS faire confiance uniquement à la réponse du widget côté navigateur :
     * c'est cette vérification serveur qui fait foi avant de marquer une commande payée.
     *
     * @return array{status: string, amount: float|null, transactionId: string|null, reference: string|null}
     */
    public function verifyTransaction(string $transactionId): array
    {
        $transaction = $this->client->verifyTransaction($transactionId);

        // Le SDK peut renvoyer un objet (stdClass) ou un tableau selon la version.
        $data = is_array($transaction) ? $transaction : (array) $transaction;

        return [
            'status' => $data['status'] ?? 'FAILED',
            'amount' => isset($data['amount']) ? (float) $data['amount'] : null,
            'transactionId' => $data['transactionId'] ?? $transactionId,
            // Champ `data` transmis au widget (ici la référence de commande) :
            // sert à vérifier que la transaction correspond bien à la commande.
            'reference' => isset($data['data']) ? (string) $data['data'] : null,
        ];
    }

    /**
     * Rembourse une transaction déjà réussie (annulation de commande côté
     * acheteur). KkiaPay ne rembourse pas les frais de transaction liés au
     * paiement initial — voir la documentation officielle. Lève une
     * exception si le remboursement échoue : à appelant de décider comment
     * réagir (voir OrderController::cancel, qui journalise l'échec sans
     * bloquer l'annulation de la commande elle-même).
     */
    public function refundTransaction(string $transactionId): bool
    {
        $result = $this->client->refundTransaction($transactionId);
        $data = is_array($result) ? $result : (array) $result;

        $status = strtoupper((string) ($data['status'] ?? ''));

        return in_array($status, ['SUCCESS', 'REFUNDED', 'ACCEPTED'], true);
    }
}
