@extends('layouts.app')

@section('title', __('Mes packs'))

@section('content')
<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
    <x-breadcrumb :items="[
        ['label' => __('Tableau de bord'), 'url' => route('vendor.dashboard')],
        ['label' => __('Mes packs'), 'url' => null],
    ]" />

    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-ink">{{ __('Mes packs promotionnels') }}</h1>
        <div class="flex gap-3">
            <a href="{{ route('vendor.dashboard') }}" class="btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" class="mr-1 h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                    <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 3a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                </svg>
                {{ __('Retour') }}
            </a>
            <a href="{{ route('vendor.packs.create') }}" class="btn-primary">+ {{ __('Nouveau pack') }}</a>
        </div>
    </div>

    <p class="mb-6 text-sm text-navy-500">
        {{ __('Astuce : regrouper plusieurs produits complementaires dans un pack a prix reduduit encourage les acheteurs a commander davantage en une seule fois.') }}
    </p>

    @if($packs->isEmpty())
        <div class="card p-10 text-center text-navy-500">{{ __("Vous n'avez pas encore cree de pack.") }}</div>
    @else
        <div class="space-y-4">
            @foreach($packs as $pack)
                <div class="card flex flex-wrap items-center justify-between gap-4 p-4">
                    <div>
                        <p class="font-medium text-ink">{{ $pack->name }}</p>
                        <p class="text-xs text-navy-300">{{ $pack->items->count() }} {{ __('produits') }} · {{ __('Economie affichee :') }} {{ $pack->savingsPercent() }}%</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <p class="font-display text-lg font-semibold text-navy-800">{{ number_format((float) $pack->pack_price, 0, ',', ' ') }} FCFA</p>
                        <form action="{{ route('vendor.packs.destroy', $pack) }}" method="POST" class="inline delete-form" data-confirm="{{ __('Supprimer ce pack ?') }}">
                            @csrf
                            <input type="hidden" name="_method" value="DELETE">
                            <button type="submit" class="text-sm text-red-600 hover:underline">{{ __('Supprimer') }}</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $packs->links() }}</div>
    @endif
</div>
@endsection
