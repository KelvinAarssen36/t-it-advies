<?php

namespace Tests\Feature\Security;

use App\Enums\SecurityEventType;
use App\Enums\SecurityOutcome;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * De afspraken rond gevoelige acties:
 *
 * - ingelogd zijn is niet genoeg, er moet een verse authenticator-code komen;
 * - recovery codes gelden hier niet;
 * - zonder bevestigde 2FA gaat de actie helemaal niet door;
 * - alles wordt gelogd, zowel de geslaagde als de mislukte pogingen.
 */
class SensitiveActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        // Een testroute die alleen bedoeld is om de middleware te raken.
        Route::middleware(['web', 'auth', '2fa.confirm'])
            ->get('/__test/sensitive', fn () => response('ok'))
            ->name('test.sensitive');
    }

    private function userWithTwoFactor(): array
    {
        $secret = app(Google2FA::class)->generateSecretKey();

        $user = User::factory()->create();
        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(
                (string) json_encode(['abcdefghij-klmnopqrst'])
            ),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return [$user, $secret];
    }

    private function currentCode(string $secret): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    public function test_a_user_without_two_factor_cannot_reach_a_sensitive_action(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/__test/sensitive')
            ->assertRedirect(route('security.edit'));

        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventType::SensitiveActionDenied->value,
            'outcome' => SecurityOutcome::Failure->value,
            'user_id' => $user->id,
        ]);
    }

    public function test_a_user_with_two_factor_is_sent_to_the_confirmation_screen(): void
    {
        [$user] = $this->userWithTwoFactor();

        $this->actingAs($user)
            ->get('/__test/sensitive')
            ->assertRedirect(route('security.two-factor.confirm'));
    }

    public function test_a_valid_code_unlocks_the_sensitive_action(): void
    {
        [$user, $secret] = $this->userWithTwoFactor();

        $this->actingAs($user)->get('/__test/sensitive');

        $this->actingAs($user)
            ->post(route('security.two-factor.confirm.store'), [
                'code' => $this->currentCode($secret),
            ])
            ->assertRedirect('/__test/sensitive');

        $this->actingAs($user)
            ->get('/__test/sensitive')
            ->assertOk()
            ->assertSee('ok');

        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventType::SensitiveActionConfirmed->value,
            'outcome' => SecurityOutcome::Success->value,
            'user_id' => $user->id,
        ]);
    }

    public function test_a_recovery_code_is_refused_for_a_sensitive_action(): void
    {
        [$user] = $this->userWithTwoFactor();

        $this->actingAs($user)
            ->from(route('security.two-factor.confirm'))
            ->post(route('security.two-factor.confirm.store'), [
                'code' => 'abcdefghij-klmnopqrst',
            ])
            ->assertSessionHasErrors('code');

        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventType::SensitiveActionRecoveryCodeRefused->value,
            'outcome' => SecurityOutcome::Failure->value,
            'user_id' => $user->id,
        ]);

        // En de actie blijft dicht.
        $this->actingAs($user)
            ->get('/__test/sensitive')
            ->assertRedirect(route('security.two-factor.confirm'));
    }

    public function test_a_wrong_code_is_logged_as_a_failed_attempt(): void
    {
        [$user] = $this->userWithTwoFactor();

        $this->actingAs($user)
            ->from(route('security.two-factor.confirm'))
            ->post(route('security.two-factor.confirm.store'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventType::SensitiveActionFailed->value,
            'outcome' => SecurityOutcome::Failure->value,
            'user_id' => $user->id,
        ]);
    }

    public function test_the_submitted_code_is_never_stored(): void
    {
        [$user] = $this->userWithTwoFactor();

        $this->actingAs($user)
            ->from(route('security.two-factor.confirm'))
            ->post(route('security.two-factor.confirm.store'), ['code' => '123456']);

        foreach (SecurityEvent::all() as $event) {
            $this->assertStringNotContainsString('123456', (string) json_encode($event->context));
        }
    }

    public function test_sensitive_actions_are_rate_limited(): void
    {
        [$user] = $this->userWithTwoFactor();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->actingAs($user)
                ->from(route('security.two-factor.confirm'))
                ->post(route('security.two-factor.confirm.store'), ['code' => '000000']);
        }

        $this->actingAs($user)
            ->post(route('security.two-factor.confirm.store'), ['code' => '000000'])
            ->assertStatus(429);

        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventType::RateLimited->value,
        ]);
    }
}
