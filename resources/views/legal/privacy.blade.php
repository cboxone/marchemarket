@extends('layouts.app')

@section('title', __('Politique de confidentialité'))

@section('content')
<div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
    <h1 class="mb-6 font-display text-2xl font-semibold text-ink">{{ __('Politique de confidentialité') }}</h1>

    <div class="card space-y-6 p-6 text-sm leading-relaxed text-navy-600">
        <p class="text-xs text-navy-300">{{ __('Dernière mise à jour') }} : {{ now()->format('d/m/Y') }}</p>

        <p>{{ __("MarcheMarket accorde une grande importance à la protection des données personnelles de ses utilisateurs. Cette page explique quelles données sont collectées, pourquoi, et comment elles sont utilisées.") }}</p>

        <div>
            <h2 class="mb-2 font-semibold text-ink">1. {{ __('Données collectées') }}</h2>
            <p>{{ __("Nom, adresse e-mail, numéro de téléphone, nom d'entreprise, adresse de livraison, ainsi que les données liées à l'activité sur la plateforme (produits consultés, commandes, messages échangés avec les fournisseurs).") }}</p>
        </div>

        <div>
            <h2 class="mb-2 font-semibold text-ink">2. {{ __('Finalités du traitement') }}</h2>
            <p>{{ __("Ces données sont utilisées pour : la gestion des comptes utilisateurs, le traitement des commandes et des paiements, la mise en relation entre acheteurs et fournisseurs, l'envoi de communications liées au service (confirmation de commande, messages, sécurité du compte), et l'amélioration de la plateforme.") }}</p>
        </div>

        <div>
            <h2 class="mb-2 font-semibold text-ink">3. {{ __('Partage des données') }}</h2>
            <p>{{ __("Les données strictement nécessaires sont partagées avec le Fournisseur concerné par une commande (nom, adresse de livraison, téléphone) et avec notre prestataire de paiement KkiaPay pour le traitement des transactions Mobile Money et carte bancaire. Aucune donnée n'est vendue à des tiers.") }}</p>
        </div>

        <div id="cookies">
            <h2 class="mb-2 font-semibold text-ink">4. {{ __('Cookies') }}</h2>
            <p class="mb-2">{{ __("MarcheMarket n'utilise aucun cookie publicitaire ni traceur de mesure d'audience. Seuls des cookies strictement nécessaires au fonctionnement du site sont déposés :") }}</p>
            <ul class="ml-4 list-disc space-y-1">
                <li>{{ __('Un cookie de session, qui vous garde connecté pendant votre visite.') }}</li>
                <li>{{ __("Un cookie de sécurité (jeton anti-falsification), qui protège les formulaires du site contre les attaques.") }}</li>
                <li>{{ __("Un cookie « se souvenir de moi », déposé uniquement si vous cochez cette option à la connexion, pour éviter de vous reconnecter à chaque visite.") }}</li>
            </ul>
            <p class="mt-2">{{ __('Ces cookies sont nécessaires au fonctionnement du site ; il n\'est pas possible de les désactiver sans empêcher la connexion à votre compte.') }}</p>
        </div>

        <div>
            <h2 class="mb-2 font-semibold text-ink">5. {{ __('Conservation des données') }}</h2>
            <p>{{ __("Les données sont conservées pendant toute la durée de vie du compte, puis archivées ou supprimées conformément aux obligations légales applicables, notamment en matière comptable et commerciale.") }}</p>
        </div>

        <div>
            <h2 class="mb-2 font-semibold text-ink">6. {{ __('Vos droits') }}</h2>
            <p>{{ __("Vous pouvez à tout moment demander l'accès, la rectification ou la suppression de vos données personnelles en nous contactant.") }}</p>
        </div>

        <p class="text-xs text-navy-300">{{ __('Ce document est un modèle fourni à titre indicatif et doit être relu et validé par un professionnel du droit avant toute mise en production.') }}</p>
    </div>
</div>
@endsection
