<?php

return [

    /*
    | Délai (en minutes) avant qu'une commande jamais payée soit annulée
    | automatiquement (stock et code promo restitués). Plancher de 10 minutes :
    | un paiement Mobile Money peut prendre quelques minutes à être confirmé.
    */
    'unpaid_order_ttl_minutes' => max(10, (int) env('UNPAID_ORDER_TTL_MINUTES', 60)),

    /*
    | Commission de la plateforme, en % du montant réellement encaissé pour
    | les articles d'un vendeur (après remise de code promo, hors frais de
    | livraison). Le taux en vigueur est figé sur chaque article au moment de
    | la commande : le modifier ici n'affecte que les commandes futures.
    | Valeur par défaut 0 : aucune commission tant que la variable n'est pas
    | renseignée.
    */
    'commission_percent' => min(100, max(0, (float) env('PLATFORM_COMMISSION_PERCENT', 0))),

    /*
    | Délai (en jours) laissé au fournisseur pour répondre à une demande de
    | devis. Passé ce délai, la demande expire (commande `quotes:expire`).
    */
    'quote_request_days' => max(1, (int) env('QUOTE_REQUEST_DAYS', 14)),

    /*
    | Délai avant qu'un reversement encaissé devienne retirable par le
    | vendeur, à compter (dans cet ordre de préférence) : de la livraison
    | constatée, sinon de l'expédition, sinon — si le vendeur ne met jamais
    | sa commande à jour — du paiement (filet de sécurité, pour ne pas
    | bloquer indéfiniment un solde légitime). Ces délais couvrent
    | approximativement la fenêtre de dépôt d'un litige ci-dessous : un
    | vendeur qui expédie et livre normalement n'est presque jamais gêné.
    */
    'payout_release_days' => [
        'after_delivery' => max(0, (int) env('PAYOUT_RELEASE_AFTER_DELIVERY_DAYS', 7)),
        'after_shipping' => max(0, (int) env('PAYOUT_RELEASE_AFTER_SHIPPING_DAYS', 21)),
        'after_payment' => max(0, (int) env('PAYOUT_RELEASE_AFTER_PAYMENT_DAYS', 45)),
    ],

    /*
    | Litiges acheteur/vendeur.
    */
    'disputes' => [
        // Fenêtre pour ouvrir un litige : après livraison constatée, ou après
        // paiement si le vendeur n'a jamais marqué la commande comme livrée.
        'window_after_delivery_days' => max(1, (int) env('DISPUTE_WINDOW_AFTER_DELIVERY_DAYS', 7)),
        'window_after_payment_days' => max(1, (int) env('DISPUTE_WINDOW_AFTER_PAYMENT_DAYS', 15)),
        // Délai laissé au vendeur pour répondre avant escalade automatique vers l'admin.
        'vendor_response_hours' => max(1, (int) env('DISPUTE_VENDOR_RESPONSE_HOURS', 72)),
        // Cible indicative d'arbitrage admin (affichée, non appliquée automatiquement :
        // un litige n'est jamais tranché sans intervention humaine).
        'admin_target_days' => max(1, (int) env('DISPUTE_ADMIN_TARGET_DAYS', 5)),
    ],

    /*
    | Virement bancaire manuel : second moyen de paiement, à côté de KkiaPay
    | (Mobile Money / carte). Aucune vérification automatique n'existe pour
    | un virement — l'acheteur dépose une preuve, un admin la rapproche à la
    | main de son relevé bancaire avant de confirmer.
    |
    | N'apparaît sur la page de paiement que si un IBAN ou un numéro de
    | compte est renseigné : mieux vaut masquer l'option que d'en proposer
    | une qui ne mène nulle part.
    */
    'bank_transfer' => [
        'bank_name' => env('BANK_TRANSFER_BANK_NAME'),
        'account_name' => env('BANK_TRANSFER_ACCOUNT_NAME'),
        'account_number' => env('BANK_TRANSFER_ACCOUNT_NUMBER'),
        'iban' => env('BANK_TRANSFER_IBAN'),
        'swift' => env('BANK_TRANSFER_SWIFT'),
        // Délai avant annulation automatique d'une commande pour laquelle
        // l'acheteur a choisi le virement mais n'a jamais déposé de preuve.
        // Volontairement long (un virement, contrairement à un paiement
        // Mobile Money, prend parfois plusieurs jours ouvrés).
        'window_hours' => max(1, (int) env('BANK_TRANSFER_WINDOW_HOURS', 72)),
    ],

    /*
    | Identité légale de la plateforme, imprimée en en-tête des factures.
    | À renseigner avant d'émettre de vraies factures (raison sociale, IFU,
    | RCCM…). Les valeurs vides sont simplement omises.
    */
    'company' => [
        'name' => env('PLATFORM_LEGAL_NAME', env('APP_NAME', 'MarcheMarket')),
        'address' => env('PLATFORM_LEGAL_ADDRESS'),
        'phone' => env('PLATFORM_LEGAL_PHONE'),
        'email' => env('PLATFORM_LEGAL_EMAIL'),
        'tax_id' => env('PLATFORM_TAX_ID'),               // IFU
        'registration' => env('PLATFORM_REGISTRATION_NUMBER'), // RCCM
    ],

    // Mention libre en pied de facture (conditions de vente, coordonnées bancaires…).
    'invoice_footer' => env('INVOICE_FOOTER'),

];
