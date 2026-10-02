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

    /**
     * Wat er van deze mail in het mailoverzicht terechtkomt.
     *
     * **Niet het echte onderwerp, want daar staat tekst van de bezoeker
     * in.** Die zou dan honderd tachtig dagen in onze database blijven
     * staan, en daar is geen enkele reden voor: het mailoverzicht is er om
     * te zien óf een bericht is aangekomen, niet om te lezen wat erin
     * stond.
     *
     * De eigenaar houdt het volledige onderwerp gewoon in zijn eigen
     * postvak. Dit gaat alleen over wat wij bewaren.
     *
     * Hierdoor kan de privacyverklaring zeggen dat we het bericht van een
     * bezoeker niet bewaren, zonder een uitzondering voor de onderwerpregel
     * erbij. Zie RecordOutgoingMail en
     * docs/architecture/bezoekcijfers.md.
     */
    public static function logboekOnderwerp(): string
    {
        return __('Bericht via het contactformulier');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.contact');
    }
}
