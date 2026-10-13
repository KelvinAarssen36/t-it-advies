<?php

namespace App\Mail;

use App\Concerns\MeldtMislukteVerzending;
use App\Concerns\VolgtDeMailstijl;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * De waarschuwing naar het óude inlogadres.
 *
 * **Deze mail is er voor het geval dat jij het niet was.** Iemand met een
 * open sessie en jouw wachtwoord kan een adreswijziging aanvragen; dit is
 * het bericht dat je dat laat weten vóórdat hij klaar is, met een knop om
 * hem af te breken.
 *
 * Die knop is dezelfde link als de herstellink later: één adres dat
 * altijd "nee, draai dit terug" betekent. Zie EmailChangeController.
 *
 * Zie docs/security/inlogadres-wijzigen.md.
 */
class InlogadresAangevraagdMail extends Mailable
{
    use MeldtMislukteVerzending, Queueable, SerializesModels, VolgtDeMailstijl;

    public function __construct(
        public readonly string $naam,
        public readonly string $nieuwAdres,
        public readonly string $afbreekLink,
    ) {
        $this->locale(config('site.locale'));
        $this->pasDeMailstijlToe();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Er is een ander inlogadres aangevraagd'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.inlogadres-aangevraagd');
    }

    public static function logboekOnderwerp(): string
    {
        return __('Er is een ander inlogadres aangevraagd');
    }
}
