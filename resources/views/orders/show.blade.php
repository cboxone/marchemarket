@extends('layouts.app')

@section('title', __('Commande') . ' ' . $order->reference)

@section('content')
<div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-ink">{{ __('Commande') }} {{ $order->reference }}</h1>
        <div class="flex items-center gap-2">
            @if($order->payment_status === 'paid')
                <span class="badge-trade">{{ __('Payée') }}</span>
            @elseif($order->payment_status === 'failed')
                <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-red-600">{{ __('Paiement échoué') }}</span>
            @else
                <a href="{{ route('payment.show', $order) }}" class="btn-primary text-xs">{{ __('Payer maintenant') }}</a>
            @endif
            <span class="badge-trade">{{ $order->statusLabel() }}</span>
            @if($order->isInvoiceable())
                <a href="{{ route('orders.invoice', $order) }}" target="_blank" rel="noopener" class="btn-secondary text-xs">{{ __('Facture') }}</a>
            @endif
            @if($order->payment_status === 'paid' && $order->status !== 'cancelled')
                <a href="{{ route('disputes.create', $order) }}" class="btn-secondary text-xs">{{ __('Signaler un problème') }}</a>
            @endif
        </div>
    </div>

    <div class="card mb-6 p-5">
        <h2 class="mb-2 font-semibold text-ink">{{ __('Livraison') }}</h2>
        <p class="text-sm text-navy-500">{{ $order->shipping_address }}, {{ $order->shipping_city }}, {{ $order->shipping_country }}</p>
        <p class="text-sm text-navy-500">{{ __('Tél') }} : {{ $order->shipping_phone }}</p>
    </div>

    @if($order->status === 'cancelled')
        <div class="mb-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <p class="font-medium">{{ __('Commande annulée') }}{{ $order->cancelled_at ? ' le ' . $order->cancelled_at->format('d/m/Y à H:i') : '' }}</p>
            @if($order->cancellation_reason)
                <p class="mt-1">{{ __('Motif') }} : {{ $order->cancellation_reason }}</p>
            @endif
            <p class="mt-1">
                @if($order->refunded_at)
                    {{ __('Remboursement effectué le :date.', ['date' => $order->refunded_at->format('d/m/Y à H:i')]) }}
                @elseif($order->payment_status === 'paid')
                    {{ __('Remboursement en cours de traitement par notre équipe.') }}
                @endif
            </p>
        </div>
    @elseif($order->isCancellable())
        <div class="mb-6">
            <button type="button" onclick="document.getElementById('cancel-order-form').classList.toggle('hidden')" class="text-sm text-red-600 underline">
                {{ __('Annuler cette commande') }}
            </button>
            <form id="cancel-order-form" action="{{ route('orders.cancel', $order) }}" method="POST" class="mt-3 hidden rounded-md border border-line bg-white p-4">
                @csrf
                <label class="field-label">{{ __('Motif (facultatif)') }}</label>
                <textarea name="cancellation_reason" rows="2" class="field-input mb-3" placeholder="{{ __('Pourquoi souhaitez-vous annuler ?') }}"></textarea>
                <button type="submit" class="btn-secondary text-red-600" onclick="return confirm({{ Illuminate\Support\Js::from(__('Confirmer l\'annulation de cette commande ?')) }})">
                    {{ __("Confirmer l'annulation") }}
                </button>
                @if($order->payment_status === 'paid' && $order->payment_method === 'kkiapay')
                    <p class="mt-2 text-xs text-navy-300">{{ __('Cette commande étant payée, un remboursement sera automatiquement tenté via KkiaPay.') }}</p>
                @elseif($order->payment_status === 'paid')
                    <p class="mt-2 text-xs text-navy-300">{{ __('Cette commande étant payée par virement bancaire, le remboursement sera traité manuellement par notre équipe.') }}</p>
                @endif
            </form>
        </div>
    @endif

    <div class="overflow-hidden rounded-lg border border-line bg-white">
        <table class="w-full text-sm">
            <thead class="bg-navy-50 text-left text-navy-500">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ __('Produit') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Fournisseur') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Qté') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Prix unitaire') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Sous-total') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Suivi') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach($order->items as $item)
                    <tr>
                        <td class="px-4 py-3 text-ink">{{ $item->product_name }}</td>
                        <td class="px-4 py-3 text-navy-500">{{ $item->vendor->store_name }}</td>
                        <td class="px-4 py-3 text-navy-500">{{ $item->quantity }}</td>
                        <td class="px-4 py-3 text-navy-500">{{ number_format((float) $item->unit_price, 0, ',', ' ') }} FCFA</td>
                        <td class="px-4 py-3 font-medium text-ink">{{ number_format($item->subtotal(), 0, ',', ' ') }} FCFA</td>
                        <td class="px-4 py-3"><span class="badge-trade">{{ $item->fulfillmentLabel() }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4 space-y-1 text-right text-sm text-navy-500">
        @if($order->coupon_id && (float) $order->discount_amount > 0)
            <p class="text-trade-green">{{ __('Code') }} {{ $order->coupon?->code }} : −{{ number_format((float) $order->discount_amount, 0, ',', ' ') }} FCFA</p>
        @endif
        <p>{{ __('Frais de livraison') }} : {{ number_format((float) $order->shipping_fee, 0, ',', ' ') }} FCFA</p>
        <p class="text-lg font-semibold text-ink">{{ __('Total') }} : {{ number_format((float) $order->total_amount, 0, ',', ' ') }} FCFA</p>
        <p class="text-xs text-navy-300">{{ __('dont TVA (:rate%) incluse', ['rate' => \App\Models\Order::TVA_RATE]) }} : {{ number_format($order->taxAmount(), 0, ',', ' ') }} FCFA</p>
    </div>
</div>
@endsection
