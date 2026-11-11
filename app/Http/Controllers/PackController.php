<?php

namespace App\Http\Controllers;

use App\Models\Pack;
use Illuminate\Http\Request;

class PackController extends Controller
{
    public function index()
    {
        $packs = Pack::query()
            ->where('is_active', true)
            ->with('items.product.images', 'vendor')
            ->latest()
            ->paginate(9);

        return view('packs.index', compact('packs'));
    }

    public function show(Pack $pack)
    {
        $pack->load('items.product.images', 'vendor');

        return view('packs.show', compact('pack'));
    }

    /**
     * Ajoute en une seule fois tous les produits du pack au panier, dans les
     * quantités définies par le vendeur.
     *
     * Limite assumée : le panier facture ensuite chaque produit à son propre
     * prix (dégressif/promo), pas au prix de pack négocié — voir README.
     */
    public function addToCart(Request $request, Pack $pack)
    {
        $cart = $request->user()->cart()->firstOrCreate([]);
        $pack->load('items.product');

        $added = 0;

        foreach ($pack->items as $packItem) {
            // Un produit retiré de la vente depuis la création du pack est
            // simplement ignoré : les autres sont ajoutés normalement.
            if (! $packItem->product->is_active) {
                continue;
            }

            // Si le produit a relevé sa quantité minimale de commande depuis
            // la création du pack, on respecte quand même ce minimum.
            $quantity = max($packItem->quantity, $packItem->product->min_order_qty);

            $existing = $cart->items()->where('product_id', $packItem->product_id)->first();

            if ($existing) {
                $existing->update(['quantity' => $existing->quantity + $quantity]);
            } else {
                $cart->items()->create([
                    'product_id' => $packItem->product_id,
                    'quantity' => $quantity,
                ]);
            }

            $added++;
        }

        if ($added === 0) {
            return back()->withErrors(['pack' => __("Aucun produit de ce pack n'est actuellement disponible.")]);
        }

        return redirect()->route('cart.index')->with('status', __('Les produits du pack ont été ajoutés à votre panier.'));
    }
}
