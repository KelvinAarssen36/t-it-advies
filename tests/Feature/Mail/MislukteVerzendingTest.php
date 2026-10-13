<?php

namespace Tests\Feature\Mail;

use App\Enums\MailStatus;
use App\Mail\ContactBevestigingMail;
use App\Mail\ContactMessageMail;
use App\Mail\SecurityAlertMail;
use App\Models\MailLog;
use App\Support\Security\AnomalyScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/**
 * Een mail die nooit is verstuurd laat alsnog een spoor achter.
 *
 * **Het gat dat dit vult merk je alleen als het misgaat, en dan te laat.**
 * `RecordOutgoingMail` luistert naar `MessageSent`, en dat event komt er
 * alleen als de mail de deur uit is. Lag de mailserver eruit, dan gooide de
 * job, werd hij opnieuw geprobeerd, en belandde hij in `failed_jobs` --
 * zonder één regel in Beheer → Mail en zonder melding. De eigenaar zag een
 * aanvraag in zijn portaal staan waar nooit een mail bij was gekomen, en
 * niets vertelde hem dat.
 *
 * Zie app/Concerns/MeldtMislukteVerzending.php en
 * docs/architecture/mail-en-queues.md.
 */
class MislukteVerzendingTest extends TestCase
{
    use RefreshDatabase;

    private function bevestiging(): ContactBevestigingMail
    {
        $mail = new ContactBevestigingMail(
            naam: 'Kees Jansen',
            onderwerp: 'We hebben je bericht ontvangen',
            tekst: 'Bedankt voor je bericht.',
            samenvatting: 'Vrijblijvend gesprek',
        );

        $mail->to('kees@example.com');

        return $mail;
    }

    public function test_a_failed_mail_gets_a_row_in_the_overview(): void
    {
        $this->bevestiging()->failed(
            new TransportException('Connection could not be established with host "smtp:1025"'),
        );

        $log = MailLog::query()->sole();

        $this->assertSame(MailStatus::Failed, $log->status);
        $this->assertSame(ContactBevestigingMail::class, $log->mailable);
        $this->assertSame(['kees@example.com'], $log->to);

        // Geen Message-ID: de provider heeft deze mail nooit gezien, dus er
        // valt ook nooit een webhook op te koppelen.
        $this->assertNull($log->message_id);

        // En niets dat suggereert dat hij is verstuurd.
        $this->assertNull($log->sent_at);
        $this->assertNotNull($log->last_event_at);
    }

    public function test_the_error_names_the_exception_class(): void
    {
        $this->bevestiging()->failed(new RuntimeException('Iets uit onze eigen code'));

        $fout = (string) MailLog::query()->sole()->error;

        /*
         * De klasse erbij, want "Connection could not be established" zegt
         * iets anders als het een TransportException is dan wanneer het uit
         * onze eigen code komt.
         */
        $this->assertStringContainsString(RuntimeException::class, $fout);
        $this->assertStringContainsString('Iets uit onze eigen code', $fout);
    }

    public function test_a_very_long_error_is_shortened(): void
    {
        $this->bevestiging()->failed(new RuntimeException(str_repeat('x', 5000)));

        $fout = (string) MailLog::query()->sole()->error;

        $this->assertLessThanOrEqual(500, mb_strlen($fout));
        $this->assertStringEndsWith('...', $fout);
    }

    /**
     * Geen woord van de bezoeker in het logboek, ook niet als het misgaat.
     *
     * Dezelfde regel als bij een geslaagde mail: het echte onderwerp van
     * `ContactMessageMail` bevat tekst die de bezoeker zelf heeft getypt, en
     * die hoort niet honderdtachtig dagen in onze database. Zie
     * `logboekOnderwerp()` en MailLoggingTest.
     */
    public function test_a_visitors_own_words_do_not_end_up_in_the_log_when_sending_fails(): void
    {
        $mail = new ContactMessageMail(
            senderName: 'Kees Jansen',
            senderEmail: 'kees@example.com',
            senderSubject: 'Mijn bedrijfsnaam staat in dit onderwerp',
            body: 'En dit is mijn bericht.',
        );

        $mail->to('info@atitadvies.nl');
        $mail->failed(new TransportException('smtp weg'));

        $log = MailLog::query()->sole();

        $this->assertSame(ContactMessageMail::logboekOnderwerp(), $log->subject);
        $this->assertStringNotContainsString('Mijn bedrijfsnaam', (string) $log->subject);
        $this->assertStringNotContainsString('En dit is mijn bericht', (string) $log->error);
    }

    /**
     * De eigenaar krijgt er ook echt een melding over.
     *
     * **Dat is het eigenlijke doel en niet het logboek.** Een regel die
     * niemand opzoekt helpt niet; `AnomalyScanner` telt een `Failed`-rij als
     * mailprobleem, dus `security:report` meldt het binnen het uur.
     */
    public function test_the_anomaly_scanner_counts_it_as_a_mail_problem(): void
    {
        config()->set('security.alerts.thresholds.mail_problems', 1);

        $this->bevestiging()->failed(new TransportException('smtp weg'));

        $signalen = collect(app(AnomalyScanner::class)->scan(now()->subHour()))
            ->filter(fn ($signaal) => $signaal->key === 'mail-problems');

        $this->assertCount(1, $signalen);
        $this->assertSame(1, $signalen->first()->count);
    }

    /**
     * De alarmeringsmail doet dit met opzet niet.
     *
     * Die mail ís de melding over mailproblemen. Zou hij bij mislukken een
     * rij schrijven, dan is dat een nieuw mailprobleem, waarover de scanner
     * weer een melding wil sturen, die weer kan mislukken.
     */
    public function test_the_alert_mail_does_not_report_itself(): void
    {
        $this->assertFalse(
            method_exists(SecurityAlertMail::class, 'failed'),
            'SecurityAlertMail mag zijn eigen mislukking niet loggen: dat is een kringetje. '
                .'Zie app/Concerns/MeldtMislukteVerzending.php.',
        );
    }
}
