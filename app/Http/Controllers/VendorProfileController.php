<?php

namespace App\Http\Controllers;

use App\Models\Vendor;

class VendorProfileController extends Controller
{
    public function show(Vendor $vendor)
    {
        $products = $vendor->products()
            ->where('is_active', true)
            ->with('images')
            ->latest()
            ->paginate(12);

        $packs = $vendor->packs()->where('is_active', true)->with('items.product')->get();

        return view('vendors.show', compact('vendor', 'products', 'packs'));
    }
}
