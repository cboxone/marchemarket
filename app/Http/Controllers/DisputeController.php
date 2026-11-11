<?php

namespace App\Http\Controllers;

use App\Mail\DisputeUpdatedMail;
use App\Models\Dispute;
use App\Models\Order;
use App\Services\ImageOptimizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class DisputeController extends Controller
{
    use \App\Http\Controllers\Concerns\ServesPrivateFile;

    public function index(Request $request)
    {
        $disputes = $request->user()->disputes()->with('vendor', 'order')->latest()->paginate(15);

        return view('disputes.index', compact('disputes'));
    }

    public function create(Request $request, Order $order)
    {
        [$vendors, $error] = $this->eligibleVendors($request, $order);

        if ($error) {
            return redirect()->route('orders.show', $order)->withErrors(['dispute' => $error]);
        }

        return view('disputes.create', compact('order', 'vendors'));
    }

    public function store(Request $request, Order $order)
    {
        [$vendors, $error] = $this->eligibleVendors($request, $order);

        if ($error) {
            return redirect()->route('orders.show', $order)->withErrors(['dispute' => $error]);
        }

        $data = $request->validate([
            'vendor_id' => ['required', Rule::in($vendors->pluck('id'))],
            'reason' => ['required', Rule::in([
                Dispute::REASON_NOT_RECEIVED, Dispute::REASON_MISSING_QTY,
                Dispute::REASON_NOT_CONFORMING, Dispute::REASON_DAMAGED, Dispute::REASON_OTHER,
            ])],
            'description' => ['required', 'string', 'max:2000'],
            'evidence.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $requiresEvidence = in_array($data['reason'], Dispute::EVIDENCE_REQUIRED_REASONS, true);

        if ($requiresEvidence && ! $request->hasFile('evidence')) {
            return back()->withErrors(['evidence' => __('Ajoutez au moins une photo pour ce motif.')])->withInput();
        }

        if ($request->hasFile('evidence') && count($request->file('evidence')) > 5) {
            return back()->withErrors(['evidence' => __('5 photos maximum.')])->withInput();
        }

        // Verrouillé pour empêcher deux onglets d'ouvrir chacun un litige sur
        // le même (commande, fournisseur) au même instant.
        $dispute = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $order, $data) {
            $stillOpen = Dispute::where('order_id', $order->id)->where('vendor_id', $data['vendor_id'])
                ->whereIn('status', [Dispute::STATUS_OPEN, Dispute::STATUS_ESCALATED])
                ->lockForUpdate()
                ->exists();

            if ($stillOpen) {
                return null;
            }

            $paths = [];
            if ($request->hasFile('evidence')) {
                $optimizer = app(ImageOptimizer::class);
                foreach ($request->file('evidence') as $file) {
                    // Disque `local` : privé, jamais exposé publiquement.
                    $paths[] = $optimizer->storeOptimized($file, 'disputes', 'local');
                }
            }

            return Dispute::create([
                'order_id' => $order->id,
                'vendor_id' => $data['vendor_id'],
                'buyer_id' => $request->user()->id,
                'reason' => $data['reason'],
                'description' => $data['description'],
                'evidence_paths' => $paths ?: null,
            ]);
        });

        if (! $dispute) {
            return back()->withErrors(['dispute' => __('Un litige est déjà en cours pour ce fournisseur sur cette commande.')]);
        }

        Mail::to($dispute->vendor->user->email)->send(new DisputeUpdatedMail($dispute->vendor->user, $dispute, 'opened'));

        return redirect()->route('disputes.show', $dispute)
            ->with('status', __('Litige ouvert. Le fournisseur a :hours heures pour répondre.', ['hours' => config('marketplace.disputes.vendor_response_hours')]));
    }

    public function show(Request $request, Dispute $dispute)
    {
        $this->authorizeBuyer($request, $dispute);

        $dispute->load('vendor', 'order.items', 'resolvedBy');

        return view('disputes.show', compact('dispute'));
    }

    public function evidence(Request $request, Dispute $dispute, int $index)
    {
        $this->authorizeBuyer($request, $dispute);

        return $this->servePrivateFile($dispute->evidence_paths[$index] ?? null);
    }

    /** Retrait par l'acheteur, possible uniquement avant toute réponse du fournisseur. */
    public function withdraw(Request $request, Dispute $dispute)
    {
        $this->authorizeBuyer($request, $dispute);

        if (! $dispute->isOpenForVendorResponse()) {
            return back()->withErrors(['dispute' => __("Ce litige n'est plus modifiable : le fournisseur a déjà répondu ou le dossier est en cours d'arbitrage.")]);
        }

        $dispute->update(['status' => Dispute::STATUS_WITHDRAWN]);

        Mail::to($dispute->vendor->user->email)->send(new DisputeUpdatedMail($dispute->vendor->user, $dispute, 'withdrawn'));

        return redirect()->route('disputes.show', $dispute)->with('status', __('Litige retiré.'));
    }

    private function authorizeBuyer(Request $request, Dispute $dispute): void
    {
        abort_unless($dispute->buyer_id === $request->user()->id, 403);
    }

    /**
     * Fournisseurs de cette commande pour lesquels l'acheteur peut encore
     * ouvrir un litige : commande payée et non annulée, dans la fenêtre de
     * dépôt, et sans litige déjà ouvert ou en arbitrage pour ce fournisseur.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: ?string}
     */
    private function eligibleVendors(Request $request, Order $order): array
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);

        if ($order->payment_status !== 'paid' || $order->status === Order::STATUS_CANCELLED) {
            return [collect(), __("Cette commande n'est pas payée ou a été annulée : aucun litige n'est possible.")];
        }

        $order->loadMissing('items.vendor');
        // Évite une requête « order » par article lors du calcul de la fenêtre ci-dessous.
        $order->items->each(fn ($item) => $item->setRelation('order', $order));
        $days = config('marketplace.disputes');
        $openDisputedVendorIds = $order->disputes()
            ->whereIn('status', [Dispute::STATUS_OPEN, Dispute::STATUS_ESCALATED])
            ->pluck('vendor_id');

        $vendors = $order->items
            ->groupBy('vendor_id')
            ->reject(fn ($items) => $openDisputedVendorIds->contains($items->first()->vendor_id))
            ->reject(function ($items) use ($days) {
                // Fenêtre : après la livraison la plus tardive de ce
                // fournisseur sur la commande, sinon après le paiement.
                $latestDelivery = $items->pluck('delivered_at')->filter()->max();

                if ($latestDelivery) {
                    $deadline = $latestDelivery->copy()->addDays($days['window_after_delivery_days']);
                } else {
                    $paidAt = $items->first()->order->invoiced_at ?? $items->first()->order->created_at;
                    $deadline = $paidAt?->copy()->addDays($days['window_after_payment_days']);
                }

                return $deadline === null || $deadline->isPast();
            })
            ->map(fn ($items) => $items->first()->vendor)
            ->values();

        if ($vendors->isEmpty()) {
            return [$vendors, __('Aucun litige ne peut être ouvert sur cette commande (délai dépassé ou litige déjà en cours).')];
        }

        return [$vendors, null];
    }
}
