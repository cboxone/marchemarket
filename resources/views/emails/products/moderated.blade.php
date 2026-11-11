<x-emails.layout>
    <h1 style="font-size:20px; margin:0 0 16px;">
        {{ $action === 'removed' ? __('Annonce supprimée') : __('Annonce suspendue') }}
    </h1>
    <p style="font-size:14px; line-height:1.6; margin:0 0 16px;">
        {{ __('Bonjour :name,', ['name' => $user->name]) }}<br>
        @if($action === 'removed')
            {{ __("Votre annonce « :product » a été supprimée du catalogue par notre équipe.", ['product' => $productName]) }}
        @else
            {{ __("Votre annonce « :product » a été suspendue par notre équipe : elle n'est plus visible dans le catalogue.", ['product' => $productName]) }}
        @endif
    </p>
    @if($reason)
        <p style="font-size:14px; line-height:1.6; margin:0 0 16px;">
            <strong>{{ __('Motif :') }}</strong> {{ $reason }}
        </p>
    @endif
    <p style="font-size:14px; line-height:1.6; margin:0;">
        {{ __("Si vous pensez qu'il s'agit d'une erreur, contactez notre support.") }}
    </p>
</x-emails.layout>
