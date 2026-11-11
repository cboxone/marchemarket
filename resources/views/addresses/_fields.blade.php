<div>
    <label class="field-label" for="{{ $idPrefix }}label">{{ __("Nom de l'adresse (ex. Entrepôt, Bureau)") }}</label>
    <input type="text" id="{{ $idPrefix }}label" name="label" maxlength="60" value="{{ old('label', $address?->label) }}" class="field-input" required>
</div>
<div>
    <label class="field-label" for="{{ $idPrefix }}recipient_name">{{ __('Nom du destinataire (facultatif)') }}</label>
    <input type="text" id="{{ $idPrefix }}recipient_name" name="recipient_name" maxlength="120" value="{{ old('recipient_name', $address?->recipient_name) }}" class="field-input">
</div>
<div>
    <label class="field-label" for="{{ $idPrefix }}address">{{ __('Adresse') }}</label>
    <input type="text" id="{{ $idPrefix }}address" name="address" maxlength="255" value="{{ old('address', $address?->address) }}" class="field-input" required>
</div>
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <label class="field-label" for="{{ $idPrefix }}city">{{ __('Ville') }}</label>
        <input type="text" id="{{ $idPrefix }}city" name="city" maxlength="120" value="{{ old('city', $address?->city) }}" class="field-input" required>
    </div>
    <div>
        <label class="field-label" for="{{ $idPrefix }}country">{{ __('Pays') }}</label>
        <input type="text" id="{{ $idPrefix }}country" name="country" maxlength="120" value="{{ old('country', $address?->country ?? 'Bénin') }}" class="field-input" required>
    </div>
</div>
<div>
    <label class="field-label" for="{{ $idPrefix }}phone">{{ __('Téléphone') }}</label>
    <input type="text" id="{{ $idPrefix }}phone" name="phone" maxlength="30" value="{{ old('phone', $address?->phone) }}" class="field-input" required>
</div>
<label class="flex items-center gap-2 text-sm text-ink">
    <input type="checkbox" name="is_default" value="1" {{ old('is_default', $address?->is_default) ? 'checked' : '' }}>
    {{ __('Utiliser comme adresse par défaut') }}
</label>
