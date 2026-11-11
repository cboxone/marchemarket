<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Un acheteur ne peut laisser qu'un seul avis par produit ; s'il en
     * dépose un nouveau, il remplace le précédent (updateOrCreate).
     */
    public function store(Request $request, Product $product)
    {
        abort_if($request->user()->isVendor() || $request->user()->isAdmin(), 403, __('Seuls les acheteurs peuvent laisser un avis.'));

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $product->reviews()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $data
        );

        return back()->with('status', __('Merci, votre avis a été enregistré.'));
    }
}
