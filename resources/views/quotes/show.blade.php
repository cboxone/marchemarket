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
        <p><span class="text-navy-300">{{ __('Produit') }} :</span>
            @if($quote->product && $quote->product->is_active)
                <a href="{{ route('products.show', $quote->product) }}" class="text-trade-green hover:underline">{{ $quote->product_name }}</a>
            @else
                {{ $quote->product_name }}
            @endif
        </p>
        <p><span class="text-navy-300">{{ __('Fournisseur') }} :</span> {{ $quote->vendor->store_name }}</p>
        <p><span class="text-navy-300">{{ __('Quantité demandée') }} :</span> {{ $quote->quantity }} {{ $quote->unit }}</p>
        @if($quote->target_price !== null)
            <p><span class="text-navy-300">{{ __('Prix visé') }} :</span> {{ number_format((float) $quote->target_price, 0, ',', ' ') }} FCFA</p>
        @endif
        @if($quote->message)
            <p><span class="text-navy-300">{{ __('Votre message') }} :</span> {{ $quote->message }}</p>
        @endif
    </section>

    @if($errors->has('quote'))
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first('quote') }}</div>
    @endif

    @if($st === 'pending')
        <div class="card p-6 text-sm text-navy-500">
            {{ __("En attente de la réponse du fournisseur (jusqu'au :date).", ['date' => $quote->expires_at?->format('d/m/Y')]) }}
        </div>
    @elseif(in_array($st, ['quoted', 'accepted', 'rejected'], true) || ($st === 'expired' && $quote->quoted_at))
        <section class="card mb-6 p-6">
            <h2 class="mb-3 text-base font-semibold text-ink">{{ __('Devis du fournisseur') }}</h2>
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-navy-500">{{ __('Prix unitaire') }}</dt><dd>{{ number_format((float) $quote->quoted_unit_price, 0, ',', ' ') }} FCFA</dd></div>
                <div class="flex justify-between"><dt class="text-navy-500">{{ __('Quantité proposée') }}</dt><dd>{{ $quote->quoted_quantity }} {{ $quote->unit }}</dd></div>
                <div class="flex justify-between"><dt class="text-navy-500">{{ __('Sous-total') }}</dt><dd>{{ number_format($quote->quotedSubtotal(), 0, ',', ' ') }} FCFA</dd></div>
                <div class="flex justify-between"><dt class="text-navy-500">{{ __('Frais de livraison') }}</dt><dd>{{ number_format($quote->quotedShippingFee(), 0, ',', ' ') }} FCFA</dd></div>
                <div class="flex justify-between border-t border-line pt-2 font-semibold text-ink"><dt>{{ __('Total TTC') }}</dt><dd>{{ number_format($quote->quotedTotal(), 0, ',', ' ') }} FCFA</dd></div>
            </dl>
            @if($quote->vendor_note)
                <p class="mt-3 text-sm text-navy-500"><span class="text-navy-300">{{ __('Note du fournisseur') }} :</span> {{ $quote->vendor_note }}</p>
            @endif
            @if($st === 'quoted')
                <p class="mt-3 text-xs text-navy-300">{{ __("Valable jusqu'au :date.", ['date' => $quote->expires_at?->format('d/m/Y')]) }}</p>
            @endif
        </section>

        @if($st === 'quoted')
            @php
                $showNewAddressForm = $addresses->isEmpty()
                    || old('address_id') === ''
                    || $errors->has('shipping_address') || $errors->has('shipping_city') || $errors->has('shipping_phone');
            @endphp
            <form action="{{ route('quotes.accept', $quote) }}" method="POST" class="card mb-4 space-y-4 p-6">
                @csrf
                <input type="hidden" name="quoted_total" value="{{ $quote->quotedTotal() }}">
                <h2 class="text-base font-semibold text-ink">{{ __('Accepter ce devis') }}</h2>
                <p class="text-sm text-navy-500">{{ __("Une commande sera créée au prix du devis ; vous la réglerez ensuite par Mobile Money ou carte.") }}</p>

                @if($addresses->isNotEmpty())
                    <div class="space-y-2">
                        @foreach($addresses as $address)
                            <label class="flex cursor-pointer items-start gap-2 rounded-md border border-line p-3 text-sm has-[:checked]:border-trade-green has-[:checked]:bg-trade-greenLight">
                                <input type="radio" name="address_id" value="{{ $address->id }}" class="mt-1" onchange="document.getElementById('quote-new-address-fields').classList.add('hidden')" {{ ! $showNewAddressForm && old('address_id', $address->is_default ? $address->id : '') == $address->id ? 'checked' : '' }}>
                                <span>
                                    <span class="font-medium text-ink">{{ $address->label }}</span>
                                    @if($address->is_default)<span class="ml-1 text-xs text-trade-green">{{ __('(par défaut)') }}</span>@endif
                                    <br>{{ $address->address }}, {{ $address->city }}, {{ $address->country }} — {{ $address->phone }}
                                </span>
                            </label>
                        @endforeach
                        <label class="flex cursor-pointer items-center gap-2 rounded-md border border-line p-3 text-sm">
                            <input type="radio" name="address_id" value="" onchange="document.getElementById('quote-new-address-fields').classList.remove('hidden')" {{ $showNewAddressForm ? 'checked' : '' }}>
                            {{ __('Utiliser une nouvelle adresse') }}
                        </label>
                    </div>
                @endif

                <div id="quote-new-address-fields" class="space-y-4 {{ $showNewAddressForm ? '' : 'hidden' }}">
                    <div>
                        <label class="field-label" for="shipping_address">{{ __('Adresse de livraison') }}</label>
                        <input type="text" id="shipping_address" name="shipping_address" value="{{ old('shipping_address') }}" class="field-input" maxlength="255">
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label class="field-label" for="shipping_city">{{ __('Ville') }}</label>
                            <input type="text" id="shipping_city" name="shipping_city" value="{{ old('shipping_city') }}" class="field-input" maxlength="120">
                        </div>
                        <div>
                            <label class="field-label" for="shipping_country">{{ __('Pays') }}</label>
                            <input type="text" id="shipping_country" name="shipping_country" value="{{ old('shipping_country', 'Bénin') }}" class="field-input" maxlength="120">
                        </div>
                        <div>
                            <label class="field-label" for="shipping_phone">{{ __('Téléphone') }}</label>
                            <input type="text" id="shipping_phone" name="shipping_phone" value="{{ old('shipping_phone') }}" class="field-input" maxlength="30">
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-ink">
                        <input type="checkbox" name="save_address" value="1" {{ old('save_address') ? 'checked' : '' }}>
                        {{ __('Enregistrer cette adresse dans mon carnet') }}
                    </label>
                    <div>
                        <label class="field-label">{{ __("Nom de l'adresse (ex. Entrepôt, Bureau)") }}</label>
                        <input type="text" name="address_label" maxlength="60" value="{{ old('address_label') }}" class="field-input">
                    </div>
                </div>

                <button type="submit" class="btn-primary">{{ __('Accepter et payer') }}</button>
            </form>
        @endif

        @if($st === 'accepted' && $quote->order)
            <a href="{{ route('orders.show', $quote->order) }}" class="btn-primary inline-block">{{ __('Voir la commande :ref', ['ref' => $quote->order->reference]) }}</a>
        @endif
    @elseif($st === 'declined')
        <div class="card p-6 text-sm text-navy-500">
            {{ __('Le fournisseur ne peut pas donner suite à cette demande.') }}
            @if($quote->decline_reason)<br>{{ __('Motif :') }} {{ $quote->decline_reason }}@endif
        </div>
    @elseif($st === 'expired')
        <div class="card p-6 text-sm text-navy-500">{{ __('Cette demande a expiré. Vous pouvez en faire une nouvelle depuis la fiche produit.') }}</div>
    @endif

    @if(in_array($st, ['pending', 'quoted'], true))
        <form action="{{ route('quotes.cancel', $quote) }}" method="POST" class="mt-4" onsubmit="return confirm({{ Illuminate\Support\Js::from($st === 'quoted' ? __('Refuser ce devis ?') : __('Annuler cette demande ?')) }});">
            @csrf
            <button type="submit" class="text-sm text-red-600 hover:underline">{{ $st === 'quoted' ? __('Refuser ce devis') : __('Annuler la demande') }}</button>
        </form>
    @endif

    <p class="mt-8"><a href="{{ route('quotes.index') }}" class="text-sm text-navy-500 hover:underline">← {{ __('Mes devis') }}</a></p>
</div>
@endsection
