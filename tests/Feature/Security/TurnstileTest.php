<?php

namespace Tests\Feature\Security;

use App\Enums\SecurityEventType;
use App\Rules\TurnstileRule;
use App\Support\Security\Turnstile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Turnstile moet dichtklappen bij twijfel. Deze tests leggen vast wanneer er
 * wel en niet wordt doorgelaten.
 */
class TurnstileTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_skipped_when_not_configured_in_testing(): void
    {
        config(['services.turnstile.secret' => null]);

        $result = app(Turnstile::class)->verify('willekeurig-token');

        $this->assertTrue($result->allowed);
        $this->assertFalse($result->checked, 'Er is niets gecontroleerd, dat moet zichtbaar blijven.');
    }

    public function test_it_refuses_everything_when_not_configured_outside_local(): void
    {
        config(['services.turnstile.secret' => null]);
        app()->detectEnvironment(fn () => 'production');

        $result = app(Turnstile::class)->verify('willekeurig-token');

        $this->assertFalse($result->allowed);
        $this->assertSame(['configuration-missing'], $result->errorCodes);
    }

    public function test_it_allows_a_token_that_cloudflare_accepts(): void
    {
        config(['services.turnstile.secret' => 'geheim']);

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true]),
        ]);

        $result = app(Turnstile::class)->verify('goed-token');

        $this->assertTrue($result->allowed);
        $this->assertTrue($result->checked);
    }

    public function test_it_refuses_a_token_that_cloudflare_rejects(): void
    {
        config(['services.turnstile.secret' => 'geheim']);

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response([
                'success' => false,
                'error-codes' => ['invalid-input-response'],
            ]),
        ]);

        $result = app(Turnstile::class)->verify('fout-token');

        $this->assertFalse($result->allowed);
        $this->assertSame(['invalid-input-response'], $result->errorCodes);
    }

    public function test_it_refuses_when_cloudflare_is_unreachable(): void
    {
        config(['services.turnstile.secret' => 'geheim']);

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response('', 500),
        ]);

        $result = app(Turnstile::class)->verify('token');

        $this->assertFalse($result->allowed, 'Bij een storing mag er niets doorheen glippen.');
    }

    public function test_a_failed_check_is_logged_without_the_token(): void
    {
        config(['services.turnstile.secret' => 'geheim']);

        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => false]),
        ]);

        $validator = validator(
            ['cf-turnstile-response' => 'geheim-token-waarde'],
            ['cf-turnstile-response' => [new TurnstileRule]],
        );

        $this->assertTrue($validator->fails());

        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventType::TurnstileFailed->value,
        ]);

        $this->assertDatabaseMissing('security_events', [
            'context->token' => 'geheim-token-waarde',
        ]);
    }
}
