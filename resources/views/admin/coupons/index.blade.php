@extends('layouts.app')

@section('title', __('Codes promo'))

@section('content')
<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
    <h1 class="mb-6 text-xl font-semibold text-ink">{{ __('Codes promo') }}</h1>

    <form action="{{ route('admin.coupons.store') }}" method="POST" class="card mb-6 grid grid-cols-1 gap-4 p-5 sm:grid-cols-5">
        @csrf
        <div>
            <label class="field-label">{{ __('Code') }}</label>
            <input type="text" name="code" placeholder="BIENVENUE10" class="field-input uppercase" required>
        </div>
        <div>
            <label class="field-label">{{ __('Réduction (%)') }}</label>
            <input type="number" name="discount_percent" min="1" max="90" class="field-input" required>
        </div>
        <div>
            <label class="field-label">{{ __('Panier minimum (facultatif)') }}</label>
            <input type="number" name="min_order_amount" step="0.01" class="field-input">
        </div>
        <div>
            <label class="field-label">{{ __("Date d'expiration (facultatif)") }}</label>
            <input type="date" name="expires_at" class="field-input">
        </div>
        <div>
            <label class="field-label">{{ __("Limite d'utilisation (facultatif)") }}</label>
            <input type="number" name="usage_limit" min="1" class="field-input">
        </div>
        <div class="sm:col-span-5">
            <button type="submit" class="btn-primary">{{ __('Créer le code promo') }}</button>
        </div>
    </form>

    <div class="overflow-hidden rounded-lg border border-line bg-white">
        <table class="w-full text-sm">
            <thead class="bg-navy-50 text-left text-navy-500">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ __('Code') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Réduction') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Utilisations') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Expire le') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Statut') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach($coupons as $coupon)
                    <tr>
                        <td class="px-4 py-3 font-mono text-ink">{{ $coupon->code }}</td>
                        <td class="px-4 py-3 text-navy-500">-{{ $coupon->discount_percent }}%</td>
                        <td class="px-4 py-3 text-navy-500">{{ $coupon->times_used }}{{ $coupon->usage_limit ? ' / ' . $coupon->usage_limit : '' }}</td>
                        <td class="px-4 py-3 text-navy-500">{{ $coupon->expires_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if($coupon->is_active)
                                <span class="badge-trade">{{ __('Actif') }}</span>
                            @else
                                <span class="rounded-full bg-navy-50 px-2.5 py-1 text-xs text-navy-500">{{ __('Inactif') }}</span>
                            @endif
                        </td>
                        <td class="space-x-3 px-4 py-3 text-right">
                            <form action="{{ route('admin.coupons.toggle', $coupon) }}" method="POST" class="inline">
                                @csrf @method('PATCH')
                                <button type="submit" class="text-navy-700 hover:underline">{{ $coupon->is_active ? __('Désactiver') : __('Activer') }}</button>
                            </form>
                            <form action="{{ route('admin.coupons.destroy', $coupon) }}" method="POST" class="inline" onsubmit="return confirm({{ Illuminate\Support\Js::from(__('Supprimer ce code promo ?')) }});">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">{{ __('Supprimer') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $coupons->links() }}</div>
</div>
@endsection
