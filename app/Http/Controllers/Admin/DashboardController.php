<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Dispute;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'vendors_count' => Vendor::count(),
            'pending_verification_count' => Vendor::where('kyc_status', Vendor::KYC_PENDING)->count(),
            'products_count' => Product::count(),
            'customers_count' => User::where('role', User::ROLE_CUSTOMER)->count(),
            'orders_count' => Order::count(),
            // Basé sur payment_status (statut réel du paiement KkiaPay), pas
            // sur status : ce dernier peut être modifié manuellement par un
            // admin (ex. correction d'une commande hors-ligne) sans qu'un
            // paiement ait réellement eu lieu, ce qui fausserait ce total.
            'revenue' => Order::where('payment_status', 'paid')->where('status', '!=', 'cancelled')->sum('total_amount'),
        ];

        // Commission cumulée de la plateforme (taux figé sur chaque article).
        // Calculée en PHP car proratisée par commande ; à passer en
        // agrégat SQL / cache si le volume de commandes devient important.
        $stats['commission'] = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('payment_status', 'paid')->where('status', '!=', 'cancelled'))
            ->with('order.items')
            ->get()
            ->sum(fn (OrderItem $item) => $item->commissionAmount());

        $stats['escalated_disputes'] = Dispute::where('status', Dispute::STATUS_ESCALATED)->count();
        $stats['pending_bank_transfers'] = Order::whereNotNull('bank_transfer_declared_at')
            ->where('payment_status', '!=', 'paid')->where('status', '!=', Order::STATUS_CANCELLED)->count();

        $recentOrders = Order::with('buyer')->latest()->take(8)->get();

        return view('admin.dashboard', compact('stats', 'recentOrders'));
    }
}
