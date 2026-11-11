<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayoutController extends Controller
{
    use \App\Http\Controllers\Concerns\ExportsCsv;

    public function index(Request $request)
    {
        $payouts = Payout::with('vendor')->latest()->paginate(20);

        return view('admin.payouts.index', compact('payouts'));
    }

    /** Export CSV de tous les reversements, filtrable par date de demande (`from`/`to`, YYYY-MM-DD). */
    public function export(Request $request)
    {
        $query = $this->applyDateRange(Payout::query()->with('vendor'), 'created_at', $request->query('from'), $request->query('to'));

        $rows = function () use ($query) {
            foreach ($query->orderBy('id')->cursor() as $payout) {
                yield [
                    $payout->vendor->store_name,
                    $payout->created_at->format('Y-m-d H:i'),
                    (float) $payout->amount,
                    $payout->payout_method,
                    $payout->status,
                    $payout->payout_reference ?: '',
                    $payout->processed_at?->format('Y-m-d H:i') ?: '',
                    $payout->admin_note ?: '',
                ];
            }
        };

        return $this->csvDownload('reversements.csv', [
            __('Vendeur'), __('Date de la demande'), __('Montant'), __('Moyen de paiement'),
            __('Statut'), __('Référence'), __('Date de traitement'), __('Note'),
        ], $rows());
    }

    public function markPaid(Request $request, Payout $payout)
    {
        $data = $request->validate([
            'payout_reference' => ['nullable', 'string', 'max:120'],
        ]);

        if (! $this->settlePending($payout, [
            'status' => Payout::STATUS_PAID,
            'payout_reference' => $data['payout_reference'] ?? null,
            'processed_at' => now(),
        ])) {
            return back()->withErrors(['payout' => __('Ce reversement a déjà été traité.')]);
        }

        AuditLog::record(
            'payout.paid',
            'Reversement payé : ' . number_format((float) $payout->amount, 0, ',', ' ') . ' FCFA à ' . $payout->vendor->store_name,
            $payout
        );

        return back()->with('status', __('Reversement marqué comme payé.'));
    }

    public function reject(Request $request, Payout $payout)
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:255'],
        ]);

        if (! $this->settlePending($payout, [
            'status' => Payout::STATUS_REJECTED,
            'admin_note' => $data['admin_note'] ?? null,
            'processed_at' => now(),
        ])) {
            return back()->withErrors(['payout' => __('Ce reversement a déjà été traité.')]);
        }

        AuditLog::record(
            'payout.rejected',
            'Reversement rejeté : ' . number_format((float) $payout->amount, 0, ',', ' ') . ' FCFA à ' . $payout->vendor->store_name,
            $payout
        );

        return back()->with('status', __('Demande de reversement rejetée.'));
    }

    /**
     * Ne traite un reversement que s'il est encore « en attente ». Sans ce
     * contrôle, un reversement déjà payé pouvait être « rejeté » : son
     * montant sortait alors du calcul des sommes réclamées, le solde du
     * vendeur se reconstituait et il pouvait le redemander (et le recevoir)
     * une seconde fois. Le statut est relu sous verrou pour que deux clics
     * simultanés ne passent pas tous les deux.
     */
    private function settlePending(Payout $payout, array $attributes): bool
    {
        return DB::transaction(function () use ($payout, $attributes) {
            $locked = Payout::whereKey($payout->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== Payout::STATUS_PENDING) {
                return false;
            }

            $locked->update($attributes);
            $payout->refresh();

            return true;
        });
    }
}
