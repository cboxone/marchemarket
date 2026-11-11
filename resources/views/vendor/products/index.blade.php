@extends('layouts.app')

@section('title', __('Mes produits'))

@section('content')
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    <x-breadcrumb :items="[
        ['label' => __('Tableau de bord'), 'url' => route('vendor.dashboard')],
        ['label' => __('Mes produits'), 'url' => null],
    ]" />

    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-ink">{{ __('Mes produits') }}</h1>
        <div class="flex gap-3">
            <a href="{{ route('vendor.dashboard') }}" class="btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" class="mr-1 h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                    <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 3a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                </svg>
                {{ __('Retour') }}
            </a>
            <a href="{{ route('vendor.products.import') }}" class="btn-secondary">{{ __('Importer un CSV') }}</a>
            <a href="{{ route('vendor.products.create') }}" class="btn-primary">+ {{ __('Nouveau produit') }}</a>
        </div>
    </div>

    @if($products->isEmpty())
        <div class="card p-10 text-center text-navy-500">{{ __("Vous n'avez pas encore publié de produit.") }}</div>
    @else
        <div class="overflow-hidden rounded-lg border border-line bg-white">
            <table class="w-full text-sm">
                <thead class="bg-navy-50 text-left text-navy-500">
                    <tr>
                        <th class="px-4 py-3 font-medium"></th>
                        <th class="px-4 py-3 font-medium">{{ __('Produit') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Catégorie') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Prix') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Stock') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Statut') }}</th>
                        <th class="px-4 py-3"></th>
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
                            <td class="px-4 py-3 text-ink">{{ $product->name }}</td>
                            <td class="px-4 py-3 text-navy-500">{{ $product->category->name }}</td>
                            <td class="px-4 py-3 text-navy-500">{{ number_format((float) $product->base_price, 0, ',', ' ') }} FCFA</td>
                            <td class="px-4 py-3 text-navy-500">{{ $product->stock_qty }}</td>
                            <td class="px-4 py-3">
                                @if($product->isSuspended())
                                    <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs text-red-700" title="{{ $product->admin_suspension_reason }}">{{ __('Suspendu par l\'administration') }}</span>
                                @elseif($product->is_active)
                                    <span class="badge-trade">{{ __('Actif') }}</span>
                                @else
                                    <span class="rounded-full bg-navy-50 px-2.5 py-1 text-xs text-navy-500">{{ __('Inactif') }}</span>
                                @endif
                            </td>
                            <td class="space-x-3 px-4 py-3 text-right">
                                <a href="{{ route('vendor.products.edit', $product) }}" class="text-navy-700 hover:underline">{{ __('Modifier') }}</a>
                                <form action="{{ route('vendor.products.destroy', $product) }}" method="POST" class="inline delete-form" data-confirm="{{ __('Supprimer ce produit ?') }}">
                                    @csrf
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button type="submit" class="text-red-600 hover:underline">{{ __('Supprimer') }}</button>
                                </form>
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
