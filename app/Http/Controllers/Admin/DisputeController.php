<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\DisputeUpdatedMail;
use App\Models\AuditLog;
use App\Models\Dispute;
use App\Services\KkiapayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class DisputeController extends Controller
{
    use \App\Http\Controllers\Concerns\ServesPrivateFile;

    public function index(Request $request)
    {
        $query = Dispute::query()->with(['buyer', 'vendor', 'order']);

        match ($request->string('status')->toString()) {
            'escalated' => $query->where('status', Dispute::STATUS_ESCALATED),
            'open' => $query->where('status', Dispute::STATUS_OPEN),
            'resolved' => $query->where('status', Dispute::STATUS_RESOLVED),
            default => $query->whereIn('status', [Dispute::STATUS_OPEN, Dispute::STATUS_ESCALATED]),
        };

        $disputes = $query->orderByRaw("status = 'escalated' desc")->latest('escalated_at')->paginate(20)->withQueryString();

        return view('admin.disputes.index', [
            'disputes' => $disputes,
            'status' => $request->string('status')->toString(),
        ]);
    }

    public function show(Request $request, Dispute $dispute)
    {
        $dispute->load('buyer', 'vendor', 'order.items', 'resolvedBy');

        // Historique des litiges de ce fournisseur, pour repérer un profil à risque.
        $vendorHistory = Dispute::where('vendor_id', $dispute->vendor_id)->where('id', '!=', $dispute->id)
            ->whereIn('status', [Dispute::STATUS_RESOLVED, Dispute::STATUS_ESCALATED])
            ->count();
        $vendorLossCount = Dispute::where('vendor_id', $dispute->vendor_id)
            ->where('status', Dispute::STATUS_RESOLVED)
            ->whereIn('decision', [Dispute::DECISION_REFUND_FULL, Dispute::DECISION_REFUND_PARTIAL])
            ->count();

        return view('admin.disputes.show', [
            'dispute' => $dispute,
            'ceiling' => $dispute->ceiling(),
            'vendorHistory' => $vendorHistory,
            'vendorLossCount' => $vendorLossCount,
        ]);
    }

    public function evidence(Dispute $dispute, int $index)
    {
        return $this->servePrivateFile($dispute->evidence_paths[$index] ?? null);
    }

    /**
     * Arbitrage : rejet, remboursement total ou partiel (plafonné au montant
     * encaissé pour ce fournisseur sur cette commande). Seule décision qui
     * peut clore un litige escaladé — le fournisseur ne peut plus agir une
     * fois le dossier transmis à l'admin.
     */
    public function resolve(Request $request, Dispute $dispute)
    {
        abort_unless($dispute->isAwaitingAdmin(), 404);

        $ceiling = $dispute->ceiling();

        $data = $request->validate([
            'decision' => ['required', Rule::in([Dispute::DECISION_REFUND_FULL, Dispute::DECISION_REFUND_PARTIAL, Dispute::DECISION_REJECT])],
            'amount' => ['required_if:decision,' . Dispute::DECISION_REFUND_PARTIAL, 'nullable', 'numeric', 'min:0.01', 'max:' . $ceiling],
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $refundAmount = match ($data['decision']) {
            Dispute::DECISION_REFUND_FULL => $ceiling,
            Dispute::DECISION_REFUND_PARTIAL => (float) $data['amount'],
            default => 0.0,
        };

        DB::transaction(function () use ($request, $dispute, $data, $refundAmount) {
            $dispute->update([
                'status' => Dispute::STATUS_RESOLVED,
                'decision' => $data['decision'],
                'refund_amount' => $refundAmount,
                'resolution_note' => $data['note'],
                'resolved_by' => $request->user()->id,
                'resolved_at' => now(),
                'refund_status' => $refundAmount > 0 ? Dispute::REFUND_PENDING_MANUAL : Dispute::REFUND_NOT_APPLICABLE,
            ]);
        });

        $dispute->refresh();

        if ($refundAmount > 0 && $dispute->isAutomaticRefundEligible()) {
            $this->attemptAutomaticRefund($dispute);
        }

        AuditLog::record(
            'dispute.resolved',
            "Litige {$dispute->reference} tranché : {$data['decision']}" . ($refundAmount > 0 ? ' — ' . number_format($refundAmount, 0, ',', ' ') . ' FCFA' : ''),
            $dispute
        );

        Mail::to($dispute->buyer->email)->send(new DisputeUpdatedMail($dispute->buyer, $dispute, 'resolved'));
        Mail::to($dispute->vendor->user->email)->send(new DisputeUpdatedMail($dispute->vendor->user, $dispute, 'resolved'));

        return redirect()->route('admin.disputes.show', $dispute)->with('status', __('Litige tranché.'));
    }

    /** Marque un remboursement partiel comme traité à la main (KkiaPay ne rembourse qu'une transaction entière). */
    public function markRefunded(Dispute $dispute)
    {
        if ($dispute->status !== Dispute::STATUS_RESOLVED || $dispute->refund_status !== Dispute::REFUND_PENDING_MANUAL) {
            return back()->withErrors(['dispute' => __("Ce litige n'attend pas de remboursement manuel.")]);
        }

        $dispute->update(['refund_status' => Dispute::REFUND_MANUAL_DONE, 'refunded_at' => now()]);

        AuditLog::record('dispute.refund_marked_manual', "Remboursement manuel confirmé pour le litige {$dispute->reference}", $dispute);

        return back()->with('status', __('Remboursement marqué comme effectué.'));
    }

    private function attemptAutomaticRefund(Dispute $dispute): void
    {
        try {
            if (app(KkiapayService::class)->refundTransaction($dispute->order->payment_reference)) {
                $dispute->update(['refund_status' => Dispute::REFUND_AUTOMATIC, 'refunded_at' => now()]);
            }
        } catch (\Throwable $e) {
            Log::warning('Échec du remboursement automatique pour le litige ' . $dispute->reference, [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
