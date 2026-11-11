@extends('layouts.app')

@section('title', __('Conversation avec') . ' ' . $conversation->buyer->name)

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
    <div class="mb-4">
        <a href="{{ route('vendor.messages.index') }}" class="text-sm text-navy-500 hover:text-navy-700">← {{ __('Retour aux messages') }}</a>
    </div>

    <div class="mb-4 rounded-lg border border-line bg-white p-4">
        <p class="font-medium text-ink">{{ $conversation->buyer->name }}</p>
        @if($conversation->buyer->company_name)
            <p class="text-xs text-navy-300">{{ $conversation->buyer->company_name }}</p>
        @endif
        @if($conversation->product)
            <a href="{{ route('products.show', $conversation->product) }}" class="text-xs text-navy-500 hover:underline">{{ __('Produit concerné :') }} {{ $conversation->product->name }}</a>
        @endif
    </div>

    <div class="card mb-4 max-h-[28rem] space-y-3 overflow-y-auto p-4">
        @foreach($conversation->messages as $message)
            @php $isMine = $message->sender_id === auth()->id(); @endphp
            <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-[75%] rounded-lg px-3 py-2 text-sm {{ $isMine ? 'bg-navy-800 text-white' : 'bg-navy-50 text-ink' }}">
                    <p>{{ $message->body }}</p>
                    <p class="mt-1 text-[10px] {{ $isMine ? 'text-navy-200' : 'text-navy-300' }}">{{ $message->created_at->format('d/m/Y H:i') }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <form action="{{ route('vendor.messages.reply', $conversation) }}" method="POST" class="flex gap-2">
        @csrf
        <input type="text" name="body" placeholder="{{ __("Répondre à l'acheteur…") }}" class="field-input" required autofocus>
        <button type="submit" class="btn-primary shrink-0">{{ __('Envoyer') }}</button>
    </form>
</div>
@endsection
