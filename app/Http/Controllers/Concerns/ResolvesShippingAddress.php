<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Résout l'adresse de livraison d'une commande ou d'un devis accepté, à
 * partir soit d'une adresse enregistrée dans le carnet (`address_id`), soit
 * de champs saisis à la main — auquel cas `save_address` coché l'ajoute au
 * carnet pour la prochaine fois. Partagé par CheckoutController et
 * QuoteRequestController, qui exposent tous les deux ce même choix à
 * l'acheteur au même endroit du parcours (juste avant de créer la commande).
 */
trait ResolvesShippingAddress
{
    /** @return array{shipping_address: string, shipping_city: string, shipping_country: string, shipping_phone: string} */
    private function resolveShippingAddress(Request $request): array
    {
        $request->validate([
            'address_id' => ['nullable', 'integer'],
            'shipping_address' => ['required_without:address_id', 'nullable', 'string', 'max:255'],
            'shipping_city' => ['required_without:address_id', 'nullable', 'string', 'max:120'],
            'shipping_country' => ['required_without:address_id', 'nullable', 'string', 'max:120'],
            'shipping_phone' => ['required_without:address_id', 'nullable', 'string', 'max:30'],
        ]);

        if ($request->filled('address_id')) {
            $address = $request->user()->addresses()->find($request->integer('address_id'));

            if (! $address) {
                throw ValidationException::withMessages(['address_id' => __("Cette adresse n'existe pas ou ne vous appartient pas.")]);
            }

            return [
                'shipping_address' => $address->address,
                'shipping_city' => $address->city,
                'shipping_country' => $address->country,
                'shipping_phone' => $address->phone,
            ];
        }

        $data = $request->only(['shipping_address', 'shipping_city', 'shipping_country', 'shipping_phone']);

        if ($request->boolean('save_address')) {
            $request->user()->addresses()->create([
                'label' => $request->string('address_label')->trim()->toString() ?: __('Adresse'),
                'address' => $data['shipping_address'],
                'city' => $data['shipping_city'],
                'country' => $data['shipping_country'],
                'phone' => $data['shipping_phone'],
                'is_default' => $request->user()->addresses()->doesntExist(),
            ]);
        }

        return $data;
    }
}
