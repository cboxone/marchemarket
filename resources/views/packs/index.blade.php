@extends('layouts.app')

@section('title', __('Packs & bons plans'))

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <h1 class="mb-2 text-2xl font-semibold text-ink">{{ __('Packs & bons plans') }}</h1>
    <p class="mb-6 text-navy-500">{{ __('Des lots de produits complémentaires assemblés par nos fournisseurs, à prix réduit par rapport à un achat séparé.') }}</p>

    @if($packs->isEmpty())
        <div class="card p-10 text-center text-navy-500">{{ __('Aucun pack disponible pour le moment.') }}</div>
    @else
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($packs as $pack)
                <a href="{{ route('packs.show', $pack) }}" class="card flex flex-col p-4 transition hover:border-amber-400">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="rounded-full bg-red-600 px-2 py-0.5 text-xs font-semibold text-white">-{{ $pack->savingsPercent() }}%</span>
                        <span class="text-xs text-navy-300">{{ $pack->items->count() }} {{ __('produits') }}</span>
                    </div>
                    <p class="font-medium text-ink">{{ $pack->name }}</p>
                    <p class="mt-1 text-xs text-navy-300">{{ $pack->vendor->store_name }}</p>
                    <p class="mt-3 font-display text-xl font-semibold text-navy-800">
                        {{ number_format((float) $pack->pack_price, 0, ',', ' ') }} FCFA
                        <span class="ml-1 text-sm font-normal text-navy-300 line-through">{{ number_format($pack->individualTotal(), 0, ',', ' ') }} FCFA</span>
                    </p>
                </a>
            @endforeach
        </div>
        <div class="mt-8">{{ $packs->links() }}</div>
    @endif
</div>
@endsection
