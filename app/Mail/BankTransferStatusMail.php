<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Confirmation ou rejet, par un admin, d'un virement bancaire déclaré par l'acheteur. */
class BankTransferStatusMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param 'confirmed'|'rejected' $event */
    public function __construct(
        public User $recipient,
        public Order $order,
        public string $event,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->event === 'confirmed'
            ? __('Virement reçu : commande :ref confirmée', ['ref' => $this->order->reference])
            : __('Virement non trouvé pour la commande :ref', ['ref' => $this->order->reference]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bank-transfer.status',
            with: [
                'recipient' => $this->recipient,
                'order' => $this->order,
                'event' => $this->event,
                'url' => route('orders.show', $this->order),
            ],
        );
    }
}
