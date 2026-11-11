<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Mail\DisputeUpdatedMail;
use App\Models\Dispute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class DisputeController extends Controller
{
    use \App\Http\Controllers\Concerns\ServesPrivateFile;

    public function index(Request $request)
    {
        $query = $request->user()->vendor->disputes()->with('buyer', 'order')->latest();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return view('vendor.disputes.index', [
            'disputes' => $query->paginate(15)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function show(Request $request, Dispute $dispute)
    {
        $this->authorizeVendor($request, $dispute);

        $dispute->load('buyer', 'order.items', 'resolvedBy');

        return view('vendor.disputes.show', ['dispute' => $dispute, 'ceiling' => $dispute->ceiling()]);
    }

    public function evidence(Request $request, Dispute $dispute, int $index)
    {
        $this->authorizeVendor($request, $dispute);

        return $this->servePrivateFile($dispute->evidence_paths[$index] ?? null);
    }

    /**
     * Réponse du fournisseur : acceptation totale (immédiatement résolue,
     * remboursement tenté aussitôt si les conditions le permettent, voir
     * Dispute::isAutomaticRefundEligible()), acceptation partielle ou
     * contestation — ces deux dernières sont transmises à l'arbitrage admin
     * plutôt que tranchées unilatéralement par le fournisseur, pour protéger
     * l'acheteur d'une offre insuffisante imposée sans recours.
     */
    public function respond(Request $request, Dispute $dispute)
    {
        $this->authorizeVendor($request, $dispute);

        $data = $request->validate([
            'response_type' => ['required', Rule::in([Dispute::VENDOR_ACCEPT_FULL, Dispute::VENDOR_ACCEPT_PARTIAL, Dispute::VENDOR_CONTEST])],
            'amount' => ['required_if:response_type,' . Dispute::VENDOR_ACCEPT_PARTIAL, 'nullable', 'numeric', 'min:1'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $ceiling = $dispute->ceiling();

        if ($data['response_type'] === Dispute::VENDOR_ACCEPT_PARTIAL && (float) $data['amount'] > $ceiling) {
            return back()->withErrors(['amount' => __('Le montant proposé ne peut pas dépasser :max FCFA.', ['max' => number_format($ceiling, 0, ',', ' ')])])->withInput();
        }

        if ($data['response_type'] === Dispute::VENDOR_CONTEST && empty($data['note'])) {
            return back()->withErrors(['note' => __("Expliquez pourquoi vous contestez ce litige.")])->withInput();
        }

        $result = DB::transaction(function () use ($dispute, $data, $ceiling) {
            $locked = Dispute::whereKey($dispute->id)->lockForUpdate()->firstOrFail();

            // Une échéance dépassée est respectée immédiatement, sans
            // attendre le passage de la commande planifiée disputes:escalate-overdue
            // (même principe que pour les devis et les commandes impayées).
            if (! $locked->isOpenForVendorResponse() || $locked->vendorResponseOverdue()) {
                return null;
            }

            $locked->update([
                'vendor_response_type' => $data['response_type'],
                'vendor_response_amount' => $data['response_type'] === Dispute::VENDOR_ACCEPT_PARTIAL ? $data['amount'] : null,
                'vendor_response_note' => $data['note'] ?? null,
                'vendor_responded_at' => now(),
            ]);

            if ($data['response_type'] === Dispute::VENDOR_ACCEPT_FULL) {
                $locked->update([
                    'status' => Dispute::STATUS_RESOLVED,
                    'decision' => Dispute::DECISION_REFUND_FULL,
                    'refund_amount' => $ceiling,
                    'resolved_at' => now(),
                    'refund_status' => Dispute::REFUND_PENDING_MANUAL,
                ]);

                return 'accepted';
            }

            $locked->update([
                'status' => Dispute::STATUS_ESCALATED,
                'escalated_at' => now(),
                'admin_target_at' => now()->addDays(config('marketplace.disputes.admin_target_days')),
            ]);

            return 'escalated';
        });

        if (! $result) {
            return back()->withErrors(['dispute' => __("Ce litige n'est plus en attente de votre réponse.")]);
        }

        $dispute->refresh();

        if ($result === 'accepted' && $dispute->isAutomaticRefundEligible()) {
            $this->attemptAutomaticRefund($dispute);
        }

        Mail::to($dispute->buyer->email)->send(new DisputeUpdatedMail($dispute->buyer, $dispute, $result === 'accepted' ? 'resolved' : 'vendor_responded'));

        return redirect()->route('vendor.disputes.show', $dispute)->with('status', __('Réponse envoyée.'));
    }

    private function attemptAutomaticRefund(Dispute $dispute): void
    {
        try {
            if (app(\App\Services\KkiapayService::class)->refundTransaction($dispute->order->payment_reference)) {
                $dispute->update(['refund_status' => Dispute::REFUND_AUTOMATIC, 'refunded_at' => now()]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Échec du remboursement automatique pour le litige ' . $dispute->reference, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function authorizeVendor(Request $request, Dispute $dispute): void
    {
        abort_unless($dispute->vendor_id === $request->user()->vendor->id, 404);
    }
}
