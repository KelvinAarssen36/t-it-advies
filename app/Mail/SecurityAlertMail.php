<?php

namespace App\Mail;

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
    use Queueable, SerializesModels;

    /**
     * @param  array<int, Anomaly>  $anomalies
     */
    public function __construct(
        public readonly array $anomalies,
        public readonly CarbonInterface $since,
    ) {}

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
