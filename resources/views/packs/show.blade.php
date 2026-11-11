@extends('layouts.app')

@section('title', $pack->name)
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags($pack->description ?? $pack->name), 155))
@section('og_description', \Illuminate\Support\Str::limit(strip_tags($pack->description ?? $pack->name), 155))

@section('content')
<div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
    <nav class="mb-5 text-xs text-navy-300">
        <a href="{{ route('packs.index') }}" class="hover:text-navy-700">{{ __('Packs & bons plans') }}</a> /
        <span class="text-navy-500">{{ $pack->name }}</span>
    </nav>

    <div class="card p-6">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="font-display text-2xl font-semibold text-ink">{{ $pack->name }}</h1>
                <p class="mt-1 text-sm text-navy-300">{{ __('Proposé par') }} {{ $pack->vendor->store_name }}</p>
            </div>
            <span class="rounded-full bg-red-600 px-3 py-1 text-sm font-semibold text-white">-{{ $pack->savingsPercent() }}% {{ __("par rapport à l'achat séparé") }}</span>
        </div>

        @if($pack->description)
            <p class="mb-4 text-sm text-navy-600">{{ $pack->description }}</p>
        @endif

        <div class="mb-4 divide-y divide-line rounded-lg border border-line">
            @foreach($pack->items as $item)
                <div class="flex items-center gap-3 p-3">
                    <div class="h-12 w-12 shrink-0 overflow-hidden rounded-md bg-navy-50">
                        @if($item->product->images->isNotEmpty())
                            <img src="{{ Storage::url($item->product->images->first()->path) }}" alt="" class="h-full w-full object-cover">
                        @endif
                    </div>
                    <div class="flex-1">
                        <a href="{{ route('products.show', $item->product) }}" class="text-sm font-medium text-ink hover:text-navy-700">{{ $item->product->name }}</a>
                        <p class="text-xs text-navy-300">{{ __('Quantité incluse :') }} {{ $item->quantity }} {{ $item->product->unit }}</p>
                    </div>
                    <p class="text-sm text-navy-500">{{ number_format((float) $item->product->base_price * $item->quantity, 0, ',', ' ') }} FCFA</p>
                </div>
            @endforeach
        </div>

        <div class="flex items-center justify-between rounded-lg bg-navy-50 p-4">
            <div>
                <p class="text-sm text-navy-500">{{ __("Prix à l'unité (somme des produits) :") }} <span class="line-through">{{ number_format($pack->individualTotal(), 0, ',', ' ') }} FCFA</span></p>
                <p class="font-display text-2xl font-semibold text-navy-800">{{ number_format((float) $pack->pack_price, 0, ',', ' ') }} FCFA</p>
            </div>
            <p class="text-sm font-medium text-trade-green">{{ __('Vous économisez') }} {{ number_format($pack->savingsAmount(), 0, ',', ' ') }} FCFA</p>
        </div>

        @auth
            <form action="{{ route('packs.add-to-cart', $pack) }}" method="POST" class="mt-4">
                @csrf
                <button type="submit" class="btn-primary w-full">{{ __('Ajouter tous les produits du pack au panier') }}</button>
            </form>
        @else
            <a href="{{ route('login') }}" class="btn-primary mt-4 block w-full text-center">{{ __('Se connecter pour commander ce pack') }}</a>
        @endauth

        <p class="mt-4 text-xs text-navy-300">
            {{ __("Le bouton ci-dessus ajoute chaque produit du pack au panier, dans les quantités indiquées. Le prix facturé reste toutefois celui de chaque produit pris séparément : pour bénéficier du tarif de pack négocié ci-dessus, contactez directement le fournisseur.") }}
        </p>
    </div>
</div>
@endsection
