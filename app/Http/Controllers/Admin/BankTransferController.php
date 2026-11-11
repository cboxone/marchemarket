<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\BankTransferStatusMail;
use App\Models\AuditLog;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Rapprochement manuel des virements bancaires déclarés par les acheteurs :
 * KkiaPay ne voit jamais ces paiements, il n'y a donc aucune vérification
 * automatique — un admin compare la preuve déposée à son relevé bancaire
 * réel avant de confirmer ou de rejeter.
 */
class BankTransferController extends Controller
{
    use \App\Http\Controllers\Concerns\ServesPrivateFile;

    public function index()
    {
        $orders = Order::query()
            ->whereNotNull('bank_transfer_declared_at')
            ->where('payment_status', '!=', 'paid')
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->with('buyer')
            ->oldest('bank_transfer_declared_at')
            ->paginate(20);

        return view('admin.bank-transfers.index', compact('orders'));
    }

    public function show(Order $order)
    {
        abort_unless($order->hasChosenBankTransfer(), 404);

        $order->load('buyer', 'items.vendor');

        return view('admin.bank-transfers.show', compact('order'));
    }

    public function proof(Order $order)
    {
        abort_unless($order->hasChosenBankTransfer(), 404);

        return $this->servePrivateFile($order->bank_transfer_proof_path);
    }

    public function confirm(Order $order)
    {
        $confirmed = DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            if (! $locked->hasPendingBankTransferDeclaration()) {
                return false;
            }

            $locked->forceFill([
                'payment_status' => 'paid',
                'status' => Order::STATUS_PAID,
                'bank_transfer_confirmed_by' => auth()->id(),
            ])->save();

            $locked->assignInvoiceNumber();

            return true;
        });

        if (! $confirmed) {
            return back()->withErrors(['bank_transfer' => __("Cette commande n'a pas (ou plus) de virement en attente de vérification.")]);
        }

        $order->refresh();

        AuditLog::record(
            'bank_transfer.confirmed',
            "Virement confirmé pour la commande {$order->reference} (" . number_format((float) $order->total_amount, 0, ',', ' ') . ' FCFA)',
            $order
        );

        Mail::to($order->buyer->email)->send(new BankTransferStatusMail($order->buyer, $order, 'confirmed'));

        return redirect()->route('admin.bank-transfers.index')->with('status', __('Virement confirmé, commande marquée payée.'));
    }

    public function reject(Request $request, Order $order)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $rejected = DB::transaction(function () use ($order, $data) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            if (! $locked->hasPendingBankTransferDeclaration()) {
                return false;
            }

            // declared_at est remis à null : la commande retourne dans
            // l'état « virement choisi, pas (encore, ou plus) déclaré », ce
            // qui laisse à l'acheteur la fenêtre de grâce restante pour
            // déposer une preuve corrigée — et à orders:cancel-unpaid la
            // possibilité de reprendre la main si cette fenêtre est dépassée.
            $locked->forceFill([
                'bank_transfer_declared_at' => null,
                'bank_transfer_rejected_reason' => $data['reason'],
                'bank_transfer_rejected_at' => now(),
            ])->save();

            return true;
        });

        if (! $rejected) {
            return back()->withErrors(['bank_transfer' => __("Cette commande n'a pas (ou plus) de virement en attente de vérification.")]);
        }

        $order->refresh();

        AuditLog::record('bank_transfer.rejected', "Virement rejeté pour la commande {$order->reference} : {$data['reason']}", $order);

        Mail::to($order->buyer->email)->send(new BankTransferStatusMail($order->buyer, $order, 'rejected'));

        return redirect()->route('admin.bank-transfers.index')->with('status', __('Virement rejeté ; l\'acheteur a été prévenu.'));
    }
}
