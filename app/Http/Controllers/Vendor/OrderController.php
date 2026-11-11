<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use \App\Http\Controllers\Concerns\ExportsCsv;

    public function index(Request $request)
    {
        $vendor = $request->user()->vendor;

        $orders = Order::whereHas('items', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->with(['buyer', 'items' => fn ($q) => $q->where('vendor_id', $vendor->id)])
            ->latest()
            ->paginate(15);

        return view('vendor.orders.index', compact('orders'));
    }

    /**
     * Export CSV des lignes de commande de ce vendeur (une ligne par
     * article, pas par commande : une commande à plusieurs de ses produits
     * y apparaît sur plusieurs lignes), filtrable par date de commande
     * (`from`/`to`, YYYY-MM-DD).
     */
    public function export(Request $request)
    {
        $vendor = $request->user()->vendor;

        $query = $this->applyDateRange(
            OrderItem::query()->where('vendor_id', $vendor->id)->with(['order.buyer', 'product']),
            'created_at',
            $request->query('from'),
            $request->query('to'),
        );

        $rows = function () use ($query) {
            foreach ($query->orderBy('id')->cursor() as $item) {
                yield [
                    $item->order->reference,
                    $item->order->created_at->format('Y-m-d H:i'),
                    $item->order->buyer->company_name ?: $item->order->buyer->name,
                    $item->product_name,
                    $item->quantity,
                    (float) $item->unit_price,
                    $item->subtotal(),
                    $item->fulfillment_status,
                    $item->order->payment_status,
                ];
            }
        };

        return $this->csvDownload('mes-commandes.csv', [
            __('Commande'), __('Date'), __('Acheteur'), __('Produit'), __('Quantité'),
            __('Prix unitaire'), __('Sous-total'), __('Statut expédition'), __('Statut paiement'),
        ], $rows());
    }

    public function show(Request $request, Order $order)
    {
        $vendor = $request->user()->vendor;

        $order->load(['buyer', 'items' => fn ($q) => $q->where('vendor_id', $vendor->id)]);

        abort_if($order->items->isEmpty(), 404);

        return view('vendor.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, OrderItem $orderItem)
    {
        abort_unless($orderItem->vendor_id === $request->user()->vendor->id, 403);

        $data = $request->validate([
            'fulfillment_status' => ['required', 'in:pending,shipped,delivered'],
        ]);

        // Un vendeur n'expédie que ce qui est réellement encaissé : sans ce
        // contrôle, il pouvait marquer « expédiée » une commande jamais
        // payée (ou déjà annulée, dont le stock a été restitué).
        $order = $orderItem->order;

        if ($order->status === Order::STATUS_CANCELLED || $order->payment_status !== 'paid') {
            return back()->withErrors(['fulfillment_status' => __('Cette commande n\'est pas payée ou a été annulée : elle ne peut pas être expédiée.')]);
        }

        // Une date déjà posée n'est jamais effacée ni réécrite : le calcul du
        // solde retirable et la fenêtre de litige de l'acheteur en dépendent.
        $timestamps = [];
        if ($data['fulfillment_status'] === OrderItem::FULFILLMENT_SHIPPED && ! $orderItem->shipped_at) {
            $timestamps['shipped_at'] = now();
        } elseif ($data['fulfillment_status'] === OrderItem::FULFILLMENT_DELIVERED) {
            if (! $orderItem->shipped_at) {
                $timestamps['shipped_at'] = now();
            }
            if (! $orderItem->delivered_at) {
                $timestamps['delivered_at'] = now();
            }
        }

        $orderItem->update([...$data, ...$timestamps]);
        $order->syncStatusFromItems();

        return back()->with('status', __('Statut mis à jour.'));
    }
}
