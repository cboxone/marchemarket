<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ProductModeratedMail;
use App\Models\AuditLog;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Modération du catalogue : retirer une annonce frauduleuse ou non conforme
 * sans avoir à bannir tout le compte du vendeur.
 */
class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()->with(['vendor', 'category', 'images']);

        if ($search = $request->string('q')->trim()->toString()) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhereHas('vendor', fn ($v) => $v->where('store_name', 'like', "%{$search}%")));
        }

        match ($request->string('status')->toString()) {
            'active' => $query->where('is_active', true)->whereNull('admin_suspended_at'),
            'suspended' => $query->whereNotNull('admin_suspended_at'),
            'inactive' => $query->where('is_active', false)->whereNull('admin_suspended_at'),
            default => null,
        };

        $products = $query->latest()->paginate(20)->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function suspend(Request $request, Product $product)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        if ($product->isSuspended()) {
            return back()->withErrors(['product' => __('Cette annonce est déjà suspendue.')]);
        }

        // forceFill : ces champs n'appartiennent pas aux attributs modifiables
        // en masse (le vendeur ne doit jamais pouvoir les écrire).
        $product->forceFill([
            'is_active' => false,
            'admin_suspended_at' => now(),
            'admin_suspension_reason' => $data['reason'],
        ])->save();

        AuditLog::record(
            'product.suspended',
            "Annonce suspendue : {$product->name} ({$product->vendor->store_name}) — {$data['reason']}",
            $product
        );

        Mail::to($product->vendor->user->email)->send(
            new ProductModeratedMail($product->vendor->user, $product->name, 'suspended', $data['reason'])
        );

        return back()->with('status', __('Annonce suspendue.'));
    }

    public function restore(Product $product)
    {
        if (! $product->isSuspended()) {
            return back()->withErrors(['product' => __("Cette annonce n'est pas suspendue.")]);
        }

        $product->forceFill([
            'is_active' => true,
            'admin_suspended_at' => null,
            'admin_suspension_reason' => null,
        ])->save();

        AuditLog::record(
            'product.restored',
            "Annonce rétablie : {$product->name} ({$product->vendor->store_name})",
            $product
        );

        return back()->with('status', __('Annonce rétablie.'));
    }

    public function destroy(Request $request, Product $product)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        // Capturés avant la suppression : le produit n'existera plus quand
        // le mail sera envoyé par la file d'attente.
        $name = $product->name;
        $vendor = $product->vendor;
        $owner = $vendor->user;

        $product->purge();

        AuditLog::record(
            'product.deleted',
            "Annonce supprimée : {$name} ({$vendor->store_name})" . (! empty($data['reason']) ? " — {$data['reason']}" : '')
        );

        Mail::to($owner->email)->send(new ProductModeratedMail($owner, $name, 'removed', $data['reason'] ?? null));

        return back()->with('status', __('Annonce supprimée.'));
    }
}
