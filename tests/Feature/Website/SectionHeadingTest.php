<?php

namespace Tests\Feature\Website;

use App\Enums\PageSectionKey;
use App\Models\SectionHeading;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectionHeadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De gedeelde tabel met kopteksten.
 *
 * Hier stonden drie bijna identieke tabellen -- `hero_headings`,
 * `experience_headings` en `service_headings` -- en elke module met een
 * beheerbare kop maakte er een bij. Nu is het één tabel met een regel
 * per onderdeel.
 *
 * Wat de schermen zelf doen staat in HeroHeadingTest, ServiceHeadingTest,
 * ExperienceHeadingTest en CertificateCrudTest. Dit bestand bewaakt de
 * regels die ze delen, want die zijn met de samenvoeging pas ontstaan:
 * dat een onderdeel precies één rij heeft, dat de rijen elkaar niet
 * raken, en dat opnieuw zaaien de tekst van de eigenaar laat staan.
 *
 * Zie docs/architecture/kopteksten.md.
 */
class SectionHeadingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
    }

    /** De onderdelen die een beheerbare kop hebben. */
    private const MET_KOP = [
        PageSectionKey::Hero,
        PageSectionKey::Diensten,
        PageSectionKey::Projecten,
        PageSectionKey::Ervaring,
        PageSectionKey::Certificaten,
        PageSectionKey::Statistieken,

        /*
         * Het contactformulier hoort er sinds de module Contact bij. Zijn
         * kop stond daarvóór hardgecodeerd in ContactSection.vue, als
         * enige onderdeel met een kop die de klant niet kon wijzigen.
         */
        PageSectionKey::Contact,
    ];

    public function test_seeding_gives_every_section_exactly_one_row(): void
    {
        $this->seed(SectionHeadingSeeder::class);

        foreach (self::MET_KOP as $sectie) {
            $this->assertSame(
                1,
                SectionHeading::query()->where('section', $sectie)->count(),
                "Het onderdeel {$sectie->value} hoort precies één kop te hebben.",
            );
        }
    }

    public function test_seeding_twice_changes_nothing(): void
    {
        $this->seed(SectionHeadingSeeder::class);
        $this->seed(SectionHeadingSeeder::class);

        $this->assertSame(count(self::MET_KOP), SectionHeading::query()->count());
    }

    /**
     * Een tweede keer zaaien mag de tekst van de eigenaar niet terugzetten.
     *
     * Zou de seeder dat wel doen, dan gooit elke deploy zijn woorden weg
     * -- en dat ziet hij op zijn eigen voorpagina.
     */
    public function test_seeding_again_keeps_what_the_owner_wrote(): void
    {
        $this->seed(SectionHeadingSeeder::class);

        SectionHeading::query()
            ->where('section', PageSectionKey::Diensten)
            ->update(['title_nl' => 'Mijn eigen kop']);

        $this->seed(SectionHeadingSeeder::class);

        $this->assertSame(
            'Mijn eigen kop',
            SectionHeading::query()
                ->where('section', PageSectionKey::Diensten)
                ->value('title_nl'),
        );
    }

    /**
     * De rijen raken elkaar niet.
     *
     * Dit is precies wat er met één gedeelde tabel mis kán gaan: een
     * opslag zonder `where` op het onderdeel schrijft over alle koppen
     * heen. In de oude opzet was dat onmogelijk, want elke kop had zijn
     * eigen tabel.
     */
    public function test_changing_one_section_leaves_the_others_alone(): void
    {
        $this->seed(SectionHeadingSeeder::class);

        $vooraf = SectionHeading::query()
            ->where('section', PageSectionKey::Hero)
            ->value('title_nl');

        SectionHeading::query()
            ->where('section', PageSectionKey::Certificaten)
            ->update(['title_nl' => 'Iets heel anders']);

        $this->assertSame(
            $vooraf,
            SectionHeading::query()
                ->where('section', PageSectionKey::Hero)
                ->value('title_nl'),
        );
    }

    /**
     * Zonder rij komt er een verse met de standaardtekst.
     *
     * Een vergeten seeder mag geen blok zonder kop opleveren, en het
     * ophalen ervan mag niets wegschrijven -- dit gebeurt ook bij het
     * tonen van de publieke site, en een GET hoort geen rij aan te maken.
     */
    public function test_a_missing_row_falls_back_to_the_default_text(): void
    {
        $kop = SectionHeading::voor(PageSectionKey::Certificaten);

        $this->assertFalse($kop->exists);
        $this->assertSame('Zwart op wit', $kop->title_nl);
        $this->assertSame(0, SectionHeading::query()->count());
    }

    /**
     * De tijdlijn heeft geen opschrift, en de rest wel.
     *
     * De kolom is nullable omdat hij het slapste geval moet aankunnen;
     * wat verplicht is verschilt per onderdeel en staat in de
     * controller. Zie de trait BewaartKoptekst.
     */
    public function test_only_the_timeline_starts_without_an_eyebrow(): void
    {
        $this->seed(SectionHeadingSeeder::class);

        $this->assertNull(
            SectionHeading::query()
                ->where('section', PageSectionKey::Ervaring)
                ->value('eyebrow_nl'),
        );

        foreach ([PageSectionKey::Hero, PageSectionKey::Diensten, PageSectionKey::Certificaten] as $sectie) {
            $this->assertNotNull(
                SectionHeading::query()->where('section', $sectie)->value('eyebrow_nl'),
                "Het onderdeel {$sectie->value} hoort een opschrift te hebben.",
            );
        }
    }

    /**
     * De standaardtekst mag niet door de vertaalfunctie gaan.
     *
     * `standaard()` levert de waarden van de kolommen `_nl` en `_en`, en
     * die staan allebei vast. Zou het Nederlandse veld door `__()` gaan,
     * dan krijgt een Engelse bezoeker Engelse tekst in het Nederlandse
     * veld -- en schrijft de eigenaar dat over in zijn beheerscherm.
     */
    public function test_the_default_dutch_text_stays_dutch_for_an_english_visitor(): void
    {
        $this->app->setLocale('en');

        $standaard = SectionHeading::standaard(PageSectionKey::Diensten);

        $this->assertSame('Diensten', $standaard['eyebrow_nl']);
        $this->assertSame('Services', $standaard['eyebrow_en']);
    }
}
