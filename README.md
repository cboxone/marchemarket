# MarcheMarket

Marketplace B2B multi-vendeurs construite avec Laravel 11. Elle permet aux acheteurs professionnels de rechercher des produits de gros, demander des devis et passer commande, tout en fournissant des espaces dédiés aux vendeurs et aux administrateurs.

## Fonctionnalités

- Catalogue avec recherche, filtres, MOQ, paliers de prix, promotions, packs et favoris.
- Panier, adresses de livraison, commandes, factures PDF, avis et messagerie.
- Demandes de devis (RFQ), suivi des litiges et exports CSV.
- Espaces vendeur : boutique, produits, commandes, reversements, vérification d'identité et import de produits.
- Back-office : modération, utilisateurs, catégories, coupons, virements, litiges, journaux d'audit et paramètres.
- Paiement Kkiapay et virement bancaire manuel ; notifications e-mail mises en file d'attente.

## Prérequis

- PHP 8.2 ou supérieur, avec `gd` et le pilote PDO de la base choisie.
- Composer.
- Node.js 18 ou supérieur et npm.
- MySQL/MariaDB pour l'environnement normal. SQLite est utilisable pour les tests.

## Installation locale

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
```

Sous macOS ou Linux, remplacez `copy` par `cp`.

Configurez ensuite les variables `DB_*` dans `.env`, créez la base de données puis lancez :

```bash
php artisan migrate --seed
php artisan db:seed --class=SettingsSeeder
php artisan storage:link
npm run build
php artisan serve
```

L'application est alors disponible sur `http://localhost:8000`. En développement front-end, lancez `npm run dev` dans un second terminal à la place de `npm run build`.

> Le seeder de paramètres est séparé du seeder principal ; il initialise la page d'administration des paramètres (`/admin/parametres`).

## Comptes de démonstration

Après `php artisan migrate --seed` :

| Rôle | Adresse | Mot de passe |
| --- | --- | --- |
| Administrateur | `admin@marchemarket.test` | `password` |
| Vendeurs | `vendeur1@marchemarket.test` à `vendeur4@marchemarket.test` | `password` |
| Client | `client@marchemarket.test` | `password` |

Ces comptes sont destinés au développement uniquement. Le seeder est bloqué en production, sauf si `ALLOW_DEMO_SEED=true` est défini explicitement.

## Données avec images

Le seeder principal ajoute le catalogue de démonstration. Pour compléter ce catalogue avec des images téléchargées depuis Unsplash (connexion Internet nécessaire), exécutez :

```bash
php artisan seed:products-with-images
```

Exécutez cette commande après le seeder principal. Les images sont enregistrées dans `storage/app/public/products` et nécessitent le lien créé par `php artisan storage:link`.

## Configuration

Les principales variables sont documentées dans `.env.example` :

- `KKIAPAY_*` pour Kkiapay ; renseignez aussi l'URL du webhook `/webhooks/kkiapay` dans le tableau de bord du prestataire.
- `BANK_TRANSFER_*` pour proposer le virement bancaire.
- `PLATFORM_COMMISSION_PERCENT`, `UNPAID_ORDER_TTL_MINUTES` et `QUOTE_REQUEST_DAYS` pour les règles de la marketplace.
- `PAYOUT_RELEASE_AFTER_*` et `DISPUTE_*` pour les reversements et litiges.
- `PLATFORM_LEGAL_*` et `INVOICE_FOOTER` pour les factures.
- `SENTRY_LARAVEL_DSN` pour le suivi d'erreurs facultatif.

En local, les e-mails sont écrits dans `storage/logs/laravel.log` (`MAIL_MAILER=log`). Pour un envoi réel, configurez les variables SMTP. Les notifications asynchrones exigent aussi un worker :

```bash
php artisan queue:work
```

## Tâches planifiées

Les commandes de maintenance annulent les commandes impayées, expirent les devis et escaladent les litiges sans réponse. En production, activez le planificateur Laravel :

```bash
* * * * * cd /chemin/vers/marchemarket && php artisan schedule:run >> /dev/null 2>&1
```

## Tests et qualité

```bash
php artisan test
./vendor/bin/pint --test
```

Les fichiers générés (`vendor`, `node_modules`, `public/build`, caches Laravel et cache PHPUnit) ne font pas partie du code source : ils sont recréés par les commandes ci-dessus.

## Mise en production

Avant le déploiement, définissez au minimum `APP_ENV=production`, `APP_DEBUG=false`, une `APP_KEY` unique, les identifiants de base de données et de messagerie, ainsi que les clés de paiement. Puis installez les dépendances et compilez les assets :

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
```

Configurez également un worker de file d'attente et le cron Laravel ci-dessus.

## Licence

Ce projet est distribué sous licence MIT.
