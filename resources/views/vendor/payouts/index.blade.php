@extends('layouts.app')

@section('title', __('Mes paiements'))

@section('content')
<div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
    <h1 class="mb-6 text-xl font-semibold text-ink">{{ __('Mes paiements') }}</h1>

    <x-csv-export-bar route="vendor.payouts.export" />

    <div class="card mb-6 flex flex-wrap items-center justify-between gap-4 p-5">
        <div>
            <p class="text-xs text-navy-300">{{ __('Solde disponible pour retrait') }}</p>
            <p class="font-display text-2xl font-semibold text-navy-800">{{ number_format($availableBalance, 0, ',', ' ') }} FCFA</p>
            @if($breakdown['commission'] > 0)
                <p class="mt-1 text-xs text-navy-300">{{ __('Encaissé : :gross FCFA − commission plateforme : :commission FCFA', ['gross' => number_format($breakdown['gross'], 0, ',', ' '), 'commission' => number_format($breakdown['commission'], 0, ',', ' ')]) }}</p>
            @endif
        </div>
    </div>

    <form action="{{ route('vendor.payouts.store') }}" method="POST" class="card mb-8 grid grid-cols-1 gap-4 p-5 sm:grid-cols-3">
        @csrf
        <div>
            <label class="field-label">{{ __('Montant à retirer (FCFA)') }}</label>
            <input type="number" step="0.01" name="amount" max="{{ $availableBalance }}" class="field-input" required>
        </div>
        <div>
            <label class="field-label">{{ __('Compte de retrait') }}</label>
            @if($vendor->hasPayoutAccount())
                <p class="field-input bg-navy-50">{{ $vendor->payoutAccountLabel() }}</p>
                <a href="{{ route('vendor.shop.edit') }}#retrait" class="mt-1 inline-block text-xs text-trade-green hover:underline">{{ __('Modifier') }}</a>
            @else
                <p class="text-sm text-red-600">
                    {{ __('Aucun compte de retrait enregistré.') }}
                    <a href="{{ route('vendor.shop.edit') }}#retrait" class="underline">{{ __('Ajouter dans Ma boutique') }}</a>
                </p>
            @endif
        </div>
        <div class="flex items-end">
            <button type="submit" class="btn-primary w-full" {{ $availableBalance <= 0 || ! $vendor->hasPayoutAccount() ? 'disabled' : '' }}>{{ __('Demander un retrait') }}</button>
        </div>
    </form>

    <h2 class="mb-3 text-lg font-semibold text-ink">{{ __('Historique des demandes') }}</h2>
    @if($payouts->isEmpty())
        <div class="card p-8 text-center text-navy-500">{{ __('Aucune demande de retrait pour le moment.') }}</div>
    @else
        <div class="overflow-hidden rounded-lg border border-line bg-white">
            <table class="w-full text-sm">
                <thead class="bg-navy-50 text-left text-navy-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Date') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Montant') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Moyen de paiement') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Statut') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach($payouts as $payout)
                        <tr>
                            <td class="px-4 py-3 text-navy-500">{{ $payout->created_at->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 font-medium text-ink">{{ number_format((float) $payout->amount, 0, ',', ' ') }} FCFA</td>
                            <td class="px-4 py-3 text-navy-500">{{ $payout->payout_method }}</td>
                            <td class="px-4 py-3"><span class="badge-trade">{{ $payout->statusLabel() }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $payouts->links() }}</div>
    @endif
</div>
@endsection
