@extends('layouts.app')

@section('title', __('Vérification de la boutique'))

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8 sm:px-6">
    <h1 class="mb-1 text-xl font-semibold text-ink">{{ __('Vérification de la boutique') }}</h1>
    <p class="mb-6 text-sm text-navy-500">{{ __("Un dossier approuvé affiche le badge « Vérifié » sur votre boutique et rassure les acheteurs.") }}</p>

    @if(session('status'))
        <div class="mb-6 rounded-md bg-trade-green/10 px-4 py-3 text-sm text-trade-green">{{ session('status') }}</div>
    @endif
    @if($errors->has('kyc'))
        <div class="mb-6 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first('kyc') }}</div>
    @endif

    @if($vendor->is_verified)
        <div class="card p-6 text-sm text-navy-600">
            <span class="badge-trade">{{ __('Vérifié') }}</span>
            <p class="mt-3">{{ __('Votre boutique est vérifiée.') }}</p>
            @if($vendor->kyc_reviewed_at)
                <p class="mt-1 text-xs text-navy-300">{{ __('Dossier approuvé le :date.', ['date' => $vendor->kyc_reviewed_at->format('d/m/Y')]) }}</p>
            @endif
        </div>
    @elseif($vendor->kyc_status === 'pending')
        <div class="card p-6 text-sm text-navy-600">
            <p>{{ __('Votre dossier est en cours d\'examen.') }}</p>
            <p class="mt-1 text-xs text-navy-300">{{ __('Envoyé le :date.', ['date' => $vendor->kyc_submitted_at?->format('d/m/Y H:i')]) }}</p>
        </div>
    @else
        @if($vendor->kyc_status === 'rejected')
            <div class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ __('Votre précédent dossier a été refusé.') }}
                @if($vendor->kyc_rejection_reason)
                    {{ __('Motif :') }} {{ $vendor->kyc_rejection_reason }}
                @endif
            </div>
        @endif

        <form action="{{ route('vendor.verification.store') }}" method="POST" enctype="multipart/form-data" class="card space-y-4 p-6">
            @csrf
            <div>
                <label class="field-label" for="tax_id">{{ __('Numéro IFU') }}</label>
                <input type="text" id="tax_id" name="tax_id" maxlength="60" value="{{ old('tax_id', $vendor->tax_id) }}" class="field-input" required>
            </div>
            <div>
                <label class="field-label" for="registration_number">{{ __('Numéro RCCM (facultatif)') }}</label>
                <input type="text" id="registration_number" name="registration_number" maxlength="60" value="{{ old('registration_number', $vendor->registration_number) }}" class="field-input">
            </div>
            <div>
                <label class="field-label" for="id_document">{{ __("Pièce d'identité du responsable (photo ou PDF)") }}</label>
                <input type="file" id="id_document" name="id_document" accept="image/png,image/jpeg,image/webp,application/pdf" required class="field-input">
            </div>
            <div>
                <label class="field-label" for="business_document">{{ __('Registre de commerce ou document professionnel (facultatif)') }}</label>
                <input type="file" id="business_document" name="business_document" accept="image/png,image/jpeg,image/webp,application/pdf" class="field-input">
            </div>
            <p class="text-xs text-navy-300">{{ __('Ces documents ne sont visibles que par notre équipe, jamais publiés sur votre boutique.') }}</p>
            <button type="submit" class="btn-primary">{{ __('Envoyer mon dossier') }}</button>
        </form>
    @endif
</div>
@endsection
