@extends('layouts.app')

@section('title', __('Mes commandes'))

@section('content')
<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
    <h1 class="mb-6 text-xl font-semibold text-ink">{{ __('Mes commandes') }}</h1>

    @if($orders->isEmpty())
        <div class="card p-10 text-center text-navy-500">{{ __("Vous n'avez pas encore passé de commande.") }}</div>
    @else
        <div class="overflow-hidden rounded-lg border border-line bg-white">
            <table class="w-full text-sm">
                <thead class="bg-navy-50 text-left text-navy-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Référence') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Date') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Statut') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Total') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach($orders as $order)
                        <tr>
                            <td class="px-4 py-3 font-medium text-ink">{{ $order->reference }}</td>
                            <td class="px-4 py-3 text-navy-500">{{ $order->created_at->format('d/m/Y') }}</td>
                            <td class="px-4 py-3"><span class="badge-trade">{{ $order->statusLabel() }}</span></td>
                            <td class="px-4 py-3 text-ink">{{ number_format((float) $order->total_amount, 0, ',', ' ') }} FCFA</td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('orders.show', $order) }}" class="text-navy-700 hover:underline">{{ __('Détails') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
