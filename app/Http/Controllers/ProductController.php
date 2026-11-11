<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()
            ->withCount('orderItems')
            ->with(['images', 'vendor', 'category'])
            ->where('is_active', true);

        if ($search = $request->string('q')->trim()->toString()) {
            // Cherche aussi dans la description : un acheteur qui tape
            // « sac 25kg » doit trouver un produit qui n'a ce détail que
            // dans son descriptif, pas seulement dans son nom.
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"));
        }

        if ($categorySlug = $request->string('categorie')->toString()) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
        }

        if ($minPrice = $request->input('prix_min')) {
            $query->where('base_price', '>=', $minPrice);
        }

        if ($maxPrice = $request->input('prix_max')) {
            $query->where('base_price', '<=', $maxPrice);
        }

        if ($city = $request->string('ville')->toString()) {
            $query->whereHas('vendor', fn ($q) => $q->where('city', $city));
        }

        if ($request->boolean('en_stock')) {
            $query->where('stock_qty', '>', 0);
        }

        if ($request->boolean('verifies')) {
            $query->whereHas('vendor', fn ($q) => $q->where('is_verified', true));
        }

        match ($request->string('tri')->toString()) {
            'prix_asc' => $query->orderBy('base_price'),
            'prix_desc' => $query->orderByDesc('base_price'),
            'popularite' => $query->orderByDesc('order_items_count'),
            default => $query->orderByDesc('created_at'),
        };

        $products = $query->paginate(12)->withQueryString();
        $categories = Category::topLevel();

        // Villes des fournisseurs ayant au moins un produit actif — sert de
        // liste au filtre « Ville », plutôt qu'un champ libre qui ne
        // trouverait rien à la moindre faute de frappe ou variante d'orthographe.
        $cities = Vendor::query()
            ->whereHas('products', fn ($q) => $q->where('is_active', true))
            ->whereNotNull('city')
            ->distinct()
            ->orderBy('city')
            ->pluck('city');

        return view('products.index', [
            'products' => $products,
            'categories' => $categories,
            'cities' => $cities,
            'filters' => $request->only(['q', 'categorie', 'prix_min', 'prix_max', 'tri', 'ville', 'en_stock', 'verifies']),
        ]);
    }

    public function show(Request $request, Product $product)
    {
        // Un produit désactivé n'est plus consultable publiquement (il n'est
        // déjà plus listé ni dans le sitemap). Son vendeur et les admins
        // gardent l'accès pour le prévisualiser.
        if (! $product->is_active) {
            $user = $request->user();
            $canPreview = $user && ($user->isAdmin() || ($user->isVendor() && $user->vendor?->id === $product->vendor_id));

            abort_unless($canPreview, 404);
        }

        $product->load(['images', 'priceTiers', 'vendor', 'category', 'reviews.user']);

        $related = Product::query()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->with('images')
            ->take(4)
            ->get();

        return view('products.show', compact('product', 'related'));
    }

    /**
     * Suggestions de recherche pour la barre de recherche (autocomplétion),
     * interrogées en AJAX pendant que l'utilisateur tape.
     */
    public function autocomplete(Request $request)
    {
        $search = $request->string('q')->trim()->toString();

        if (mb_strlen($search) < 2) {
            return response()->json([]);
        }

        $products = Product::query()
            ->where('is_active', true)
            ->where('name', 'like', "%{$search}%")
            ->orderByDesc('created_at')
            ->take(8)
            ->get(['id', 'name', 'slug'])
            ->map(fn (Product $product) => [
                'name' => $product->name,
                'url' => route('products.show', $product),
            ]);

        return response()->json($products);
    }
}
