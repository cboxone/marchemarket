@extends('layouts.app')

@section('title', __('Mes favoris'))

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <h1 class="mb-6 text-xl font-semibold text-ink">{{ __('Mes favoris') }}</h1>

    @if($products->isEmpty())
        <div class="card p-10 text-center text-navy-500">
            {{ __("Vous n'avez pas encore de produit favori.") }}
            <a href="{{ route('products.index') }}" class="mt-3 inline-block text-navy-700 underline">{{ __('Parcourir le catalogue') }}</a>
        </div>
    @else
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach($products as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
        <div class="mt-8">{{ $products->links() }}</div>
    @endif
</div>
@endsection
