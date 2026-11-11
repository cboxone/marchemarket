@extends('layouts.app')

@section('title', __('Mes commandes'))

@section('content')
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    <x-breadcrumb :items="[
        ['label' => __('Tableau de bord'), 'url' => route('vendor.dashboard')],
        ['label' => __('Mes commandes'), 'url' => null],
    ]" />

    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-ink">{{ __('Commandes reçues') }}</h1>
        <a href="{{ route('vendor.dashboard') }}" class="btn-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" class="mr-1 h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 3a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
            </svg>
            {{ __('Retour') }}
        </a>
    </div>

    <x-csv-export-bar route="vendor.orders.export" />

    @if($orders->isEmpty())
        <div class="card p-10 text-center text-navy-500">{{ __("Vous n'avez pas encore reçu de commande.") }}</div>
    @else
        <div class="overflow-hidden rounded-lg border border-line bg-white">
            <table class="w-full text-sm">
                <thead class="bg-navy-50 text-left text-navy-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Référence') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Acheteur') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Articles') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Montant (vos articles)') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Statut') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach($orders as $order)
                        @php
                            $myItems = $order->items;
                            $myTotal = $myItems->sum(fn ($i) => $i->subtotal());
                            $allShipped = $myItems->every(fn ($i) => $i->fulfillment_status !== 'pending');
                        @endphp
                        <tr>
                            <td class="px-4 py-3 text-ink">{{ $order->reference }}</td>
                            <td class="px-4 py-3 text-navy-500">{{ $order->buyer->name }}</td>
                            <td class="px-4 py-3 text-navy-500">{{ $myItems->sum('quantity') }} ({{ $myItems->count() }} {{ __('produits') }})</td>
                            <td class="px-4 py-3 font-medium text-ink">{{ number_format($myTotal, 0, ',', ' ') }} FCFA</td>
                            <td class="px-4 py-3">
                                @if($allShipped)
                                    <span class="badge-trade">{{ __('Traitée') }}</span>
                                @else
                                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-600">{{ __('À traiter') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('vendor.orders.show', $order) }}" class="text-navy-700 hover:underline">{{ __('Détails') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
