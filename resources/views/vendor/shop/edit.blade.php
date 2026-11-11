@extends('layouts.app')

@section('title', __('Ma boutique'))

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-ink">{{ __('Ma boutique') }}</h1>
        <a href="{{ route('vendors.show', $vendor) }}" class="text-sm text-trade-green hover:underline">{{ __('Voir ma page publique') }}</a>
    </div>

    <form action="{{ route('vendor.shop.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="card space-y-4 p-6">
            <h2 class="text-base font-semibold text-ink">{{ __('Profil public') }}</h2>

            <div>
                <label class="field-label" for="store_name">{{ __('Nom de la boutique') }}</label>
                <input type="text" id="store_name" name="store_name" value="{{ old('store_name', $vendor->store_name) }}" class="field-input" required maxlength="120">
                @if($vendor->is_verified)
                    <p class="mt-1 text-xs text-navy-300">{{ __('Changer le nom retire le badge « Vérifié » jusqu\'à nouvelle validation par notre équipe.') }}</p>
                @endif
            </div>

            <div>
                <label class="field-label" for="description">{{ __('Description') }}</label>
                <textarea id="description" name="description" rows="4" maxlength="2000" class="field-input">{{ old('description', $vendor->description) }}</textarea>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="field-label" for="city">{{ __('Ville') }}</label>
                    <input type="text" id="city" name="city" value="{{ old('city', $vendor->city) }}" class="field-input" maxlength="80">
                </div>
                <div>
                    <label class="field-label" for="country">{{ __('Pays') }}</label>
                    <input type="text" id="country" name="country" value="{{ old('country', $vendor->country) }}" class="field-input" required maxlength="80">
                </div>
            </div>

            <div>
                <label class="field-label" for="logo">{{ __('Logo') }}</label>
                @if($vendor->logo_path)
                    <div class="mb-2 flex items-center gap-3">
                        <img src="{{ Storage::url($vendor->logo_path) }}" alt="" class="h-16 w-16 rounded-lg border border-line object-cover">
                        <label class="flex items-center gap-2 text-sm text-navy-500">
                            <input type="checkbox" name="remove_logo" value="1"> {{ __('Supprimer le logo') }}
                        </label>
                    </div>
                @endif
                <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp" class="field-input">
                <p class="mt-1 text-xs text-navy-300">{{ __('JPG, PNG ou WebP, 2 Mo maximum.') }}</p>
            </div>
        </section>

        <section id="retrait" class="card space-y-4 p-6">
            <div>
                <h2 class="text-base font-semibold text-ink">{{ __('Compte de retrait (Mobile Money)') }}</h2>
                <p class="mt-1 text-sm text-navy-500">{{ __('Vos reversements sont envoyés sur ce compte. Vous recevrez un e-mail à chaque modification.') }}</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="field-label" for="payout_provider">{{ __('Opérateur') }}</label>
                    <select id="payout_provider" name="payout_provider" class="field-input">
                        <option value="">{{ __('— Aucun —') }}</option>
                        @foreach(\App\Models\Vendor::PAYOUT_PROVIDERS as $key => $label)
                            <option value="{{ $key }}" @selected(old('payout_provider', $vendor->payout_provider) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label" for="payout_phone">{{ __('Numéro') }}</label>
                    <input type="tel" id="payout_phone" name="payout_phone" value="{{ old('payout_phone', $vendor->payout_phone) }}" placeholder="+229 96 00 00 00" class="field-input">
                </div>
            </div>

            <div>
                <label class="field-label" for="current_password">{{ __('Mot de passe actuel') }}</label>
                <input type="password" id="current_password" name="current_password" autocomplete="current-password" class="field-input">
                <p class="mt-1 text-xs text-navy-300">{{ __('Requis uniquement si vous modifiez le compte de retrait.') }}</p>
            </div>
        </section>

        <button type="submit" class="btn-primary">{{ __('Enregistrer') }}</button>
    </form>
</div>
@endsection
