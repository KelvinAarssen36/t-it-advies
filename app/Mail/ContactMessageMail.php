<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Het bericht dat binnenkomt via het contactformulier.
 *
 * Deze mail wordt altijd via de queue verstuurd (ShouldQueue). Het formulier
 * hoeft dus niet te wachten op de mailprovider, en een tijdelijke storing bij
 * de provider kost geen bericht: de job wordt opnieuw geprobeerd.
 *
 * De afzender is altijd ons eigen domein, want alleen daarvan kloppen SPF en
 * DKIM. Het adres van de bezoeker zetten we in Reply-To. Zie
 * docs/security/e-mailauthenticatie.md voor waarom dat belangrijk is.
 */
class ContactMessageMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $senderName,
        public readonly string $senderEmail,
        public readonly string $senderSubject,
        public readonly string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Contactformulier: :subject', ['subject' => $this->senderSubject]),
            replyTo: [new Address($this->senderEmail, $this->senderName)],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.contact');
    }
}
