@csrf
@if(isset($product)) @method('PUT') @endif

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label class="field-label">{{ __('Nom du produit') }}</label>
        <input type="text" name="name" value="{{ old('name', $product->name ?? '') }}" class="field-input" required>
    </div>

    <div>
        <label class="field-label">{{ __('Catégorie') }}</label>
        <select name="category_id" class="field-input" required>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected(old('category_id', $product->category_id ?? null) == $cat->id)>{{ $cat->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="field-label">{{ __('Unité de vente (ex : sac, carton, pièce)') }}</label>
        <input type="text" name="unit" value="{{ old('unit', $product->unit ?? '') }}" class="field-input" required>
    </div>

    <div>
        <label class="field-label">{{ __('Quantité minimale de commande (MOQ)') }}</label>
        <input type="number" name="min_order_qty" value="{{ old('min_order_qty', $product->min_order_qty ?? 1) }}" min="1" class="field-input" required>
    </div>

    <div>
        <label class="field-label">{{ __('Quantité maximale de commande (facultatif)') }}</label>
        <input type="number" name="max_order_qty" value="{{ old('max_order_qty', $product->max_order_qty ?? '') }}" min="1" placeholder="{{ __('Illimitée si vide') }}" class="field-input">
    </div>

    <div>
        <label class="field-label">{{ __('Stock disponible') }}</label>
        <input type="number" name="stock_qty" value="{{ old('stock_qty', $product->stock_qty ?? 0) }}" min="0" class="field-input" required>
    </div>

    <div class="sm:col-span-2">
        <label class="field-label">{{ __('Description') }}</label>
        <textarea name="description" rows="4" class="field-input">{{ old('description', $product->description ?? '') }}</textarea>
    </div>
</div>

<div class="mt-6 rounded-lg border border-line bg-white p-4">
    <h3 class="mb-3 text-sm font-semibold text-ink">{{ __('Photos du produit') }}</h3>

    @if(isset($product) && $product->images->isNotEmpty())
        <p class="mb-2 text-xs text-navy-500">{{ __('Cochez les photos à supprimer :') }}</p>
        <div class="mb-4 grid grid-cols-3 gap-3 sm:grid-cols-6">
            @foreach($product->images as $image)
                <label class="relative block cursor-pointer overflow-hidden rounded-md border border-line">
                    <img src="{{ Storage::url($image->path) }}" alt="" class="aspect-square w-full object-cover">
                    <span class="absolute inset-x-0 bottom-0 flex items-center justify-center gap-1 bg-navy-900/70 py-1 text-xs text-white">
                        <input type="checkbox" name="delete_images[]" value="{{ $image->id }}"> {{ __('Supprimer') }}
                    </span>
                </label>
            @endforeach
        </div>
    @endif

    <label class="field-label">{{ __('Ajouter des photos (JPG, PNG ou WEBP, 4 Mo max chacune, 6 max)') }}</label>
    <input type="file" name="images[]" accept="image/*" multiple class="field-input">
</div>

<div class="mt-6 rounded-lg border border-line bg-navy-50 p-4">
    <h3 class="mb-3 text-sm font-semibold text-ink">{{ __('Prix dégressifs par quantité (comme sur les marketplaces de gros)') }}</h3>
    <p class="mb-4 text-xs text-navy-500">{{ __("Le palier 1 démarre automatiquement à votre MOQ, au prix de base ci-dessous. Ajoutez jusqu'à deux paliers supplémentaires pour récompenser les grosses commandes.") }}</p>

    <div class="mb-4">
        <label class="field-label">{{ __('Prix de base (palier 1, à partir du MOQ)') }}</label>
        <input type="number" step="0.01" name="base_price" value="{{ old('base_price', $product->base_price ?? '') }}" class="field-input w-48" required>
    </div>

    <div class="mb-4 grid grid-cols-2 gap-4 rounded-md bg-amber-50 p-3">
        <div>
            <label class="field-label">{{ __('Réduction promotionnelle (%)') }}</label>
            <input type="number" name="discount_percent" min="1" max="90" value="{{ old('discount_percent', $product->discount_percent ?? '') }}" placeholder="{{ __('Ex : 10') }}" class="field-input">
        </div>
        <div>
            <label class="field-label">{{ __("Promotion valable jusqu'au") }}</label>
            <input type="datetime-local" name="promo_ends_at" value="{{ old('promo_ends_at', optional($product->promo_ends_at ?? null)->format('Y-m-d\TH:i')) }}" class="field-input">
        </div>
        <p class="col-span-2 text-xs text-navy-500">{{ __('Laissez la date vide pour une promotion sans échéance. Laissez le pourcentage vide pour retirer la promotion.') }}</p>
    </div>

    @php $tiers = $product->priceTiers ?? collect(); @endphp
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="field-label">{{ __('Palier 2 — à partir de (qté)') }}</label>
            <input type="number" name="tier2_min_qty" value="{{ old('tier2_min_qty', $tiers->get(1)->min_qty ?? '') }}" class="field-input">
        </div>
        <div>
            <label class="field-label">{{ __('Palier 2 — prix unitaire') }}</label>
            <input type="number" step="0.01" name="tier2_price" value="{{ old('tier2_price', $tiers->get(1)->unit_price ?? '') }}" class="field-input">
        </div>
        <div>
            <label class="field-label">{{ __('Palier 3 — à partir de (qté)') }}</label>
            <input type="number" name="tier3_min_qty" value="{{ old('tier3_min_qty', $tiers->get(2)->min_qty ?? '') }}" class="field-input">
        </div>
        <div>
            <label class="field-label">{{ __('Palier 3 — prix unitaire') }}</label>
            <input type="number" step="0.01" name="tier3_price" value="{{ old('tier3_price', $tiers->get(2)->unit_price ?? '') }}" class="field-input">
        </div>
    </div>
</div>

@if(isset($product) && $product->isSuspended())
    <div class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
        <p class="font-medium">{{ __("Ce produit a été suspendu par l'administration : il n'est plus visible dans le catalogue.") }}</p>
        @if($product->admin_suspension_reason)
            <p class="mt-1">{{ __('Motif :') }} {{ $product->admin_suspension_reason }}</p>
        @endif
        <p class="mt-1">{{ __('Corrigez-le si nécessaire, puis contactez le support pour demander sa remise en ligne.') }}</p>
    </div>
@else
    <div class="mt-6 flex items-center gap-2">
        {{-- Champ caché : une case décochée n'envoie rien, et le produit restait donc actif. --}}
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $product->is_active ?? true) ? 'checked' : '' }}>
        <label for="is_active" class="text-sm text-ink">{{ __('Produit visible dans le catalogue') }}</label>
    </div>
@endif

<button type="submit" class="btn-primary mt-6">{{ isset($product) ? __('Enregistrer les modifications') : __('Publier le produit') }}</button>
