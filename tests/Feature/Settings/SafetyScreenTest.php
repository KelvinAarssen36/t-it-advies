<?php

namespace Tests\Feature\Settings;

use App\Enums\SecurityEventType;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Het scherm Veiligheid in de instellingen.
 *
 * Een scherm zonder knoppen: het legt uit hoe alles beschermd is en laat
 * een paar echte cijfers zien.
 *
 * **Waar deze tests over gaan is dat er niets verzonnen wordt.** Een scherm
 * dat zegt dat de spamcontrole aanstaat terwijl de sleutel leeg is, is
 * erger dan geen scherm -- dan denkt de eigenaar beschermd te zijn. Dus:
 * elke stand komt uit de instelling die hem ook echt bepaalt.
 *
 * Zie docs/security/overzicht-voor-de-eigenaar.md.
 */
class SafetyScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('safety.show'))->assertRedirect(route('login'));
    }

    public function test_the_screen_opens(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('safety.show'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/Veiligheid')
                ->has('cijfers')
                ->has('beschermingen')
                ->has('termijnen'));
    }

    /** Mislukte inlogpogingen worden geteld, gelukte niet. */
    public function test_failed_logins_are_counted(): void
    {
        SecurityEvent::query()->create([
            'event' => SecurityEventType::LoginFailed->value,
            'outcome' => 'failure',
            'ip_address' => '198.51.100.1',
        ]);

        SecurityEvent::query()->create([
            'event' => SecurityEventType::Login->value,
            'outcome' => 'success',
            'ip_address' => '198.51.100.1',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('safety.show'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('cijfers.mislukteLogins', 1));
    }

    /**
     * Wat er is tegengehouden telt als één getal.
     *
     * Spam, een te snelle bezoeker en een geblokkeerde inlogpoging staan
     * in het logboek als verschillende soorten, maar voor de eigenaar zijn
     * het allemaal "pogingen die niet door zijn gekomen".
     */
    public function test_blocked_attempts_are_counted_together(): void
    {
        foreach ([
            SecurityEventType::SpamBlocked,
            SecurityEventType::RateLimited,
            SecurityEventType::Lockout,
        ] as $soort) {
            SecurityEvent::query()->create([
                'event' => $soort->value,
                'outcome' => 'failure',
                'ip_address' => '198.51.100.2',
            ]);
        }

        $this->actingAs(User::factory()->create())
            ->get(route('safety.show'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('cijfers.geblokkeerd', 3));
    }

    /** Iets van langer dan een maand terug telt niet mee. */
    public function test_older_events_are_not_counted(): void
    {
        $gebeurtenis = SecurityEvent::query()->create([
            'event' => SecurityEventType::LoginFailed->value,
            'outcome' => 'failure',
            'ip_address' => '198.51.100.3',
        ]);

        $gebeurtenis->forceFill(['created_at' => now()->subDays(40)])->save();

        $this->actingAs(User::factory()->create())
            ->get(route('safety.show'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('cijfers.mislukteLogins', 0));
    }

    /**
     * De spamcontrole wordt niet aangekondigd als hij uitstaat.
     *
     * Dit is de kern van dit scherm: liever een kruisje met uitleg dan een
     * vinkje dat niet waar is.
     */
    public function test_the_spam_check_is_not_claimed_when_it_is_off(): void
    {
        config(['services.turnstile.site_key' => null]);

        $this->actingAs(User::factory()->create())
            ->get(route('safety.show'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('beschermingen.spamcontrole', false));

        config(['services.turnstile.site_key' => '0x4AAA-test']);

        $this->actingAs(User::factory()->create())
            ->get(route('safety.show'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('beschermingen.spamcontrole', true));
    }

    /** En hetzelfde voor het adres waar meldingen naartoe gaan. */
    public function test_alerting_shows_its_real_state(): void
    {
        config(['security.alerts.address' => null]);

        $this->actingAs(User::factory()->create())
            ->get(route('safety.show'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('beschermingen.alarmering', false));
    }

    /** De stand van tweestapsverificatie is die van de gebruiker zelf. */
    public function test_two_factor_shows_the_state_of_this_user(): void
    {
        $zonder = User::factory()->create(['two_factor_confirmed_at' => null]);

        $this->actingAs($zonder)
            ->get(route('safety.show'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('beschermingen.tweestaps', false)
                ->where('beschermingen.tweestapsSinds', null));

        $met = User::factory()->create(['two_factor_confirmed_at' => now()]);

        $this->actingAs($met)
            ->get(route('safety.show'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('beschermingen.tweestaps', true));
    }

    /** De bewaartermijnen komen uit de instellingen. */
    public function test_the_retention_periods_come_from_the_settings(): void
    {
        config([
            'security.logging.retention_days' => 11,
            'security.logging.activity_retention_days' => 22,
            'mail.log_retention_days' => 33,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('safety.show'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('termijnen.beveiliging', 11)
                ->where('termijnen.activiteit', 22)
                ->where('termijnen.mail', 33));
    }

    /**
     * Dit scherm verandert niets.
     *
     * Het heeft geen knoppen, en daarom ook geen enkele route die iets
     * opslaat. Komt daar ooit een POST of PUT bij, dan hoort deze test om
     * te vallen -- dan is het geen uitlegscherm meer.
     */
    public function test_the_screen_has_no_route_that_changes_anything(): void
    {
        $routes = collect(app('router')->getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'safety.'))
            ->flatMap(fn ($route) => $route->methods());

        $this->assertEqualsCanonicalizing(['GET', 'HEAD'], $routes->unique()->values()->all());
    }

    /**
     * Het scherm is uitleg en geen controlelijst.
     *
     * **Dit legt een toon vast en niet een functie, en dat is met opzet.**
     * Er stond eerst bij elk punt een vinkje of een kruisje met de echte
     * stand erachter. Technisch klopte dat, maar acht regels onder elkaar
     * waarvan er een paar oranje staan leest als een keuring -- terwijl dit
     * scherm er juist is om gerust te stellen. De eigenaar vroeg om "gewoon
     * simpel wat we allemaal aan veiligheid gebruiken, niet per se een
     * echte check".
     *
     * Deze test leest het component, want de toon zit in het sjabloon en
     * niet in een prop. Zet iemand de kruisjes terug, dan valt hij om en
     * hoort er eerst een gesprek te komen.
     */
    public function test_the_screen_is_not_a_checklist(): void
    {
        $scherm = (string) file_get_contents(
            resource_path('js/pages/settings/Veiligheid.vue'),
        );

        foreach (['X,', '<X ', 'data-uit'] as $spoor) {
            $this->assertStringNotContainsString(
                $spoor,
                $scherm,
                'Het scherm Veiligheid is weer een afvinklijst geworden.',
            );
        }
    }

    /**
     * De spamcontrole wordt niet verzwegen als hij uitstaat.
     *
     * Rustig van toon mag, onwaar niet. Staat de sleutel van Cloudflare
     * niet ingevuld, dan draait die laag niet, en dan hoort de tekst over
     * twee lagen te gaan en niet over drie. Hetzelfde principe als op de
     * privacyverklaring.
     */
    public function test_both_versions_of_the_spam_text_exist(): void
    {
        $scherm = (string) file_get_contents(
            resource_path('js/pages/settings/Veiligheid.vue'),
        );

        $this->assertStringContainsString('Drie lagen houden spam tegen', $scherm);
        $this->assertStringContainsString('Twee lagen houden spam tegen', $scherm);
    }
}
