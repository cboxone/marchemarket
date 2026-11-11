@extends('layouts.app')

@section('title', __('Mon profil'))

@section('content')
<div class="mx-auto max-w-xl px-4 py-8 sm:px-6">
    <h1 class="mb-6 text-xl font-semibold text-ink">{{ __('Mon profil') }}</h1>

    <form action="{{ route('profile.update') }}" method="POST" class="card space-y-4 p-6">
        @csrf @method('PATCH')
        <div>
            <label class="field-label">{{ __('Nom complet') }}</label>
            <input type="text" name="name" value="{{ old('name', $user->name) }}" class="field-input" required>
        </div>
        <div>
            <label class="field-label">{{ __('Adresse e-mail') }}</label>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" class="field-input" required>
        </div>
        <div>
            <label class="field-label">{{ __('Téléphone') }}</label>
            <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" class="field-input">
        </div>
        <div>
            <label class="field-label">{{ __('Entreprise') }}</label>
            <input type="text" name="company_name" value="{{ old('company_name', $user->company_name) }}" class="field-input">
        </div>
        <button type="submit" class="btn-primary">{{ __('Enregistrer') }}</button>
    </form>

    <section class="card mt-6 space-y-3 p-6">
        <h2 class="text-base font-semibold text-ink">{{ __('Mes données') }}</h2>
        <p class="text-sm text-navy-500">{{ __('Téléchargez une copie de vos données personnelles : profil, adresses, commandes, devis, litiges et avis.') }}</p>
        <a href="{{ route('profile.export') }}" class="btn-secondary inline-block">{{ __('Exporter mes données (JSON)') }}</a>
    </section>

    <section class="card mt-6 space-y-3 border-red-200 p-6">
        <h2 class="text-base font-semibold text-red-700">{{ __('Supprimer mon compte') }}</h2>

        @if($errors->has('account'))
            <p class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first('account') }}</p>
        @endif

        @if(! $canDeleteAccount)
            <p class="text-sm text-navy-500">
                @if($user->isVendor())
                    {{ __('Un compte vendeur ne peut pas être supprimé directement : contactez le support pour la fermeture de votre boutique.') }}
                @else
                    {{ __('Impossible de supprimer votre compte pour le moment : vous avez une commande, un devis ou un litige encore en cours.') }}
                @endif
            </p>
        @else
            <p class="text-sm text-navy-500">{{ __("Cette action est irréversible. Votre nom, votre e-mail et votre téléphone sont retirés ; vos commandes passées sont conservées de façon anonyme, comme l'exige la réglementation comptable.") }}</p>
            <form action="{{ route('profile.destroy') }}" method="POST" onsubmit="return confirm({{ Illuminate\Support\Js::from(__('Supprimer définitivement votre compte ?')) }});" class="space-y-3">
                @csrf @method('DELETE')
                <div>
                    <label class="field-label" for="current_password">{{ __('Mot de passe actuel') }}</label>
                    <input type="password" id="current_password" name="current_password" autocomplete="current-password" class="field-input" required>
                    @error('current_password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="rounded-md border border-red-600 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">{{ __('Supprimer mon compte') }}</button>
            </form>
        @endif
    </section>
</div>
@endsection
