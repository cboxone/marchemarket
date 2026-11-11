@extends('layouts.app')

@section('title', __('Connexion'))

@section('content')
<div class="mx-auto max-w-md px-4 py-12 sm:px-6">
    <div class="card p-8">
        <h1 class="mb-6 text-center font-display text-2xl font-semibold text-ink">{{ __('Connexion') }}</h1>
        <form action="{{ route('login') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="field-label">{{ __('Adresse e-mail') }}</label>
                <input type="email" name="email" value="{{ old('email') }}" class="field-input" required autofocus>
            </div>
            <div>
                <label class="field-label">{{ __('Mot de passe') }}</label>
                <input type="password" name="password" class="field-input" required>
            </div>
            <label class="flex items-center gap-2 text-sm text-navy-500">
                <input type="checkbox" name="remember"> {{ __('Se souvenir de moi') }}
            </label>
            <div class="text-right">
                <a href="{{ route('password.request') }}" class="text-sm text-navy-500 hover:underline">{{ __('Mot de passe oublié ?') }}</a>
            </div>
            <button type="submit" class="btn-primary w-full">{{ __('Se connecter') }}</button>
        </form>
        <p class="mt-6 text-center text-sm text-navy-500">
            {{ __('Pas encore de compte ?') }} <a href="{{ route('register') }}" class="font-medium text-navy-700 hover:underline">{{ __('Inscrivez-vous') }}</a>
        </p>
        <div class="mt-6 rounded-md bg-navy-50 p-3 text-xs text-navy-500">
            {{ __('Comptes de démonstration :') }} <br>
            admin@marchemarket.test · vendeur1@marchemarket.test · client@marchemarket.test<br>
            {{ __('Mot de passe :') }} password
        </div>
    </div>
</div>
@endsection
