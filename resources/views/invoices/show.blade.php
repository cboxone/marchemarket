<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Facture') }} {{ $order->invoice_number }}</title>
    <style>
        /* Mise en page volontairement en tableaux : dompdf (génération PDF)
           gère mal flexbox et grid. DejaVu Sans est la police fournie avec
           dompdf et couvre les accents français. */
        @page { margin: 22mm 16mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 11px; color: #1a2540; margin: 0; }
        .page { max-width: 780px; margin: 0 auto; padding: 24px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }
        h1 { font-size: 24px; margin: 0 0 4px; letter-spacing: 1px; color: #1a2540; }
        .muted { color: #6b7a99; }
        .small { font-size: 10px; }
        .right { text-align: right; }
        .box { border: 1px solid #dfe3ec; padding: 10px 12px; }
        .box h3 { margin: 0 0 6px; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #6b7a99; }
        .items th { background: #eef1f6; text-align: left; padding: 7px 8px; border: 1px solid #dfe3ec; font-size: 10px; }
        .items td { padding: 7px 8px; border: 1px solid #dfe3ec; }
        .items tr.vendor td { background: #f7f8fb; font-weight: bold; font-size: 10px; color: #4a5875; }
        .totals { width: 55%; margin-left: 45%; margin-top: 12px; }
        .totals td { padding: 4px 8px; }
        .totals tr.grand td { border-top: 2px solid #1a2540; font-size: 13px; font-weight: bold; padding-top: 8px; }
        .badge { display: inline-block; padding: 2px 8px; border: 1px solid #1f8a5f; color: #1f8a5f; font-size: 10px; font-weight: bold; }
        .toolbar { margin-bottom: 16px; }
        .toolbar a, .toolbar button { display: inline-block; font: inherit; padding: 8px 14px; margin-right: 6px; border: 1px solid #1a2540; background: #fff; color: #1a2540; text-decoration: none; cursor: pointer; }
        .toolbar .primary { background: #1a2540; color: #fff; }
        @media print { .toolbar { display: none; } .page { padding: 0; } }
    </style>
</head>
<body>
<div class="page">
    @unless($pdf)
        <div class="toolbar">
            <button type="button" class="primary" onclick="window.print()">{{ __('Imprimer') }}</button>
            <a href="{{ route('orders.invoice.download', $order) }}">{{ __('Télécharger le PDF') }}</a>
            @if(auth()->id() === $order->buyer_id)
                <a href="{{ route('orders.show', $order) }}">{{ __('Retour à la commande') }}</a>
            @endif
        </div>
    @endunless

    <table>
        <tr>
            <td style="width:55%">
                <strong style="font-size:15px;">{{ $company['name'] }}</strong><br>
                @if(!empty($company['address'])){{ $company['address'] }}<br>@endif
                @if(!empty($company['phone'])){{ $company['phone'] }}<br>@endif
                @if(!empty($company['email'])){{ $company['email'] }}<br>@endif
                @if(!empty($company['tax_id']))<span class="muted">{{ __('IFU') }} :</span> {{ $company['tax_id'] }}<br>@endif
                @if(!empty($company['registration']))<span class="muted">{{ __('RCCM') }} :</span> {{ $company['registration'] }}<br>@endif
            </td>
            <td class="right">
                <h1>{{ __('FACTURE') }}</h1>
                <strong>{{ $order->invoice_number }}</strong><br>
                <span class="muted">{{ __("Date d'émission") }} :</span> {{ $order->invoiced_at->format('d/m/Y') }}<br>
                <span class="muted">{{ __('Commande') }} :</span> {{ $order->reference }}<br>
                <span class="badge">{{ __('PAYÉE') }}</span>
            </td>
        </tr>
    </table>

    <table style="margin-top:18px;">
        <tr>
            <td style="width:49%" class="box">
                <h3>{{ __('Facturé à') }}</h3>
                <strong>{{ $order->buyer->company_name ?: $order->buyer->name }}</strong><br>
                @if($order->buyer->company_name){{ $order->buyer->name }}<br>@endif
                {{ $order->buyer->email }}<br>
                @if($order->buyer->phone){{ $order->buyer->phone }}<br>@endif
            </td>
            <td style="width:2%"></td>
            <td style="width:49%" class="box">
                <h3>{{ __('Livraison') }}</h3>
                {{ $order->shipping_address }}<br>
                {{ $order->shipping_city }}, {{ $order->shipping_country }}<br>
                {{ __('Tél') }} : {{ $order->shipping_phone }}
            </td>
        </tr>
    </table>

    <table style="margin-top:12px;">
        <tr>
            <td class="box small">
                <strong>{{ __('Paiement') }}</strong> :
                {{ match($order->payment_method) {
                    'kkiapay' => __('Mobile Money / carte (KkiaPay)'),
                    'bank_transfer' => __('Virement bancaire'),
                    default => $order->payment_method ?: '—',
                } }}
                @if($order->payment_reference)
                    · {{ __('Réf. transaction') }} : {{ $order->payment_reference }}
                @endif
            </td>
        </tr>
    </table>

    @php
        $subtotal = $order->items->sum(fn ($item) => $item->subtotal());
        $tax = $order->taxAmount();
        $byVendor = $order->items->groupBy('vendor_id');
    @endphp

    <table class="items" style="margin-top:16px;">
        <thead>
            <tr>
                <th>{{ __('Désignation') }}</th>
                <th class="right" style="width:8%">{{ __('Qté') }}</th>
                <th class="right" style="width:18%">{{ __('Prix unitaire') }}</th>
                <th class="right" style="width:18%">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($byVendor as $vendorItems)
                <tr class="vendor">
                    <td colspan="4">
                        {{ __('Vendu par') }} : {{ $vendorItems->first()->vendor->store_name }}
                        @if($vendorItems->first()->vendor->city) — {{ $vendorItems->first()->vendor->city }}@endif
                    </td>
                </tr>
                @foreach($vendorItems as $item)
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td class="right">{{ $item->quantity }}</td>
                        <td class="right">{{ number_format((float) $item->unit_price, 0, ',', ' ') }} FCFA</td>
                        <td class="right">{{ number_format($item->subtotal(), 0, ',', ' ') }} FCFA</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>{{ __('Sous-total articles') }}</td>
            <td class="right">{{ number_format($subtotal, 0, ',', ' ') }} FCFA</td>
        </tr>
        @if($order->coupon_id && (float) $order->discount_amount > 0)
            <tr>
                <td>{{ __('Code') }} {{ $order->coupon?->code }}</td>
                <td class="right">−{{ number_format((float) $order->discount_amount, 0, ',', ' ') }} FCFA</td>
            </tr>
        @endif
        <tr>
            <td>{{ __('Frais de livraison') }}</td>
            <td class="right">{{ number_format((float) $order->shipping_fee, 0, ',', ' ') }} FCFA</td>
        </tr>
        <tr>
            <td class="muted">{{ __('Total HT') }}</td>
            <td class="right muted">{{ number_format((float) $order->total_amount - $tax, 0, ',', ' ') }} FCFA</td>
        </tr>
        <tr>
            <td class="muted">{{ __('TVA (:rate%)', ['rate' => \App\Models\Order::TVA_RATE]) }}</td>
            <td class="right muted">{{ number_format($tax, 0, ',', ' ') }} FCFA</td>
        </tr>
        <tr class="grand">
            <td>{{ __('Total TTC') }}</td>
            <td class="right">{{ number_format((float) $order->total_amount, 0, ',', ' ') }} FCFA</td>
        </tr>
    </table>

    <p class="muted small" style="margin-top:22px;">
        {{ __('Montants en francs CFA (FCFA). Les prix affichés sont toutes taxes comprises ; la TVA est indiquée à titre de détail.') }}<br>
        {{ __('Commande passée via :platform ; les articles sont vendus par les fournisseurs indiqués ci-dessus.', ['platform' => $company['name']]) }}
    </p>
    @if(!empty($footer))
        <p class="muted small">{{ $footer }}</p>
    @endif
</div>
</body>
</html>
