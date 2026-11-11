@extends('layouts.app')

@section('title', __('Demander un devis'))

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8 sm:px-6">
    <h1 class="mb-1 text-xl font-semibold text-ink">{{ __('Demander un devis') }}</h1>
    <p class="mb-6 text-sm text-navy-500">
        {{ $product->name }} — {{ $product->vendor->store_name }}<br>
        {{ __('Prix catalogue : :price FCFA / :unit', ['price' => number_format((float) $product->base_price, 0, ',', ' '), 'unit' => $product->unit]) }}
    </p>

    <form action="{{ route('quotes.store', $product) }}" method="POST" class="card space-y-4 p-6">
        @csrf

        <div>
            <label class="field-label" for="quantity">{{ __('Quantité souhaitée (:unit)', ['unit' => $product->unit]) }}</label>
            <input type="number" id="quantity" name="quantity" min="1" max="1000000" value="{{ old('quantity', $product->min_order_qty) }}" class="field-input" required>
        </div>

        <div>
            <label class="field-label" for="target_price">{{ __('Prix unitaire visé (FCFA, facultatif)') }}</label>
            <input type="number" id="target_price" name="target_price" min="0" step="any" value="{{ old('target_price') }}" class="field-input">
        </div>

        <div>
            <label class="field-label" for="message">{{ __('Message au fournisseur (facultatif)') }}</label>
            <textarea id="message" name="message" rows="4" maxlength="2000" class="field-input" placeholder="{{ __('Délai souhaité, conditionnement, livraison récurrente…') }}">{{ old('message') }}</textarea>
        </div>

        <button type="submit" class="btn-primary">{{ __('Envoyer la demande') }}</button>
    </form>
</div>
@endsection
