<?php

namespace Tests\Feature\Security;

use App\Enums\SecurityEventType;
use App\Enums\SecurityOutcome;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Support\Security\SecurityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * De belangrijkste test van het hele project: bewijzen dat er geen geheimen
 * in het logboek belanden. Als deze test faalt, lekt de applicatie.
 */
class SecurityLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_a_successful_event(): void
    {
        $user = User::factory()->create();

        app(SecurityLogger::class)->success(SecurityEventType::Login, $user, ['guard' => 'web']);

        $event = SecurityEvent::sole();

        $this->assertSame(SecurityEventType::Login->value, $event->event);
        $this->assertSame(SecurityOutcome::Success, $event->outcome);
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame($user->email, $event->email);
        $this->assertSame(['guard' => 'web'], $event->context);
    }

    public function test_it_records_a_failed_event_without_a_user(): void
    {
        app(SecurityLogger::class)->failure(
            SecurityEventType::LoginFailed,
            email: 'onbekend@example.com',
        );

        $event = SecurityEvent::sole();

        $this->assertSame(SecurityOutcome::Failure, $event->outcome);
        $this->assertNull($event->user_id);
        $this->assertSame('onbekend@example.com', $event->email);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function sensitiveKeys(): array
    {
        return [
            'wachtwoord' => ['password'],
            'wachtwoordbevestiging' => ['password_confirmation'],
            'totp-code' => ['code'],
            'recovery code' => ['recovery_code'],
            '2fa secret' => ['two_factor_secret'],
            'recovery codes' => ['two_factor_recovery_codes'],
            'token' => ['token'],
            'turnstile-token' => ['cf-turnstile-response'],
        ];
    }

    #[DataProvider('sensitiveKeys')]
    public function test_it_never_stores_sensitive_values(string $key): void
    {
        app(SecurityLogger::class)->failure(SecurityEventType::LoginFailed, context: [
            $key => 'dit-mag-nooit-worden-opgeslagen',
        ]);

        $event = SecurityEvent::sole();

        $this->assertSame('[redacted]', $event->context[$key]);
        $this->assertStringNotContainsString(
            'dit-mag-nooit-worden-opgeslagen',
            (string) json_encode($event->context),
        );
    }

    public function test_it_redacts_regardless_of_how_the_key_is_written(): void
    {
        app(SecurityLogger::class)->failure(SecurityEventType::LoginFailed, context: [
            'Recovery-Code' => 'geheim-a',
            'recoveryCode' => 'geheim-b',
            'PASSWORD' => 'geheim-c',
        ]);

        $json = (string) json_encode(SecurityEvent::sole()->context);

        $this->assertStringNotContainsString('geheim-a', $json);
        $this->assertStringNotContainsString('geheim-b', $json);
        $this->assertStringNotContainsString('geheim-c', $json);
    }

    public function test_it_redacts_nested_values(): void
    {
        app(SecurityLogger::class)->failure(SecurityEventType::LoginFailed, context: [
            'request' => [
                'credentials' => [
                    'email' => 'iemand@example.com',
                    'password' => 'geheim',
                ],
            ],
        ]);

        $event = SecurityEvent::sole();

        $this->assertSame('[redacted]', $event->context['request']['credentials']['password']);
        $this->assertSame('iemand@example.com', $event->context['request']['credentials']['email']);
    }
}
