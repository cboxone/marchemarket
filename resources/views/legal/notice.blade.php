@extends('layouts.app')

@section('title', __('Mentions légales'))

@section('content')
<div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
    <h1 class="mb-6 font-display text-2xl font-semibold text-ink">{{ __('Mentions légales') }}</h1>

    <div class="card space-y-6 p-6 text-sm leading-relaxed text-navy-600">
        <div>
            <h2 class="mb-2 font-semibold text-ink">{{ __('Éditeur du site') }}</h2>
            <p>{{ __('MarcheMarket — Marketplace B2B') }}<br>
            {{ __('Siège social') }} : Cotonou, {{ __('Bénin') }}<br>
            {{ __('Contact') }} : contact@marchemarket.test</p>
        </div>

        <div>
            <h2 class="mb-2 font-semibold text-ink">{{ __('Hébergement') }}</h2>
            <p>{{ __("Informations d'hébergement à compléter avant mise en production.") }}</p>
        </div>

        <div>
            <h2 class="mb-2 font-semibold text-ink">{{ __('Propriété intellectuelle') }}</h2>
            <p>{{ __("L'ensemble des éléments composant le site MarcheMarket (textes, logos, mise en page) est protégé par le droit de la propriété intellectuelle. Toute reproduction non autorisée est interdite.") }}</p>
        </div>

        <div>
            <h2 class="mb-2 font-semibold text-ink">{{ __('Médiation et litiges') }}</h2>
            <p>{{ __("En cas de litige, une solution amiable sera recherchée en priorité. À défaut d'accord, les tribunaux compétents de la République du Bénin seront seuls compétents.") }}</p>
        </div>

        <p class="text-xs text-navy-300">{{ __('Ce document est un modèle fourni à titre indicatif et doit être relu et validé par un professionnel du droit avant toute mise en production.') }}</p>
    </div>
</div>
@endsection
