<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $cart = $request->user()->cart()->with('items.product.vendor', 'items.product.images', 'items.product.priceTiers')->firstOrCreate([]);

        return view('cart.index', compact('cart'));
    }

    public function count(Request $request)
    {
        $cart = $request->user()->cart;
        $count = $cart ? $cart->items()->count() : 0;

        return response()->json(['count' => $count]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            // Un produit désactivé par son vendeur ne doit plus pouvoir entrer
            // dans un panier, même en forgeant la requête.
            'product_id' => ['required', Rule::exists('products', 'id')->where('is_active', true)],
            'quantity' => ['required', 'integer', 'min:1'],
        ], [
            'product_id.exists' => __("Ce produit n'est plus disponible."),
        ]);

        $product = Product::findOrFail($data['product_id']);

        if ($data['quantity'] < $product->min_order_qty) {
            return back()->withErrors([
                'quantity' => __('La quantité minimale de commande pour ce produit est de :min :unit.', ['min' => $product->min_order_qty, 'unit' => $product->unit]),
            ]);
        }

        $cart = $request->user()->cart()->firstOrCreate([]);

        $item = $cart->items()->where('product_id', $product->id)->first();
        $newQuantity = $item ? $item->quantity + $data['quantity'] : $data['quantity'];

        if ($newQuantity > $product->stock_qty) {
            return back()->withErrors([
                'quantity' => __(':product : seulement :available :unit disponible(s) en stock.', [
                    'product' => $product->name,
                    'available' => $product->stock_qty,
                    'unit' => $product->unit,
                ]),
            ]);
        }

        if ($product->max_order_qty && $newQuantity > $product->max_order_qty) {
            return back()->withErrors([
                'quantity' => __('La quantité maximale de commande pour ce produit est de :max :unit.', ['max' => $product->max_order_qty, 'unit' => $product->unit]),
            ]);
        }

        if ($item) {
            $item->update(['quantity' => $newQuantity]);
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'quantity' => $newQuantity,
            ]);
        }

        // Si c'est une requête AJAX, retourne JSON pour le toast
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('Produit ajouté au panier.'),
                'cart_count' => $cart->items()->count(),
            ]);
        }

        return back()->with('status', __('Produit ajouté au panier.'));
    }

    public function update(Request $request, CartItem $cartItem)
    {
        $this->authorizeItem($request, $cartItem);

        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1']]);
        $product = $cartItem->product;

        if ($data['quantity'] < $product->min_order_qty) {
            return back()->withErrors([
                'quantity' => __('La quantité minimale pour ce produit est de :min.', ['min' => $product->min_order_qty]),
            ]);
        }

        if ($data['quantity'] > $product->stock_qty) {
            return back()->withErrors([
                'quantity' => __(':product : seulement :available :unit disponible(s) en stock.', [
                    'product' => $product->name,
                    'available' => $product->stock_qty,
                    'unit' => $product->unit,
                ]),
            ]);
        }

        if ($product->max_order_qty && $data['quantity'] > $product->max_order_qty) {
            return back()->withErrors([
                'quantity' => __('La quantité maximale pour ce produit est de :max.', ['max' => $product->max_order_qty]),
            ]);
        }

        $cartItem->update($data);

        return back()->with('status', __('Panier mis à jour.'));
    }

    public function destroy(Request $request, CartItem $cartItem)
    {
        $this->authorizeItem($request, $cartItem);
        $cartItem->delete();

        return back()->with('status', __('Article retiré du panier.'));
    }

    private function authorizeItem(Request $request, CartItem $cartItem): void
    {
        abort_unless($cartItem->cart->user_id === $request->user()->id, 403);
    }
}
