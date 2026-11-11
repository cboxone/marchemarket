@extends('layouts.app')

@section('title', __('Tableau de bord vendeur'))

@section('content')
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-ink">{{ __('Bonjour,') }} {{ $vendor->store_name }}</h1>
        <div class="flex gap-3">
            <a href="{{ route('vendor.orders.index') }}" class="btn-secondary">{{ __('Mes commandes') }}</a>
            <a href="{{ route('vendor.packs.index') }}" class="btn-secondary">{{ __('Mes packs') }}</a>
            <a href="{{ route('vendor.disputes.index') }}" class="btn-secondary">
                {{ __('Litiges') }}
                @if($stats['open_disputes'] > 0)
                    <span class="ml-1 rounded-full bg-red-500 px-1.5 text-xs text-white">{{ $stats['open_disputes'] }}</span>
                @endif
            </a>
            <a href="{{ route('vendor.quotes.index') }}" class="btn-secondary">
                {{ __('Devis') }}
                @if($stats['pending_quotes'] > 0)
                    <span class="ml-1 rounded-full bg-amber-500 px-1.5 text-xs text-white">{{ $stats['pending_quotes'] }}</span>
                @endif
            </a>
            <a href="{{ route('vendor.shop.edit') }}" class="btn-secondary">{{ __('Ma boutique') }}</a>
            <a href="{{ route('vendor.payouts.index') }}" class="btn-secondary">{{ __('Mes paiements') }}</a>
            <a href="{{ route('vendor.products.create') }}" class="btn-primary">+ {{ __('Nouveau produit') }}</a>
        </div>
    </div>

    <div class="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        <div class="card p-4">
            <p class="text-xs text-navy-300">{{ __('Produits actifs') }}</p>
            <p class="mt-1 font-display text-2xl font-semibold text-navy-800">{{ $stats['active_products_count'] }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-navy-300">{{ __('Total produits') }}</p>
            <p class="mt-1 font-display text-2xl font-semibold text-navy-800">{{ $stats['products_count'] }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-navy-300">{{ __('Commandes reçues') }}</p>
            <p class="mt-1 font-display text-2xl font-semibold text-navy-800">{{ $stats['orders_count'] }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-navy-300">{{ __("Chiffre d'affaires") }}</p>
            <p class="mt-1 font-display text-2xl font-semibold text-navy-800">{{ number_format($stats['revenue'], 0, ',', ' ') }} FCFA</p>
            @if($stats['commission'] > 0)
                <p class="mt-1 text-xs text-navy-300">{{ __('dont commission plateforme : :amount FCFA', ['amount' => number_format($stats['commission'], 0, ',', ' ')]) }}</p>
            @endif
        </div>
        <div class="card p-4">
            <p class="text-xs text-navy-300">{{ __('Solde disponible pour retrait') }}</p>
            <p class="mt-1 font-display text-2xl font-semibold text-trade-green">{{ number_format($stats['available_balance'], 0, ',', ' ') }} FCFA</p>
        </div>
    </div>

    <div class="mb-4 flex items-center gap-2">
        @if($vendor->is_verified)
            <span class="badge-trade">{{ __('Compte vérifié') }}</span>
        @elseif($vendor->kyc_status === 'pending')
            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-600">{{ __("En attente de vérification par l'équipe MarcheMarket") }}</span>
        @elseif($vendor->kyc_status === 'rejected')
            <a href="{{ route('vendor.verification.edit') }}" class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-red-600 hover:underline">{{ __('Dossier de vérification refusé — corriger') }}</a>
        @else
            <a href="{{ route('vendor.verification.edit') }}" class="rounded-full bg-navy-50 px-2.5 py-1 text-xs font-medium text-navy-600 hover:underline">{{ __('Faire vérifier ma boutique') }}</a>
        @endif
    </div>

    <h2 class="mb-3 text-lg font-semibold text-ink">{{ __('Dernières ventes') }}</h2>
    @if($recentOrderItems->isEmpty())
        <div class="card p-8 text-center text-navy-500">{{ __('Aucune vente pour le moment.') }}</div>
    @else
        <div class="overflow-hidden rounded-lg border border-line bg-white">
            <table class="w-full text-sm">
                <thead class="bg-navy-50 text-left text-navy-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Commande') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Produit') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Qté') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Montant') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach($recentOrderItems as $item)
                        <tr>
                            <td class="px-4 py-3 text-ink">{{ $item->order->reference }}</td>
                            <td class="px-4 py-3 text-navy-500">{{ $item->product_name }}</td>
                            <td class="px-4 py-3 text-navy-500">{{ $item->quantity }}</td>
                            <td class="px-4 py-3 font-medium text-ink">{{ number_format($item->subtotal(), 0, ',', ' ') }} FCFA</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
