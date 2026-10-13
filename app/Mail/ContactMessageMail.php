<?php

namespace App\Mail;

use App\Concerns\MeldtMislukteVerzending;
use App\Concerns\VolgtDeMailstijl;
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
 *
 * **Er worden alleen losse waarden doorgegeven en niet het model.** Met
 * `SerializesModels` zou de job alleen het id bewaren en de rij bij het
 * versturen opnieuw ophalen. Verwijdert de eigenaar de aanvraag in het
 * scherm Aanvragen voordat de wachtrij eraan toekomt, dan valt de job om
 * met een `ModelNotFoundException` en is het bericht weg. Lelijker, maar
 * het kan niet stuk.
 */
class ContactMessageMail extends Mailable implements ShouldQueue
{
    use MeldtMislukteVerzending, Queueable, SerializesModels, VolgtDeMailstijl;

    public function __construct(
        public readonly string $senderName,
        public readonly string $senderEmail,
        public readonly string $senderSubject,
        public readonly string $body,
        public readonly ?string $senderCompany = null,
        public readonly ?string $senderPhone = null,
        /** De taal waarin de bezoeker het formulier invulde. */
        public readonly string $senderLocale = 'nl',
    ) {
        /*
         * **Deze mail is altijd in de taal van de eigenaar, en dat zet hij
         * hier zelf.**
         *
         * Dat stond eerder bij de aanroeper, als
         * `->locale(config('app.locale'))`, en dat deed niets:
         * `App::setLocale()` schrijft de taal van het verzoek ín die
         * configuratiewaarde, dus na de middleware `SetLocale` ís
         * `app.locale` de taal van de bezoeker. Een Engelstalige bezoeker
         * leverde de eigenaar dus een Engels onderwerp in zijn eigen
         * postvak -- precies wat die regel moest voorkomen.
         *
         * Hier kan geen aanroeper het vergeten. `site.locale` hoort bij het
         * bedrijf en wordt door geen enkel verzoek omgezet. Wil een
         * aanroeper er toch van afwijken, dan kan dat nog met
         * `Mail::to(...)->locale(...)`: `PendingMail::fill()` overschrijft
         * deze waarde alleen als hij expliciet is meegegeven.
         *
         * De taal van de bezoeker staat wél in de mail, als regel "Taal van
         * de bezoeker" -- zodat de eigenaar weet in welke taal hij hoort te
         * antwoorden. Zie resources/views/mail/contact.blade.php.
         */
        $this->locale(config('site.locale'));

        // En de stijl die de eigenaar heeft gekozen; zie VolgtDeMailstijl.
        $this->pasDeMailstijlToe();
    }

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
     * **Dat de aanvraag zelf nu wél wordt bewaard verandert hier niets
     * aan.** Die staat in `contact_submissions` met een eigen
     * bewaartermijn van een jaar; het mailoverzicht heeft het onderwerp
     * daarnaast niet nodig en bewaart honderdtachtig dagen. Zie
     * RecordOutgoingMail en docs/architecture/modules/contact.md.
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
