@props(['product'])

<div class="card group relative flex flex-col overflow-hidden transition hover:border-navy-300">
    <a href="{{ route('products.show', $product) }}" class="absolute inset-0 z-0" aria-label="{{ $product->name }}"></a>

    <div class="pointer-events-none flex aspect-square items-center justify-center overflow-hidden bg-navy-50">
        @if($product->hasActivePromo())
            <span class="absolute m-2 self-start rounded-full bg-red-600 px-2 py-0.5 text-xs font-semibold text-white">-{{ $product->discount_percent }}%</span>
        @endif
        @if($product->images->isNotEmpty())
            <img src="{{ Storage::url($product->images->first()->path) }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
        @else
            <svg xmlns="http://www.w3.org/2000/svg" class="h-14 w-14 text-navy-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-8.25-4.5L3.75 7.5m16.5 0l-8.25 4.5m8.25-4.5v9l-8.25 4.5m0-9L3.75 7.5m8.25 4.5v9m-8.25-9v9l8.25 4.5"/></svg>
        @endif
    </div>

    @auth
        @if(!auth()->user()->isVendor())
            @php $isWishlisted = in_array($product->id, $wishlistedIds ?? []); @endphp
            <form action="{{ route('wishlist.toggle', $product) }}" method="POST" class="absolute right-2 top-2 z-10">
                @csrf
                <button type="submit" aria-label="{{ __('Ajouter aux favoris') }}" class="flex h-7 w-7 items-center justify-center rounded-full bg-white/90 shadow hover:text-red-500 {{ $isWishlisted ? 'text-red-500' : 'text-navy-300' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="{{ $isWishlisted ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
                </button>
            </form>
        @endif
    @endauth

    <div class="pointer-events-none flex flex-1 flex-col gap-1.5 p-3">
        <p class="line-clamp-2 text-sm font-medium text-ink group-hover:text-navy-700">{{ $product->name }}</p>
        @if($product->hasActivePromo())
            <p class="font-display text-lg font-semibold text-red-600">
                {{ number_format($product->promoPrice(), 0, ',', ' ') }} FCFA
                <span class="ml-1 text-xs font-normal text-navy-300 line-through">{{ number_format((float) $product->base_price, 0, ',', ' ') }} FCFA</span>
            </p>
        @else
            <p class="font-display text-lg font-semibold text-navy-800">
                {{ number_format((float) $product->base_price, 0, ',', ' ') }} FCFA
                <span class="text-xs font-normal text-navy-300">/ {{ $product->unit }}</span>
            </p>
        @endif
        <p class="text-xs text-navy-500">{{ __('MOQ') }} : {{ $product->min_order_qty }} {{ $product->unit }}</p>
        @if($product->vendor)
            <p class="mt-1 truncate text-xs text-navy-300">{{ $product->vendor->store_name }}</p>
        @endif
    </div>
</div>
