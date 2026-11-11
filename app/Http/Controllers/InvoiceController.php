<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    /** Facture imprimable dans le navigateur (Ctrl+P → « Enregistrer au format PDF »). */
    public function show(Request $request, Order $order)
    {
        if ($redirect = $this->prepare($request, $order)) {
            return $redirect;
        }

        return view('invoices.show', $this->data($order, false));
    }

    /** Téléchargement direct en PDF. */
    public function download(Request $request, Order $order)
    {
        if ($redirect = $this->prepare($request, $order)) {
            return $redirect;
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::class;

        if (! class_exists($pdf)) {
            abort(503, __('Le téléchargement PDF nécessite le paquet barryvdh/laravel-dompdf (composer install).'));
        }

        return $pdf::loadView('invoices.show', $this->data($order, true))
            ->setPaper('a4')
            ->download('Facture-' . $order->invoice_number . '.pdf');
    }

    /**
     * Autorise (acheteur de la commande ou admin), vérifie qu'une facture
     * existe pour cette commande, et numérote au besoin les commandes payées
     * avant la mise en place des factures (voir aussi `invoices:backfill`).
     */
    private function prepare(Request $request, Order $order): ?RedirectResponse
    {
        $user = $request->user();
        $isBuyer = $order->buyer_id === $user->id;

        abort_unless($isBuyer || $user->isAdmin(), 403);

        if (! $order->isInvoiceable()) {
            // Pas de facture avant paiement, ni pour une commande annulée.
            abort_unless($isBuyer, 404);

            return redirect()->route('orders.show', $order)
                ->withErrors(['invoice' => __("Aucune facture n'est disponible pour cette commande : elle n'est pas payée ou a été annulée.")]);
        }

        $order->assignInvoiceNumber();

        return null;
    }

    private function data(Order $order, bool $pdf): array
    {
        $order->load('items.vendor', 'buyer', 'coupon');

        return [
            'order' => $order,
            'company' => config('marketplace.company'),
            'footer' => config('marketplace.invoice_footer'),
            'pdf' => $pdf,
        ];
    }
}
