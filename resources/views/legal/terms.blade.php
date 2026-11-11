@extends('layouts.app')

@section('title', __("Conditions générales d'utilisation"))

@section('content')
<div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
    <h1 class="mb-6 font-display text-2xl font-semibold text-ink">{{ __("Conditions générales d'utilisation") }}</h1>

    <div class="card space-y-6 p-6 text-sm leading-relaxed text-navy-600">
        <p class="text-xs text-navy-300">{{ __('Dernière mise à jour') }} : {{ now()->format('d/m/Y') }}</p>

        <p>{{ __("Les présentes conditions générales d'utilisation (« CGU ») régissent l'accès et l'utilisation de la plateforme MarcheMarket, marketplace de mise en relation entre vendeurs professionnels (« Fournisseurs ») et acheteurs professionnels (« Acheteurs ») basée à Cotonou, Bénin. En créant un compte ou en utilisant le site, vous acceptez sans réserve les présentes CGU.") }}</p>

        <div>
            <h2 class="mb-2 font-semibold text-ink">1. {{ __("Rôle de MarcheMarket") }}</h2>
            <p>{{ __("MarcheMarket agit en tant qu'intermédiaire technique mettant en relation Fournisseurs et Acheteurs. MarcheMarket n'est ni fabricant, ni propriétaire, ni vendeur des produits proposés sur la plateforme : chaque Fournisseur reste seul responsable de l'exactitude de ses annonces, de la qualité, de la conformité et de la livraison de ses produits.") }}</p>
        </div>

        <div>
            <h2 class="mb-2 font-semibold text-ink">2. {{ __('Comptes utilisateurs') }}</h2>
            <p>{{ __("L'inscription est ouverte à toute personne physique ou morale agissant à titre professionnel. Les informations fournies lors de l'inscription doivent être exactes et tenues à jour. Chaque utilisateur est responsable de la confidentialité de ses identifiants.") }}</p>
        </div>

        <div>
            <h2 class="mb-2 font-semibold text-ink">3. {{ __('Commandes et paiement') }}</h2>
            <p>{{ __("Les prix affichés sont exprimés en Francs CFA (FCFA) et incluent, le cas échéant, les paliers de prix dégressifs communiqués par le Fournisseur. Le paiement s'effectue en ligne via Mobile Money ou carte bancaire, par l'intermédiaire du prestataire de paiement KkiaPay. Une commande n'est considérée comme confirmée qu'après validation effective du paiement.") }}</p>
        </div>

        <div>
            <h2 class="mb-2 font-semibold text-ink">4. {{ __('Obligations des Fournisseurs') }}</h2>
            <p>{{ __("Le Fournisseur garantit disposer de tous les droits et autorisations nécessaires à la commercialisation de ses produits, et s'engage à respecter la réglementation applicable en République du Bénin et, le cas échéant, le droit OHADA relatif au commerce général.") }}</p>
        </div>

        <div>
            <h2 class="mb-2 font-semibold text-ink">5. {{ __('Responsabilité') }}</h2>
            <p>{{ __("MarcheMarket met tout en œuvre pour assurer la disponibilité et la sécurité de la plateforme, sans garantie de résultat. MarcheMarket ne saurait être tenu responsable des litiges relatifs à l'exécution d'une commande entre un Acheteur et un Fournisseur.") }}</p>
        </div>

        <div>
            <h2 class="mb-2 font-semibold text-ink">6. {{ __('Modification des CGU') }}</h2>
            <p>{{ __("MarcheMarket se réserve le droit de modifier les présentes CGU à tout moment. Les utilisateurs seront informés de toute modification substantielle.") }}</p>
        </div>

        <p class="text-xs text-navy-300">{{ __('Ce document est un modèle fourni à titre indicatif et doit être relu et validé par un professionnel du droit avant toute mise en production.') }}</p>
    </div>
</div>
@endsection
