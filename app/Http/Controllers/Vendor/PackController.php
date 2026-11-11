<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Pack;
use Illuminate\Http\Request;

class PackController extends Controller
{
    public function index(Request $request)
    {
        $packs = $request->user()->vendor->packs()->with('items.product')->latest()->paginate(10);

        return view('vendor.packs.index', compact('packs'));
    }

    public function create(Request $request)
    {
        $products = $request->user()->vendor->products()->where('is_active', true)->orderBy('name')->get();

        return view('vendor.packs.create', compact('products'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'pack_price' => ['required', 'numeric', 'min:0'],
            'product_ids' => ['required', 'array', 'min:2'],
            'product_ids.*' => ['exists:products,id'],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['integer', 'min:1'],
        ]);

        $vendor = $request->user()->vendor;

        // On s'assure que le vendeur ne peut assembler que ses propres produits.
        $ownProductIds = $vendor->products()->pluck('id')->all();
        $selectedIds = array_intersect($data['product_ids'], $ownProductIds);

        abort_if(count($selectedIds) < 2, 422, __('Un pack doit contenir au moins deux de vos produits.'));

        $pack = $vendor->packs()->create([
            'name' => $data['name'],
            'slug' => str($data['name'])->slug() . '-' . uniqid(),
            'description' => $data['description'] ?? null,
            'pack_price' => $data['pack_price'],
        ]);

        foreach ($selectedIds as $productId) {
            $pack->items()->create([
                'product_id' => $productId,
                'quantity' => $data['quantities'][$productId] ?? 1,
            ]);
        }

        return redirect()->route('vendor.packs.index')->with('status', __('Pack créé avec succès.'));
    }

    public function destroy(Request $request, Pack $pack)
    {
        abort_unless($pack->vendor_id === $request->user()->vendor->id, 403);
        $pack->delete();

        return back()->with('status', __('Pack supprimé.'));
    }
}
