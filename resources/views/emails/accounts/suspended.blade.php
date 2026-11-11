<x-emails.layout>
    <h1 style="font-size:20px; margin:0 0 16px;">{{ __('Votre compte a été suspendu') }}</h1>
    <p style="font-size:14px; line-height:1.6; margin:0 0 16px;">
        {{ __('Bonjour :name,', ['name' => $user->name]) }}<br>
        {{ __("Votre compte MarcheMarket a été suspendu par notre équipe. Vous ne pouvez plus vous connecter pour le moment.") }}
    </p>
    <p style="font-size:14px; line-height:1.6; margin:0;">
        {{ __("Si vous pensez qu'il s'agit d'une erreur, contactez notre support.") }}
    </p>
</x-emails.layout>
