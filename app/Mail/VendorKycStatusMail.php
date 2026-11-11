<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Décision de l'admin sur le dossier de vérification (KYC) d'un vendeur. */
class VendorKycStatusMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param 'approved'|'rejected' $event */
    public function __construct(
        public User $recipient,
        public Vendor $vendor,
        public string $event,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->event === 'approved'
            ? __('Votre boutique est vérifiée')
            : __('Votre dossier de vérification a été refusé'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.vendors.kyc-status',
            with: [
                'recipient' => $this->recipient,
                'vendor' => $this->vendor,
                'event' => $this->event,
                'url' => route('vendor.verification.edit'),
            ],
        );
    }
}
