<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ImageOptimizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = $request->user()->vendor->products()->with('category', 'images')->latest()->paginate(10);

        return view('vendor.products.index', compact('products'));
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get();

        return view('vendor.products.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $vendor = $request->user()->vendor;

        $product = $vendor->products()->create([
            ...$data,
            'slug' => str($data['name'])->slug() . '-' . uniqid(),
        ]);

        $this->savePriceTiers($product, $data);
        $this->storeUploadedImages($request, $product);

        return redirect()->route('vendor.products.index')->with('status', __('Produit publié avec succès.'));
    }

    public function edit(Request $request, Product $product)
    {
        $this->authorizeProduct($request, $product);
        $categories = Category::orderBy('name')->get();
        $product->load('priceTiers', 'images');

        return view('vendor.products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product)
    {
        $this->authorizeProduct($request, $product);

        $data = $this->validated($request);

        // Une annonce suspendue par l'administration ne peut pas être
        // remise en ligne par le vendeur, quoi que contienne la requête.
        if ($product->isSuspended()) {
            $data['is_active'] = false;
        }

        $product->update($data);

        $product->priceTiers()->delete();
        $this->savePriceTiers($product, $data);

        $this->deleteSelectedImages($request, $product);
        $this->storeUploadedImages($request, $product);

        return redirect()->route('vendor.products.index')->with('status', __('Produit mis à jour.'));
    }

    public function destroy(Request $request, Product $product)
    {
        $this->authorizeProduct($request, $product);

        $product->purge();

        return back()->with('status', __('Produit supprimé.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit' => ['required', 'string', 'max:60'],
            'min_order_qty' => ['required', 'integer', 'min:1'],
            'max_order_qty' => ['nullable', 'integer', 'gte:min_order_qty'],
            'stock_qty' => ['required', 'integer', 'min:0'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'integer', 'min:1', 'max:90'],
            'promo_ends_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'tier2_min_qty' => ['nullable', 'integer', 'min:1'],
            'tier2_price' => ['nullable', 'numeric', 'min:0'],
            'tier3_min_qty' => ['nullable', 'integer', 'min:1'],
            'tier3_price' => ['nullable', 'numeric', 'min:0'],
            'images' => ['nullable', 'array', 'max:6'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'delete_images' => ['nullable', 'array'],
            'delete_images.*' => ['integer', 'exists:product_images,id'],
        ]);
    }

    /**
     * Enregistre les photos envoyées par le vendeur sur le disque public
     * (storage/app/public/products, exposé via `php artisan storage:link`),
     * après redimensionnement/compression par ImageOptimizer.
     */
    private function storeUploadedImages(Request $request, Product $product): void
    {
        if (! $request->hasFile('images')) {
            return;
        }

        $optimizer = app(ImageOptimizer::class);
        $nextPosition = $product->images()->max('position') + 1;

        foreach ($request->file('images') as $file) {
            $path = $optimizer->storeOptimized($file, 'products');

            ProductImage::create([
                'product_id' => $product->id,
                'path' => $path,
                'position' => $nextPosition++,
            ]);
        }
    }

    private function deleteSelectedImages(Request $request, Product $product): void
    {
        $ids = $request->input('delete_images', []);

        if (empty($ids)) {
            return;
        }

        $images = $product->images()->whereIn('id', $ids)->get();

        foreach ($images as $image) {
            Storage::disk('public')->delete($image->path);
            $image->delete();
        }
    }

    private function savePriceTiers(Product $product, array $data): void
    {
        $tier1Max = ($data['tier2_min_qty'] ?? null) ? $data['tier2_min_qty'] - 1 : null;
        PriceTier::create([
            'product_id' => $product->id,
            'min_qty' => $data['min_order_qty'],
            'max_qty' => $tier1Max,
            'unit_price' => $data['base_price'],
        ]);

        if (! empty($data['tier2_min_qty']) && ! empty($data['tier2_price'])) {
            $tier2Max = ($data['tier3_min_qty'] ?? null) ? $data['tier3_min_qty'] - 1 : null;
            PriceTier::create([
                'product_id' => $product->id,
                'min_qty' => $data['tier2_min_qty'],
                'max_qty' => $tier2Max,
                'unit_price' => $data['tier2_price'],
            ]);
        }

        if (! empty($data['tier3_min_qty']) && ! empty($data['tier3_price'])) {
            PriceTier::create([
                'product_id' => $product->id,
                'min_qty' => $data['tier3_min_qty'],
                'max_qty' => null,
                'unit_price' => $data['tier3_price'],
            ]);
        }
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        abort_unless($product->vendor_id === $request->user()->vendor->id, 403);
    }
}
