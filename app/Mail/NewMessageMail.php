<?php

namespace App\Mail;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewMessageMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param string $recipientName Nom de la personne qui reçoit l'e-mail (pas l'expéditeur du message)
     * @param string $viewUrl Lien vers le fil de discussion, déjà résolu côté contrôleur (route différente acheteur/vendeur)
     */
    public function __construct(
        public Message $message,
        public string $recipientName,
        public string $viewUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Nouveau message sur MarcheMarket'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.messages.new-message',
            with: [
                'message' => $this->message,
                'recipientName' => $this->recipientName,
                'viewUrl' => $this->viewUrl,
            ],
        );
    }
}
