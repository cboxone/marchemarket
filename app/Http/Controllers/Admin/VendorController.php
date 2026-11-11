<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Vendor;

class VendorController extends Controller
{
    public function index()
    {
        $vendors = Vendor::withCount('products')->latest()->paginate(15);

        return view('admin.vendors.index', compact('vendors'));
    }

    public function toggleVerification(Vendor $vendor)
    {
        $vendor->update(['is_verified' => ! $vendor->is_verified]);

        AuditLog::record(
            $vendor->is_verified ? 'vendor.verified' : 'vendor.unverified',
            ($vendor->is_verified ? 'Vendeur vérifié : ' : 'Vérification retirée : ') . $vendor->store_name,
            $vendor
        );

        return back()->with('status', $vendor->is_verified ? __('Vendeur vérifié.') : __('Vérification retirée.'));
    }
}
