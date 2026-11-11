<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $products = $request->user()->wishlistedProducts()
            ->with('images', 'vendor')
            ->latest('wishlists.created_at')
            ->paginate(12);

        return view('wishlist.index', compact('products'));
    }

    /**
     * Ajoute ou retire un produit des favoris selon son état actuel
     * (bouton cœur unique sur la fiche produit / carte produit).
     */
    public function toggle(Request $request, Product $product)
    {
        $user = $request->user();

        if ($user->wishlistedProducts()->where('product_id', $product->id)->exists()) {
            $user->wishlistedProducts()->detach($product->id);
            $status = __('Produit retiré de vos favoris.');
        } else {
            $user->wishlistedProducts()->attach($product->id);
            $status = __('Produit ajouté à vos favoris.');
        }

        return back()->with('status', $status);
    }
}
