<?php

namespace App\Mail;

use App\Concerns\VolgtDeMailstijl;
use App\Support\Security\Anomaly;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * De melding die uitgaat wanneer de alarmering iets ziet.
 *
 * Bewust karig: wat er speelt, hoe vaak, en een link naar het logboek. Geen
 * e-mailadressen van gebruikers en geen mailinhoud, want deze mail landt in
 * een postbus die minder goed beveiligd is dan de applicatie zelf.
 *
 * Zie docs/operations/onderhoudstaken.md.
 */
class SecurityAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, VolgtDeMailstijl;

    /**
     * @param  array<int, Anomaly>  $anomalies
     */
    public function __construct(
        public readonly array $anomalies,
        public readonly CarbonInterface $since,
    ) {
        /*
         * Altijd in de taal van de eigenaar, net als de andere twee mails
         * die naar hem toe gaan.
         *
         * Deze draait uit de planner, waar de taal van een verzoek niet
         * bestaat, dus hier ging het nog niet mis. Hij staat er toch, om
         * dezelfde reden als een gordel in een auto die nog nooit is
         * gebotst: zodra iemand `security:report` ooit vanuit een verzoek
         * aanroept, klopt het nog steeds. Zie config/site.php.
         */
        $this->locale(config('site.locale'));

        // En de stijl die de eigenaar heeft gekozen; zie VolgtDeMailstijl.
        $this->pasDeMailstijlToe();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Beveiligingsmelding: :titels', [
                'titels' => implode(', ', array_map(
                    fn (Anomaly $anomaly) => $anomaly->title,
                    $this->anomalies,
                )),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.security-alert',
            with: [
                'logUrl' => route('admin.security.index'),
            ],
        );
    }
}
