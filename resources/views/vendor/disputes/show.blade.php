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

    @if($errors->any())
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
        </div>
    @endif

    <section class="card mb-6 space-y-2 p-6 text-sm">
        <p><span class="text-navy-300">{{ __('Commande') }} :</span> {{ $dispute->order->reference }}</p>
        <p><span class="text-navy-300">{{ __('Acheteur') }} :</span> {{ $dispute->buyer->company_name ?: $dispute->buyer->name }}</p>
        <p><span class="text-navy-300">{{ __('Motif') }} :</span> {{ $dispute->reasonLabel() }}</p>
        <p><span class="text-navy-300">{{ __('Description') }} :</span> {{ $dispute->description }}</p>
        @if($dispute->evidence_paths)
            <div class="flex flex-wrap gap-2 pt-1">
                @foreach($dispute->evidence_paths as $i => $path)
                    <a href="{{ route('vendor.disputes.evidence', [$dispute, $i]) }}" target="_blank" rel="noopener"><img src="{{ route('vendor.disputes.evidence', [$dispute, $i]) }}" alt="" class="h-20 w-20 rounded-md border border-line object-cover"></a>
                @endforeach
            </div>
        @endif
        <p><span class="text-navy-300">{{ __('Montant maximal engagé') }} :</span> {{ number_format($ceiling, 0, ',', ' ') }} FCFA</p>
    </section>

    @if($dispute->status === 'open')
        <form action="{{ route('vendor.disputes.respond', $dispute) }}" method="POST" class="card space-y-4 p-6">
            @csrf
            <h2 class="text-base font-semibold text-ink">{{ __('Répondre') }}</h2>
            <p class="text-xs text-navy-300">{{ __('À répondre avant le :date.', ['date' => $dispute->vendor_deadline_at->format('d/m/Y H:i')]) }}</p>

            <div class="space-y-2">
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="response_type" value="accept_full" onchange="document.getElementById('partial-amount').classList.add('hidden')" {{ old('response_type', 'accept_full') === 'accept_full' ? 'checked' : '' }}>
                    {{ __('Accepter le remboursement intégral (:amount FCFA)', ['amount' => number_format($ceiling, 0, ',', ' ')]) }}
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="response_type" value="accept_partial" onchange="document.getElementById('partial-amount').classList.remove('hidden')" {{ old('response_type') === 'accept_partial' ? 'checked' : '' }}>
                    {{ __('Proposer un remboursement partiel') }}
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="response_type" value="contest" onchange="document.getElementById('partial-amount').classList.add('hidden')" {{ old('response_type') === 'contest' ? 'checked' : '' }}>
                    {{ __('Contester ce litige') }}
                </label>
            </div>

            <div id="partial-amount" class="{{ old('response_type') === 'accept_partial' ? '' : 'hidden' }}">
                <label class="field-label" for="amount">{{ __('Montant proposé (FCFA, max :max)', ['max' => number_format($ceiling, 0, ',', ' ')]) }}</label>
                <input type="number" id="amount" name="amount" min="1" max="{{ $ceiling }}" step="any" value="{{ old('amount') }}" class="field-input">
            </div>

            <div>
                <label class="field-label" for="note">{{ __('Note (obligatoire si vous contestez)') }}</label>
                <textarea id="note" name="note" rows="4" maxlength="2000" class="field-input">{{ old('note') }}</textarea>
            </div>

            <p class="text-xs text-navy-300">{{ __('Une proposition partielle ou une contestation est transmise à notre équipe, qui tranchera.') }}</p>
            <button type="submit" class="btn-primary">{{ __('Envoyer ma réponse') }}</button>
        </form>
    @elseif($dispute->status === 'escalated')
        <div class="card p-6 text-sm text-navy-500">{{ __("Ce dossier est en cours d'arbitrage par notre équipe. Vous serez informé de la décision.") }}</div>
    @elseif($dispute->status === 'resolved')
        <div class="card p-6 text-sm text-navy-500">
            @if($dispute->decision === 'reject')
                {{ __('Litige rejeté : aucun remboursement.') }}
            @else
                {{ __('Décision : :amount FCFA remboursés à l\'acheteur.', ['amount' => number_format((float) $dispute->refund_amount, 0, ',', ' ')]) }}
            @endif
            @if($dispute->resolution_note)<p class="mt-2">{{ $dispute->resolution_note }}</p>@endif
        </div>
    @endif

    <p class="mt-8"><a href="{{ route('vendor.disputes.index') }}" class="text-sm text-navy-500 hover:underline">← {{ __('Litiges') }}</a></p>
</div>
@endsection
