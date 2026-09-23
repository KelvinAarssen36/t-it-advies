<?php

namespace Tests\Feature\Console;

use App\Enums\MailStatus;
use App\Enums\SecurityEventType;
use App\Enums\SecurityOutcome;
use App\Models\MailLog;
use App\Models\SecurityEvent;
use App\Support\Maintenance\RowPruner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De logboeken groeien bij elke inlogpoging en elke verstuurde mail. Zonder
 * opruimtaak loopt dat vol, en dan worden juist de zoekopdrachten traag die
 * je nodig hebt op het moment dat er iets aan de hand is.
 *
 * Deze tests bewaken twee dingen: dat oude regels echt verdwijnen, en dat
 * recente regels blijven staan. Dat tweede is het belangrijkste -- een
 * opruimtaak die te veel weghaalt, wist je bewijsmateriaal.
 */
class MaintenanceCommandsTest extends TestCase
{
    use RefreshDatabase;

    private function securityEvent(int $daysAgo): SecurityEvent
    {
        $event = SecurityEvent::create([
            'event' => SecurityEventType::LoginFailed->value,
            'outcome' => SecurityOutcome::Failure,
            'email' => 'iemand@example.com',
            'ip_address' => '203.0.113.10',
        ]);

        // created_at staat niet in de fillable-lijst: de tabel is append-only
        // en zet hem zelf. Voor de test schuiven we hem met de query builder.
        SecurityEvent::query()->whereKey($event->getKey())->update([
            'created_at' => now()->subDays($daysAgo),
        ]);

        return $event;
    }

    private function mailLog(int $daysAgo, string $messageId): MailLog
    {
        $log = MailLog::create([
            'message_id' => $messageId,
            'to' => ['klant@example.com'],
            'status' => MailStatus::Delivered,
            'sent_at' => now()->subDays($daysAgo),
        ]);

        MailLog::query()->whereKey($log->getKey())->update([
            'created_at' => now()->subDays($daysAgo),
        ]);

        return $log;
    }

    public function test_old_security_events_are_removed_and_recent_ones_stay(): void
    {
        config(['security.logging.retention_days' => 30]);

        $old = $this->securityEvent(daysAgo: 60);
        $recent = $this->securityEvent(daysAgo: 5);

        $this->artisan('security:prune-events')->assertSuccessful();

        $this->assertDatabaseMissing('security_events', ['id' => $old->id]);
        $this->assertDatabaseHas('security_events', ['id' => $recent->id]);
    }

    public function test_the_days_option_overrides_the_configured_retention(): void
    {
        config(['security.logging.retention_days' => 365]);

        $event = $this->securityEvent(daysAgo: 10);

        $this->artisan('security:prune-events', ['--days' => 7])->assertSuccessful();

        $this->assertDatabaseMissing('security_events', ['id' => $event->id]);
    }

    public function test_a_retention_of_zero_days_is_refused(): void
    {
        $event = $this->securityEvent(daysAgo: 400);

        $this->artisan('security:prune-events', ['--days' => 0])->assertFailed();

        // Het hele logboek wissen is nooit de bedoeling van een onderhoudstaak.
        $this->assertDatabaseHas('security_events', ['id' => $event->id]);
    }

    public function test_old_mail_logs_are_removed_and_recent_ones_stay(): void
    {
        config(['mail.log_retention_days' => 30]);

        $old = $this->mailLog(daysAgo: 60, messageId: 'oud-1');
        $recent = $this->mailLog(daysAgo: 2, messageId: 'nieuw-1');

        $this->artisan('mail:prune-logs')->assertSuccessful();

        $this->assertDatabaseMissing('mail_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('mail_logs', ['id' => $recent->id]);
    }

    public function test_pruning_continues_past_the_first_chunk(): void
    {
        foreach (range(1, 5) as $index) {
            $this->securityEvent(daysAgo: 100 + $index);
        }

        $deleted = app(RowPruner::class)->prune(
            SecurityEvent::query(),
            now()->subDays(50),
            chunk: 2,
        );

        $this->assertSame(5, $deleted);
        $this->assertSame(0, SecurityEvent::query()->count());
    }
}
