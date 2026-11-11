@extends('layouts.app')

@section('title', __('Commande') . ' ' . $order->reference)

@section('content')
<div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
    <div class="mb-4">
        <a href="{{ route('vendor.orders.index') }}" class="text-sm text-navy-500 hover:text-navy-700">← {{ __('Retour aux commandes') }}</a>
    </div>

    <h1 class="mb-6 text-xl font-semibold text-ink">{{ __('Commande') }} {{ $order->reference }}</h1>

    <div class="card mb-6 grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
        <div>
            <h2 class="mb-2 font-semibold text-ink">{{ __('Acheteur') }}</h2>
            <p class="text-sm text-navy-500">{{ $order->buyer->name }}</p>
            @if($order->buyer->company_name)
                <p class="text-sm text-navy-500">{{ $order->buyer->company_name }}</p>
            @endif
        </div>
        <div>
            <h2 class="mb-2 font-semibold text-ink">{{ __('Adresse de livraison') }}</h2>
            <p class="text-sm text-navy-500">{{ $order->shipping_address }}, {{ $order->shipping_city }}, {{ $order->shipping_country }}</p>
            <p class="text-sm text-navy-500">{{ __('Tél') }} : {{ $order->shipping_phone }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-line bg-white">
        <table class="w-full text-sm">
            <thead class="bg-navy-50 text-left text-navy-500">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ __('Produit') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Qté') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Sous-total') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Statut') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach($order->items as $item)
                    <tr>
                        <td class="px-4 py-3 text-ink">{{ $item->product_name }}</td>
                        <td class="px-4 py-3 text-navy-500">{{ $item->quantity }}</td>
                        <td class="px-4 py-3 font-medium text-ink">{{ number_format($item->subtotal(), 0, ',', ' ') }} FCFA</td>
                        <td class="px-4 py-3">
                            <form action="{{ route('vendor.orders.update-status', $item) }}" method="POST" class="flex items-center gap-2">
                                @csrf @method('PATCH')
                                <select name="fulfillment_status" onchange="this.form.submit()" class="field-input py-1 text-xs">
                                    <option value="pending" @selected($item->fulfillment_status === 'pending')>{{ __('En attente') }}</option>
                                    <option value="shipped" @selected($item->fulfillment_status === 'shipped')>{{ __('Expédiée') }}</option>
                                    <option value="delivered" @selected($item->fulfillment_status === 'delivered')>{{ __('Livrée') }}</option>
                                </select>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4 text-right text-lg font-semibold text-ink">
        {{ __('Total (vos articles)') }} : {{ number_format($order->items->sum(fn ($i) => $i->subtotal()), 0, ',', ' ') }} FCFA
    </div>
</div>
@endsection
