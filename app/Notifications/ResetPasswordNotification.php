<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(private readonly string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject(__('Réinitialisation de votre mot de passe MarcheMarket'))
            ->greeting(__('Bonjour :name,', ['name' => $notifiable->name]))
            ->line(__('Vous recevez cet e-mail car nous avons reçu une demande de réinitialisation de mot de passe pour votre compte.'))
            ->action(__('Réinitialiser le mot de passe'), $url)
            ->line(__('Ce lien de réinitialisation expirera dans :count minutes.', ['count' => config('auth.passwords.users.expire', 60)]))
            ->line(__("Si vous n'êtes pas à l'origine de cette demande, aucune action n'est requise."));
    }
}
