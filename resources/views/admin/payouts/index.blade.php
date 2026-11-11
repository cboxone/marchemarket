@extends('layouts.app')

@section('title', __('Paiements aux vendeurs'))

@section('content')
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    <h1 class="mb-6 text-xl font-semibold text-ink">{{ __('Demandes de reversement') }}</h1>

    <x-csv-export-bar route="admin.payouts.export" />

    <div class="overflow-hidden rounded-lg border border-line bg-white">
        <table class="w-full text-sm">
            <thead class="bg-navy-50 text-left text-navy-500">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Vendeur') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Montant') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Moyen de paiement') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Statut') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach($payouts as $payout)
                    <tr>
                        <td class="px-4 py-3 text-navy-500">{{ $payout->created_at->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-ink">{{ $payout->vendor->store_name }}</td>
                        <td class="px-4 py-3 font-medium text-ink">{{ number_format((float) $payout->amount, 0, ',', ' ') }} FCFA</td>
                        <td class="px-4 py-3 text-navy-500">{{ $payout->payout_method }}</td>
                        <td class="px-4 py-3"><span class="badge-trade">{{ $payout->statusLabel() }}</span></td>
                        <td class="px-4 py-3 text-right">
                            @if($payout->status === 'pending')
                                <div class="flex justify-end gap-3">
                                    <form action="{{ route('admin.payouts.mark-paid', $payout) }}" method="POST" class="flex items-center gap-1">
                                        @csrf @method('PATCH')
                                        <input type="text" name="payout_reference" placeholder="{{ __('Référence') }}" class="field-input w-28 py-1 text-xs">
                                        <button type="submit" class="text-navy-700 hover:underline">{{ __('Marquer payé') }}</button>
                                    </form>
                                    <form action="{{ route('admin.payouts.reject', $payout) }}" method="POST" onsubmit="return confirm({{ Illuminate\Support\Js::from(__('Rejeter cette demande ?')) }});">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="text-red-600 hover:underline">{{ __('Rejeter') }}</button>
                                    </form>
                                </div>
                            @elseif($payout->payout_reference)
                                <span class="text-xs text-navy-300">{{ __('Réf.') }} {{ $payout->payout_reference }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $payouts->links() }}</div>
</div>
@endsection
