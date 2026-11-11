@extends('layouts.app')

@section('title', __('Passer commande'))

@section('content')
<div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
    <h1 class="mb-6 text-xl font-semibold text-ink">{{ __('Finaliser la commande') }}</h1>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <form action="{{ route('checkout.store') }}" method="POST" class="card space-y-4 p-5 lg:col-span-2">
            @csrf
            <h2 class="font-semibold text-ink">{{ __('Adresse de livraison') }}</h2>

            @php
                // Le formulaire s'affiche « nouvelle adresse » ouvert par défaut s'il
                // n'y a rien d'enregistré, si l'acheteur l'a explicitement choisi, ou
                // si la requête précédente a échoué sur ces champs.
                $showNewAddressForm = $addresses->isEmpty()
                    || old('address_id') === ''
                    || $errors->has('shipping_address') || $errors->has('shipping_city') || $errors->has('shipping_phone');
            @endphp

            @if($addresses->isNotEmpty())
                <div class="space-y-2">
                    @foreach($addresses as $address)
                        <label class="flex cursor-pointer items-start gap-2 rounded-md border border-line p-3 text-sm has-[:checked]:border-trade-green has-[:checked]:bg-trade-greenLight">
                            <input type="radio" name="address_id" value="{{ $address->id }}" class="mt-1" onchange="document.getElementById('new-address-fields').classList.add('hidden')" {{ ! $showNewAddressForm && old('address_id', $address->is_default ? $address->id : '') == $address->id ? 'checked' : '' }}>
                            <span>
                                <span class="font-medium text-ink">{{ $address->label }}</span>
                                @if($address->is_default)<span class="ml-1 text-xs text-trade-green">{{ __('(par défaut)') }}</span>@endif
                                <br>{{ $address->address }}, {{ $address->city }}, {{ $address->country }} — {{ $address->phone }}
                            </span>
                        </label>
                    @endforeach
                    <label class="flex cursor-pointer items-center gap-2 rounded-md border border-line p-3 text-sm">
                        <input type="radio" name="address_id" value="" onchange="document.getElementById('new-address-fields').classList.remove('hidden')" {{ $showNewAddressForm ? 'checked' : '' }}>
                        {{ __('Utiliser une nouvelle adresse') }}
                    </label>
                </div>
            @endif

            <div id="new-address-fields" class="space-y-4 {{ $showNewAddressForm ? '' : 'hidden' }}">
                <div>
                    <label class="field-label">{{ __('Adresse') }}</label>
                    <input type="text" name="shipping_address" value="{{ old('shipping_address') }}" class="field-input">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="field-label">{{ __('Ville') }}</label>
                        <input type="text" name="shipping_city" value="{{ old('shipping_city') }}" class="field-input">
                    </div>
                    <div>
                        <label class="field-label">{{ __('Pays') }}</label>
                        <input type="text" name="shipping_country" value="{{ old('shipping_country', 'Bénin') }}" class="field-input">
                    </div>
                </div>
                <div>
                    <label class="field-label">{{ __('Téléphone') }}</label>
                    <input type="text" name="shipping_phone" value="{{ old('shipping_phone') }}" class="field-input">
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

            <button type="submit" class="btn-primary w-full">{{ __('Confirmer la commande') }}</button>
        </form>

        <div class="card h-fit space-y-3 p-5">
            <h2 class="font-semibold text-ink">{{ __('Récapitulatif') }}</h2>
            @foreach($cart->items as $item)
                <div class="flex justify-between text-sm text-navy-500">
                    <span>{{ $item->product->name }} × {{ $item->quantity }}</span>
                    <span class="text-ink">{{ number_format($item->subtotal(), 0, ',', ' ') }} FCFA</span>
                </div>
            @endforeach

            {{-- Code promo --}}
            <div class="border-t border-line pt-3">
                @if($coupon)
                    <div class="flex items-center justify-between rounded-md bg-trade-greenLight px-3 py-2 text-sm text-trade-green">
                        <span>{{ __('Code') }} <strong>{{ $coupon->code }}</strong> (-{{ $coupon->discount_percent }}%)</span>
                        <form action="{{ route('checkout.remove-coupon') }}" method="POST">
                            @csrf @method('DELETE')
                            <button type="submit" class="underline">{{ __('Retirer') }}</button>
                        </form>
                    </div>
                @else
                    <form action="{{ route('checkout.apply-coupon') }}" method="POST" class="flex gap-2">
                        @csrf
                        <input type="text" name="code" placeholder="{{ __('Code promo') }}" class="field-input text-sm uppercase">
                        <button type="submit" class="btn-secondary shrink-0 px-3 text-xs">{{ __('Appliquer') }}</button>
                    </form>
                @endif
            </div>

            @if($coupon)
                <div class="flex justify-between text-sm text-trade-green">
                    <span>{{ __('Réduction') }}</span>
                    <span>−{{ number_format($discount, 0, ',', ' ') }} FCFA</span>
                </div>
            @endif

            <div class="flex justify-between border-t border-line pt-3 text-sm text-navy-500">
                <span>{{ __('Frais de livraison') }}</span>
                <span class="text-ink">{{ number_format($shippingFee, 0, ',', ' ') }} FCFA</span>
            </div>

            @php $grandTotal = max(0, $cart->total() - $discount) + $shippingFee; @endphp
            <div class="border-t border-line pt-3 text-right font-semibold text-ink">
                {{ __('Total') }} : {{ number_format($grandTotal, 0, ',', ' ') }} FCFA
            </div>
            @php $tva = round($grandTotal * \App\Models\Order::TVA_RATE / (100 + \App\Models\Order::TVA_RATE), 2); @endphp
            <p class="text-right text-xs text-navy-300">{{ __('dont TVA (:rate%) incluse', ['rate' => \App\Models\Order::TVA_RATE]) }} : {{ number_format($tva, 0, ',', ' ') }} FCFA</p>
        </div>
    </div>
</div>
@endsection
