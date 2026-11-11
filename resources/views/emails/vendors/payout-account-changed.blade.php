<x-emails.layout>
    <h1 style="font-size:20px; margin:0 0 16px;">{{ __('Compte de retrait modifié') }}</h1>
    <p style="font-size:14px; line-height:1.6; margin:0 0 16px;">
        {{ __('Bonjour :name,', ['name' => $user->name]) }}<br>
        @if($accountLabel)
            {{ __('Le compte utilisé pour vos retraits est désormais : :account.', ['account' => $accountLabel]) }}
        @else
            {{ __('Le compte utilisé pour vos retraits a été supprimé.') }}
        @endif
    </p>
    <p style="font-size:14px; line-height:1.6; margin:0;">
        {{ __("Si vous n'êtes pas à l'origine de ce changement, modifiez immédiatement votre mot de passe et contactez notre support.") }}
    </p>
</x-emails.layout>
