@extends('layouts.app')

@section('title', __('Mes messages'))

@section('content')
<div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
    <h1 class="mb-6 text-xl font-semibold text-ink">{{ __('Messages') }}</h1>

    @if($conversations->isEmpty())
        <div class="card p-10 text-center text-navy-500">
            {{ __("Vous n'avez pas encore contacté de fournisseur. Rendez-vous sur une fiche produit pour poser une question.") }}
        </div>
    @else
        <div class="divide-y divide-line overflow-hidden rounded-lg border border-line bg-white">
            @foreach($conversations as $conversation)
                @php $unread = $conversation->unreadCountFor(auth()->id()); @endphp
                <a href="{{ route('messages.show', $conversation) }}" class="flex items-center justify-between gap-4 p-4 hover:bg-navy-50">
                    <div class="min-w-0">
                        <p class="font-medium text-ink">{{ $conversation->vendor->store_name }}</p>
                        @if($conversation->product)
                            <p class="text-xs text-navy-300">{{ __('À propos de :') }} {{ $conversation->product->name }}</p>
                        @endif
                        <p class="truncate text-sm text-navy-500">{{ optional($conversation->messages->first())->body }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        @if($unread > 0)
                            <span class="rounded-full bg-amber-500 px-2 py-0.5 text-xs font-semibold text-white">{{ $unread }}</span>
                        @endif
                        <span class="text-xs text-navy-300">{{ optional($conversation->last_message_at)->diffForHumans() }}</span>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-6">{{ $conversations->links() }}</div>
    @endif
</div>
@endsection
