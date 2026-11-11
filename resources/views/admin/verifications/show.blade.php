@extends('layouts.app')

@section('title', __('Dossier — :store', ['store' => $vendor->store_name]))

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
    <h1 class="mb-6 text-xl font-semibold text-ink">{{ __('Dossier — :store', ['store' => $vendor->store_name]) }}</h1>

    @if($errors->has('kyc'))
        <div class="mb-6 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first('kyc') }}</div>
    @endif

    <section class="card mb-6 space-y-2 p-6 text-sm">
        <p><span class="text-navy-300">{{ __('Contact') }} :</span> {{ $vendor->user->name }} ({{ $vendor->user->email }})</p>
        <p><span class="text-navy-300">{{ __('Ville') }} :</span> {{ $vendor->city }}, {{ $vendor->country }}</p>
        <p><span class="text-navy-300">{{ __('Numéro IFU') }} :</span> {{ $vendor->tax_id }}</p>
        <p><span class="text-navy-300">{{ __('Numéro RCCM') }} :</span> {{ $vendor->registration_number ?: '—' }}</p>
        <p><span class="text-navy-300">{{ __('Statut') }} :</span> {{ __(ucfirst($vendor->kyc_status)) }}</p>
        <p><span class="text-navy-300">{{ __('Envoyé le') }} :</span> {{ $vendor->kyc_submitted_at?->format('d/m/Y H:i') ?? '—' }}</p>
        @if($vendor->kyc_reviewed_at)
            <p><span class="text-navy-300">{{ __('Examiné le') }} :</span> {{ $vendor->kyc_reviewed_at->format('d/m/Y H:i') }}</p>
        @endif
    </section>

    <section class="card mb-6 space-y-4 p-6">
        <div>
            <h2 class="mb-2 text-sm font-semibold text-ink">{{ __("Pièce d'identité") }}</h2>
            @if(str_ends_with($vendor->kyc_id_document_path ?? '', '.pdf'))
                <a href="{{ route('admin.verifications.document', [$vendor, 'id']) }}" target="_blank" rel="noopener" class="text-trade-green hover:underline">{{ __('Ouvrir le document (PDF)') }}</a>
            @elseif($vendor->kyc_id_document_path)
                <a href="{{ route('admin.verifications.document', [$vendor, 'id']) }}" target="_blank" rel="noopener">
                    <img src="{{ route('admin.verifications.document', [$vendor, 'id']) }}" alt="" class="max-h-80 rounded-md border border-line">
                </a>
            @endif
        </div>
        @if($vendor->kyc_business_document_path)
            <div>
                <h2 class="mb-2 text-sm font-semibold text-ink">{{ __('Document professionnel') }}</h2>
                @if(str_ends_with($vendor->kyc_business_document_path, '.pdf'))
                    <a href="{{ route('admin.verifications.document', [$vendor, 'business']) }}" target="_blank" rel="noopener" class="text-trade-green hover:underline">{{ __('Ouvrir le document (PDF)') }}</a>
                @else
                    <a href="{{ route('admin.verifications.document', [$vendor, 'business']) }}" target="_blank" rel="noopener">
                        <img src="{{ route('admin.verifications.document', [$vendor, 'business']) }}" alt="" class="max-h-80 rounded-md border border-line">
                    </a>
                @endif
            </div>
        @endif
    </section>

    @if($vendor->kyc_status === 'pending')
        <div class="flex flex-wrap gap-3">
            <form action="{{ route('admin.verifications.approve', $vendor) }}" method="POST" onsubmit="return confirm({{ Illuminate\Support\Js::from(__('Approuver ce dossier et vérifier la boutique ?')) }})">
                @csrf
                <button type="submit" class="btn-primary">{{ __('Approuver') }}</button>
            </form>
            <form action="{{ route('admin.verifications.reject', $vendor) }}" method="POST" class="flex items-center gap-2">
                @csrf
                <input type="text" name="reason" required maxlength="255" placeholder="{{ __('Motif du refus') }}" class="field-input w-64">
                <button type="submit" class="text-sm text-red-600 hover:underline">{{ __('Refuser') }}</button>
            </form>
        </div>
    @else
        <div class="card p-6 text-sm text-navy-500">{{ __('Ce dossier a déjà été traité.') }}</div>
    @endif

    <p class="mt-8"><a href="{{ route('admin.verifications.index') }}" class="text-sm text-navy-500 hover:underline">← {{ __('Vérifications KYC') }}</a></p>
</div>
@endsection
