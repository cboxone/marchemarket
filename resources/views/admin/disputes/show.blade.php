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
        <p><span class="text-navy-300">{{ __('Fournisseur') }} :</span> {{ $dispute->vendor->store_name }}
            <span class="text-xs text-navy-300">({{ __(':total litige(s) antérieur(s), :lost perdu(s)', ['total' => $vendorHistory, 'lost' => $vendorLossCount]) }})</span>
        </p>
        <p><span class="text-navy-300">{{ __('Acheteur') }} :</span> {{ $dispute->buyer->company_name ?: $dispute->buyer->name }} ({{ $dispute->buyer->email }})</p>
        <p><span class="text-navy-300">{{ __('Motif') }} :</span> {{ $dispute->reasonLabel() }}</p>
        <p><span class="text-navy-300">{{ __('Description') }} :</span> {{ $dispute->description }}</p>
        @if($dispute->evidence_paths)
            <div class="flex flex-wrap gap-2 pt-1">
                @foreach($dispute->evidence_paths as $i => $path)
                    <a href="{{ route('admin.disputes.evidence', [$dispute, $i]) }}" target="_blank" rel="noopener"><img src="{{ route('admin.disputes.evidence', [$dispute, $i]) }}" alt="" class="h-20 w-20 rounded-md border border-line object-cover"></a>
                @endforeach
            </div>
        @endif
        <p><span class="text-navy-300">{{ __('Montant maximal remboursable') }} :</span> {{ number_format($ceiling, 0, ',', ' ') }} FCFA</p>
        <p><span class="text-navy-300">{{ __('Ouvert le') }} :</span> {{ $dispute->created_at->format('d/m/Y H:i') }}</p>
    </section>

    @if($dispute->vendor_responded_at)
        <section class="card mb-6 p-6 text-sm">
            <h2 class="mb-2 font-semibold text-ink">{{ __('Réponse du fournisseur') }}</h2>
            <p>
                @switch($dispute->vendor_response_type)
                    @case('accept_full') {{ __('Acceptation intégrale') }} @break
                    @case('accept_partial') {{ __('Proposition partielle : :amount FCFA', ['amount' => number_format((float) $dispute->vendor_response_amount, 0, ',', ' ')]) }} @break
                    @default {{ __('Contestation') }}
                @endswitch
            </p>
            @if($dispute->vendor_response_note)
                <p class="mt-1 text-navy-500">{{ $dispute->vendor_response_note }}</p>
            @endif
        </section>
    @endif

    @if($dispute->isAwaitingAdmin())
        <form action="{{ route('admin.disputes.resolve', $dispute) }}" method="POST" class="card space-y-4 p-6">
            @csrf
            <h2 class="text-base font-semibold text-ink">{{ __('Trancher') }}</h2>

            <div class="space-y-2">
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="decision" value="refund_full" onchange="document.getElementById('admin-amount').classList.add('hidden')" checked>
                    {{ __('Remboursement intégral (:amount FCFA)', ['amount' => number_format($ceiling, 0, ',', ' ')]) }}
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="decision" value="refund_partial" onchange="document.getElementById('admin-amount').classList.remove('hidden')">
                    {{ __('Remboursement partiel') }}
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="decision" value="reject" onchange="document.getElementById('admin-amount').classList.add('hidden')">
                    {{ __('Rejeter (aucun remboursement)') }}
                </label>
            </div>

            <div id="admin-amount" class="hidden">
                <label class="field-label" for="amount">{{ __('Montant (FCFA, max :max)', ['max' => number_format($ceiling, 0, ',', ' ')]) }}</label>
                <input type="number" id="amount" name="amount" min="0.01" max="{{ $ceiling }}" step="any" class="field-input">
            </div>

            <div>
                <label class="field-label" for="note">{{ __('Motif de la décision (visible par les deux parties)') }}</label>
                <textarea id="note" name="note" rows="4" maxlength="2000" required class="field-input"></textarea>
            </div>

            <p class="text-xs text-navy-300">{{ __('Un remboursement intégral sur une commande à un seul fournisseur est tenté automatiquement via KkiaPay ; sinon, il est à traiter manuellement.') }}</p>
            <button type="submit" class="btn-primary" onclick="return confirm({{ Illuminate\Support\Js::from(__('Confirmer cette décision ? Elle est définitive.')) }})">{{ __('Valider la décision') }}</button>
        </form>
    @elseif($dispute->status === 'resolved')
        <div class="card p-6 text-sm text-navy-500">
            <p>
                @if($dispute->decision === 'reject')
                    {{ __('Rejeté.') }}
                @else
                    {{ __('Décision : :amount FCFA remboursés.', ['amount' => number_format((float) $dispute->refund_amount, 0, ',', ' ')]) }}
                @endif
                {{ __('Par :admin le :date.', ['admin' => $dispute->resolvedBy?->name ?? __('système'), 'date' => $dispute->resolved_at?->format('d/m/Y H:i')]) }}
            </p>
            @if($dispute->resolution_note)<p class="mt-1">{{ $dispute->resolution_note }}</p>@endif

            @if($dispute->refund_status === 'pending_manual')
                <form action="{{ route('admin.disputes.mark-refunded', $dispute) }}" method="POST" class="mt-4">
                    @csrf
                    <p class="mb-2 text-red-600">{{ __("Remboursement à traiter manuellement (commande multi-fournisseurs ou montant partiel : KkiaPay ne rembourse qu'une transaction entière).") }}</p>
                    <button type="submit" class="btn-secondary">{{ __('Marquer comme remboursé') }}</button>
                </form>
            @elseif($dispute->refund_status === 'refunded_automatic')
                <p class="mt-2 text-trade-green">{{ __('Remboursé automatiquement le :date.', ['date' => $dispute->refunded_at?->format('d/m/Y H:i')]) }}</p>
            @elseif($dispute->refund_status === 'refunded_manual')
                <p class="mt-2 text-trade-green">{{ __('Remboursement manuel confirmé le :date.', ['date' => $dispute->refunded_at?->format('d/m/Y H:i')]) }}</p>
            @endif
        </div>
    @endif

    <p class="mt-8"><a href="{{ route('admin.disputes.index') }}" class="text-sm text-navy-500 hover:underline">← {{ __('Litiges') }}</a></p>
</div>
@endsection
