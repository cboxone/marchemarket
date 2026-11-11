<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Alerte de sécurité : le compte de retrait d'un vendeur vient d'être
 * modifié. Si ce n'est pas lui, c'est le signe d'un accès frauduleux à son
 * compte — mieux vaut qu'il le sache avant le prochain reversement.
 */
class PayoutAccountChangedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public ?string $accountLabel)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Votre compte de retrait MarcheMarket a été modifié'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.vendors.payout-account-changed',
            with: ['user' => $this->user, 'accountLabel' => $this->accountLabel],
        );
    }
}
