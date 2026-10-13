<?php

namespace Tests\Feature\Website;

use App\Models\FooterSetting;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Wat er van de voet op de site terechtkomt.
 *
 * De belangrijkste test staat onderaan:
 * `test_every_public_controller_passes_the_footer`. De voetgegevens gaan per
 * controller mee en niet als gedeelde prop -- dat is dezelfde afweging als
 * bij `navigation`, zodat een portaalverzoek niet voor een query betaalt
 * die alleen de publieke site nodig heeft. De prijs daarvan is zeven
 * plekken om het te vergeten, en die test is wat daartegenover staat.
 *
 * Zie docs/architecture/modules/voettekst.md.
 */
class FooterPublishingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
    }

    /* --- Wat de bezoeker krijgt ----------------------------------------- */

    public function test_the_landing_page_gets_what_the_owner_filled_in(): void
    {
        FooterSetting::query()->create([
            'tagline_nl' => 'IT-advies en realisatie.',
            'phone' => '06 12 34 56 78',
            'region_nl' => 'Noord-Brabant',
            'kvk' => '12 34 56 78',
            'vat' => 'NL001234567B01',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('voet.tagline', 'IT-advies en realisatie.')
                ->where('voet.telefoon.tekst', '06 12 34 56 78')
                ->where('voet.plaats', 'Noord-Brabant')
                ->where('voet.kvk', '12 34 56 78')
                ->where('voet.btw', 'NL001234567B01'));
    }

    /**
     * Wat leeg is, komt er niet in.
     *
     * De browser hoeft dan nergens te controleren óf een waarde bestaat:
     * staat hij er, dan is er iets te tonen. Zou een leeg veld als lege
     * string meegaan, dan krijg je een kopje boven niets.
     */
    public function test_empty_fields_are_left_out(): void
    {
        FooterSetting::query()->create(['tagline_nl' => 'Alleen een zin.']);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('voet.tagline', 'Alleen een zin.')
                ->where('voet.telefoon', null)
                ->where('voet.plaats', null)
                ->where('voet.kvk', null)
                ->where('voet.btw', null));
    }

    /** Zonder rij hoort de site gewoon te werken, met een lege voet. */
    public function test_the_site_works_without_a_row(): void
    {
        $this->assertDatabaseCount('footer_settings', 0);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('voet.tagline', null)
                ->where('voet.kvk', null));
    }

    /* --- Het telefoonnummer --------------------------------------------- */

    /**
     * De tekst houdt zijn spaties, de link niet.
     *
     * Hoe het nummer eruitziet is aan de eigenaar; wat een telefoon kan
     * bellen niet.
     */
    public function test_the_phone_link_is_stripped_but_the_text_is_not(): void
    {
        FooterSetting::query()->create(['phone' => '06 12 34 56 78']);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('voet.telefoon.tekst', '06 12 34 56 78')
                ->where('voet.telefoon.link', '0612345678'));
    }

    public function test_a_plus_sign_survives_the_link(): void
    {
        FooterSetting::query()->create(['phone' => '+31 6 12345678']);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('voet.telefoon.link', '+31612345678'));
    }

    /* --- De twee talen --------------------------------------------------- */

    /**
     * De plaats valt terug op het Nederlands, de zin eronder niet.
     *
     * Een plaatsnaam zegt een Engelse bezoeker net zoveel als een
     * Nederlandse; een Nederlandse zin onder een Engels logo leest als een
     * fout.
     */
    public function test_the_region_falls_back_but_the_tagline_does_not(): void
    {
        FooterSetting::query()->create([
            'tagline_nl' => 'IT-advies en realisatie.',
            'tagline_en' => null,
            'region_nl' => 'Noord-Brabant',
            'region_en' => null,
        ]);

        $this->withSession(['locale' => 'en'])
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('voet.plaats', 'Noord-Brabant')
                ->where('voet.tagline', null));
    }

    public function test_the_english_text_is_used_when_it_is_there(): void
    {
        FooterSetting::query()->create([
            'tagline_nl' => 'IT-advies en realisatie.',
            'tagline_en' => 'IT consultancy and delivery.',
            'region_nl' => 'Noord-Brabant en omstreken',
            'region_en' => 'Noord-Brabant and surroundings',
        ]);

        $this->withSession(['locale' => 'en'])
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('voet.tagline', 'IT consultancy and delivery.')
                ->where('voet.plaats', 'Noord-Brabant and surroundings'));
    }

    /* --- Alle zeven pagina's -------------------------------------------- */

    /**
     * Elke controller die een publieke pagina tekent, geeft de voet mee.
     *
     * **Dit is de test die de zeven losse regels bewaakt.** De voetgegevens
     * gaan per controller mee, en dat is zeven kansen om het te vergeten --
     * met als uitkomst een halve voet op één pagina, die niemand opmerkt
     * omdat de andere zes het wel doen.
     *
     * **Hij leest de bestanden als tekst en roept de pagina's niet aan**,
     * en dat is een bewuste keuze. Vier van de zeven pagina's bestaan
     * alleen als de eigenaar ze heeft aangezet en geven anders een 404;
     * die alle vier opbouwen maakt van deze test een berg opzetwerk waarin
     * het eigenlijke punt verdwijnt. Dezelfde vorm als `AppSidebarTest` en
     * `PrivacyPageTest::test_the_footer_links_to_it`.
     *
     * Komt er een achtste publieke pagina bij, dan valt deze test om zodra
     * die zijn eerste regel tekent.
     */
    public function test_every_public_controller_passes_the_footer(): void
    {
        $gevonden = [];
        $ontbreekt = [];

        foreach (File::allFiles(app_path('Http/Controllers')) as $bestand) {
            if ($bestand->getExtension() !== 'php') {
                continue;
            }

            $inhoud = (string) file_get_contents($bestand->getPathname());

            // Tekent dit bestand een publieke pagina?
            $publiek = str_contains($inhoud, "Inertia::render('public/")
                || str_contains($inhoud, "Inertia::render('Welcome'");

            if (! $publiek) {
                continue;
            }

            $gevonden[] = $bestand->getFilename();

            if (! str_contains($inhoud, "'voet' =>")) {
                $ontbreekt[] = $bestand->getFilename();
            }
        }

        $this->assertNotEmpty(
            $gevonden,
            'Er is geen enkele publieke controller gevonden; de zoekterm klopt niet meer.',
        );

        $this->assertSame(
            [],
            $ontbreekt,
            'Deze controllers tekenen een publieke pagina maar geven de voet niet mee: '
                .implode(', ', $ontbreekt).'. Zet er "voet" bij, naast "navigation".',
        );
    }

    /**
     * En op de twee pagina's die altijd bestaan ook echt.
     *
     * De test hierboven leest bestanden; deze kijkt of het ook aankomt.
     * De voorpagina en de privacyverklaring zijn er altijd, dus die twee
     * kosten geen opzetwerk.
     */
    public function test_the_footer_arrives_on_the_pages_that_always_exist(): void
    {
        FooterSetting::query()->create(['kvk' => '12 34 56 78']);

        foreach (['/', '/privacy'] as $pad) {
            $this->get($pad)
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->where('voet.kvk', '12 34 56 78'));
        }
    }

    /**
     * Het publieke adres gaat met elke pagina mee.
     *
     * Het stond eerst alleen op de drie pagina's die het tonen. Sinds het
     * ook in de voet staat is dat elke pagina, en dan is per-pagina
     * doorgeven drie plekken om het te vergeten.
     */
    public function test_the_public_address_is_shared_with_every_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('email', (string) config('site.email')));
    }
}
