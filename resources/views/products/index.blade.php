@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
        {{-- Filtres --}}
        <aside class="lg:col-span-1">
            <form action="{{ route('products.index') }}" method="GET" class="card space-y-5 p-4">
                <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">

                <div>
                    <h3 class="mb-2 text-sm font-semibold text-ink">{{ __('Catégorie') }}</h3>
                    <div class="space-y-1.5 text-sm">
                        <label class="flex items-center gap-2">
                            <input type="radio" name="categorie" value="" {{ empty($filters['categorie']) ? 'checked' : '' }} onchange="this.form.submit()">
                            {{ __('Toutes les catégories') }}
                        </label>
                        @foreach($categories as $cat)
                            <label class="flex items-center gap-2">
                                <input type="radio" name="categorie" value="{{ $cat->slug }}" {{ ($filters['categorie'] ?? '') === $cat->slug ? 'checked' : '' }} onchange="this.form.submit()">
                                {{ $cat->name }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 text-sm font-semibold text-ink">{{ __('Prix unitaire (FCFA)') }}</h3>
                    <div class="flex gap-2">
                        <input type="number" name="prix_min" value="{{ $filters['prix_min'] ?? '' }}" placeholder="{{ __('Min') }}" class="field-input">
                        <input type="number" name="prix_max" value="{{ $filters['prix_max'] ?? '' }}" placeholder="{{ __('Max') }}" class="field-input">
                    </div>
                </div>

                @if($cities->isNotEmpty())
                    <div>
                        <h3 class="mb-2 text-sm font-semibold text-ink">{{ __('Ville du fournisseur') }}</h3>
                        <select name="ville" class="field-input">
                            <option value="">{{ __('Toutes les villes') }}</option>
                            @foreach($cities as $city)
                                <option value="{{ $city }}" @selected(($filters['ville'] ?? '') === $city)>{{ $city }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="space-y-1.5">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="en_stock" value="1" {{ ($filters['en_stock'] ?? false) ? 'checked' : '' }}>
                        {{ __('En stock uniquement') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="verifies" value="1" {{ ($filters['verifies'] ?? false) ? 'checked' : '' }}>
                        {{ __('Fournisseurs vérifiés uniquement') }}
                    </label>
                </div>

                <button type="submit" class="btn-dark w-full">{{ __('Filtrer') }}</button>

                @if(collect($filters)->except(['q', 'tri'])->filter()->isNotEmpty())
                    <a href="{{ route('products.index', array_filter(['q' => $filters['q'] ?? null])) }}" class="block text-center text-xs text-navy-300 hover:underline">{{ __('Réinitialiser les filtres') }}</a>
                @endif
            </form>
        </aside>

        {{-- Résultats --}}
        <div class="lg:col-span-3">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-semibold text-ink">
                    @if(!empty($filters['q']))
                        {{ __('Résultats pour') }} « {{ $filters['q'] }} »
                    @else
                        {{ __('Catalogue des produits de gros') }}
                    @endif
                </h1>
                <div class="flex items-center gap-3">
                    <span class="text-sm text-navy-300">{{ $products->total() }} {{ __('produits') }}</span>
                    <form action="{{ route('products.index') }}" method="GET">
                        @foreach(collect($filters)->except('tri')->filter() as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach
                        <select name="tri" onchange="this.form.submit()" class="field-input py-1.5 text-sm">
                            <option value="" @selected(empty($filters['tri']))>{{ __('Plus récents') }}</option>
                            <option value="popularite" @selected(($filters['tri'] ?? '') === 'popularite')>{{ __('Popularité') }}</option>
                            <option value="prix_asc" @selected(($filters['tri'] ?? '') === 'prix_asc')>{{ __('Prix croissant') }}</option>
                            <option value="prix_desc" @selected(($filters['tri'] ?? '') === 'prix_desc')>{{ __('Prix décroissant') }}</option>
                        </select>
                    </form>
                </div>
            </div>

            @if($products->isEmpty())
                <div class="card p-10 text-center text-navy-500">
                    {{ __('Aucun produit ne correspond à votre recherche pour le moment.') }}
                </div>
            @else
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    @foreach($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
                <div class="mt-8">{{ $products->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
