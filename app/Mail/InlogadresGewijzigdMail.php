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
 * Het bericht naar het oude adres dat de wijziging is doorgevoerd.
 *
 * **Deze mail bevat met opzet géén nieuwe herstellink**, en dat verdient
 * uitleg. Het herstel-token staat gehasht in de database, zoals een
 * wachtwoord -- het platte token bestaat maar één keer, in de eerste mail.
 * Hier een link zetten zou betekenen dat we een vers token maken en het
 * oude ongeldig verklaren.
 *
 * Dat is precies wat je niet wil. Komt déze mail niet aan -- een hapering
 * bij de provider, een volle postbus -- dan heb je met een vers token
 * helemaal geen werkende link meer, want de oude is dan net weggegooid.
 * Met één token blijft de link uit de eerste mail veertien dagen werken,
 * wat er met deze ook gebeurt.
 *
 * De tekst verwijst daarom naar die eerste mail. Iets minder handig, een
 * stuk moeilijker stuk te krijgen -- en dit is het vangnet, dus die kant
 * hoort hij op te vallen.
 *
 * Zie docs/security/inlogadres-wijzigen.md.
 */
class InlogadresGewijzigdMail extends Mailable
{
    use MeldtMislukteVerzending, Queueable, SerializesModels, VolgtDeMailstijl;

    public function __construct(
        public readonly string $naam,
        public readonly string $oudAdres,
        public readonly string $nieuwAdres,
        public readonly int $geldigeDagen,
    ) {
        $this->locale(config('site.locale'));
        $this->pasDeMailstijlToe();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Je inlogadres is gewijzigd'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.inlogadres-gewijzigd');
    }

    public static function logboekOnderwerp(): string
    {
        return __('Je inlogadres is gewijzigd');
    }
}
