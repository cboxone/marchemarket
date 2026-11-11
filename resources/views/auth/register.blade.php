@extends('layouts.app')

@section('title', __('Créer un compte'))

@section('content')
<div class="mx-auto max-w-lg px-4 py-12 sm:px-6">
    <div class="card p-8">
        <h1 class="mb-6 text-center font-display text-2xl font-semibold text-ink">{{ __('Créer un compte') }}</h1>

        <form action="{{ route('register') }}" method="POST" class="space-y-4">
            @csrf

            <div class="grid grid-cols-2 gap-3">
                <label class="card cursor-pointer p-3 text-center text-sm has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50">
                    <input type="radio" name="account_type" value="customer" class="sr-only" onchange="document.getElementById('vendor-fields').classList.add('hidden')" {{ old('account_type', 'customer') === 'customer' ? 'checked' : '' }}>
                    {{ __('Acheteur professionnel') }}
                </label>
                <label class="card cursor-pointer p-3 text-center text-sm has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50">
                    <input type="radio" name="account_type" value="vendor" class="sr-only" onchange="document.getElementById('vendor-fields').classList.remove('hidden')" {{ old('account_type') === 'vendor' ? 'checked' : '' }}>
                    {{ __('Fournisseur / Vendeur') }}
                </label>
            </div>

            <div>
                <label class="field-label">{{ __('Nom complet') }}</label>
                <input type="text" name="name" value="{{ old('name') }}" class="field-input" required>
            </div>
            <div>
                <label class="field-label">{{ __('Adresse e-mail') }}</label>
                <input type="email" name="email" value="{{ old('email') }}" class="field-input" required>
            </div>
            <div>
                <label class="field-label">{{ __("Nom de l'entreprise (facultatif)") }}</label>
                <input type="text" name="company_name" value="{{ old('company_name') }}" class="field-input">
            </div>

            <div id="vendor-fields" class="{{ old('account_type') === 'vendor' ? '' : 'hidden' }}">
                <label class="field-label">{{ __('Nom de votre boutique') }}</label>
                <input type="text" name="store_name" value="{{ old('store_name') }}" class="field-input">
            </div>

            <div>
                <label class="field-label">{{ __('Mot de passe') }}</label>
                <input type="password" name="password" class="field-input" required>
            </div>
            <div>
                <label class="field-label">{{ __('Confirmer le mot de passe') }}</label>
                <input type="password" name="password_confirmation" class="field-input" required>
            </div>

            <label class="flex items-start gap-2 text-sm text-navy-500">
                <input type="checkbox" name="terms" value="1" class="mt-0.5" {{ old('terms') ? 'checked' : '' }} required>
                <span>
                    {{ __("J'accepte les") }}
                    <a href="{{ route('legal.terms') }}" target="_blank" class="text-navy-700 underline">{{ __('conditions générales d\'utilisation') }}</a>
                    {{ __('et la') }}
                    <a href="{{ route('legal.privacy') }}" target="_blank" class="text-navy-700 underline">{{ __('politique de confidentialité') }}</a>.
                </span>
            </label>

            <button type="submit" class="btn-primary w-full">{{ __('Créer mon compte') }}</button>
        </form>

        <p class="mt-6 text-center text-sm text-navy-500">
            {{ __('Déjà inscrit ?') }} <a href="{{ route('login') }}" class="font-medium text-navy-700 hover:underline">{{ __('Connectez-vous') }}</a>
        </p>
    </div>
</div>
@endsection
