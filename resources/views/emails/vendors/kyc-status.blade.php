<x-emails.layout>
    <h1 style="font-size:20px; margin:0 0 16px;">
        {{ $event === 'approved' ? __('Boutique vérifiée') : __('Dossier refusé') }}
    </h1>
    <p style="font-size:14px; line-height:1.6; margin:0 0 16px;">
        {{ __('Bonjour :name,', ['name' => $recipient->name]) }}<br>
        @if($event === 'approved')
            {{ __('Votre dossier de vérification a été validé : votre boutique :store affiche désormais le badge « Vérifié ».', ['store' => $vendor->store_name]) }}
        @else
            {{ __("Votre dossier de vérification pour :store n'a pas été validé.", ['store' => $vendor->store_name]) }}
            @if($vendor->kyc_rejection_reason)
                <br>{{ __('Motif :') }} {{ $vendor->kyc_rejection_reason }}
            @endif
            <br>{{ __('Vous pouvez corriger et déposer un nouveau dossier.') }}
        @endif
    </p>
    <p style="margin:0;">
        <a href="{{ $url }}" style="display:inline-block; padding:10px 18px; background:#1a2540; color:#ffffff; text-decoration:none; font-size:14px;">
            {{ __('Ma vérification') }}
        </a>
    </p>
</x-emails.layout>
