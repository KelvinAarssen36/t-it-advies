<?php

namespace Tests\Feature\Security;

use App\Enums\MailStatus;
use App\Enums\SecurityEventType;
use App\Enums\SecurityOutcome;
use App\Mail\SecurityAlertMail;
use App\Models\MailLog;
use App\Models\SecurityEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        Mail::assertNotQueued(SecurityAlertMail::class);
    }

    public function test_an_alert_is_sent_above_the_threshold(): void
    {
        Mail::fake();

        $this->failedLogins(6);

        $this->artisan('security:report')->assertSuccessful();

        Mail::assertQueued(SecurityAlertMail::class);

        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventType::AlertSent->value,
        ]);
    }

    public function test_mail_problems_trigger_their_own_alert(): void
    {
        Mail::fake();

        $this->bouncedMails(4);

        $this->artisan('security:report')->assertSuccessful();

        Mail::assertQueued(SecurityAlertMail::class, function (SecurityAlertMail $mail) {
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

        Mail::assertNotQueued(SecurityAlertMail::class);
    }

    public function test_the_same_signal_is_not_repeated_within_the_cooldown(): void
    {
        Mail::fake();

        $this->failedLogins(6);

        $this->artisan('security:report')->assertSuccessful();
        $this->artisan('security:report')->assertSuccessful();

        Mail::assertQueued(SecurityAlertMail::class, 1);
    }

    public function test_force_ignores_the_cooldown(): void
    {
        Mail::fake();

        $this->failedLogins(6);

        $this->artisan('security:report')->assertSuccessful();
        $this->artisan('security:report', ['--force' => true])->assertSuccessful();

        Mail::assertQueued(SecurityAlertMail::class, 2);
    }

    public function test_without_an_address_nothing_is_sent_and_nothing_breaks(): void
    {
        Mail::fake();

        config(['security.alerts.address' => null]);

        $this->failedLogins(6);

        // Geen fout: de scheduler moet blijven draaien. Er gaat wel een
        // waarschuwing naar de applicatielog.
        $this->artisan('security:report')->assertSuccessful();

        Mail::assertNotQueued(SecurityAlertMail::class);
    }

    public function test_the_alert_contains_no_user_addresses(): void
    {
        Mail::fake();

        $this->failedLogins(6);

        $this->artisan('security:report')->assertSuccessful();

        Mail::assertQueued(SecurityAlertMail::class, function (SecurityAlertMail $mail) {
            $rendered = $mail->render();

            return ! str_contains($rendered, 'aanvaller1@example.com')
                && str_contains($rendered, '203.0.113.10');
        });
    }
}
