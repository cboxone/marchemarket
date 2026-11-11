<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $vendor = $request->user()->vendor;

        $breakdown = $vendor->revenueBreakdown();

        $stats = [
            'products_count' => $vendor->products()->count(),
            'active_products_count' => $vendor->products()->where('is_active', true)->count(),
            'orders_count' => $vendor->orderItems()->distinct('order_id')->count('order_id'),
            // Même définition que availableBalance() : uniquement les commandes
            // réellement payées et non annulées. « revenue » est BRUT ; le solde
            // disponible est net de commission (voir revenueBreakdown()).
            'revenue' => $breakdown['gross'],
            'commission' => $breakdown['commission'],
            'available_balance' => $vendor->availableBalance(),
            // Demandes de devis qui attendent une réponse (échéance non dépassée).
            'pending_quotes' => $vendor->quoteRequests()->where('status', 'pending')->where('expires_at', '>', now())->count(),
            'open_disputes' => $vendor->disputes()->where('status', 'open')->count(),
            'pending_release_balance' => $vendor->pendingReleaseBalance(),
        ];

        $recentOrderItems = $vendor->orderItems()->with('order', 'product')->latest()->take(8)->get();

        return view('vendor.dashboard', compact('vendor', 'stats', 'recentOrderItems'));
    }
}
