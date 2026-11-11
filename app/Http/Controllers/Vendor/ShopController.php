<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Mail\PayoutAccountChangedMail;
use App\Models\Vendor;
use App\Services\ImageOptimizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * « Ma boutique » : profil public du vendeur (nom, description, logo, ville)
 * et compte Mobile Money sur lequel il demande ses retraits.
 */
class ShopController extends Controller
{
    public function edit(Request $request)
    {
        return view('vendor.shop.edit', ['vendor' => $request->user()->vendor]);
    }

    public function update(Request $request)
    {
        $vendor = $request->user()->vendor;

        // Normalise le numéro avant validation : « +229 96 00-00.00 » → « +22996000000 ».
        if ($request->filled('payout_phone')) {
            $request->merge(['payout_phone' => preg_replace('/[^\d+]/', '', (string) $request->input('payout_phone'))]);
        }

        $newProvider = $request->input('payout_provider') ?: null;
        $newPhone = $request->input('payout_phone') ?: null;
        $payoutChanged = $newProvider !== $vendor->payout_provider || $newPhone !== $vendor->payout_phone;

        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'country' => ['required', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'payout_provider' => ['nullable', Rule::in(array_keys(Vendor::PAYOUT_PROVIDERS)), 'required_with:payout_phone'],
            'payout_phone' => ['nullable', 'regex:/^\+?\d{8,15}$/', 'required_with:payout_provider'],
            // Modifier l'endroit où l'argent est envoyé exige de reconfirmer
            // son mot de passe : une session volée ne suffit pas à détourner
            // les reversements.
            'current_password' => $payoutChanged ? ['required', 'current_password'] : ['nullable'],
        ], [
            'payout_phone.regex' => __('Numéro de téléphone invalide (8 à 15 chiffres, indicatif éventuel avec +).'),
            'current_password.required' => __('Confirmez votre mot de passe pour modifier le compte de retrait.'),
            'current_password.current_password' => __('Mot de passe incorrect.'),
        ]);

        $renamed = $data['store_name'] !== $vendor->store_name;
        $revoked = false;

        $vendor->fill([
            'store_name' => $data['store_name'],
            'description' => $data['description'] ?? null,
            'country' => $data['country'],
            'city' => $data['city'] ?? null,
            'payout_provider' => $newProvider,
            'payout_phone' => $newPhone,
        ]);

        // Le badge « Vérifié » atteste une entité précise : un vendeur vérifié
        // qui change de nom doit être revalidé par l'équipe.
        if ($renamed && $vendor->is_verified) {
            $vendor->is_verified = false;
            $revoked = true;
        }

        if ($request->hasFile('logo')) {
            $this->deleteLogo($vendor);
            $vendor->logo_path = app(ImageOptimizer::class)->storeOptimized($request->file('logo'), 'logos');
        } elseif ($request->boolean('remove_logo')) {
            $this->deleteLogo($vendor);
            $vendor->logo_path = null;
        }

        $vendor->save();

        if ($payoutChanged) {
            Mail::to($request->user()->email)->send(new PayoutAccountChangedMail($request->user(), $vendor->payoutAccountLabel()));
        }

        $message = __('Boutique mise à jour.');

        if ($revoked) {
            $message .= ' ' . __('Votre boutique a été renommée : le badge « Vérifié » doit être revalidé par notre équipe.');
        }

        return redirect()->route('vendor.shop.edit')->with('status', $message);
    }

    private function deleteLogo(Vendor $vendor): void
    {
        if ($vendor->logo_path) {
            Storage::disk('public')->delete($vendor->logo_path);
        }
    }
}
