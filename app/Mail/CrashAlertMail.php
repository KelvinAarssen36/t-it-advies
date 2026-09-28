<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * De melding die uitgaat wanneer de applicatie omvalt.
 *
 * Bewust **niet** in de wachtrij, in tegenstelling tot de andere mail in
 * dit project. Valt de applicatie om doordat de database weg is, dan komt
 * een taak in de wachtrij nooit aan -- en dan mis je juist de melding die
 * je het hardst nodig had.
 *
 * Even bewust karig: soort, plek, adres. Geen stacktrace en geen
 * verzoekgegevens, want deze mail landt in een postbus die minder goed
 * beveiligd is dan de applicatie zelf. Voor het volledige verhaal ga je
 * naar `storage/logs/laravel.log` -- deze mail zegt alleen dát je moet
 * kijken en waar.
 *
 * Zie docs/operations/monitoring.md.
 */
class CrashAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $soort,
        public readonly string $melding,
        public readonly string $plek,
        public readonly ?string $adres,
        public readonly int|string|null $gebruiker,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Er ging iets mis op :app', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.crash-alert');
    }
}
