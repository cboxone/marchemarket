@extends('layouts.app')

@section('title', __('Litiges'))

@section('content')
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    <h1 class="mb-6 text-xl font-semibold text-ink">{{ __('Litiges') }}</h1>

    <form action="{{ route('vendor.disputes.index') }}" method="GET" class="card mb-6 flex items-end gap-4 p-4">
        <div>
            <label class="field-label">{{ __('Statut') }}</label>
            <select name="status" class="field-input">
                <option value="">{{ __('Tous') }}</option>
                <option value="open" @selected($status === 'open')>{{ __('En attente de ma réponse') }}</option>
                <option value="escalated" @selected($status === 'escalated')>{{ __("En cours d'arbitrage") }}</option>
                <option value="resolved" @selected($status === 'resolved')>{{ __('Résolus') }}</option>
            </select>
        </div>
        <button type="submit" class="btn-dark">{{ __('Filtrer') }}</button>
    </form>

    @if($disputes->isEmpty())
        <div class="card p-10 text-center text-navy-500">{{ __('Aucun litige.') }}</div>
    @else
        <div class="overflow-x-auto rounded-lg border border-line bg-white">
            <table class="w-full text-sm">
                <thead class="bg-navy-50 text-left text-navy-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Référence') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Commande') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Acheteur') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Motif') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Statut') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach($disputes as $dispute)
                        @php
    $cls = match (true) {
        $dispute->status === 'open' => 'rounded-full bg-amber-50 px-2.5 py-1 text-xs text-amber-700',
        $dispute->status === 'escalated' => 'rounded-full bg-red-50 px-2.5 py-1 text-xs text-red-700',
        $dispute->status === 'withdrawn' => 'rounded-full bg-navy-50 px-2.5 py-1 text-xs text-navy-500',
        $dispute->decision === 'reject' => 'rounded-full bg-navy-50 px-2.5 py-1 text-xs text-navy-500',
        default => 'badge-trade',
    };
@endphp
                        <tr>
                            <td class="px-4 py-3 font-medium text-ink">{{ $dispute->reference }}</td>
                            <td class="px-4 py-3 text-navy-500">{{ $dispute->order->reference }}</td>
                            <td class="px-4 py-3 text-navy-500">{{ $dispute->buyer->company_name ?: $dispute->buyer->name }}</td>
                            <td class="px-4 py-3 text-navy-500">{{ $dispute->reasonLabel() }}</td>
                            <td class="px-4 py-3"><span class="{{ $cls }}">{{ $dispute->statusLabel() }}</span></td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('vendor.disputes.show', $dispute) }}" class="text-navy-700 hover:underline">{{ __('Ouvrir') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $disputes->links() }}</div>
    @endif
</div>
@endsection
