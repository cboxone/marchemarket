<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Coupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index()
    {
        $coupons = Coupon::withCount('orders')->latest()->paginate(20);

        return view('admin.coupons.index', compact('coupons'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:coupons,code', 'alpha_dash'],
            'discount_percent' => ['required', 'integer', 'min:1', 'max:90'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'expires_at' => ['nullable', 'date'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
        ]);

        $data['code'] = strtoupper($data['code']);

        $coupon = Coupon::create($data);

        AuditLog::record('coupon.created', 'Code promo créé : ' . $coupon->code . ' (-' . $coupon->discount_percent . '%)', $coupon);

        return back()->with('status', __('Code promo créé.'));
    }

    public function toggle(Coupon $coupon)
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);

        AuditLog::record(
            $coupon->is_active ? 'coupon.activated' : 'coupon.deactivated',
            ($coupon->is_active ? 'Code promo activé : ' : 'Code promo désactivé : ') . $coupon->code,
            $coupon
        );

        return back()->with('status', $coupon->is_active ? __('Code promo activé.') : __('Code promo désactivé.'));
    }

    public function destroy(Coupon $coupon)
    {
        $code = $coupon->code;
        $coupon->delete();

        AuditLog::record('coupon.deleted', 'Code promo supprimé : ' . $code);

        return back()->with('status', __('Code promo supprimé.'));
    }
}
