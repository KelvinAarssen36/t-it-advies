<?php

namespace Tests\Feature;

use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectionHeadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * De privacyverklaring.
 *
 * **Deze tests gaan over een juridische verplichting en niet over een
 * pagina.** Zonder deze verklaring is de wettelijke kant van de site niet
 * rond: er worden IP-adressen vastgelegd voor de beveiliging en er worden
 * bezoekcijfers geteld, en daar hoort transparantie bij.
 *
 * Daarom controleren ze ook dat hij **vindbaar** is. Een verklaring die
 * bestaat maar nergens staat, is geen verklaring.
 *
 * Zie docs/architecture/bezoekcijfers.md.
 */
class PrivacyPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
        $this->seed(SectionHeadingSeeder::class);
    }

    public function test_the_page_is_public(): void
    {
        $this->get(route('privacy'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('public/Privacy'));
    }

    /**
     * Het publieke adres staat erop, en het inlogadres niet.
     *
     * Dezelfde regel als op de rest van de site; zie EmailAdressenTest en
     * config/site.php.
     */
    public function test_the_public_address_is_on_it(): void
    {
        $this->get(route('privacy'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('email', config('site.email')));
    }

    /**
     * De bewaartermijn van het mailoverzicht staat erop.
     *
     * Die hoort erbij, want een verstuurd bericht van een bezoeker laat een
     * spoor achter in dat logboek. Hij kwam er pas bij nadat de verklaring
     * woord voor woord tegen de code was nagelopen.
     */
    public function test_the_mail_log_retention_is_on_it(): void
    {
        config(['mail.log_retention_days' => 77]);

        $this->get(route('privacy'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('bewaartermijnMail', 77));
    }

    /**
     * Cloudflare wordt alleen genoemd als de spamcontrole echt aanstaat.
     *
     * **Een derde partij noemen die er niet is, is net zo fout als er een
     * verzwijgen.** Staat er geen sleutel, dan gaat er geen enkel verzoek
     * naar Cloudflare en hoort die alinea er niet te staan.
     */
    public function test_cloudflare_is_only_named_when_it_is_actually_used(): void
    {
        config(['services.turnstile.site_key' => null]);

        $this->get(route('privacy'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('spamcontrole', false));

        config(['services.turnstile.site_key' => '0x4AAA-test']);

        $this->get(route('privacy'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('spamcontrole', true));
    }

    public function test_the_login_address_is_not_on_it(): void
    {
        $antwoord = $this->get(route('privacy'));

        $inlogadres = (string) config('security.portal_account.email');

        if ($inlogadres !== '') {
            $antwoord->assertDontSee($inlogadres, false);
        }
    }

    /**
     * De bewaartermijn komt uit de instelling die hem bepaalt.
     *
     * Een getal dat met de hand in de tekst staat, klopt tot de dag dat
     * iemand die instelling wijzigt -- en dan staat er een onwaarheid in
     * een juridische tekst.
     */
    public function test_the_retention_period_comes_from_the_setting(): void
    {
        config(['security.logging.retention_days' => 123]);

        $this->get(route('privacy'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('bewaartermijnBeveiliging', 123));
    }

    /**
     * Het logbestand van de webserver staat erop.
     *
     * **Dit schrijven wij niet zelf**, en juist daarom ligt het hier vast.
     * Elke webserver legt van elk verzoek het IP-adres vast; dat gebeurt
     * een laag onder onze code, bij de partij waar de server staat. Het is
     * daardoor het makkelijkste gegeven om te vergeten, want er is geen
     * regel code die eraan herinnert -- en het is wel het enige gegeven
     * van een gewone bezoeker dat de verklaring eerst niet beschreef.
     *
     * Net als `test_the_footer_links_to_it` leest deze test het component
     * en niet de HTML: de tekst staat in het sjabloon en wordt pas in de
     * browser opgebouwd.
     */
    public function test_the_server_log_is_disclosed(): void
    {
        $verklaring = (string) file_get_contents(
            resource_path('js/pages/public/Privacy.vue'),
        );

        $this->assertStringContainsString(
            'technisch logbestand',
            $verklaring,
            'De privacyverklaring vertelt niet meer dat de webserver een logbestand met IP-adressen bijhoudt.',
        );
    }

    /**
     * Hij staat in de voet van de site, dus hij is te vinden.
     *
     * **Deze test leest het component en niet de HTML van de pagina**, en
     * dat is geen luiheid: de landingspagina wordt door Vue in de browser
     * opgebouwd, dus in het antwoord van de server staat de voet nog
     * nergens. Hetzelfde patroon als TranslationsTest, die ook de bronmap
     * leest.
     *
     * Wat hier wordt vastgelegd is dat niemand die link per ongeluk uit de
     * voet haalt. Een verklaring die bestaat maar nergens staat, is geen
     * verklaring.
     */
    public function test_the_footer_links_to_it(): void
    {
        $voet = (string) file_get_contents(
            resource_path('js/components/site/SiteFooter.vue'),
        );

        $this->assertStringContainsString('privacy()', $voet);
        $this->assertStringContainsString("from '@/routes'", $voet);
    }

    /** En een bezoek aan de verklaring telt niet mee als bezoek. */
    public function test_the_privacy_page_is_not_counted_as_a_visit(): void
    {
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 Chrome/131.0'])
            ->get(route('privacy'))
            ->assertOk();

        $this->assertDatabaseCount('site_day_totals', 0);
    }
}
