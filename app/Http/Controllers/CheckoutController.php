<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesShippingAddress;
use App\Mail\OrderConfirmationMail;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    use ResolvesShippingAddress;

    public function create(Request $request)
    {
        $cart = $request->user()->cart()->with('items.product.vendor', 'items.product.priceTiers')->firstOrCreate([]);

        if ($cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('status', __('Votre panier est vide.'));
        }

        $shippingFee = Order::SHIPPING_FEE;
        [$coupon, $discount] = $this->currentCoupon($request, $cart->total());
        $addresses = $request->user()->addresses;

        return view('checkout.index', compact('cart', 'shippingFee', 'coupon', 'discount', 'addresses'));
    }

    /**
     * Applique un code promo au panier en cours (stocké en session, car la
     * commande n'existe pas encore à ce stade). Revalidé une seconde fois
     * au moment de valider la commande, pour éviter qu'un code n'expire ou
     * n'atteigne sa limite entre l'application et le paiement.
     */
    public function applyCoupon(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:40']]);

        $cart = $request->user()->cart()->with('items.product.priceTiers')->firstOrCreate([]);
        $coupon = Coupon::whereRaw('UPPER(code) = ?', [strtoupper($request->string('code'))])->first();

        if (! $coupon) {
            return back()->withErrors(['code' => __('Ce code promo est introuvable.')]);
        }

        if ($error = $coupon->validationError($cart->total())) {
            return back()->withErrors(['code' => $error]);
        }

        $request->session()->put('coupon_code', $coupon->code);

        return back()->with('status', __('Code promo appliqué.'));
    }

    public function removeCoupon(Request $request)
    {
        $request->session()->forget('coupon_code');

        return back()->with('status', __('Code promo retiré.'));
    }

    public function store(Request $request)
    {
        $data = $this->resolveShippingAddress($request);

        $cart = $request->user()->cart()->with('items.product')->firstOrCreate([]);

        if ($cart->items->isEmpty()) {
            return redirect()->route('cart.index');
        }

        try {
            $order = DB::transaction(function () use ($request, $data, $cart) {
                // Verrouille chaque produit concerné le temps de la transaction pour
                // éviter que deux commandes simultanées ne survendent le même stock.
                $lockedProducts = Product::whereIn('id', $cart->items->pluck('product_id'))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($cart->items as $item) {
                    // Produit désactivé depuis l'ajout au panier : on refuse
                    // (le stock n'est alors pas touché, la transaction est annulée).
                    if (! $lockedProducts[$item->product_id]->is_active) {
                        throw ValidationException::withMessages([
                            'cart' => __(":product n'est plus disponible à la vente. Retirez-le de votre panier.", ['product' => $item->product->name]),
                        ]);
                    }

                    $available = $lockedProducts[$item->product_id]->stock_qty;

                    if ($item->quantity > $available) {
                        throw ValidationException::withMessages([
                            'quantity' => __(':product : seulement :available :unit disponible(s) en stock.', [
                                'product' => $item->product->name,
                                'available' => $available,
                                'unit' => $item->product->unit,
                            ]),
                        ]);
                    }
                }

                $shippingFee = Order::SHIPPING_FEE;
                $cartTotal = $cart->total();

                // Re-verrouille et revalide le code promo au moment de payer :
                // il a pu expirer, être désactivé, ou atteindre sa limite
                // depuis que l'acheteur l'a saisi.
                $coupon = null;
                $discount = 0.0;
                $couponCode = $request->session()->get('coupon_code');

                if ($couponCode) {
                    $coupon = Coupon::whereRaw('UPPER(code) = ?', [strtoupper($couponCode)])->lockForUpdate()->first();

                    if ($coupon && ! $coupon->validationError($cartTotal)) {
                        $discount = $coupon->discountFor($cartTotal);
                        $coupon->increment('times_used');
                    } else {
                        $coupon = null;
                    }
                }

                $order = Order::create([
                    ...$data,
                    'buyer_id' => $request->user()->id,
                    'coupon_id' => $coupon?->id,
                    'discount_amount' => $discount,
                    'shipping_fee' => $shippingFee,
                    'total_amount' => max(0, $cartTotal - $discount) + $shippingFee,
                ]);

                foreach ($cart->items as $item) {
                    $order->items()->create([
                        'vendor_id' => $item->product->vendor_id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->product->priceForQty($item->quantity),
                        // Taux figé à la commande : un changement ultérieur de la
                        // commission n'affecte jamais une vente déjà passée.
                        'commission_rate' => config('marketplace.commission_percent'),
                    ]);

                    // Décrément du stock : c'est ici, une fois la commande actée,
                    // que la quantité réservée quitte réellement le stock du vendeur.
                    $lockedProducts[$item->product_id]->decrement('stock_qty', $item->quantity);
                }

                $cart->items()->delete();

                return $order;
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $request->session()->forget('coupon_code');

        Mail::to($order->buyer->email)->send(new OrderConfirmationMail($order));

        return redirect()->route('payment.show', $order)->with('status', __('Commande enregistrée. Il ne reste plus qu\'à régler par Mobile Money ou carte.'));
    }

    /**
     * @return array{0: ?Coupon, 1: float}
     */
    private function currentCoupon(Request $request, float $cartTotal): array
    {
        $code = $request->session()->get('coupon_code');

        if (! $code) {
            return [null, 0.0];
        }

        $coupon = Coupon::whereRaw('UPPER(code) = ?', [strtoupper($code)])->first();

        if (! $coupon || $coupon->validationError($cartTotal)) {
            $request->session()->forget('coupon_code');

            return [null, 0.0];
        }

        return [$coupon, $coupon->discountFor($cartTotal)];
    }
}
