<x-emails.layout>
    <h1 style="font-size:20px; margin:0 0 16px;">{{ __('Merci pour votre commande !') }}</h1>
    <p style="font-size:14px; line-height:1.6; margin:0 0 16px;">
        {{ __('Bonjour :name,', ['name' => $order->buyer->name]) }}<br>
        {{ __('Nous avons bien reçu votre commande :reference. Voici son récapitulatif :', ['reference' => $order->reference]) }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; border-collapse:collapse; margin-bottom:16px;">
        <thead>
            <tr style="background-color:#eef1f6;">
                <td style="padding:8px; border:1px solid #e2e5ea;">{{ __('Produit') }}</td>
                <td style="padding:8px; border:1px solid #e2e5ea;">{{ __('Qté') }}</td>
                <td style="padding:8px; border:1px solid #e2e5ea;">{{ __('Sous-total') }}</td>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td style="padding:8px; border:1px solid #e2e5ea;">{{ $item->product_name }}</td>
                    <td style="padding:8px; border:1px solid #e2e5ea;">{{ $item->quantity }}</td>
                    <td style="padding:8px; border:1px solid #e2e5ea;">{{ number_format($item->subtotal(), 0, ',', ' ') }} FCFA</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($order->coupon_id && (float) $order->discount_amount > 0)
        <p style="font-size:14px; margin:0 0 4px; color:#1f8a5f;">{{ __('Code') }} {{ $order->coupon?->code }} : −{{ number_format((float) $order->discount_amount, 0, ',', ' ') }} FCFA</p>
    @endif
    <p style="font-size:14px; margin:0 0 4px;">{{ __('Frais de livraison') }} : {{ number_format((float) $order->shipping_fee, 0, ',', ' ') }} FCFA</p>
    <p style="font-size:16px; font-weight:700; margin:0 0 4px;">{{ __('Total') }} : {{ number_format((float) $order->total_amount, 0, ',', ' ') }} FCFA</p>
    <p style="font-size:12px; color:#8b9ab6; margin:0 0 20px;">{{ __('dont TVA (:rate%) incluse', ['rate' => \App\Models\Order::TVA_RATE]) }} : {{ number_format($order->taxAmount(), 0, ',', ' ') }} FCFA</p>

    <a href="{{ route('orders.show', $order) }}" style="display:inline-block; background-color:#e8792b; color:#ffffff; text-decoration:none; padding:10px 20px; border-radius:6px; font-size:14px; font-weight:600;">
        {{ __('Voir ma commande') }}
    </a>
</x-emails.layout>
