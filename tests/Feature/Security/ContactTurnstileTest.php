<?php

namespace Tests\Feature\Security;

use App\Models\ContactSubmission;
use Database\Seeders\ContactSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Honeypot\EncryptedTime;
use Tests\TestCase;

/**
 * De botcheck op het contactformulier, met Turnstile echt aan.
 *
 * **Deze tests bestonden niet, en dáárom zat er maandenlang een gat.** De
 * regel stond op `nullable`, en Laravel slaat een niet-impliciete regel over
 * zodra het veld afwezig of leeg is -- dus wie het token simpelweg niet
 * meestuurde werd niet gecontroleerd. Een *onjuist* token werd wél netjes
 * geweigerd, en dat is precies wat je zou testen als je er één test over
 * schreef. Vandaar dat `test_an_invalid_token_is_refused` hier ook staat:
 * zonder die tegenhanger bewijst de rest niets.
 *
 * Alle tests hieronder zetten een secret, want zonder secret slaat
 * `Turnstile` de controle in local en testing over -- en dan test je de
 * ontwikkelstand in plaats van de productiestand. Dat is de tweede reden dat
 * dit niemand opviel.
 *
 * Zie docs/security/spam-en-botbescherming.md en
 * app/Rules/TurnstileRule.php.
 */
class ContactTurnstileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ContactSeeder::class);

        // Zoals in productie: Turnstile is ingesteld, dus hij controleert.
        config()->set('services.turnstile.secret', 'secret-voor-deze-test');
        config()->set('services.turnstile.site_key', 'sitekey-voor-deze-test');

        Mail::fake();
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Kees Jansen',
            'email' => 'kees@example.com',
            'subject_text' => 'Vraag over een migratie',
            'message' => 'Graag advies over onze overstap naar een nieuwe omgeving.',
            config('honeypot.name_field_name') => '',
            config('honeypot.valid_from_field_name') => EncryptedTime::create(now()->subMinute()),
        ], $overrides);
    }

    /** Cloudflare keurt het token goed. */
    private function cloudflareZegtJa(): void
    {
        Http::fake(['*' => Http::response(['success' => true])]);
    }

    /** Cloudflare keurt het token af. */
    private function cloudflareZegtNee(string $code = 'invalid-input-response'): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'error-codes' => [$code]])]);
    }

    /* --- Het gat ---------------------------------------------------------- */

    /**
     * Zonder tokenveld komt er niets door.
     *
     * **Dit is de test die er had moeten zijn.** Hij faalde op de oude code
     * met een 302 zonder foutmelding en een rij in de database.
     */
    public function test_a_submission_without_a_token_is_refused(): void
    {
        $this->cloudflareZegtNee('missing-input-response');

        $this->post(route('contact.store'), $this->payload())
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->assertSame(0, ContactSubmission::query()->count());
        Mail::assertNothingQueued();
    }

    /**
     * Een leeg tokenveld ook niet.
     *
     * Een eigen test en geen variant van de vorige: Laravel behandelt "veld
     * ontbreekt" en "veld is een lege string" via twee verschillende
     * controles, en beide leidden tot overslaan.
     */
    public function test_a_submission_with_an_empty_token_is_refused(): void
    {
        $this->cloudflareZegtNee('missing-input-response');

        $this->post(route('contact.store'), $this->payload(['cf-turnstile-response' => '']))
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->assertSame(0, ContactSubmission::query()->count());
        Mail::assertNothingQueued();
    }

    /**
     * De melding bij een ontbrekend token is er een waar een bezoeker iets
     * mee kan.
     *
     * Hij heeft dat veld nooit gezien -- Cloudflare vult het zelf in -- dus
     * "het veld verificatie is verplicht" zou hem niets zeggen.
     */
    public function test_the_message_about_a_missing_token_tells_the_visitor_what_to_do(): void
    {
        $this->cloudflareZegtNee('missing-input-response');

        $this->post(route('contact.store'), $this->payload())
            ->assertSessionHasErrors([
                'cf-turnstile-response' => __('De verificatie kon niet worden geladen. Ververs de pagina en probeer het opnieuw.'),
            ]);
    }

    /* --- De tegenhangers -------------------------------------------------- */

    /**
     * Een onjuist token wordt geweigerd.
     *
     * Dit werkte al, en juist daarom staat hij hier: zonder deze test zou
     * een regel die álles weigert ook groen zijn.
     */
    public function test_an_invalid_token_is_refused(): void
    {
        $this->cloudflareZegtNee();

        $this->post(route('contact.store'), $this->payload(['cf-turnstile-response' => 'onzin']))
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->assertSame(0, ContactSubmission::query()->count());
    }

    /** En een goedgekeurd token komt er gewoon door. */
    public function test_a_valid_token_passes(): void
    {
        $this->cloudflareZegtJa();

        $this->post(route('contact.store'), $this->payload(['cf-turnstile-response' => 'goed-token']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, ContactSubmission::query()->count());
    }

    /* --- En de ontwikkelstand -------------------------------------------- */

    /**
     * Zonder secret werkt het formulier lokaal gewoon.
     *
     * Dat is de uitzondering die `TurnstileRule::veld()` zelf maakt, en hij
     * hoort vastgelegd: zou `required` ook zonder Turnstile gelden, dan is
     * het formulier op elke ontwikkelmachine stuk -- want dan rendert de
     * widget niets en is er geen veld.
     */
    public function test_without_a_secret_the_form_still_works_locally(): void
    {
        config()->set('services.turnstile.secret', null);

        $this->post(route('contact.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, ContactSubmission::query()->count());
    }
}
