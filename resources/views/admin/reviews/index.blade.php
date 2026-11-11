@extends('layouts.app')

@section('title', __('Modération des avis'))

@section('content')
<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
    <h1 class="mb-6 text-xl font-semibold text-ink">{{ __('Avis clients') }}</h1>

    @if($reviews->isEmpty())
        <div class="card p-10 text-center text-navy-500">{{ __('Aucun avis pour le moment.') }}</div>
    @else
        <div class="space-y-4">
            @foreach($reviews as $review)
                <div class="card p-4">
                    <div class="mb-1 flex items-center justify-between">
                        <div>
                            <a href="{{ route('products.show', $review->product) }}" class="text-sm font-medium text-ink hover:text-navy-700">{{ $review->product->name }}</a>
                            <span class="ml-2 text-amber-500">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                        </div>
                        <form action="{{ route('admin.reviews.destroy', $review) }}" method="POST" onsubmit="return confirm({{ Illuminate\Support\Js::from(__('Supprimer cet avis ?')) }});">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-600 hover:underline">{{ __('Supprimer') }}</button>
                        </form>
                    </div>
                    <p class="text-xs text-navy-300">{{ __('Par') }} {{ $review->user->name }} · {{ $review->created_at->format('d/m/Y') }}</p>
                    @if($review->comment)
                        <p class="mt-2 text-sm text-navy-600">{{ $review->comment }}</p>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $reviews->links() }}</div>
    @endif
</div>
@endsection
