<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        $addresses = $request->user()->addresses;

        return view('addresses.index', compact('addresses'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        // La toute première adresse enregistrée devient automatiquement la
        // valeur par défaut : sans ça, elle ne serait proposée nulle part
        // tant que l'acheteur n'aurait pas pensé à la marquer lui-même.
        $data['is_default'] = $data['is_default'] || $request->user()->addresses()->doesntExist();

        $request->user()->addresses()->create($data);

        return back()->with('status', __('Adresse enregistrée.'));
    }

    public function update(Request $request, Address $address)
    {
        $this->authorize($request, $address);

        $address->update($this->validated($request));

        return back()->with('status', __('Adresse mise à jour.'));
    }

    public function destroy(Request $request, Address $address)
    {
        $this->authorize($request, $address);

        $address->delete();

        return back()->with('status', __('Adresse supprimée.'));
    }

    public function setDefault(Request $request, Address $address)
    {
        $this->authorize($request, $address);

        $address->update(['is_default' => true]);

        return back()->with('status', __('Adresse par défaut mise à jour.'));
    }

    private function authorize(Request $request, Address $address): void
    {
        abort_unless($address->user_id === $request->user()->id, 403);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'recipient_name' => ['nullable', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        // Une case à cocher absente du formulaire n'apparaît pas dans la
        // requête : $request->boolean() la traite correctement comme false
        // plutôt que de la faire disparaître du tableau validé.
        $data['is_default'] = $request->boolean('is_default');

        return $data;
    }
}
