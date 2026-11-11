@extends('layouts.app')

@section('title', __('Mot de passe oublié'))

@section('content')
<div class="mx-auto max-w-md px-4 py-12 sm:px-6">
    <div class="card p-8">
        <h1 class="mb-2 text-center font-display text-2xl font-semibold text-ink">{{ __('Mot de passe oublié') }}</h1>
        <p class="mb-6 text-center text-sm text-navy-500">
            {{ __('Indiquez votre adresse e-mail : nous vous enverrons un lien pour choisir un nouveau mot de passe.') }}
        </p>

        @if(session('status'))
            <div class="mb-4 rounded-md border border-trade-green/30 bg-trade-greenLight px-4 py-3 text-sm text-trade-green">
                {{ session('status') }}
            </div>
        @endif

        <form action="{{ route('password.email') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="field-label">{{ __('Adresse e-mail') }}</label>
                <input type="email" name="email" value="{{ old('email') }}" class="field-input" required autofocus>
            </div>
            <button type="submit" class="btn-primary w-full">{{ __('Envoyer le lien de réinitialisation') }}</button>
        </form>

        <p class="mt-6 text-center text-sm text-navy-500">
            <a href="{{ route('login') }}" class="font-medium text-navy-700 hover:underline">{{ __('Retour à la connexion') }}</a>
        </p>
    </div>
</div>
@endsection
