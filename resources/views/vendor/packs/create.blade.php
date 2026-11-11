@extends('layouts.app')

@section('title', __('Créer un pack'))

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
    <x-breadcrumb :items="[
        ['label' => __('Tableau de bord'), 'url' => route('vendor.dashboard')],
        ['label' => __('Mes packs'), 'url' => route('vendor.packs.index')],
        ['label' => __('Créer un pack'), 'url' => null],
    ]" />

    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-ink">{{ __('Créer un pack promotionnel') }}</h1>
        <a href="{{ route('vendor.packs.index') }}" class="btn-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" class="mr-1 h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 3a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
            </svg>
            {{ __('Retour') }}
        </a>
    </div>

    <p class="mb-6 text-sm text-navy-500">{{ __("Sélectionnez au moins deux de vos produits et fixez un prix de pack inférieur à la somme de leurs prix de base : l'économie sera automatiquement calculée et affichée aux acheteurs.") }}</p>

    @if($products->count() < 2)
        <div class="card p-6 text-center text-navy-500">
            {{ __('Il vous faut au moins deux produits actifs pour créer un pack.') }}
            <a href="{{ route('vendor.products.create') }}" class="mt-2 block text-navy-700 underline">{{ __('Publier un nouveau produit') }}</a>
        </div>
    @else
        <form action="{{ route('vendor.packs.store') }}" method="POST" class="card space-y-5 p-6">
            @csrf
            <div>
                <label class="field-label">{{ __('Nom du pack') }}</label>
                <input type="text" name="name" value="{{ old('name') }}" class="field-input" placeholder="{{ __('Ex : Pack démarrage boutique') }}" required>
            </div>
            <div>
                <label class="field-label">{{ __('Description (facultatif)') }}</label>
                <textarea name="description" rows="2" class="field-input">{{ old('description') }}</textarea>
            </div>

            <div>
                <label class="field-label">{{ __('Produits inclus (cochez-en au moins deux, ajustez la quantité de chacun)') }}</label>
                <div class="space-y-2 rounded-md border border-line p-3">
                    @foreach($products as $product)
                        <div class="flex items-center gap-3 text-sm">
                            <input type="checkbox" name="product_ids[]" value="{{ $product->id }}" id="product-{{ $product->id }}">
                            <label for="product-{{ $product->id }}" class="flex-1">{{ $product->name }} <span class="text-navy-300">({{ number_format((float) $product->base_price, 0, ',', ' ') }} FCFA / {{ $product->unit }})</span></label>
                            <input type="number" name="quantities[{{ $product->id }}]" value="1" min="1" class="field-input w-20">
                        </div>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="field-label">{{ __('Prix du pack (FCFA)') }}</label>
                <input type="number" step="0.01" name="pack_price" value="{{ old('pack_price') }}" class="field-input w-48" required>
            </div>

            <button type="submit" class="btn-primary">{{ __('Publier le pack') }}</button>
        </form>
    @endif
</div>
@endsection
