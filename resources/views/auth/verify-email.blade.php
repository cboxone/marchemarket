@extends('layouts.app')

@section('title', __('Vérifiez votre adresse e-mail'))

@section('content')
<div class="mx-auto max-w-md px-4 py-12 sm:px-6">
    <div class="card p-8 text-center">
        <h1 class="mb-3 font-display text-2xl font-semibold text-ink">{{ __('Vérifiez votre adresse e-mail') }}</h1>
        <p class="mb-6 text-sm text-navy-500">
            {{ __("Merci de votre inscription ! Avant de continuer, cliquez sur le lien que nous venons de vous envoyer par e-mail pour confirmer votre adresse.") }}
        </p>

        @if(session('status'))
            <div class="mb-4 rounded-md border border-trade-green/30 bg-trade-greenLight px-4 py-3 text-sm text-trade-green">
                {{ session('status') }}
            </div>
        @endif

        <form action="{{ route('verification.send') }}" method="POST" class="mb-4">
            @csrf
            <button type="submit" class="btn-primary w-full">{{ __('Renvoyer le lien de vérification') }}</button>
        </form>

        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="text-sm text-navy-500 underline">{{ __('Déconnexion') }}</button>
        </form>
    </div>
</div>
@endsection
