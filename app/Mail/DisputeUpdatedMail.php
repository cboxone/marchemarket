<?php

namespace App\Mail;

use App\Models\Dispute;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notification d'une étape du cycle de vie d'un litige.
 *
 * Événements : opened (vers le fournisseur), vendor_responded et resolved
 * (vers l'acheteur), escalated et resolved (vers le fournisseur),
 * withdrawn (vers le fournisseur).
 */
class DisputeUpdatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param 'opened'|'vendor_responded'|'escalated'|'resolved'|'withdrawn' $event */
    public function __construct(
        public User $recipient,
        public Dispute $dispute,
        public string $event,
    ) {
    }

    public function envelope(): Envelope
    {
        $ref = $this->dispute->reference;

        return new Envelope(subject: match ($this->event) {
            'opened' => __('Nouveau litige :ref', ['ref' => $ref]),
            'vendor_responded' => __('Réponse à votre litige :ref', ['ref' => $ref]),
            'escalated' => __('Litige :ref transmis à notre équipe', ['ref' => $ref]),
            'resolved' => __('Litige :ref résolu', ['ref' => $ref]),
            default => __('Litige :ref retiré', ['ref' => $ref]),
        });
    }

    public function content(): Content
    {
        $forVendor = in_array($this->event, ['opened', 'escalated', 'withdrawn'], true)
            || ($this->event === 'resolved' && $this->recipient->isVendor());

        return new Content(
            view: 'emails.disputes.updated',
            with: [
                'recipient' => $this->recipient,
                'dispute' => $this->dispute,
                'event' => $this->event,
                'url' => $forVendor ? route('vendor.disputes.show', $this->dispute) : route('disputes.show', $this->dispute),
            ],
        );
    }
}
