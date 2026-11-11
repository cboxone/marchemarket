<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = $request->user()->orders()->latest()->paginate(10);

        return view('orders.index', compact('orders'));
    }

    public function show(Request $request, Order $order)
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);
        $order->load('items.vendor');

        return view('orders.show', compact('order'));
    }

    /**
     * Annulation d'une commande par l'acheteur, tant qu'aucun vendeur n'a
     * commencé à l'expédier. Si la commande était payée, tente un
     * remboursement automatique via KkiaPay ; en cas d'échec du
     * remboursement (API indisponible, etc.), la commande est tout de même
     * annulée et le stock restitué — le remboursement reste à traiter
     * manuellement par l'équipe (voir `refunded_at` resté à null).
     */
    public function cancel(Request $request, Order $order)
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);
        abort_unless($order->isCancellable(), 422, __('Cette commande ne peut plus être annulée.'));

        $data = $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'max:500'],
        ]);

        // Le remboursement n'est tenté que si CETTE requête a réellement
        // annulé la commande : deux clics simultanés ne rembourseront donc
        // jamais deux fois.
        if ($order->cancel($data['cancellation_reason'] ?? null)) {
            $order->attemptRefund();
        }

        $message = $order->refunded_at
            ? __('Commande annulée et remboursée.')
            : __('Commande annulée. Si elle était payée, le remboursement sera traité sous peu par notre équipe.');

        return redirect()->route('orders.show', $order)->with('status', $message);
    }
}
