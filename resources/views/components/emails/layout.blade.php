<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MarcheMarket</title>
</head>
<body style="margin:0; padding:0; background-color:#f7f8fa; font-family: Arial, Helvetica, sans-serif; color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f7f8fa; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width:560px; background-color:#ffffff; border-radius:8px; overflow:hidden; border:1px solid #e2e5ea;">
                    <tr>
                        <td style="background-color:#12233d; padding:20px 28px;">
                            <span style="font-size:20px; font-weight:700; color:#ffffff;">Marche<span style="color:#e8792b;">Market</span></span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px; background-color:#f7f8fa; border-top:1px solid #e2e5ea;">
                            <p style="margin:0; font-size:12px; color:#8b9ab6;">
                                © {{ date('Y') }} MarcheMarket — {{ __('Projet de démonstration.') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
