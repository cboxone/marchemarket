@extends('layouts.app')

@section('title', __('Devis :ref', ['ref' => $quote->reference]))

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
    @php
    $st = $quote->effectiveStatus();
    $cls = match ($st) {
        'quoted', 'accepted' => 'badge-trade',
        'pending' => 'rounded-full bg-amber-50 px-2.5 py-1 text-xs text-amber-700',
        default => 'rounded-full bg-navy-50 px-2.5 py-1 text-xs text-navy-500',
    };
@endphp
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-ink">{{ __('Devis :ref', ['ref' => $quote->reference]) }}</h1>
        <span class="{{ $cls }}">{{ $quote->statusLabel() }}</span>
    </div>

    <section class="card mb-6 space-y-2 p-6 text-sm">
        <p><span class="text-navy-300">{{ __('Acheteur') }} :</span> {{ $quote->buyer->company_name ?: $quote->buyer->name }}@if($quote->buyer->company_name) ({{ $quote->buyer->name }})@endif</p>
        <p><span class="text-navy-300">{{ __('Produit') }} :</span> {{ $quote->product_name }}
            @if($quote->product)
                — {{ __('catalogue :price FCFA / :unit, stock : :stock', ['price' => number_format((float) $quote->product->base_price, 0, ',', ' '), 'unit' => $quote->unit, 'stock' => $quote->product->stock_qty]) }}
            @endif
        </p>
        <p><span class="text-navy-300">{{ __('Quantité demandée') }} :</span> {{ $quote->quantity }} {{ $quote->unit }}</p>
        @if($quote->target_price !== null)
            <p><span class="text-navy-300">{{ __('Prix visé') }} :</span> {{ number_format((float) $quote->target_price, 0, ',', ' ') }} FCFA</p>
        @endif
        @if($quote->message)
            <p><span class="text-navy-300">{{ __("Message de l'acheteur") }} :</span> {{ $quote->message }}</p>
        @endif
        <p><span class="text-navy-300">{{ __('Échéance') }} :</span> {{ $quote->expires_at?->format('d/m/Y') }}</p>
    </section>

    @if($errors->has('quote'))
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first('quote') }}</div>
    @endif

    @if(in_array($st, ['pending', 'quoted'], true))
        <form action="{{ route('vendor.quotes.respond', $quote) }}" method="POST" class="card mb-4 space-y-4 p-6">
            @csrf
            <h2 class="text-base font-semibold text-ink">{{ $st === 'quoted' ? __('Réviser mon devis') : __('Envoyer un devis') }}</h2>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="field-label" for="unit_price">{{ __('Prix unitaire (FCFA, TTC)') }}</label>
                    <input type="number" id="unit_price" name="unit_price" min="1" step="any" value="{{ old('unit_price', $quote->quoted_unit_price ?? $quote->product?->base_price) }}" class="field-input" required>
                </div>
                <div>
                    <label class="field-label" for="quantity">{{ __('Quantité proposée') }}</label>
                    <input type="number" id="quantity" name="quantity" min="1" value="{{ old('quantity', $quote->quoted_quantity ?? $quote->quantity) }}" class="field-input" required>
                </div>
                <div>
                    <label class="field-label" for="shipping_fee">{{ __('Frais de livraison (FCFA)') }}</label>
                    <input type="number" id="shipping_fee" name="shipping_fee" min="0" step="any" value="{{ old('shipping_fee', $quote->quoted_shipping_fee ?? \App\Models\Order::SHIPPING_FEE) }}" class="field-input">
                </div>
                <div>
                    <label class="field-label" for="valid_days">{{ __('Validité du devis (jours)') }}</label>
                    <input type="number" id="valid_days" name="valid_days" min="1" max="60" value="{{ old('valid_days', 7) }}" class="field-input" required>
                </div>
            </div>

            <div>
                <label class="field-label" for="note">{{ __('Note pour l\'acheteur (facultatif)') }}</label>
                <textarea id="note" name="note" rows="3" maxlength="2000" class="field-input">{{ old('note', $quote->vendor_note) }}</textarea>
            </div>

            <p class="text-xs text-navy-300">{{ __('Les prix incluent la TVA. Le stock est vérifié et réservé au moment où l\'acheteur accepte.') }}</p>
            <button type="submit" class="btn-primary">{{ $st === 'quoted' ? __('Mettre à jour le devis') : __('Envoyer le devis') }}</button>
        </form>

        <form action="{{ route('vendor.quotes.decline', $quote) }}" method="POST" class="flex items-end gap-2">
            @csrf
            <input type="text" name="reason" maxlength="255" placeholder="{{ __('Motif (facultatif)') }}" class="field-input w-64">
            <button type="submit" class="text-sm text-red-600 hover:underline">{{ __('Décliner la demande') }}</button>
        </form>
    @elseif($st === 'accepted')
        <div class="card p-6 text-sm text-navy-500">
            {{ __('Devis accepté : total :total FCFA.', ['total' => number_format($quote->quotedTotal(), 0, ',', ' ')]) }}
            @if($quote->order)<br>{{ __('Commande :ref', ['ref' => $quote->order->reference]) }} — {{ $quote->order->statusLabel() }}@endif
        </div>
    @elseif($st === 'declined' && $quote->decline_reason)
        <div class="card p-6 text-sm text-navy-500">{{ __('Motif :') }} {{ $quote->decline_reason }}</div>
    @endif

    <p class="mt-8"><a href="{{ route('vendor.quotes.index') }}" class="text-sm text-navy-500 hover:underline">← {{ __('Demandes de devis') }}</a></p>
</div>
@endsection
