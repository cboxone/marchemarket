@extends('layouts.app')

@section('title', __('Réinitialiser le mot de passe'))

@section('content')
<div class="mx-auto max-w-md px-4 py-12 sm:px-6">
    <div class="card p-8">
        <h1 class="mb-6 text-center font-display text-2xl font-semibold text-ink">{{ __('Choisir un nouveau mot de passe') }}</h1>

        <form action="{{ route('password.update') }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label class="field-label">{{ __('Adresse e-mail') }}</label>
                <input type="email" name="email" value="{{ old('email', $email) }}" class="field-input" required autofocus>
            </div>
            <div>
                <label class="field-label">{{ __('Nouveau mot de passe') }}</label>
                <input type="password" name="password" class="field-input" required>
            </div>
            <div>
                <label class="field-label">{{ __('Confirmer le mot de passe') }}</label>
                <input type="password" name="password_confirmation" class="field-input" required>
            </div>
            <button type="submit" class="btn-primary w-full">{{ __('Réinitialiser le mot de passe') }}</button>
        </form>
    </div>
</div>
@endsection
