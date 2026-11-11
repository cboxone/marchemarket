<?php

return [
    // Laissez vide en développement : aucune donnée n'est envoyée à Sentry
    // tant que cette variable n'est pas renseignée dans .env. Récupérez ce
    // DSN depuis votre projet sur https://sentry.io (ou une instance
    // auto-hébergée) avant la mise en production.
    'dsn' => env('SENTRY_LARAVEL_DSN'),

    // Précise l'environnement dans les événements remontés (utile pour
    // distinguer les erreurs de staging de celles de production).
    'environment' => env('APP_ENV', 'production'),

    // Proportion des requêtes tracées pour la performance (0 = désactivé).
    // À ajuster à la hausse seulement si vous avez besoin du monitoring de
    // performance Sentry — 1.0 enverrait une trace de CHAQUE requête.
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.0),

    // N'envoie pas le contenu des requêtes/réponses par défaut (données
    // potentiellement sensibles : mots de passe, tokens de paiement...).
    'send_default_pii' => false,
];
