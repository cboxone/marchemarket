<x-emails.layout>
    <h1 style="font-size:20px; margin:0 0 16px;">
        @switch($event)
            @case('requested') {{ __('Nouvelle demande de devis') }} @break
            @case('quoted') {{ __('Votre devis est prêt') }} @break
            @case('declined') {{ __('Demande de devis déclinée') }} @break
            @case('accepted') {{ __('Devis accepté') }} @break
            @case('rejected') {{ __('Devis refusé') }} @break
            @default {{ __('Demande de devis annulée') }}
        @endswitch
    </h1>

    <p style="font-size:14px; line-height:1.6; margin:0 0 16px;">
        {{ __('Bonjour :name,', ['name' => $recipient->name]) }}<br>
        @switch($event)
            @case('requested')
                {{ __(':buyer demande un devis pour :quantity × :product.', ['buyer' => $quote->buyer->company_name ?: $quote->buyer->name, 'quantity' => $quote->quantity, 'product' => $quote->product_name]) }}
                @break
            @case('quoted')
                {{ __(':vendor vous a envoyé un devis pour :product : :total FCFA (livraison comprise).', ['vendor' => $quote->vendor->store_name, 'product' => $quote->product_name, 'total' => number_format($quote->quotedTotal(), 0, ',', ' ')]) }}
                @if($quote->expires_at)
                    <br>{{ __("Il est valable jusqu'au :date.", ['date' => $quote->expires_at->format('d/m/Y')]) }}
                @endif
                @break
            @case('declined')
                {{ __(':vendor ne peut pas donner suite à votre demande pour :product.', ['vendor' => $quote->vendor->store_name, 'product' => $quote->product_name]) }}
                @if($quote->decline_reason)
                    <br>{{ __('Motif :') }} {{ $quote->decline_reason }}
                @endif
                @break
            @case('accepted')
                {{ __("Votre devis pour :product a été accepté : une commande a été créée et attend son paiement.", ['product' => $quote->product_name]) }}
                @break
            @case('rejected')
                {{ __("Votre devis pour :product a été refusé par l'acheteur.", ['product' => $quote->product_name]) }}
                @break
            @default
                {{ __("L'acheteur a retiré sa demande de devis pour :product.", ['product' => $quote->product_name]) }}
        @endswitch
    </p>

    <p style="margin:0;">
        <a href="{{ $url }}" style="display:inline-block; padding:10px 18px; background:#1a2540; color:#ffffff; text-decoration:none; font-size:14px;">
            {{ __('Voir le devis') }} {{ $quote->reference }}
        </a>
    </p>
</x-emails.layout>
