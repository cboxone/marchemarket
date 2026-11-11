<x-emails.layout>
    <h1 style="font-size:20px; margin:0 0 16px;">
        @switch($event)
            @case('opened') {{ __('Nouveau litige') }} @break
            @case('vendor_responded') {{ __('Réponse du fournisseur') }} @break
            @case('escalated') {{ __('Litige transmis à notre équipe') }} @break
            @case('resolved') {{ __('Litige résolu') }} @break
            @default {{ __('Litige retiré') }}
        @endswitch
    </h1>

    <p style="font-size:14px; line-height:1.6; margin:0 0 16px;">
        {{ __('Bonjour :name,', ['name' => $recipient->name]) }}<br>
        @switch($event)
            @case('opened')
                {{ __(':buyer a ouvert un litige sur la commande :order (:reason).', ['buyer' => $dispute->buyer->company_name ?: $dispute->buyer->name, 'order' => $dispute->order->reference, 'reason' => $dispute->reasonLabel()]) }}
                {{ __('Vous avez :hours heures pour répondre.', ['hours' => config('marketplace.disputes.vendor_response_hours')]) }}
                @break
            @case('vendor_responded')
                {{ __(':vendor a répondu à votre litige sur la commande :order.', ['vendor' => $dispute->vendor->store_name, 'order' => $dispute->order->reference]) }}
                @break
            @case('escalated')
                {{ __('Le litige sur la commande :order a été transmis à notre équipe pour arbitrage.', ['order' => $dispute->order->reference]) }}
                @break
            @case('resolved')
                @if($dispute->decision === 'reject')
                    {{ __('Le litige sur la commande :order a été examiné : aucun remboursement ne sera effectué.', ['order' => $dispute->order->reference]) }}
                @else
                    {{ __('Le litige sur la commande :order a été résolu : :amount FCFA seront remboursés.', ['order' => $dispute->order->reference, 'amount' => number_format((float) $dispute->refund_amount, 0, ',', ' ')]) }}
                @endif
                @if($dispute->resolution_note)
                    <br>{{ __('Note :') }} {{ $dispute->resolution_note }}
                @endif
                @break
            @default
                {{ __("L'acheteur a retiré son litige sur la commande :order.", ['order' => $dispute->order->reference]) }}
        @endswitch
    </p>

    <p style="margin:0;">
        <a href="{{ $url }}" style="display:inline-block; padding:10px 18px; background:#1a2540; color:#ffffff; text-decoration:none; font-size:14px;">
            {{ __('Voir le litige') }} {{ $dispute->reference }}
        </a>
    </p>
</x-emails.layout>
