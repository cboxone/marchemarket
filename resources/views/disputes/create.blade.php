@extends('layouts.app')

@section('title', __('Signaler un problème'))

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8 sm:px-6">
    <h1 class="mb-1 text-xl font-semibold text-ink">{{ __('Signaler un problème') }}</h1>
    <p class="mb-6 text-sm text-navy-500">{{ __('Commande') }} {{ $order->reference }}</p>

    <form action="{{ route('disputes.store', $order) }}" method="POST" enctype="multipart/form-data" class="card space-y-4 p-6">
        @csrf

        @if($vendors->count() > 1)
            <div>
                <label class="field-label" for="vendor_id">{{ __('Fournisseur concerné') }}</label>
                <select id="vendor_id" name="vendor_id" class="field-input" required>
                    @foreach($vendors as $vendor)
                        <option value="{{ $vendor->id }}" @selected(old('vendor_id') == $vendor->id)>{{ $vendor->store_name }}</option>
                    @endforeach
                </select>
            </div>
        @else
            <input type="hidden" name="vendor_id" value="{{ $vendors->first()->id }}">
            <p class="text-sm text-navy-500">{{ __('Fournisseur') }} : <strong>{{ $vendors->first()->store_name }}</strong></p>
        @endif

        <div>
            <label class="field-label" for="reason">{{ __('Motif') }}</label>
            <select id="reason" name="reason" class="field-input" required>
                <option value="not_received" @selected(old('reason') === 'not_received')>{{ __('Commande non reçue') }}</option>
                <option value="missing_qty" @selected(old('reason') === 'missing_qty')>{{ __('Quantité manquante') }}</option>
                <option value="not_conforming" @selected(old('reason') === 'not_conforming')>{{ __('Produit non conforme') }}</option>
                <option value="damaged" @selected(old('reason') === 'damaged')>{{ __('Produit endommagé') }}</option>
                <option value="other" @selected(old('reason') === 'other')>{{ __('Autre') }}</option>
            </select>
        </div>

        <div>
            <label class="field-label" for="description">{{ __('Description') }}</label>
            <textarea id="description" name="description" rows="5" maxlength="2000" required class="field-input" placeholder="{{ __('Décrivez précisément le problème rencontré.') }}">{{ old('description') }}</textarea>
        </div>

        <div>
            <label class="field-label" for="evidence">{{ __('Photos (jusqu\'à 5, requises pour « non conforme » et « endommagé »)') }}</label>
            <input type="file" id="evidence" name="evidence[]" accept="image/png,image/jpeg,image/webp" multiple class="field-input">
        </div>

        <p class="text-xs text-navy-300">{{ __('Le fournisseur a :hours heures pour répondre ; sans réponse, le dossier est transmis à notre équipe.', ['hours' => config('marketplace.disputes.vendor_response_hours')]) }}</p>

        <button type="submit" class="btn-primary">{{ __('Envoyer le litige') }}</button>
    </form>
</div>
@endsection
