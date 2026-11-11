<x-emails.layout>
    <h1 style="font-size:20px; margin:0 0 16px;">
        {{ $event === 'confirmed' ? __('Virement reçu') : __('Virement introuvable') }}
    </h1>

    <p style="font-size:14px; line-height:1.6; margin:0 0 16px;">
        {{ __('Bonjour :name,', ['name' => $recipient->name]) }}<br>
        @if($event === 'confirmed')
            {{ __('Nous avons bien reçu votre virement pour la commande :ref. Elle est maintenant confirmée et va être préparée.', ['ref' => $order->reference]) }}
        @else
            {{ __("Nous n'avons pas retrouvé votre virement pour la commande :ref sur notre relevé bancaire.", ['ref' => $order->reference]) }}
            @if($order->bank_transfer_rejected_reason)
                <br>{{ __('Motif :') }} {{ $order->bank_transfer_rejected_reason }}
            @endif
            <br>{{ __('Vous pouvez déposer une nouvelle preuve depuis la page de paiement de la commande.') }}
        @endif
    </p>

    <p style="margin:0;">
        <a href="{{ $url }}" style="display:inline-block; padding:10px 18px; background:#1a2540; color:#ffffff; text-decoration:none; font-size:14px;">
            {{ __('Voir la commande') }} {{ $order->reference }}
        </a>
    </p>
</x-emails.layout>
