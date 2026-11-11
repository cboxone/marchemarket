<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayoutController extends Controller
{
    use \App\Http\Controllers\Concerns\ExportsCsv;

    public function index(Request $request)
    {
        $vendor = $request->user()->vendor;

        $payouts = $vendor->payouts()->latest()->paginate(15);
        $availableBalance = $vendor->availableBalance();
        $breakdown = $vendor->revenueBreakdown();

        return view('vendor.payouts.index', compact('vendor', 'payouts', 'availableBalance', 'breakdown'));
    }

    /** Export CSV des demandes de reversement de ce vendeur, filtrable par date (`from`/`to`, YYYY-MM-DD). */
    public function export(Request $request)
    {
        $vendor = $request->user()->vendor;

        $query = $this->applyDateRange($vendor->payouts(), 'created_at', $request->query('from'), $request->query('to'));

        $rows = function () use ($query) {
            foreach ($query->orderBy('id')->cursor() as $payout) {
                yield [
                    $payout->created_at->format('Y-m-d H:i'),
                    (float) $payout->amount,
                    $payout->payout_method,
                    $payout->status,
                    $payout->payout_reference ?: '',
                    $payout->processed_at?->format('Y-m-d H:i') ?: '',
                ];
            }
        };

        return $this->csvDownload('mes-reversements.csv', [
            __('Date de la demande'), __('Montant'), __('Moyen de paiement'),
            __('Statut'), __('Référence'), __('Date de traitement'),
        ], $rows());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        $vendorId = $request->user()->vendor->id;

        // Les retraits partent toujours vers le compte Mobile Money enregistré
        // (et protégé par mot de passe) dans « Ma boutique » : plus de champ
        // libre ressaisi à chaque demande, où une erreur de frappe ou une
        // session détournée envoyait l'argent au mauvais numéro.
        if (! $request->user()->vendor->hasPayoutAccount()) {
            return back()->withErrors(['payout_account' => __("Enregistrez d'abord votre compte de retrait dans « Ma boutique ».")]);
        }

        // Le solde est recalculé et la demande créée dans la même
        // transaction, avec la ligne du vendeur verrouillée : deux demandes
        // envoyées en même temps ne peuvent plus chacune voir « tout le
        // solde disponible » et le dépasser ensemble.
        $created = DB::transaction(function () use ($vendorId, $data) {
            $vendor = Vendor::whereKey($vendorId)->lockForUpdate()->firstOrFail();

            if ((float) $data['amount'] > $vendor->availableBalance()) {
                return false;
            }

            // Compte relu sous verrou et figé sur la demande : modifier le
            // compte ensuite ne redirige pas un retrait déjà demandé.
            $vendor->payouts()->create([
                'amount' => $data['amount'],
                'payout_method' => $vendor->payoutAccountLabel(),
            ]);

            return true;
        });

        if (! $created) {
            return back()->withErrors(['amount' => __('Le montant demandé dépasse votre solde disponible.')]);
        }

        return back()->with('status', __('Demande de reversement envoyée. Notre équipe la traite sous 48h.'));
    }
}
