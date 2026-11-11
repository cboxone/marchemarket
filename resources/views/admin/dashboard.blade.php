@extends('layouts.app')

@section('title', __('Back-office admin'))

@section('content')
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    <h1 class="mb-6 text-xl font-semibold text-ink">{{ __('Tableau de bord administrateur') }}</h1>

    <div class="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
        <div class="card p-4"><p class="text-xs text-navy-300">{{ __('Vendeurs') }}</p><p class="mt-1 font-display text-xl font-semibold text-navy-800">{{ $stats['vendors_count'] }}</p></div>
        <a href="{{ route('admin.verifications.index') }}" class="card p-4 transition hover:border-amber-300"><p class="text-xs text-navy-300">{{ __('Dossiers KYC à vérifier') }}</p><p class="mt-1 font-display text-xl font-semibold text-amber-600">{{ $stats['pending_verification_count'] }}</p></a>
        <div class="card p-4"><p class="text-xs text-navy-300">{{ __('Produits') }}</p><p class="mt-1 font-display text-xl font-semibold text-navy-800">{{ $stats['products_count'] }}</p></div>
        <div class="card p-4"><p class="text-xs text-navy-300">{{ __('Clients') }}</p><p class="mt-1 font-display text-xl font-semibold text-navy-800">{{ $stats['customers_count'] }}</p></div>
        <div class="card p-4"><p class="text-xs text-navy-300">{{ __('Commandes') }}</p><p class="mt-1 font-display text-xl font-semibold text-navy-800">{{ $stats['orders_count'] }}</p></div>
        <div class="card p-4"><p class="text-xs text-navy-300">{{ __('Revenu total') }}</p><p class="mt-1 font-display text-xl font-semibold text-navy-800">{{ number_format($stats['revenue'], 0, ',', ' ') }} FCFA</p>@if($stats['commission'] > 0)<p class="text-xs text-navy-300">{{ __('dont commission : :amount FCFA', ['amount' => number_format($stats['commission'], 0, ',', ' ')]) }}</p>@endif</div>
    </div>

    <div class="mb-6 flex flex-wrap gap-4 text-sm">
        <a href="{{ route('admin.vendors.index') }}" class="btn-secondary">{{ __('Gérer les vendeurs') }}</a>
        <a href="{{ route('admin.verifications.index') }}" class="btn-secondary">
            {{ __('Vérifications KYC') }}
            @if($stats['pending_verification_count'] > 0)
                <span class="ml-1 rounded-full bg-amber-500 px-1.5 text-xs text-white">{{ $stats['pending_verification_count'] }}</span>
            @endif
        </a>
        <a href="{{ route('admin.users.index') }}" class="btn-secondary">{{ __('Gérer les utilisateurs') }}</a>
        <a href="{{ route('admin.bank-transfers.index') }}" class="btn-secondary">
            {{ __('Virements à vérifier') }}
            @if($stats['pending_bank_transfers'] > 0)
                <span class="ml-1 rounded-full bg-amber-500 px-1.5 text-xs text-white">{{ $stats['pending_bank_transfers'] }}</span>
            @endif
        </a>
        <a href="{{ route('admin.disputes.index') }}" class="btn-secondary">
            {{ __('Litiges') }}
            @if($stats['escalated_disputes'] > 0)
                <span class="ml-1 rounded-full bg-red-500 px-1.5 text-xs text-white">{{ $stats['escalated_disputes'] }}</span>
            @endif
        </a>
        <a href="{{ route('admin.products.index') }}" class="btn-secondary">{{ __('Modérer les produits') }}</a>
        <a href="{{ route('admin.categories.index') }}" class="btn-secondary">{{ __('Gérer les catégories') }}</a>
        <a href="{{ route('admin.coupons.index') }}" class="btn-secondary">{{ __('Codes promo') }}</a>
        <a href="{{ route('admin.orders.index') }}" class="btn-secondary">{{ __('Superviser les commandes') }}</a>
        <a href="{{ route('admin.payouts.index') }}" class="btn-secondary">{{ __('Paiements aux vendeurs') }}</a>
        <a href="{{ route('admin.reviews.index') }}" class="btn-secondary">{{ __('Modérer les avis') }}</a>
        <a href="{{ route('admin.audit-logs.index') }}" class="btn-secondary">{{ __("Journal d'audit") }}</a>
    </div>

    <h2 class="mb-3 text-lg font-semibold text-ink">{{ __('Dernières commandes') }}</h2>
    <div class="overflow-hidden rounded-lg border border-line bg-white">
        <table class="w-full text-sm">
            <thead class="bg-navy-50 text-left text-navy-500">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ __('Référence') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Acheteur') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Statut') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach($recentOrders as $order)
                    <tr>
                        <td class="px-4 py-3 text-ink">{{ $order->reference }}</td>
                        <td class="px-4 py-3 text-navy-500">{{ $order->buyer->name }}</td>
                        <td class="px-4 py-3"><span class="badge-trade">{{ $order->statusLabel() }}</span></td>
                        <td class="px-4 py-3 font-medium text-ink">{{ number_format((float) $order->total_amount, 0, ',', ' ') }} FCFA</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
