@extends('layouts.app')

@section('title', __('Mes devis'))

@section('content')
<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
    <h1 class="mb-6 text-xl font-semibold text-ink">{{ __('Mes devis') }}</h1>

    @if($quotes->isEmpty())
        <div class="card p-10 text-center text-navy-500">{{ __("Vous n'avez pas encore demandé de devis. Ouvrez une fiche produit et cliquez sur « Demander un devis ».") }}</div>
    @else
        <div class="overflow-x-auto rounded-lg border border-line bg-white">
            <table class="w-full text-sm">
                <thead class="bg-navy-50 text-left text-navy-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Référence') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Produit') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Fournisseur') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Quantité') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Statut') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach($quotes as $quote)
                        @php
    $st = $quote->effectiveStatus();
    $cls = match ($st) {
        'quoted', 'accepted' => 'badge-trade',
        'pending' => 'rounded-full bg-amber-50 px-2.5 py-1 text-xs text-amber-700',
        default => 'rounded-full bg-navy-50 px-2.5 py-1 text-xs text-navy-500',
    };
@endphp
                        <tr>
                            <td class="px-4 py-3 font-medium text-ink">{{ $quote->reference }}</td>
                            <td class="px-4 py-3 text-navy-500">{{ $quote->product_name }}</td>
                            <td class="px-4 py-3 text-navy-500">{{ $quote->vendor->store_name }}</td>
                            <td class="px-4 py-3 text-navy-500">{{ $quote->quantity }} {{ $quote->unit }}</td>
                            <td class="px-4 py-3"><span class="{{ $cls }}">{{ $quote->statusLabel() }}</span></td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('quotes.show', $quote) }}" class="text-navy-700 hover:underline">{{ __('Détails') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $quotes->links() }}</div>
    @endif
</div>
@endsection
