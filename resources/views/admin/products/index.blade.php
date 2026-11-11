@extends('layouts.app')

@section('title', __('Modération des produits'))

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <x-breadcrumb :items="[
        ['label' => __('Tableau de bord'), 'url' => route('admin.dashboard')],
        ['label' => __('Modération des produits'), 'url' => null],
    ]" />

    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-ink">{{ __('Produits') }}</h1>
        <a href="{{ route('admin.dashboard') }}" class="btn-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" class="mr-1 h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 3a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
            </svg>
            {{ __('Retour') }}
        </a>
    </div>

    <form action="{{ route('admin.products.index') }}" method="GET" class="card mb-6 flex flex-wrap items-end gap-4 p-4">
        <div>
            <label class="field-label">{{ __('Recherche') }}</label>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Produit ou boutique') }}" class="field-input">
        </div>
        <div>
            <label class="field-label">{{ __('Statut') }}</label>
            <select name="status" class="field-input">
                <option value="">{{ __('Tous') }}</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('Actifs') }}</option>
                <option value="suspended" @selected(($filters['status'] ?? '') === 'suspended')>{{ __('Suspendus par l\'administration') }}</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('Masqués par le vendeur') }}</option>
            </select>
        </div>
        <button type="submit" class="btn-dark">{{ __('Filtrer') }}</button>
    </form>

    @if($products->isEmpty())
        <div class="card p-10 text-center text-navy-500">{{ __('Aucun produit ne correspond.') }}</div>
    @else
        <div class="overflow-x-auto rounded-lg border border-line bg-white">
            <table class="w-full text-sm">
                <thead class="bg-navy-50 text-left text-navy-500">
                    <tr>
                        <th class="px-4 py-3"></th>
                        <th class="px-4 py-3 font-medium">{{ __('Produit') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Boutique') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Prix') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Statut') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach($products as $product)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="h-10 w-10 overflow-hidden rounded-md bg-navy-50">
                                    @if($product->images->isNotEmpty())
                                        <img src="{{ Storage::url($product->images->first()->path) }}" alt="" class="h-full w-full object-cover">
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-ink">
                                <a href="{{ route('products.show', $product) }}" class="hover:underline" target="_blank" rel="noopener">{{ $product->name }}</a>
                                <p class="text-xs text-navy-300">{{ $product->category->name ?? '—' }}</p>
                            </td>
                            <td class="px-4 py-3 text-navy-500">{{ $product->vendor->store_name }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-navy-500">{{ number_format((float) $product->base_price, 0, ',', ' ') }} FCFA</td>
                            <td class="px-4 py-3">
                                @if($product->isSuspended())
                                    <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs text-red-700" title="{{ $product->admin_suspension_reason }}">{{ __('Suspendu') }}</span>
                                    @if($product->admin_suspension_reason)
                                        <p class="mt-1 max-w-xs text-xs text-navy-300">{{ $product->admin_suspension_reason }}</p>
                                    @endif
                                @elseif($product->is_active)
                                    <span class="badge-trade">{{ __('Actif') }}</span>
                                @else
                                    <span class="rounded-full bg-navy-50 px-2.5 py-1 text-xs text-navy-500">{{ __('Masqué') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if($product->isSuspended())
                                        <form action="{{ route('admin.products.restore', $product) }}" method="POST" class="inline delete-form" data-confirm="{{ __('Retablir ce produit ?') }}">
                                            @csrf
                                            <input type="hidden" name="_method" value="PATCH">
                                            <button type="submit" class="text-trade-green hover:underline">{{ __('Rétablir') }}</button>
                                        </form>
                                    @else
                                        <form action="{{ route('admin.products.suspend', $product) }}" method="POST" class="flex items-center gap-2">
                                            @csrf
                                            <input type="hidden" name="_method" value="PATCH">
                                            <input type="text" name="reason" required maxlength="255" placeholder="{{ __('Motif') }}" class="field-input w-36 py-1 text-xs">
                                            <button type="submit" class="text-amber-700 hover:underline">{{ __('Suspendre') }}</button>
                                        </form>
                                    @endif
                                    <form action="{{ route('admin.products.destroy', $product) }}" method="POST" class="inline delete-form" data-confirm="{{ __('Supprimer definitivement cette annonce ?') }}">
                                        @csrf
                                        <input type="hidden" name="_method" value="DELETE">
                                        <button type="submit" class="text-red-600 hover:underline">{{ __('Supprimer') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $products->links() }}</div>
    @endif
</div>
@endsection
