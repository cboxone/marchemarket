@extends('layouts.app')

@section('title', __('Supervision des commandes'))

@section('content')
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    <x-breadcrumb :items="[
        ['label' => __('Tableau de bord'), 'url' => route('admin.dashboard')],
        ['label' => __('Supervision des commandes'), 'url' => null],
    ]" />

    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-ink">{{ __('Commandes') }}</h1>
        <a href="{{ route('admin.dashboard') }}" class="btn-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" class="mr-1 h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 3a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
            </svg>
            {{ __('Retour') }}
        </a>
    </div>

    <x-csv-export-bar route="admin.orders.export" />

    <div class="overflow-hidden rounded-lg border border-line bg-white">
        <table class="w-full text-sm">
            <thead class="bg-navy-50 text-left text-navy-500">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ __('Référence') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Acheteur') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Total') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Statut') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach($orders as $order)
                    <tr>
                        <td class="px-4 py-3 text-ink">{{ $order->reference }}</td>
                        <td class="px-4 py-3 text-navy-500">{{ $order->buyer->name }}</td>
                        <td class="px-4 py-3 text-navy-500">{{ number_format((float) $order->total_amount, 0, ',', ' ') }} FCFA</td>
                        <td class="px-4 py-3">
                            <form action="{{ route('admin.orders.update-status', $order) }}" method="POST" class="flex items-center gap-2">
                                @csrf @method('PATCH')
                                <select name="status" onchange="this.form.submit()" class="field-input py-1 text-xs">
                                    @foreach(['pending' => __('En attente'), 'paid' => __('Payée'), 'shipped' => __('Expédiée'), 'delivered' => __('Livrée'), 'cancelled' => __('Annulée')] as $value => $label)
                                        <option value="{{ $value }}" @selected($order->status === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $orders->links() }}</div>
</div>
@endsection
