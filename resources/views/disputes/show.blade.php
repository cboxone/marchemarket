@extends('layouts.app')

@section('title', __('Litige :ref', ['ref' => $dispute->reference]))

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
    @php
    $cls = match (true) {
        $dispute->status === 'open' => 'rounded-full bg-amber-50 px-2.5 py-1 text-xs text-amber-700',
        $dispute->status === 'escalated' => 'rounded-full bg-red-50 px-2.5 py-1 text-xs text-red-700',
        $dispute->status === 'withdrawn' => 'rounded-full bg-navy-50 px-2.5 py-1 text-xs text-navy-500',
        $dispute->decision === 'reject' => 'rounded-full bg-navy-50 px-2.5 py-1 text-xs text-navy-500',
        default => 'badge-trade',
    };
@endphp
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-ink">{{ __('Litige :ref', ['ref' => $dispute->reference]) }}</h1>
        <span class="{{ $cls }}">{{ $dispute->statusLabel() }}</span>
    </div>

    @if($errors->has('dispute'))
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first('dispute') }}</div>
    @endif

    <section class="card mb-6 space-y-2 p-6 text-sm">
        <p><span class="text-navy-300">{{ __('Commande') }} :</span> <a href="{{ route('orders.show', $dispute->order) }}" class="text-trade-green hover:underline">{{ $dispute->order->reference }}</a></p>
        <p><span class="text-navy-300">{{ __('Fournisseur') }} :</span> {{ $dispute->vendor->store_name }}</p>
        <p><span class="text-navy-300">{{ __('Motif') }} :</span> {{ $dispute->reasonLabel() }}</p>
        <p><span class="text-navy-300">{{ __('Description') }} :</span> {{ $dispute->description }}</p>
        @if($dispute->evidence_paths)
            <div class="flex flex-wrap gap-2 pt-1">
                @foreach($dispute->evidence_paths as $i => $path)
                    <a href="{{ route('disputes.evidence', [$dispute, $i]) }}" target="_blank" rel="noopener"><img src="{{ route('disputes.evidence', [$dispute, $i]) }}" alt="" class="h-20 w-20 rounded-md border border-line object-cover"></a>
                @endforeach
            </div>
        @endif
    </section>

    @if($dispute->status === 'open')
        <div class="card p-6 text-sm text-navy-500">
            {{ __('En attente de la réponse du fournisseur (avant le :date).', ['date' => $dispute->vendor_deadline_at->format('d/m/Y H:i')]) }}
        </div>
    @elseif($dispute->status === 'escalated')
        <div class="card p-6 text-sm text-navy-500">
            @if($dispute->vendor_response_note)
                <p class="mb-2"><span class="text-navy-300">{{ __('Réponse du fournisseur') }} :</span> {{ $dispute->vendor_response_note }}</p>
            @endif
            {{ __('Ce dossier est en cours d\'examen par notre équipe.') }}
        </div>
    @elseif($dispute->status === 'resolved')
        <div class="card p-6 text-sm text-navy-500">
            @if($dispute->decision === 'reject')
                <p>{{ __('Litige rejeté : aucun remboursement.') }}</p>
            @else
                <p>{{ __('Montant remboursé : :amount FCFA.', ['amount' => number_format((float) $dispute->refund_amount, 0, ',', ' ')]) }}</p>
                <p class="mt-1 text-xs text-navy-300">
                    @if($dispute->refund_status === 'refunded_automatic' || $dispute->refund_status === 'refunded_manual')
                        {{ __('Remboursement effectué.') }}
                    @else
                        {{ __('Remboursement en cours de traitement.') }}
                    @endif
                </p>
            @endif
            @if($dispute->resolution_note)
                <p class="mt-2"><span class="text-navy-300">{{ __('Note') }} :</span> {{ $dispute->resolution_note }}</p>
            @endif
        </div>
    @elseif($dispute->status === 'withdrawn')
        <div class="card p-6 text-sm text-navy-500">{{ __('Vous avez retiré ce litige.') }}</div>
    @endif

    @if($dispute->status === 'open')
        <form action="{{ route('disputes.withdraw', $dispute) }}" method="POST" class="mt-4" onsubmit="return confirm({{ Illuminate\Support\Js::from(__('Retirer ce litige ?')) }});">
            @csrf
            <button type="submit" class="text-sm text-red-600 hover:underline">{{ __('Retirer le litige') }}</button>
        </form>
    @endif

    <p class="mt-8"><a href="{{ route('disputes.index') }}" class="text-sm text-navy-500 hover:underline">← {{ __('Mes litiges') }}</a></p>
</div>
@endsection
