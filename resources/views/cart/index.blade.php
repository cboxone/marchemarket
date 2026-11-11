@extends('layouts.app')

@section('title', __('Mon panier'))

@section('content')
<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
    <h1 class="mb-6 text-xl font-semibold text-ink">{{ __('Mon panier') }}</h1>

    @if($cart->items->isEmpty())
        <div class="card p-10 text-center text-navy-500">
            {{ __('Votre panier est vide pour le moment.') }}
            <a href="{{ route('products.index') }}" class="mt-3 inline-block text-navy-700 underline">{{ __('Parcourir le catalogue') }}</a>
        </div>
    @else
        <div class="space-y-4">
            @foreach($cart->items as $item)
                <div class="card flex flex-wrap items-center gap-4 p-4">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-md bg-navy-50">
                        @if($item->product->images->isNotEmpty())
                            <img src="{{ Storage::url($item->product->images->first()->path) }}" alt="{{ $item->product->name }}" class="h-full w-full object-cover">
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-navy-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-8.25-4.5L3.75 7.5m16.5 0l-8.25 4.5m8.25-4.5v9l-8.25 4.5m0-9L3.75 7.5"/></svg>
                        @endif
                    </div>
                    <div class="flex-1">
                        <a href="{{ route('products.show', $item->product) }}" class="font-medium text-ink hover:text-navy-700">{{ $item->product->name }}</a>
                        <p class="text-xs text-navy-300">{{ $item->product->vendor->store_name }} · {{ __('MOQ') }} {{ $item->product->min_order_qty }} {{ $item->product->unit }}</p>
                        <p class="text-xs text-navy-500">{{ number_format($item->product->priceForQty($item->quantity), 0, ',', ' ') }} FCFA / {{ $item->product->unit }}</p>
                    </div>

                    <form action="{{ route('cart.update', $item) }}" method="POST" class="flex items-center gap-2">
                        @csrf @method('PATCH')
                        <div class="flex items-stretch overflow-hidden rounded-md border border-line">
                            <button
                                type="button"
                                class="px-3 text-navy-500 hover:bg-navy-50 disabled:cursor-not-allowed disabled:opacity-30"
                                onclick="const i=this.nextElementSibling; const min=parseInt(i.min||1); if(parseInt(i.value)>min){ i.value=parseInt(i.value)-1; i.form.requestSubmit(); }"
                                {{ $item->quantity <= $item->product->min_order_qty ? 'disabled' : '' }}
                            >−</button>
                            <input
                                type="number"
                                name="quantity"
                                value="{{ $item->quantity }}"
                                min="{{ $item->product->min_order_qty }}"
                                @if($item->product->max_order_qty) max="{{ $item->product->max_order_qty }}" @endif
                                class="w-16 border-x border-line text-center text-sm focus:outline-none"
                                onchange="if(parseInt(this.value) >= parseInt(this.min)) this.form.requestSubmit()"
                            >
                            <button
                                type="button"
                                class="px-3 text-navy-500 hover:bg-navy-50 disabled:cursor-not-allowed disabled:opacity-30"
                                onclick="const i=this.previousElementSibling; const max=i.max?parseInt(i.max):Infinity; if(parseInt(i.value)<max){ i.value=parseInt(i.value)+1; i.form.requestSubmit(); }"
                                {{ $item->product->max_order_qty && $item->quantity >= $item->product->max_order_qty ? 'disabled' : '' }}
                            >+</button>
                        </div>
                        <noscript><button type="submit" class="btn-secondary px-3 py-2 text-xs">{{ __('Mettre à jour') }}</button></noscript>
                    </form>

                    <p class="w-32 text-right font-medium text-ink">{{ number_format($item->subtotal(), 0, ',', ' ') }} FCFA</p>
                    <form action="{{ route('cart.destroy', $item) }}" method="POST">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs text-red-600 hover:underline">{{ __('Retirer') }}</button>
                    </form>
                </div>
            @endforeach
        </div>

        <div class="mt-6 flex items-center justify-between rounded-lg border border-line bg-white p-5">
            <p class="text-lg font-semibold text-ink">{{ __('Total') }} : {{ number_format($cart->total(), 0, ',', ' ') }} FCFA</p>
            <a href="{{ route('checkout.create') }}" class="btn-primary">{{ __('Passer la commande') }}</a>
        </div>
    @endif
</div>
@endsection
