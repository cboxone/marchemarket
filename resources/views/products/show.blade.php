@extends('layouts.app')

@section('title', $product->name)
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags($product->description), 155))
@section('og_description', \Illuminate\Support\Str::limit(strip_tags($product->description), 155))
@if($product->images->isNotEmpty())
    @section('og_image', Storage::url($product->images->first()->path))
@endif

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <nav class="mb-5 text-xs text-navy-300">
        <a href="{{ route('home') }}" class="hover:text-navy-700">{{ __('Accueil') }}</a> /
        <a href="{{ route('products.index', ['categorie' => $product->category->slug]) }}" class="hover:text-navy-700">{{ $product->category->name }}</a> /
        <span class="text-navy-500">{{ $product->name }}</span>
    </nav>

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
        {{-- Image --}}
        <div class="lg:col-span-1">
            @if($product->images->isNotEmpty())
                <div class="card aspect-square overflow-hidden">
                    <img id="main-product-image" src="{{ Storage::url($product->images->first()->path) }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
                </div>
                @if($product->images->count() > 1)
                    <div class="mt-3 grid grid-cols-4 gap-2">
                        @foreach($product->images as $image)
                            <button type="button" onclick="document.getElementById('main-product-image').src = this.querySelector('img').src" class="aspect-square overflow-hidden rounded-md border border-line hover:border-amber-400">
                                <img src="{{ Storage::url($image->path) }}" alt="" class="h-full w-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="card flex aspect-square items-center justify-center bg-navy-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 text-navy-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-8.25-4.5L3.75 7.5m16.5 0l-8.25 4.5m8.25-4.5v9l-8.25 4.5m0-9L3.75 7.5m8.25 4.5v9m-8.25-9v9l8.25 4.5"/></svg>
                </div>
            @endif
        </div>

        {{-- Détails et paliers de prix --}}
        <div class="lg:col-span-2">
            <div class="flex items-start justify-between gap-4">
                <h1 class="font-display text-2xl font-semibold text-ink">{{ $product->name }}</h1>
                @auth
                    @if(!auth()->user()->isVendor())
                        @php $isWishlisted = auth()->user()->wishlistedProducts()->where('product_id', $product->id)->exists(); @endphp
                        <form action="{{ route('wishlist.toggle', $product) }}" method="POST" class="shrink-0">
                            @csrf
                            <button type="submit" class="btn-secondary {{ $isWishlisted ? 'text-red-500' : '' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="{{ $isWishlisted ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
                                {{ $isWishlisted ? __('Dans vos favoris') : __('Ajouter aux favoris') }}
                            </button>
                        </form>
                    @endif
                @endauth
            </div>
            <div class="mt-1 flex items-center gap-3 text-sm text-navy-500">
                <span>{{ $product->averageRating() ?: '—' }} ★ ({{ $product->reviews->count() }} {{ __('avis') }})</span>
                <span>·</span>
                <span>{{ $product->stock_qty }} {{ $product->unit }} {{ __('en stock') }}</span>
            </div>

            @if($product->hasActivePromo())
                <div class="mt-3 inline-flex items-center gap-2 rounded-md bg-red-50 px-3 py-2">
                    <span class="rounded-full bg-red-600 px-2 py-0.5 text-xs font-semibold text-white">-{{ $product->discount_percent }}%</span>
                    <span class="text-sm text-red-700">
                        {{ __('Prix promo') }} : <strong>{{ number_format($product->promoPrice(), 0, ',', ' ') }} FCFA</strong>
                        <span class="ml-1 text-navy-400 line-through">{{ number_format((float) $product->base_price, 0, ',', ' ') }} FCFA</span>
                        @if($product->promo_ends_at)
                            — {{ __('jusqu\'au') }} {{ $product->promo_ends_at->format('d/m/Y à H:i') }}
                        @endif
                    </span>
                </div>
            @endif

            {{-- Tableau des paliers de prix --}}
            <div class="mt-5 overflow-hidden rounded-lg border border-line">
                <table class="w-full text-sm">
                    <thead class="bg-navy-50 text-navy-500">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">{{ __('Quantité') }} ({{ $product->unit }})</th>
                            <th class="px-4 py-2 text-left font-medium">{{ __('Prix unitaire') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach($product->priceTiers as $tier)
                            @php $tierPrice = $product->hasActivePromo() ? round((float) $tier->unit_price * (1 - $product->discount_percent / 100), 2) : (float) $tier->unit_price; @endphp
                            <tr data-role="tier-row" data-min="{{ $tier->min_qty }}" data-max="{{ $tier->max_qty ?? '' }}" data-price="{{ $tierPrice }}" class="border-l-2 border-transparent transition">
                                <td class="px-4 py-2">{{ $tier->min_qty }}{{ $tier->max_qty ? ' – ' . $tier->max_qty : '+' }}</td>
                                <td class="px-4 py-2 font-medium text-ink">
                                    {{ number_format($tierPrice, 0, ',', ' ') }} FCFA
                                    @if($product->hasActivePromo())
                                        <span class="ml-1 text-xs text-navy-300 line-through">{{ number_format((float) $tier->unit_price, 0, ',', ' ') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="mt-3 text-sm text-navy-500">
                {{ __('Quantité minimale de commande (MOQ)') }} : <strong class="text-ink">{{ $product->min_order_qty }} {{ $product->unit }}</strong>
                @if($product->max_order_qty)
                    · {{ __('Quantité maximale') }} : <strong class="text-ink">{{ $product->max_order_qty }} {{ $product->unit }}</strong>
                @endif
            </p>

            {{-- Formulaire d'ajout au panier --}}
            <form action="{{ route('cart.store') }}" method="POST" data-cart-add="true" class="mt-5 flex flex-wrap items-end gap-4 rounded-lg border border-line bg-white p-4">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <div>
                    <label class="field-label">{{ __('Quantité') }}</label>
                    <input type="number" name="quantity" data-role="quantity-input" value="{{ $product->min_order_qty }}" min="{{ $product->min_order_qty }}" @if($product->max_order_qty) max="{{ $product->max_order_qty }}" @endif class="field-input w-28">
                </div>
                <div class="text-sm text-navy-500">
                    {{ __('Prix unitaire') }} : <strong data-role="unit-price" class="text-ink">{{ number_format($product->hasActivePromo() ? $product->promoPrice() : (float) $product->base_price, 0, ',', ' ') }} FCFA</strong><br>
                    {{ __('Total estimé') }} : <strong data-role="total-price" class="text-ink">—</strong>
                </div>
                @auth
                    <button type="submit" class="btn-primary ml-auto">{{ __('Ajouter au panier') }}</button>
                @else
                    <a href="{{ route('login') }}" class="btn-primary ml-auto">{{ __('Se connecter pour commander') }}</a>
                @endauth
            </form>

            {{-- Fournisseur --}}
            <div class="mt-6 flex items-center justify-between rounded-lg border border-line bg-white p-4">
                <div>
                    <a href="{{ route('vendors.show', $product->vendor) }}" class="font-medium text-ink hover:text-navy-700">{{ $product->vendor->store_name }}</a>
                    <p class="text-xs text-navy-300">{{ $product->vendor->city }}, {{ $product->vendor->country }} · {{ $product->vendor->years_active }} {{ __('ans d\'activité') }}</p>
                </div>
                <div class="flex gap-2">
                    @if($product->vendor->is_verified)
                        <span class="badge-trade">{{ __('Vérifié') }}</span>
                    @endif
                    @if($product->vendor->trade_assurance)
                        <span class="badge-trade">Trade Assurance</span>
                    @endif
                </div>
            </div>

            {{-- Contacter le fournisseur --}}
            @auth
                @if(!auth()->user()->isVendor())
                    <a href="{{ route('quotes.create', $product) }}" class="btn-secondary mt-4 block text-center">{{ __('Demander un devis') }}</a>
                    <form action="{{ route('messages.store', $product) }}" method="POST" class="mt-4 rounded-lg border border-line bg-white p-4">
                        @csrf
                        <label class="field-label">{{ __('Une question sur ce produit ? Contactez directement le fournisseur') }}</label>
                        <div class="flex gap-2">
                            <input type="text" name="body" placeholder="{{ __('Ex : Avez-vous ce produit en stock pour 200 unités ?') }}" class="field-input" required>
                            <button type="submit" class="btn-secondary shrink-0">{{ __('Envoyer') }}</button>
                        </div>
                    </form>
                @endif
            @else
                <a href="{{ route('login') }}" class="mt-4 block text-center text-sm text-navy-500 underline">{{ __('Connectez-vous pour contacter ce fournisseur') }}</a>
            @endauth
        </div>
    </div>

    {{-- Description --}}
    <div class="mt-10 card p-6">
        <h2 class="mb-3 text-lg font-semibold text-ink">{{ __('Description du produit') }}</h2>
        <p class="whitespace-pre-line text-sm leading-relaxed text-navy-600">{{ $product->description }}</p>
    </div>

    {{-- Avis clients --}}
    <div class="mt-10 card p-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-ink">{{ __('Avis clients') }}</h2>
            <span class="text-sm text-navy-500">{{ $product->averageRating() ?: '—' }} ★ {{ __('sur') }} {{ $product->reviews->count() }} {{ __('avis') }}</span>
        </div>

        @auth
            @if(!auth()->user()->isVendor() && !auth()->user()->isAdmin())
                @php $myReview = $product->reviews->firstWhere('user_id', auth()->id()); @endphp
                <form action="{{ route('reviews.store', $product) }}" method="POST" class="mb-6 rounded-lg border border-line bg-navy-50 p-4">
                    @csrf
                    <p class="field-label">{{ $myReview ? __('Modifier mon avis') : __('Laisser un avis') }}</p>
                    <div class="mb-2 flex gap-1">
                        @for($i = 1; $i <= 5; $i++)
                            <label class="cursor-pointer text-2xl text-amber-500">
                                <input type="radio" name="rating" value="{{ $i }}" class="sr-only peer" {{ old('rating', $myReview->rating ?? 0) == $i ? 'checked' : '' }} required>
                                <span class="peer-checked:hidden">☆</span>
                                <span class="hidden peer-checked:inline">★</span>
                            </label>
                        @endfor
                    </div>
                    <textarea name="comment" rows="2" placeholder="{{ __('Votre expérience avec ce produit (facultatif)') }}" class="field-input mb-3">{{ old('comment', $myReview->comment ?? '') }}</textarea>
                    <button type="submit" class="btn-secondary">{{ $myReview ? __('Mettre à jour mon avis') : __('Publier mon avis') }}</button>
                </form>
            @endif
        @else
            <p class="mb-6 text-sm text-navy-500"><a href="{{ route('login') }}" class="underline">{{ __('Connectez-vous') }}</a> {{ __('pour laisser un avis.') }}</p>
        @endauth

        @if($product->reviews->isEmpty())
            <p class="text-sm text-navy-300">{{ __('Aucun avis pour le moment.') }}</p>
        @else
            <div class="space-y-4">
                @foreach($product->reviews as $review)
                    <div class="border-t border-line pt-4 first:border-t-0 first:pt-0">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-ink">{{ $review->user->name }}</p>
                            <span class="text-amber-500">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                        </div>
                        @if($review->comment)
                            <p class="mt-1 text-sm text-navy-500">{{ $review->comment }}</p>
                        @endif
                        <p class="mt-1 text-xs text-navy-300">{{ $review->created_at->format('d/m/Y') }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Produits similaires --}}
    @if($related->isNotEmpty())
        <div class="mt-10">
            <h2 class="mb-4 text-lg font-semibold text-ink">{{ __('Produits similaires') }}</h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach($related as $item)
                    <x-product-card :product="$item" />
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
