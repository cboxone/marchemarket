<x-emails.layout>
    <h1 style="font-size:20px; margin:0 0 16px;">{{ __('Nouveau message sur MarcheMarket') }}</h1>
    <p style="font-size:14px; line-height:1.6; margin:0 0 16px;">
        {{ __('Bonjour :name,', ['name' => $recipientName]) }}<br>
        {{ __('Vous avez reçu un nouveau message :') }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
        <tr>
            <td style="padding:14px; background-color:#eef1f6; border-radius:6px; font-size:14px; font-style:italic;">
                « {{ \Illuminate\Support\Str::limit($message->body, 240) }} »
            </td>
        </tr>
    </table>

    <a href="{{ $viewUrl }}" style="display:inline-block; background-color:#e8792b; color:#ffffff; text-decoration:none; padding:10px 20px; border-radius:6px; font-size:14px; font-weight:600;">
        {{ __('Répondre') }}
    </a>
</x-emails.layout>
