<?php

namespace App\Mail;

use App\Concerns\MeldtMislukteVerzending;
use App\Concerns\VolgtDeMailstijl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * De bevestiging aan de bezoeker die het contactformulier invulde.
 *
 * **Dit is een visitekaartje en geen ontvangstbevestiging.** Voor veel
 * mensen is dit de eerste mail die ze van dit bedrijf krijgen, en vaak de
 * enige tot er antwoord komt. Daarom staat hij in de huisstijl en niet in
 * het standaard witte sjabloon van Laravel.
 *
 * **De taal komt van buiten en niet van `app()->getLocale()`.** Deze mail
 * gaat via de wachtrij, en die draait in een losse opdrachtregel waar de
 * taal van het verzoek niet meer bestaat -- daar is hij altijd Nederlands.
 * De aanroeper gebruikt `Mail::to(...)->locale($aanvraag->locale)`; zie
 * ContactController::verstuur().
 *
 * **De tekst is van de eigenaar.** Hij staat in `contact_settings` en is
 * te wijzigen op het scherm Website → Contact, in beide talen.
 *
 * Zie docs/architecture/modules/contact.md.
 */
class ContactBevestigingMail extends Mailable implements ShouldQueue
{
    use MeldtMislukteVerzending, Queueable, SerializesModels, VolgtDeMailstijl;

    public function __construct(
        public readonly string $naam,
        public readonly string $onderwerp,
        public readonly string $tekst,
        /** Het onderwerp dat de bezoeker koos, als geheugensteun. */
        public readonly string $samenvatting,
    ) {
        /*
         * De stijl die de eigenaar heeft gekozen; zie VolgtDeMailstijl.
         *
         * **Wél de stijl en níet de taal.** Deze mail is de uitzondering:
         * zijn taal is van de bezoeker en komt van de aanroeper. De stijl
         * is wel van de eigenaar, net als bij elke andere mail -- dat zijn
         * twee verschillende vragen en ze horen niet dezelfde kant op.
         */
        $this->pasDeMailstijlToe();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->onderwerp);
    }

    /**
     * Wat er van deze mail in het mailoverzicht terechtkomt.
     *
     * Het onderwerp is hier van de eigenaar en niet van de bezoeker, dus
     * het zou mogen blijven staan. Toch een neutrale regel: het overzicht
     * is er om te zien óf een bevestiging is aangekomen, en honderd
     * dezelfde onderwerpregels onder elkaar maken dat niet makkelijker.
     */
    public static function logboekOnderwerp(): string
    {
        return __('Bevestiging aan een bezoeker');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.contact-bevestiging');
    }
}
