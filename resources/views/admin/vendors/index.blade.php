@extends('layouts.app')

@section('title', __('Gestion des vendeurs'))

@section('content')
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    <h1 class="mb-6 text-xl font-semibold text-ink">{{ __('Vendeurs') }}</h1>

    <div class="overflow-hidden rounded-lg border border-line bg-white">
        <table class="w-full text-sm">
            <thead class="bg-navy-50 text-left text-navy-500">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ __('Boutique') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Ville') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Produits') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Statut') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach($vendors as $vendor)
                    <tr>
                        <td class="px-4 py-3 text-ink">{{ $vendor->store_name }}</td>
                        <td class="px-4 py-3 text-navy-500">{{ $vendor->city }}</td>
                        <td class="px-4 py-3 text-navy-500">{{ $vendor->products_count }}</td>
                        <td class="px-4 py-3">
                            @if($vendor->is_verified)
                                <span class="badge-trade">{{ __('Vérifié') }}</span>
                            @else
                                <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs text-amber-600">{{ __('Non vérifié') }}</span>
                            @endif
                            @if($vendor->kyc_status === 'pending')
                                <a href="{{ route('admin.verifications.show', $vendor) }}" class="ml-1 text-xs text-trade-green hover:underline">{{ __('Dossier en attente') }}</a>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <form action="{{ route('admin.vendors.toggle-verification', $vendor) }}" method="POST">
                                @csrf @method('PATCH')
                                <button type="submit" class="text-navy-700 hover:underline">
                                    {{ $vendor->is_verified ? __('Retirer la vérification') : __('Vérifier') }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $vendors->links() }}</div>
</div>
@endsection
