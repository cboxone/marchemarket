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
 * Prévient un vendeur qu'une de ses annonces a été suspendue ou supprimée
 * par l'administration. Reçoit des valeurs simples (et non le modèle
 * Product) : pour une suppression, le produit n'existe plus au moment où
 * la file d'attente exécute l'envoi.
 */
class ProductModeratedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param 'suspended'|'removed' $action */
    public function __construct(
        public User $user,
        public string $productName,
        public string $action,
        public ?string $reason = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->action === 'removed'
                ? __('Une de vos annonces a été supprimée')
                : __('Une de vos annonces a été suspendue'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.products.moderated',
            with: [
                'user' => $this->user,
                'productName' => $this->productName,
                'action' => $this->action,
                'reason' => $this->reason,
            ],
        );
    }
}
