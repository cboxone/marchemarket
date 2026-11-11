<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use \App\Http\Controllers\Concerns\ExportsCsv;

    public function index()
    {
        $orders = Order::with('buyer')->latest()->paginate(15);

        return view('admin.orders.index', compact('orders'));
    }

    /** Export CSV de toutes les commandes, filtrable par date de création (`from`/`to`, YYYY-MM-DD). */
    public function export(Request $request)
    {
        $query = $this->applyDateRange(Order::query()->with('buyer'), 'created_at', $request->query('from'), $request->query('to'));

        $rows = function () use ($query) {
            foreach ($query->orderBy('id')->cursor() as $order) {
                yield [
                    $order->reference,
                    $order->created_at->format('Y-m-d H:i'),
                    $order->buyer->company_name ?: $order->buyer->name,
                    $order->buyer->email,
                    $order->shipping_city,
                    (float) $order->total_amount,
                    (float) $order->shipping_fee,
                    (float) $order->discount_amount,
                    $order->payment_method ?: '',
                    $order->payment_status,
                    $order->status,
                    $order->invoice_number ?: '',
                ];
            }
        };

        return $this->csvDownload('commandes.csv', [
            __('Référence'), __('Date'), __('Acheteur'), __('E-mail'), __('Ville'),
            __('Total'), __('Livraison'), __('Remise'), __('Paiement'), __('Statut paiement'),
            __('Statut commande'), __('Facture'),
        ], $rows());
    }

    public function updateStatus(Request $request, Order $order)
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,paid,shipped,delivered,cancelled'],
        ]);

        $previousStatus = $order->status;
        $newStatus = $data['status'];

        // Une commande annulée a déjà restitué son stock et son code promo :
        // la « rouvrir » à la main les laisserait décomptés en double.
        if ($previousStatus === Order::STATUS_CANCELLED && $newStatus !== Order::STATUS_CANCELLED) {
            return back()->withErrors(['status' => __('Une commande annulée ne peut pas être rouverte.')]);
        }

        // Le statut « payée » (et les suivants) doit refléter un vrai
        // paiement KkiaPay confirmé, pas un choix manuel : sinon le
        // vendeur pourrait expédier — et être crédité — sans encaissement.
        if (in_array($newStatus, [Order::STATUS_PAID, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED], true)
            && $order->payment_status !== 'paid') {
            return back()->withErrors(['status' => __('Cette commande n\'a pas de paiement confirmé : elle ne peut pas passer à ce statut.')]);
        }

        // Inversement, une commande réellement payée ne peut pas redevenir « en attente ».
        if ($newStatus === Order::STATUS_PENDING && $order->payment_status === 'paid') {
            return back()->withErrors(['status' => __('Une commande payée ne peut pas repasser en attente.')]);
        }

        if ($newStatus === Order::STATUS_CANCELLED) {
            if ($order->cancel(__('Annulée par l\'administration'))) {
                $order->attemptRefund();
            }
        } else {
            $order->update(['status' => $newStatus]);
        }

        AuditLog::record(
            'order.status_changed',
            "Commande {$order->reference} : statut changé de « {$previousStatus} » à « {$newStatus} »",
            $order
        );

        $message = __('Statut de la commande mis à jour.');

        if ($newStatus === Order::STATUS_CANCELLED && $order->payment_status === 'paid' && ! $order->refunded_at) {
            $message .= ' ' . __('Le remboursement automatique a échoué : à traiter manuellement.');
        }

        return back()->with('status', $message);
    }
}
