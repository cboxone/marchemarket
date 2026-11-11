@extends('layouts.app')

@section('title', $vendor->store_name)
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags($vendor->description ?? $vendor->store_name), 155))
@section('og_description', \Illuminate\Support\Str::limit(strip_tags($vendor->description ?? $vendor->store_name), 155))

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <div class="card mb-8 flex flex-wrap items-center justify-between gap-4 p-6">
        <div class="flex items-center gap-4">
            @if($vendor->logo_path)
                <img src="{{ Storage::url($vendor->logo_path) }}" alt="{{ $vendor->store_name }}" class="h-16 w-16 shrink-0 rounded-lg border border-line object-cover">
            @endif
            <div>
            <h1 class="font-display text-2xl font-semibold text-ink">{{ $vendor->store_name }}</h1>
            <p class="mt-1 text-sm text-navy-300">{{ $vendor->city }}, {{ $vendor->country }} · {{ $vendor->years_active }} {{ __("ans d'activité") }}</p>
            @if($vendor->description)
                <p class="mt-3 max-w-2xl text-sm text-navy-600">{{ $vendor->description }}</p>
            @endif
            </div>
        </div>
        <div class="flex gap-2">
            @if($vendor->is_verified)
                <span class="badge-trade">{{ __('Vérifié') }}</span>
            @endif
            @if($vendor->trade_assurance)
                <span class="badge-trade">Trade Assurance</span>
            @endif
        </div>
    </div>

    @if($packs->isNotEmpty())
        <h2 class="mb-4 text-lg font-semibold text-ink">{{ __('Packs de ce fournisseur') }}</h2>
        <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
            @foreach($packs as $pack)
                <a href="{{ route('packs.show', $pack) }}" class="card flex flex-col p-4 transition hover:border-amber-400">
                    <span class="mb-2 w-fit rounded-full bg-red-600 px-2 py-0.5 text-xs font-semibold text-white">-{{ $pack->savingsPercent() }}%</span>
                    <p class="font-medium text-ink">{{ $pack->name }}</p>
                    <p class="mt-2 font-display text-lg font-semibold text-navy-800">{{ number_format((float) $pack->pack_price, 0, ',', ' ') }} FCFA</p>
                </a>
            @endforeach
        </div>
    @endif

    <h2 class="mb-4 text-lg font-semibold text-ink">{{ __('Produits de ce fournisseur') }}</h2>
    @if($products->isEmpty())
        <div class="card p-10 text-center text-navy-500">{{ __("Ce fournisseur n'a pas encore de produit publié.") }}</div>
    @else
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach($products as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
        <div class="mt-8">{{ $products->links() }}</div>
    @endif
</div>
@endsection
