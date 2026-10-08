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
 * De bevestigingslink naar het níeuwe inlogadres.
 *
 * **Deze mail is de hele grendel.** Zolang hij niet is geopend verandert
 * er niets aan het account -- dat is wat een typefout onschadelijk maakt.
 *
 * **Niet in de wachtrij.** De eigenaar staat op dat moment naar zijn
 * scherm te kijken en wacht erop; een mail die pas komt als de wachtrij
 * eraan toe is, is hier het verschil tussen "het werkt" en "het werkt
 * niet". De andere twee mails van deze stroom gaan om dezelfde reden ook
 * direct.
 *
 * Zie docs/security/inlogadres-wijzigen.md.
 */
class InlogadresBevestigenMail extends Mailable
{
    use MeldtMislukteVerzending, Queueable, SerializesModels, VolgtDeMailstijl;

    public function __construct(
        public readonly string $naam,
        public readonly string $oudAdres,
        public readonly string $nieuwAdres,
        public readonly string $link,
        public readonly int $geldigeMinuten,
    ) {
        // Altijd in de taal van de eigenaar; deze mail gaat alleen naar
        // hem. Zie config/site.php.
        $this->locale(config('site.locale'));

        $this->pasDeMailstijlToe();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Bevestig je nieuwe inlogadres'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.inlogadres-bevestigen');
    }

    /**
     * Wat er in het mailoverzicht komt te staan.
     *
     * Het onderwerp zelf mag, maar niet de link: die staat in de inhoud en
     * niet in het logboek. Een logboekregel met een geldig token erin is
     * een token dat op twee plekken ligt.
     */
    public static function logboekOnderwerp(): string
    {
        return __('Bevestig je nieuwe inlogadres');
    }
}
