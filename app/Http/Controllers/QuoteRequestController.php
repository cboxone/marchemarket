<?php

namespace App\Http\Controllers;

use App\Mail\OrderConfirmationMail;
use App\Mail\QuoteUpdatedMail;
use App\Models\Order;
use App\Models\Product;
use App\Models\QuoteRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class QuoteRequestController extends Controller
{
    use \App\Http\Controllers\Concerns\ResolvesShippingAddress;

    public function index(Request $request)
    {
        $quotes = $request->user()->quoteRequests()->with('vendor')->latest()->paginate(15);

        return view('quotes.index', compact('quotes'));
    }

    public function create(Request $request, Product $product)
    {
        $this->ensureQuotable($request, $product);

        return view('quotes.create', compact('product'));
    }

    public function store(Request $request, Product $product)
    {
        $this->ensureQuotable($request, $product);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'target_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        // Une seule demande ouverte par produit : évite les doublons et le
        // spam d'un fournisseur par des demandes répétées.
        $existing = $request->user()->quoteRequests()
            ->where('product_id', $product->id)
            ->whereIn('status', [QuoteRequest::STATUS_PENDING, QuoteRequest::STATUS_QUOTED])
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            return redirect()->route('quotes.show', $existing)
                ->with('status', __('Vous avez déjà une demande de devis en cours pour ce produit.'));
        }

        $quote = QuoteRequest::create([
            'buyer_id' => $request->user()->id,
            'vendor_id' => $product->vendor_id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit' => $product->unit,
            'quantity' => $data['quantity'],
            'target_price' => $data['target_price'] ?? null,
            'message' => $data['message'] ?? null,
        ]);

        $quote->forceFill(['expires_at' => now()->addDays(config('marketplace.quote_request_days'))])->save();

        Mail::to($product->vendor->user->email)->send(new QuoteUpdatedMail($product->vendor->user, $quote, 'requested'));

        return redirect()->route('quotes.show', $quote)
            ->with('status', __('Demande de devis envoyée. Le fournisseur vous répondra par e-mail.'));
    }

    public function show(Request $request, QuoteRequest $quote)
    {
        $this->authorizeBuyer($request, $quote);

        $quote->load('vendor', 'product', 'order');

        $addresses = $request->user()->addresses;

        return view('quotes.show', compact('quote', 'addresses'));
    }

    /**
     * Transforme le devis en commande ordinaire (à payer ensuite via KkiaPay),
     * au prix négocié. Même logique de sécurité que le checkout : le produit
     * et le devis sont verrouillés, le stock est vérifié puis réservé.
     */
    public function accept(Request $request, QuoteRequest $quote)
    {
        $this->authorizeBuyer($request, $quote);

        $shipping = $this->resolveShippingAddress($request);

        $data = $request->validate([
            // Le montant affiché à l'acheteur au moment où il accepte : si le
            // fournisseur a modifié son devis entre-temps, on refuse plutôt
            // que de lui faire payer un prix qu'il n'a pas lu.
            'quoted_total' => ['required', 'numeric'],
        ]) + $shipping;

        try {
            $order = DB::transaction(function () use ($request, $quote, $data) {
                $locked = QuoteRequest::whereKey($quote->id)->lockForUpdate()->firstOrFail();

                if (! $locked->canBeAccepted()) {
                    throw ValidationException::withMessages(['quote' => __("Ce devis n'est plus valable.")]);
                }

                if (abs($locked->quotedTotal() - (float) $data['quoted_total']) > 0.01) {
                    throw ValidationException::withMessages(['quote' => __("Ce devis a été modifié par le fournisseur : relisez-le avant de l'accepter.")]);
                }

                $product = $locked->product_id
                    ? Product::whereKey($locked->product_id)->lockForUpdate()->first()
                    : null;

                if (! $product || ! $product->is_active) {
                    throw ValidationException::withMessages(['quote' => __("Ce produit n'est plus disponible à la vente.")]);
                }

                if ($locked->quoted_quantity > $product->stock_qty) {
                    throw ValidationException::withMessages([
                        'quote' => __(':product : seulement :available :unit disponible(s) en stock.', [
                            'product' => $product->name,
                            'available' => $product->stock_qty,
                            'unit' => $product->unit,
                        ]),
                    ]);
                }

                $order = Order::create([
                    'shipping_address' => $data['shipping_address'],
                    'shipping_city' => $data['shipping_city'],
                    'shipping_country' => $data['shipping_country'],
                    'shipping_phone' => $data['shipping_phone'],
                    'buyer_id' => $request->user()->id,
                    'coupon_id' => null,
                    'discount_amount' => 0,
                    'shipping_fee' => $locked->quotedShippingFee(),
                    'total_amount' => $locked->quotedTotal(),
                ]);

                $order->items()->create([
                    'vendor_id' => $locked->vendor_id,
                    'product_id' => $product->id,
                    'product_name' => $locked->product_name,
                    'quantity' => $locked->quoted_quantity,
                    'unit_price' => $locked->quoted_unit_price,
                    'commission_rate' => config('marketplace.commission_percent'),
                ]);

                // Le stock est réservé dès maintenant, comme au checkout ; la
                // commande impayée est annulée automatiquement au bout du
                // délai (orders:cancel-unpaid), ce qui le restitue.
                $product->decrement('stock_qty', $locked->quoted_quantity);

                $locked->forceFill([
                    'status' => QuoteRequest::STATUS_ACCEPTED,
                    'order_id' => $order->id,
                ])->save();

                return $order;
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $quote->refresh();

        Mail::to($request->user()->email)->send(new OrderConfirmationMail($order));
        Mail::to($quote->vendor->user->email)->send(new QuoteUpdatedMail($quote->vendor->user, $quote, 'accepted'));

        return redirect()->route('payment.show', $order)
            ->with('status', __("Devis accepté. Il ne reste plus qu'à régler par Mobile Money ou carte."));
    }

    /**
     * Retire une demande en attente (« annulée »), ou refuse un devis reçu
     * (« refusé ») : dans les deux cas le fournisseur en est informé.
     */
    public function cancel(Request $request, QuoteRequest $quote)
    {
        $this->authorizeBuyer($request, $quote);

        $event = DB::transaction(function () use ($quote) {
            $locked = QuoteRequest::whereKey($quote->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                return null;
            }

            $wasQuoted = $locked->status === QuoteRequest::STATUS_QUOTED;

            $locked->forceFill([
                'status' => $wasQuoted ? QuoteRequest::STATUS_REJECTED : QuoteRequest::STATUS_CANCELLED,
            ])->save();

            return $wasQuoted ? 'rejected' : 'cancelled';
        });

        if (! $event) {
            return back()->withErrors(['quote' => __("Cette demande n'est plus modifiable.")]);
        }

        $quote->refresh();
        Mail::to($quote->vendor->user->email)->send(new QuoteUpdatedMail($quote->vendor->user, $quote, $event));

        return redirect()->route('quotes.show', $quote)
            ->with('status', $event === 'rejected' ? __('Devis refusé.') : __('Demande de devis annulée.'));
    }

    private function authorizeBuyer(Request $request, QuoteRequest $quote): void
    {
        abort_unless($quote->buyer_id === $request->user()->id, 403);
    }

    /** Un produit n'est « devisable » que s'il est en vente, et jamais par son propre fournisseur. */
    private function ensureQuotable(Request $request, Product $product): void
    {
        abort_unless($product->is_active, 404);

        abort_if($request->user()->vendor?->id === $product->vendor_id, 403);
    }
}
