<?php

namespace App\Mail;

use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notification d'une étape du cycle de vie d'un devis.
 *
 * Événements : requested (vers le fournisseur), quoted et declined (vers
 * l'acheteur), accepted, rejected et cancelled (vers le fournisseur).
 */
class QuoteUpdatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param 'requested'|'quoted'|'declined'|'accepted'|'rejected'|'cancelled' $event */
    public function __construct(
        public User $recipient,
        public QuoteRequest $quote,
        public string $event,
    ) {
    }

    public function envelope(): Envelope
    {
        $ref = $this->quote->reference;

        return new Envelope(subject: match ($this->event) {
            'requested' => __('Nouvelle demande de devis :ref', ['ref' => $ref]),
            'quoted' => __('Votre devis est prêt : :ref', ['ref' => $ref]),
            'declined' => __('Votre demande de devis :ref a été déclinée', ['ref' => $ref]),
            'accepted' => __('Devis accepté : :ref', ['ref' => $ref]),
            'rejected' => __('Devis refusé : :ref', ['ref' => $ref]),
            default => __('Demande de devis annulée : :ref', ['ref' => $ref]),
        });
    }

    public function content(): Content
    {
        // Les événements destinés au fournisseur pointent vers son espace, les autres vers celui de l'acheteur.
        $url = in_array($this->event, ['quoted', 'declined'], true)
            ? route('quotes.show', $this->quote)
            : route('vendor.quotes.show', $this->quote);

        return new Content(
            view: 'emails.quotes.updated',
            with: [
                'recipient' => $this->recipient,
                'quote' => $this->quote,
                'event' => $this->event,
                'url' => $url,
            ],
        );
    }
}
