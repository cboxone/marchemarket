@extends('layouts.app')

@section('title', __('Virement — commande :ref', ['ref' => $order->reference]))

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
    <h1 class="mb-6 text-xl font-semibold text-ink">{{ __('Virement — commande :ref', ['ref' => $order->reference]) }}</h1>

    @if($errors->has('bank_transfer'))
        <div class="mb-6 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first('bank_transfer') }}</div>
    @endif

    <section class="card mb-6 space-y-2 p-6 text-sm">
        <p><span class="text-navy-300">{{ __('Acheteur') }} :</span> {{ $order->buyer->company_name ?: $order->buyer->name }} ({{ $order->buyer->email }})</p>
        <p><span class="text-navy-300">{{ __('Montant à retrouver sur le relevé') }} :</span> <strong>{{ number_format((float) $order->total_amount, 0, ',', ' ') }} FCFA</strong></p>
        <p><span class="text-navy-300">{{ __('Référence à chercher (motif du virement)') }} :</span> {{ $order->reference }}</p>
        @if($order->bank_transfer_reference)
            <p><span class="text-navy-300">{{ __("Référence donnée par l'acheteur") }} :</span> {{ $order->bank_transfer_reference }}</p>
        @endif
        <p><span class="text-navy-300">{{ __('Déposé le') }} :</span> {{ $order->bank_transfer_declared_at?->format('d/m/Y H:i') ?? __('pas encore déclaré') }}</p>
        @if($order->bank_transfer_proof_path)
            <div class="pt-2">
                @if(str_ends_with($order->bank_transfer_proof_path, '.pdf'))
                    <a href="{{ route('admin.bank-transfers.proof', $order) }}" target="_blank" rel="noopener" class="text-trade-green hover:underline">{{ __('Ouvrir la preuve (PDF)') }}</a>
                @else
                    <a href="{{ route('admin.bank-transfers.proof', $order) }}" target="_blank" rel="noopener">
                        <img src="{{ route('admin.bank-transfers.proof', $order) }}" alt="" class="max-h-96 rounded-md border border-line">
                    </a>
                @endif
            </div>
        @endif
    </section>

    @if($order->hasPendingBankTransferDeclaration())
        <div class="flex flex-wrap gap-3">
            <form action="{{ route('admin.bank-transfers.confirm', $order) }}" method="POST" onsubmit="return confirm({{ Illuminate\Support\Js::from(__('Confirmer que ce virement a bien été reçu ?')) }})">
                @csrf
                <button type="submit" class="btn-primary">{{ __('Confirmer la réception') }}</button>
            </form>
            <form action="{{ route('admin.bank-transfers.reject', $order) }}" method="POST" class="flex items-center gap-2">
                @csrf
                <input type="text" name="reason" required maxlength="255" placeholder="{{ __('Motif du rejet') }}" class="field-input w-64">
                <button type="submit" class="text-sm text-red-600 hover:underline">{{ __('Rejeter') }}</button>
            </form>
        </div>
    @else
        <div class="card p-6 text-sm text-navy-500">{{ __("Ce virement n'est plus en attente de vérification (déjà confirmé, rejeté, ou commande annulée).") }}</div>
    @endif

    <p class="mt-8"><a href="{{ route('admin.bank-transfers.index') }}" class="text-sm text-navy-500 hover:underline">← {{ __('Virements à vérifier') }}</a></p>
</div>
@endsection
