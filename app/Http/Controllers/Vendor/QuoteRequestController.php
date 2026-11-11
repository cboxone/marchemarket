<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Mail\QuoteUpdatedMail;
use App\Models\Order;
use App\Models\QuoteRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class QuoteRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()->vendor->quoteRequests()->with('buyer')->latest();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return view('vendor.quotes.index', [
            'quotes' => $query->paginate(15)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function show(Request $request, QuoteRequest $quote)
    {
        $this->authorizeVendor($request, $quote);

        $quote->load('buyer', 'product', 'order');

        return view('vendor.quotes.show', compact('quote'));
    }

    /** Envoie (ou révise) le devis. Possible tant que l'acheteur ne l'a pas accepté. */
    public function respond(Request $request, QuoteRequest $quote)
    {
        $this->authorizeVendor($request, $quote);

        $data = $request->validate([
            'unit_price' => ['required', 'numeric', 'min:1', 'max:999999999'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'shipping_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'valid_days' => ['required', 'integer', 'min:1', 'max:60'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $done = DB::transaction(function () use ($quote, $data) {
            // Verrou partagé avec QuoteRequestController::accept : le devis ne
            // peut pas être révisé pendant que l'acheteur l'accepte.
            $locked = QuoteRequest::whereKey($quote->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                return false;
            }

            $locked->forceFill([
                'status' => QuoteRequest::STATUS_QUOTED,
                'quoted_unit_price' => $data['unit_price'],
                'quoted_quantity' => $data['quantity'],
                'quoted_shipping_fee' => $data['shipping_fee'] ?? Order::SHIPPING_FEE,
                'vendor_note' => $data['note'] ?? null,
                'quoted_at' => now(),
                'expires_at' => now()->addDays((int) $data['valid_days']),
            ])->save();

            return true;
        });

        if (! $done) {
            return back()->withErrors(['quote' => __("Cette demande n'est plus ouverte : elle a été acceptée, annulée ou a expiré.")]);
        }

        $quote->refresh();
        Mail::to($quote->buyer->email)->send(new QuoteUpdatedMail($quote->buyer, $quote, 'quoted'));

        return redirect()->route('vendor.quotes.show', $quote)->with('status', __('Devis envoyé.'));
    }

    public function decline(Request $request, QuoteRequest $quote)
    {
        $this->authorizeVendor($request, $quote);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $done = DB::transaction(function () use ($quote, $data) {
            $locked = QuoteRequest::whereKey($quote->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                return false;
            }

            $locked->forceFill([
                'status' => QuoteRequest::STATUS_DECLINED,
                'decline_reason' => $data['reason'] ?? null,
            ])->save();

            return true;
        });

        if (! $done) {
            return back()->withErrors(['quote' => __("Cette demande n'est plus ouverte : elle a été acceptée, annulée ou a expiré.")]);
        }

        $quote->refresh();
        Mail::to($quote->buyer->email)->send(new QuoteUpdatedMail($quote->buyer, $quote, 'declined'));

        return redirect()->route('vendor.quotes.show', $quote)->with('status', __('Demande déclinée.'));
    }

    /** 404 plutôt que 403 : on ne révèle pas l'existence du devis d'un autre fournisseur. */
    private function authorizeVendor(Request $request, QuoteRequest $quote): void
    {
        abort_unless($quote->vendor_id === $request->user()->vendor->id, 404);
    }
}
