@extends('layouts.app')

@section('title', __('Virements à vérifier'))

@section('content')
<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
    <h1 class="mb-6 text-xl font-semibold text-ink">{{ __('Virements à vérifier') }}</h1>

    @if(session('status'))
        <div class="mb-6 rounded-md bg-trade-green/10 px-4 py-3 text-sm text-trade-green">{{ session('status') }}</div>
    @endif
    @if($errors->has('bank_transfer'))
        <div class="mb-6 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first('bank_transfer') }}</div>
    @endif

    @if($orders->isEmpty())
        <div class="card p-10 text-center text-navy-500">{{ __('Aucun virement en attente.') }}</div>
    @else
        <div class="overflow-x-auto rounded-lg border border-line bg-white">
            <table class="w-full text-sm">
                <thead class="bg-navy-50 text-left text-navy-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Commande') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Acheteur') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Montant') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Déposé le') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach($orders as $order)
                        <tr>
                            <td class="px-4 py-3 font-medium text-ink">{{ $order->reference }}</td>
                            <td class="px-4 py-3 text-navy-500">{{ $order->buyer->company_name ?: $order->buyer->name }}</td>
                            <td class="px-4 py-3 text-navy-500">{{ number_format((float) $order->total_amount, 0, ',', ' ') }} FCFA</td>
                            <td class="px-4 py-3 text-navy-500">{{ $order->bank_transfer_declared_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('admin.bank-transfers.show', $order) }}" class="text-navy-700 hover:underline">{{ __('Examiner') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
