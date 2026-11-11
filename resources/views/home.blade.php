@extends('layouts.app')

@section('meta_description', __("MarcheMarket connecte les commerçants et industriels du Bénin à des fournisseurs vérifiés, avec des prix qui baissent à mesure que la quantité commandée augmente."))

@section('content')
    {{-- Hero --}}
    <section class="relative overflow-hidden bg-navy-800">
        {{-- Background images --}}
        @if($backgroundImages->isNotEmpty())
            <div class="absolute inset-0 opacity-10">
                @foreach($backgroundImages as $index => $bgImage)
                    @if($bgImage->images->isNotEmpty())
                        <img src="{{ asset('storage/' . $bgImage->images->first()->path) }}"
                             alt=""
                             class="absolute {{ $index % 2 === 0 ? 'right-0' : 'left-0' }} {{ $index < 2 ? 'top-0' : ($index < 4 ? 'top-1/3' : 'bottom-0') }} h-48 w-48 -translate-y-1/2 translate-x-1/2 rounded-full object-cover blur-sm animate-float-{{ $index }}">
                    @endif
                @endforeach
            </div>
        @endif

        <div class="relative mx-auto grid max-w-7xl gap-8 px-4 py-14 sm:px-6 lg:grid-cols-2 lg:items-center">
            <div>
                <h1 class="font-display text-4xl font-semibold leading-tight text-white sm:text-5xl">
                    {{ __('Achetez en gros,') }}<br>{{ __('directement auprès des fournisseurs.') }}
                </h1>
                <p class="mt-4 max-w-md text-navy-200">
                    {{ __('MarcheMarket connecte les commerçants et industriels du Bénin à des fournisseurs vérifiés, avec des prix qui baissent à mesure que la quantité commandée augmente.') }}
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ route('products.index') }}" class="btn-primary">{{ __('Parcourir le catalogue') }}</a>
                    <a href="{{ route('register') }}" class="btn-secondary bg-transparent text-white hover:bg-white hover:text-navy-800">{{ __('Devenir fournisseur') }}</a>
                </div>
                <div class="mt-8 flex flex-wrap gap-6 text-sm text-navy-200">
                    <div class="flex items-center gap-2">
                        <span class="badge-trade">{{ __('Vérifié') }}</span> {{ __('Fournisseurs contrôlés') }}
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="badge-trade">{{ __('Garantie') }}</span> Trade Assurance
                    </div>
                </div>
            </div>
            <div class="hidden lg:block">
                <div class="grid grid-cols-2 gap-4">
                    @foreach($featuredProducts->take(4) as $p)
                        <a href="{{ route('products.show', $p) }}" class="flex rounded-lg bg-navy-700/60 p-3 transition hover:bg-navy-700/80 hover:scale-105">
                            <div class="h-16 w-16 flex-shrink-0 overflow-hidden rounded bg-navy-800">
                                @if($p->images->isNotEmpty())
                                    <img src="{{ asset('storage/' . $p->images->first()->path) }}"
                                         alt="{{ $p->name }}"
                                         class="h-full w-full object-cover">
                                @else
                                    <div class="flex h-full items-center justify-center text-navy-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                @endif
                            </div>
                            <div class="ml-3 flex-1">
                                <div class="flex items-start justify-between">
                                    <p class="text-xs text-navy-300">{{ $p->category->name }}</p>
                                    @if($p->discount_percent > 0)
                                        <span class="rounded-full bg-red-600 px-1.5 py-0.5 text-xs font-semibold text-white">-{{ $p->discount_percent }}%</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-sm font-medium text-white">{{ \Illuminate\Support\Str::limit($p->name, 35) }}</p>
                                <div class="mt-1">
                                    @if($p->discount_percent > 0)
                                        <p class="text-xs text-navy-400 line-through">{{ number_format((float) $p->base_price, 0, ',', ' ') }} FCFA</p>
                                        <p class="font-display text-base font-semibold text-amber-400">{{ number_format((float) $p->promoPrice(), 0, ',', ' ') }} FCFA</p>
                                    @else
                                        <p class="font-display text-base font-semibold text-amber-400">{{ number_format((float) $p->base_price, 0, ',', ' ') }} FCFA</p>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <style>
        @keyframes float-0 {
            0%, 100% {
                transform: translateY(0) translateX(0);
            }
            50% {
                transform: translateY(-20px) translateX(10px);
            }
        }
        @keyframes float-1 {
            0%, 100% {
                transform: translateY(0) translateX(0);
            }
            50% {
                transform: translateY(-15px) translateX(-10px);
            }
        }
        @keyframes float-2 {
            0%, 100% {
                transform: translateY(0) translateX(0);
            }
            50% {
                transform: translateY(-25px) translateX(15px);
            }
        }
        @keyframes float-3 {
            0%, 100% {
                transform: translateY(0) translateX(0);
            }
            50% {
                transform: translateY(-18px) translateX(-15px);
            }
        }
        @keyframes float-4 {
            0%, 100% {
                transform: translateY(0) translateX(0);
            }
            50% {
                transform: translateY(-22px) translateX(12px);
            }
        }
        @keyframes float-5 {
            0%, 100% {
                transform: translateY(0) translateX(0);
            }
            50% {
                transform: translateY(-16px) translateX(-12px);
            }
        }
        .animate-float-0 {
            animation: float-0 3s ease-in-out infinite;
        }
        .animate-float-1 {
            animation: float-1 4s ease-in-out infinite;
        }
        .animate-float-2 {
            animation: float-2 5s ease-in-out infinite;
        }
        .animate-float-3 {
            animation: float-3 6s ease-in-out infinite;
        }
        .animate-float-4 {
            animation: float-4 7s ease-in-out infinite;
        }
        .animate-float-5 {
            animation: float-5 8s ease-in-out infinite;
        }
    </style>

    {{-- Catégories --}}
    <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6">
        <h2 class="mb-5 text-xl font-semibold text-ink">{{ __('Catégories populaires') }}</h2>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
            @foreach($categories as $category)
                <x-category-card :category="$category" />
            @endforeach
        </div>
    </section>

    {{-- Packs & bons plans --}}
    @if($packs->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 py-4 sm:px-6">
            <div class="mb-5 flex items-center justify-between">
                <h2 class="text-xl font-semibold text-ink">{{ __('Packs & bons plans') }}</h2>
                <a href="{{ route('packs.index') }}" class="text-sm font-medium text-navy-700 hover:text-amber-600">{{ __('Voir tous les packs') }} →</a>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                @foreach($packs as $pack)
                    <a href="{{ route('packs.show', $pack) }}" class="card flex flex-col p-4 transition hover:border-amber-400">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="rounded-full bg-red-600 px-2 py-0.5 text-xs font-semibold text-white">-{{ $pack->savingsPercent() }}%</span>
                            <span class="text-xs text-navy-300">{{ $pack->items->count() }} {{ __('produits') }}</span>
                        </div>
                        <p class="font-medium text-ink">{{ $pack->name }}</p>
                        <p class="mt-1 text-xs text-navy-300">{{ $pack->vendor->store_name }}</p>
                        <p class="mt-3 font-display text-xl font-semibold text-navy-800">{{ number_format((float) $pack->pack_price, 0, ',', ' ') }} FCFA</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Produits vedettes --}}
    <section class="mx-auto max-w-7xl px-4 py-4 sm:px-6">
        <div class="mb-5 flex items-center justify-between">
            <h2 class="text-xl font-semibold text-ink">{{ __('Produits récemment ajoutés') }}</h2>
            <a href="{{ route('products.index') }}" class="text-sm font-medium text-navy-700 hover:text-amber-600">{{ __('Voir tout le catalogue') }} →</a>
        </div>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach($featuredProducts as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
    </section>

    {{-- Fournisseurs vérifiés --}}
    @if($topVendors->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6">
            <h2 class="mb-5 text-xl font-semibold text-ink">{{ __('Fournisseurs vérifiés à la une') }}</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($topVendors as $vendor)
                    <div class="card p-4">
                        <div class="flex items-start justify-between">
                            <p class="font-medium text-ink">{{ $vendor->store_name }}</p>
                            @if($vendor->is_verified)
                                <span class="badge-trade">{{ __('Vérifié') }}</span>
                            @endif
                        </div>
                        <p class="mt-1 text-xs text-navy-300">{{ $vendor->city }}, {{ $vendor->country }}</p>
                        <div class="mt-3 flex justify-between text-xs text-navy-500">
                            <span>{{ $vendor->products_count }} {{ __('produits') }}</span>
                            <span>{{ $vendor->response_rate }}% {{ __('réponse') }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
@endsection
