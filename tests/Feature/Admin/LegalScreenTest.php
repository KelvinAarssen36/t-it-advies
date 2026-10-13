<?php

namespace Tests\Feature\Admin;

use App\Enums\SecurityEventType;
use App\Models\ContactSubmission;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Support\Juridisch\Gegevensoverzicht;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Het scherm Juridisch.
 *
 * **Twee van deze tests leggen een beslissing vast en niet een gedrag.**
 * `test_the_security_log_cannot_be_deleted_from_this_screen` en
 * `test_nothing_is_stored_about_the_request_itself` bewaken keuzes die
 * bewust zo zijn: een logboek waar regels uit te halen zijn kan geen
 * misbruik meer aantonen, en een register van verzoeken zou nieuwe
 * persoonsgegevens aanmaken om een verzoek over persoonsgegevens af te
 * handelen.
 *
 * Zie docs/security/verzoeken-van-bezoekers.md.
 */
class LegalScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function beheerder(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage portal');

        return $user;
    }

    /**
     * Een beheerder die zojuist een geldige authenticator-code heeft
     * ingevoerd.
     *
     * Nodig voor het weggooien van mislukte mailpogingen: daar verdwijnt
     * een bericht van iemand onherstelbaar, dus die route staat achter
     * `2fa.confirm`. Dezelfde opzet als UserManagementTest.
     */
    private function bevestigdeBeheerder(): User
    {
        $beheerder = $this->beheerder();

        $secret = app(Google2FA::class)->generateSecretKey();

        $beheerder->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(
                (string) json_encode(['abcdefghij-klmnopqrst'])
            ),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($beheerder)->post(route('security.two-factor.confirm.store'), [
            'code' => app(Google2FA::class)->getCurrentOtp($secret),
        ]);

        return $beheerder;
    }

    private function gebeurtenis(array $kenmerken = []): SecurityEvent
    {
        return SecurityEvent::query()->create([
            'event' => SecurityEventType::LoginFailed->value,
            'outcome' => 'failure',
            'email' => 'bezoeker@example.com',
            'ip_address' => '198.51.100.9',
            ...$kenmerken,
        ]);
    }

    /* --- Rechten ---------------------------------------------------------- */

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('admin.legal.index'))->assertRedirect(route('login'));
    }

    public function test_a_user_without_the_permission_is_refused(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.legal.index'))
            ->assertForbidden();
    }

    /* --- Zoeken ----------------------------------------------------------- */

    public function test_the_screen_opens_without_a_search(): void
    {
        $this->actingAs($this->beheerder())
            ->get(route('admin.legal.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Juridisch')
                ->where('zoekterm', null)
                ->has('resultaten', 0)
                ->has('plekken'));
    }

    public function test_someone_can_be_found_by_email(): void
    {
        $this->gebeurtenis();

        $this->actingAs($this->beheerder())
            ->get(route('admin.legal.index', ['zoek' => 'bezoeker@example.com']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('resultaten', 1)
                ->where('resultaten.0.email', 'bezoeker@example.com'));
    }

    public function test_someone_can_be_found_by_ip(): void
    {
        $this->gebeurtenis();

        $this->actingAs($this->beheerder())
            ->get(route('admin.legal.index', ['zoek' => '198.51.100.9']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('resultaten', 1)
                ->where('resultaten.0.ip', '198.51.100.9'));
    }

    /**
     * Een zoekopdracht van één of twee tekens levert niets op.
     *
     * Zonder die ondergrens komt het halve logboek terug bij het typen van
     * een "a", en dan is het scherm een exportknop voor alles wat we
     * hebben -- precies het tegenovergestelde van gericht zoeken.
     */
    public function test_a_search_that_is_too_short_returns_nothing(): void
    {
        $this->gebeurtenis();

        $this->actingAs($this->beheerder())
            ->get(route('admin.legal.index', ['zoek' => 'be']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('resultaten', 0));
    }

    /**
     * Een underscore in een zoekterm is een letter en geen jokerteken.
     *
     * In een LIKE betekent `_` "één willekeurig teken", en underscores
     * staan in heel veel e-mailadressen. Zonder ontsnappen vond
     * `jan_de_vries@example.com` dus ook het adres van iemand anders --
     * op het ene scherm waar dat het meest ongewenst is.
     */
    public function test_an_underscore_is_not_a_wildcard(): void
    {
        $this->gebeurtenis(['email' => 'jan_de_vries@example.com']);
        $this->gebeurtenis(['email' => 'janXdeYvries@example.com']);

        $this->actingAs($this->beheerder())
            ->get(route('admin.legal.index', ['zoek' => 'jan_de_vries@example.com']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('resultaten', 1)
                ->where('resultaten.0.email', 'jan_de_vries@example.com'));
    }

    /** Een procentteken geeft niet ineens het hele logboek terug. */
    public function test_a_percent_sign_does_not_return_everything(): void
    {
        $this->gebeurtenis();
        $this->gebeurtenis(['email' => 'iemand-anders@example.com']);

        $this->actingAs($this->beheerder())
            ->get(route('admin.legal.index', ['zoek' => '%@example.com']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('resultaten', 0));
    }

    /** En het ontsnappingsteken zelf is ook gewoon een teken. */
    public function test_the_escape_character_is_just_a_character(): void
    {
        $this->gebeurtenis(['email' => 'hoi!daar@example.com']);
        $this->gebeurtenis(['email' => 'hoidaar@example.com']);

        $this->actingAs($this->beheerder())
            ->get(route('admin.legal.index', ['zoek' => 'hoi!daar']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('resultaten', 1)
                ->where('resultaten.0.email', 'hoi!daar@example.com'));
    }

    public function test_a_search_without_a_hit_is_an_answer_too(): void
    {
        $this->gebeurtenis();

        $this->actingAs($this->beheerder())
            ->get(route('admin.legal.index', ['zoek' => 'iemand@anders.nl']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('zoekterm', 'iemand@anders.nl')
                ->has('resultaten', 0));
    }

    /* --- De contactaanvragen ---------------------------------------------- */

    /**
     * Het scherm vindt nu ook de berichten uit het contactformulier.
     *
     * **Dit is waarom dit scherm beter is geworden.** Een verzoek om
     * verwijdering ging eerst altijd over de mailbox, waar de applicatie
     * niets kan. Sinds de module Contact staan de aanvragen in het portaal,
     * en kan de eigenaar het hier vinden én weghalen.
     */
    public function test_a_contact_request_is_found(): void
    {
        ContactSubmission::factory()->create([
            'email' => 'bezoeker@example.com',
            'name' => 'Kees Jansen',
            'subject_text' => 'Vraag over een migratie',
        ]);

        $this->actingAs($this->beheerder())
            ->get(route('admin.legal.index', ['zoek' => 'bezoeker@example.com']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('aanvragen', 1)
                ->where('aanvragen.0.naam', 'Kees Jansen')
                ->where('aanvragen.0.onderwerp', 'Vraag over een migratie'));
    }

    /**
     * Het bericht zelf staat niet op dit scherm.
     *
     * Dit scherm is er om te kunnen zeggen dát er iets van iemand staat en
     * om het te verwijderen. Lezen doe je op het scherm Aanvragen; het
     * bericht van een bezoeker hoeft niet op twee plekken te staan.
     */
    public function test_the_message_itself_is_not_on_this_screen(): void
    {
        ContactSubmission::factory()->create([
            'email' => 'bezoeker@example.com',
            'message' => 'Dit bericht hoort hier niet te staan.',
        ]);

        $this->actingAs($this->beheerder())
            ->get(route('admin.legal.index', ['zoek' => 'bezoeker@example.com']))
            ->assertDontSee('Dit bericht hoort hier niet te staan.', false);
    }

    /** En ze zijn er ook als er niets in het beveiligingslogboek staat. */
    public function test_requests_are_found_without_a_security_event(): void
    {
        ContactSubmission::factory()->create([
            'email' => 'alleen-contact@example.com',
        ]);

        $this->actingAs($this->beheerder())
            ->get(route('admin.legal.index', ['zoek' => 'alleen-contact@example.com']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('resultaten', 0)
                ->has('aanvragen', 1));
    }

    /** De aanvragen staan als zoekbare én wisbare plek in het overzicht. */
    public function test_the_requests_are_a_searchable_and_erasable_place(): void
    {
        $plekken = collect(app(Gegevensoverzicht::class)->plekken());

        $aanvragen = $plekken->firstWhere('sleutel', 'aanvragen');

        $this->assertNotNull($aanvragen);
        $this->assertTrue($aanvragen['zoekbaar']);
        $this->assertTrue($aanvragen['wisbaar']);
    }

    /* --- De antwoordtekst ------------------------------------------------- */

    /**
     * Het antwoord staat er in allebei de talen klaar.
     *
     * **De taal van het antwoord hoort bij de bezoeker en niet bij het
     * portaal.** De eigenaar werkt in het Nederlands; komt de vraag in het
     * Engels binnen, dan hoort hij een Engels antwoord te kunnen sturen
     * zonder zijn hele beheeromgeving om te zetten voor één mail. Daarom
     * komen ze allebei mee en kiest het scherm er een.
     */
    public function test_the_answer_comes_in_both_languages(): void
    {
        $this->actingAs($this->beheerder())
            ->get(route('admin.legal.index', ['zoek' => 'iemand@anders.nl']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('antwoord.nl')
                ->has('antwoord.en'));
    }

    /**
     * Het Engelse antwoord is ook echt Engels, ook in een Nederlands portaal.
     *
     * Dit is de test die omvalt zodra iemand de tekst terugzet naar de
     * taal van het scherm. De eigenaar is hier ingelogd met het portaal op
     * Nederlands -- precies de situatie waarin het eerst fout ging.
     */
    public function test_the_english_answer_is_english_in_a_dutch_portal(): void
    {
        $antwoord = $this->actingAs($this->beheerder())
            ->get(route('admin.legal.index', ['zoek' => 'iemand@anders.nl']))
            ->viewData('page')['props']['antwoord'];

        $this->assertStringContainsString('Beste,', $antwoord['nl']);
        $this->assertStringContainsString(
            (string) __('Beste,', [], 'en'),
            $antwoord['en'],
        );
        $this->assertStringNotContainsString('Beste,', $antwoord['en']);
    }

    /**
     * Ook de logboekregels in het antwoord gaan mee in de taal.
     *
     * Die labels kwamen van `SecurityEvent::label()`, en die vertaalt met
     * de taal van de applicatie. Zonder de sleutel apart te houden stond
     * er "Mislukte login" midden in een Engelse brief.
     */
    public function test_the_log_lines_in_the_answer_follow_the_language(): void
    {
        $this->gebeurtenis();

        $antwoord = $this->actingAs($this->beheerder())
            ->get(route('admin.legal.index', ['zoek' => 'bezoeker@example.com']))
            ->viewData('page')['props']['antwoord'];

        $this->assertStringContainsString('Mislukte login', $antwoord['nl']);
        $this->assertStringContainsString(
            (string) __('Mislukte login', [], 'en'),
            $antwoord['en'],
        );
        $this->assertStringNotContainsString('Mislukte login', $antwoord['en']);
    }

    /**
     * Zonder zoekopdracht doet het antwoord geen bewering.
     *
     * "We hebben niets gevonden" terwijl er niet is gezocht, is een
     * uitspraak die niemand heeft gecontroleerd.
     */
    public function test_without_a_search_the_answer_claims_nothing(): void
    {
        $antwoord = $this->actingAs($this->beheerder())
            ->get(route('admin.legal.index'))
            ->viewData('page')['props']['antwoord'];

        foreach (['nl', 'en'] as $taal) {
            $this->assertStringNotContainsString(
                (string) __('We hebben je niet in onze gegevens kunnen vinden. Er staat niets van je bij ons.', [], $taal),
                $antwoord[$taal],
            );
        }
    }

    /**
     * Het logbestand van de webserver staat ook in het antwoord.
     *
     * De verklaring op de website noemt het, dus een antwoord dat zegt
     * "wij bewaren geen IP-adressen" zonder die uitzondering spreekt zijn
     * eigen privacyverklaring tegen.
     */
    public function test_the_answer_mentions_the_server_log(): void
    {
        $antwoord = $this->actingAs($this->beheerder())
            ->get(route('admin.legal.index', ['zoek' => 'iemand@anders.nl']))
            ->viewData('page')['props']['antwoord'];

        $this->assertStringContainsString('technisch logbestand', $antwoord['nl']);
        $this->assertStringContainsString('technical log file', $antwoord['en']);
    }

    /** De bewaartermijn in het antwoord komt uit de instelling. */
    public function test_the_answer_uses_the_configured_retention(): void
    {
        config(['security.logging.retention_days' => 321]);

        $this->gebeurtenis();

        $antwoord = $this->actingAs($this->beheerder())
            ->get(route('admin.legal.index', ['zoek' => 'bezoeker@example.com']))
            ->viewData('page')['props']['antwoord'];

        $this->assertStringContainsString('321', $antwoord['nl']);
        $this->assertStringContainsString('321', $antwoord['en']);
    }

    /* --- Wat er niet kan -------------------------------------------------- */

    /**
     * Er is geen route om een regel uit het beveiligingslogboek te halen.
     *
     * **Dit is een beslissing en geen ontbrekende functie.** Een logboek
     * waar regels uit te halen zijn kan geen misbruik meer aantonen, en
     * zo'n knop is precies wat iemand die binnenkomt zou gebruiken om zijn
     * sporen te wissen. De AVG laat verwijdering weigeren waar de
     * verwerking nodig is om misbruik tegen te gaan.
     *
     * Staat hier ooit wél zo'n route, dan hoort deze test om te vallen en
     * hoort er een gesprek te komen in plaats van een snelle fix.
     */
    public function test_the_security_log_cannot_be_deleted_from_this_screen(): void
    {
        $gebeurtenis = $this->gebeurtenis();

        $routes = collect(app('router')->getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter()
            ->filter(fn (string $naam) => str_contains($naam, 'security')
                && str_contains($naam, 'destroy'));

        $this->assertCount(0, $routes);
        $this->assertModelExists($gebeurtenis);
    }

    /**
     * Van het verzoek zelf wordt niets vastgelegd.
     *
     * Een register van verzoeken zou de naam en het adres van iemand die
     * om verwijdering vraagt gaan opslaan -- nieuwe persoonsgegevens
     * aanmaken om een verzoek over persoonsgegevens af te handelen. De
     * AVG vraagt dat niet.
     */
    public function test_nothing_is_stored_about_the_request_itself(): void
    {
        $beheerder = $this->beheerder();

        /*
         * De nulmeting gebeurt ná het aanmaken van de beheerder: dat
         * aanmaken schrijft zelf een regel in het activiteitenlogboek, en
         * die hoort niet bij wat we hier meten.
         */
        $eventsVoor = SecurityEvent::query()->count();
        $activiteitVoor = DB::table('activity_entries')->count();

        $this->actingAs($beheerder)
            ->get(route('admin.legal.index', ['zoek' => 'bezoeker@example.com']));

        $this->assertSame($eventsVoor, SecurityEvent::query()->count());
        $this->assertSame($activiteitVoor, DB::table('activity_entries')->count());
    }

    /* --- Mislukte mailpogingen -------------------------------------------- */

    private function mislukteMail(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{"naam":"Kees","bericht":"gevoelige inhoud"}',
            'exception' => 'Boem',
            'failed_at' => now(),
        ]);
    }

    public function test_the_screen_counts_failed_mail(): void
    {
        $this->mislukteMail();

        $this->actingAs($this->beheerder())
            ->get(route('admin.legal.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('mislukteMail', 1));
    }

    public function test_failed_mail_can_be_thrown_away(): void
    {
        $this->mislukteMail();

        $this->actingAs($this->bevestigdeBeheerder())
            ->delete(route('admin.legal.failed-mail.destroy'))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    /** En dat komt in het beveiligingslogboek, want er verdwijnen gegevens. */
    public function test_throwing_it_away_is_logged(): void
    {
        $this->mislukteMail();

        $this->actingAs($this->bevestigdeBeheerder())
            ->delete(route('admin.legal.failed-mail.destroy'));

        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventType::PrivacyDataCleared->value,
        ]);
    }

    /** Niets weggooien levert geen melding op dat er iets is weggegooid. */
    public function test_nothing_to_throw_away_says_so(): void
    {
        $this->actingAs($this->bevestigdeBeheerder())
            ->delete(route('admin.legal.failed-mail.destroy'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('security_events', [
            'event' => SecurityEventType::PrivacyDataCleared->value,
        ]);
    }

    /**
     * Zonder verse authenticator-code gaat er niets weg.
     *
     * Het recht alleen is hier niet genoeg: een bericht van iemand dat
     * onherstelbaar verdwijnt is precies de handeling waar `2fa.confirm`
     * voor bestaat.
     */
    public function test_without_a_fresh_code_nothing_is_thrown_away(): void
    {
        $this->mislukteMail();

        $this->actingAs($this->beheerder())
            ->delete(route('admin.legal.failed-mail.destroy'));

        $this->assertSame(1, DB::table('failed_jobs')->count());
    }

    public function test_a_guest_cannot_throw_away_failed_mail(): void
    {
        $this->mislukteMail();

        $this->delete(route('admin.legal.failed-mail.destroy'))
            ->assertRedirect(route('login'));

        $this->assertSame(1, DB::table('failed_jobs')->count());
    }

    /* --- De bewaartermijnen ----------------------------------------------- */

    /**
     * De termijnen komen uit de instellingen en niet uit een tekst.
     *
     * Dat is de reden dat dit scherm bestaat in plaats van een briefje:
     * een met de hand getypte lijst klopt tot de dag dat iemand een
     * termijn wijzigt, en dan geeft de eigenaar een antwoord dat niet waar
     * is.
     */
    public function test_the_retention_periods_come_from_the_settings(): void
    {
        config([
            'security.logging.retention_days' => 111,
            'mail.log_retention_days' => 222,
            'session.lifetime' => 33,
        ]);

        $this->actingAs($this->beheerder())
            ->get(route('admin.legal.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('termijnen.beveiliging', 111)
                ->where('termijnen.mail', 222)
                ->where('termijnen.sessie', 33)
                ->where('termijnen.mislukteMail', Gegevensoverzicht::MISLUKTE_MAIL_DAGEN));
    }

    /** Elke plek in het overzicht heeft een opschrift in de frontend. */
    public function test_every_place_has_a_label_in_the_screen(): void
    {
        $scherm = (string) file_get_contents(
            resource_path('js/pages/admin/Juridisch.vue'),
        );

        foreach (app(Gegevensoverzicht::class)->plekken() as $plek) {
            /*
             * De sleutel komt in het sjabloon voor als sleutel van de
             * opschriftenlijst -- met aanhalingstekens als er een streepje
             * in zit, en zonder als hij uit één woord bestaat. Daarom
             * alleen op de sleutel zelf zoeken.
             */
            $this->assertStringContainsString(
                $plek['sleutel'],
                $scherm,
                "De plek '{$plek['sleutel']}' heeft geen opschrift op het scherm.",
            );
        }
    }
}
