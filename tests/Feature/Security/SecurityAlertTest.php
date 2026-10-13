<?php

namespace Tests\Feature\Security;

use App\Enums\MailStatus;
use App\Enums\SecurityEventType;
use App\Enums\SecurityOutcome;
use App\Mail\SecurityAlertMail;
use App\Models\MailLog;
use App\Models\SecurityEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Een logboek waar niemand in kijkt is geen bewaking. De alarmering kijkt
 * voor je, maar dan moet hij wel precies zijn: te vroeg alarm maakt dat
 * niemand het nog leest, te laat alarm is nutteloos.
 *
 * De belangrijkste afspraak die hier wordt bewaakt: in de meldingsmail
 * staan geen e-mailadressen van gebruikers. Die mail gaat naar een postbus
 * die minder goed beveiligd is dan de applicatie zelf.
 */
class SecurityAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'security.alerts.address' => 'beheer@example.com',
            'security.alerts.window_minutes' => 60,
            'security.alerts.cooldown_minutes' => 180,
            'security.alerts.thresholds.failed_logins' => 5,
            'security.alerts.thresholds.mail_problems' => 3,

            /*
             * De wachtrijcontrole staat hier uit, anders meldt hij mee in
             * élke test hieronder: een `Mail::fake()` zet niets in de
             * tabel `jobs`, maar de drempel staat in productie op 1 en
             * nul-is-nul zou dan toevallig goed gaan. Hij heeft zijn eigen
             * tests onderaan.
             */
            'security.alerts.thresholds.stuck_jobs' => 0,
        ]);
    }

    /** Eén job in de wachtrij zetten die al over tijd is. */
    private function vastgelopenJob(int $minutenOud): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subMinutes($minutenOud)->getTimestamp(),
            'created_at' => now()->subMinutes($minutenOud)->getTimestamp(),
        ]);
    }

    private function failedLogins(int $count): void
    {
        foreach (range(1, $count) as $index) {
            SecurityEvent::create([
                'event' => SecurityEventType::LoginFailed->value,
                'outcome' => SecurityOutcome::Failure,
                'email' => "aanvaller{$index}@example.com",
                'ip_address' => '203.0.113.10',
            ]);
        }
    }

    private function bouncedMails(int $count): void
    {
        foreach (range(1, $count) as $index) {
            MailLog::create([
                'message_id' => "bounce-{$index}",
                'to' => ["klant{$index}@example.com"],
                'status' => MailStatus::Bounced,
                'sent_at' => now(),
                'last_event_at' => now(),
            ]);
        }
    }

    public function test_nothing_is_sent_below_the_threshold(): void
    {
        Mail::fake();

        $this->failedLogins(4);

        $this->artisan('security:report')->assertSuccessful();

        Mail::assertNotSent(SecurityAlertMail::class);
    }

    public function test_an_alert_is_sent_above_the_threshold(): void
    {
        Mail::fake();

        $this->failedLogins(6);

        $this->artisan('security:report')->assertSuccessful();

        Mail::assertSent(SecurityAlertMail::class);

        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventType::AlertSent->value,
        ]);
    }

    public function test_mail_problems_trigger_their_own_alert(): void
    {
        Mail::fake();

        $this->bouncedMails(4);

        $this->artisan('security:report')->assertSuccessful();

        Mail::assertSent(SecurityAlertMail::class, function (SecurityAlertMail $mail) {
            return count($mail->anomalies) === 1
                && $mail->anomalies[0]->key === 'mail-problems';
        });
    }

    public function test_old_events_fall_outside_the_window(): void
    {
        Mail::fake();

        $this->failedLogins(6);

        SecurityEvent::query()->update(['created_at' => now()->subDay()]);

        $this->artisan('security:report')->assertSuccessful();

        Mail::assertNotSent(SecurityAlertMail::class);
    }

    public function test_the_same_signal_is_not_repeated_within_the_cooldown(): void
    {
        Mail::fake();

        $this->failedLogins(6);

        $this->artisan('security:report')->assertSuccessful();
        $this->artisan('security:report')->assertSuccessful();

        Mail::assertSent(SecurityAlertMail::class, 1);
    }

    public function test_force_ignores_the_cooldown(): void
    {
        Mail::fake();

        $this->failedLogins(6);

        $this->artisan('security:report')->assertSuccessful();
        $this->artisan('security:report', ['--force' => true])->assertSuccessful();

        Mail::assertSent(SecurityAlertMail::class, 2);
    }

    public function test_without_an_address_nothing_is_sent_and_nothing_breaks(): void
    {
        Mail::fake();

        config(['security.alerts.address' => null]);

        $this->failedLogins(6);

        // Geen fout: de scheduler moet blijven draaien. Er gaat wel een
        // waarschuwing naar de applicatielog.
        $this->artisan('security:report')->assertSuccessful();

        Mail::assertNotSent(SecurityAlertMail::class);
    }

    public function test_the_alert_contains_no_user_addresses(): void
    {
        Mail::fake();

        $this->failedLogins(6);

        $this->artisan('security:report')->assertSuccessful();

        Mail::assertSent(SecurityAlertMail::class, function (SecurityAlertMail $mail) {
            $rendered = $mail->render();

            return ! str_contains($rendered, 'aanvaller1@example.com')
                && str_contains($rendered, '203.0.113.10');
        });
    }

    /* --- De wachtrij die stilvalt --------------------------------------- */

    /**
     * **Het alarm gaat niet door de wachtrij die het bewaakt.**
     *
     * Dit is de kern van deze hele controle. `SecurityAlertMail` is een
     * `ShouldQueue`, en `Mail::send()` zet zo'n mail alsnog in de wachtrij
     * -- dat doet de mailer zelf. Daarmee ging precies het ene bericht dat
     * je nodig hebt als de worker stilstaat, door die stilstaande worker.
     *
     * Vandaar `sendNow()` in het commando, en vandaar deze test: hij valt
     * om op het moment dat iemand daar weer `send()` van maakt.
     */
    public function test_the_alert_does_not_go_through_the_queue(): void
    {
        Mail::fake();

        $this->failedLogins(6);

        $this->artisan('security:report')->assertSuccessful();

        Mail::assertSent(SecurityAlertMail::class);
        Mail::assertNotQueued(SecurityAlertMail::class);
    }

    /**
     * Werk dat blijft liggen levert een melding op.
     *
     * De stilste storing die deze applicatie heeft: alle mail gaat via de
     * wachtrij, dus zonder worker krijgt de bezoeker zijn bedankje, staat
     * de aanvraag netjes in het portaal, en wacht de eigenaar op een mail
     * die nooit komt. Er is niets dat eruitziet als een fout.
     */
    public function test_a_stalled_queue_raises_an_alert(): void
    {
        Mail::fake();

        config([
            'security.alerts.thresholds.stuck_jobs' => 1,
            // De testsuite draait op ; deze controle hangt aan .
            'queue.default' => 'database',
        ]);

        $this->vastgelopenJob(minutenOud: 30);

        $this->artisan('security:report')->assertSuccessful();

        Mail::assertSent(SecurityAlertMail::class, function (SecurityAlertMail $mail) {
            return str_contains($mail->render(), __('Werk blijft in de wachtrij liggen'));
        });
    }

    /** Werk dat net binnen is, is geen storing. */
    public function test_fresh_work_in_the_queue_is_not_an_alert(): void
    {
        Mail::fake();

        config([
            'security.alerts.thresholds.stuck_jobs' => 1,
            'security.alerts.stuck_job_minutes' => 15,
            'queue.default' => 'database',
        ]);

        $this->vastgelopenJob(minutenOud: 2);

        $this->artisan('security:report')->assertSuccessful();

        Mail::assertNotSent(SecurityAlertMail::class);
    }

    /**
     * Een job die een worker al heeft opgepakt telt wél mee.
     *
     * Staat zo'n rij een half uur later nog in de tabel, dan is die worker
     * onderweg gestopt -- en dat is net zo goed een storing als een worker
     * die nooit begon.
     */
    public function test_work_a_worker_picked_up_and_abandoned_counts(): void
    {
        Mail::fake();

        config([
            'security.alerts.thresholds.stuck_jobs' => 1,
            // De testsuite draait op ; deze controle hangt aan .
            'queue.default' => 'database',
        ]);

        $this->vastgelopenJob(minutenOud: 30);

        DB::table('jobs')->update([
            'reserved_at' => now()->subMinutes(25)->getTimestamp(),
            'attempts' => 1,
        ]);

        $this->artisan('security:report')->assertSuccessful();

        Mail::assertSent(SecurityAlertMail::class);
    }

    /**
     * Met een andere wachtrij dan de database zwijgt de controle.
     *
     * Bij `sync` wordt werk tijdens het verzoek zelf gedaan, dus kan er per
     * definitie niets blijven liggen; bij Redis zit de wachtrij ergens waar
     * wij hier niet in kijken. Een alarm dat altijd nul meldt leert je het
     * te negeren.
     */
    public function test_another_queue_driver_stays_quiet(): void
    {
        Mail::fake();

        config([
            'security.alerts.thresholds.stuck_jobs' => 1,
            'queue.default' => 'sync',
        ]);

        $this->vastgelopenJob(minutenOud: 30);

        $this->artisan('security:report')->assertSuccessful();

        Mail::assertNotSent(SecurityAlertMail::class);
    }
}
