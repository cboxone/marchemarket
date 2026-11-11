@extends('layouts.app')

@section('title', __('Mes adresses'))

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
    <h1 class="mb-6 text-xl font-semibold text-ink">{{ __('Mes adresses') }}</h1>

    @if(session('status'))
        <div class="mb-6 rounded-md bg-trade-green/10 px-4 py-3 text-sm text-trade-green">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-6 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">
            @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
        </div>
    @endif

    @if($addresses->isNotEmpty())
        <div class="mb-8 space-y-3">
            @foreach($addresses as $address)
                <div class="card flex flex-col gap-3 p-5 sm:flex-row sm:items-start sm:justify-between">
                    <div class="text-sm">
                        <div class="mb-1 flex items-center gap-2">
                            <span class="font-medium text-ink">{{ $address->label }}</span>
                            @if($address->is_default)
                                <span class="badge-trade">{{ __('Par défaut') }}</span>
                            @endif
                        </div>
                        @if($address->recipient_name)<p class="text-navy-500">{{ $address->recipient_name }}</p>@endif
                        <p class="text-navy-500">{{ $address->address }}</p>
                        <p class="text-navy-500">{{ $address->city }}, {{ $address->country }}</p>
                        <p class="text-navy-500">{{ __('Tél') }} : {{ $address->phone }}</p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-3 text-sm">
                        @unless($address->is_default)
                            <form action="{{ route('addresses.set-default', $address) }}" method="POST">
                                @csrf
                                <button type="submit" class="text-trade-green hover:underline">{{ __('Définir par défaut') }}</button>
                            </form>
                        @endunless
                        <button type="button" class="text-navy-500 hover:underline" onclick="document.getElementById('edit-{{ $address->id }}').classList.toggle('hidden')">{{ __('Modifier') }}</button>
                        <form action="{{ route('addresses.destroy', $address) }}" method="POST" onsubmit="return confirm({{ Illuminate\Support\Js::from(__('Supprimer cette adresse ?')) }});">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">{{ __('Supprimer') }}</button>
                        </form>
                    </div>
                </div>

                <div id="edit-{{ $address->id }}" class="hidden">
                    <form action="{{ route('addresses.update', $address) }}" method="POST" class="card space-y-4 p-5">
                        @csrf @method('PUT')
                        @include('addresses._fields', ['address' => $address, 'idPrefix' => 'edit-' . $address->id . '-'])
                        <button type="submit" class="btn-primary">{{ __('Enregistrer les modifications') }}</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif

    <h2 class="mb-3 text-base font-semibold text-ink">{{ __('Ajouter une adresse') }}</h2>
    <form action="{{ route('addresses.store') }}" method="POST" class="card space-y-4 p-5">
        @csrf
        @include('addresses._fields', ['address' => null, 'idPrefix' => 'new-'])
        <button type="submit" class="btn-primary">{{ __('Ajouter cette adresse') }}</button>
    </form>
</div>
@endsection
