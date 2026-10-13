<?php

namespace Tests\Feature;

use App\Enums\PageSectionKey;
use App\Models\Project;
use App\Models\SectionHeading;
use App\Support\Page\Metagegevens;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Wat WhatsApp, LinkedIn en een crawler van een gedeelde link zien.
 *
 * **Deze tests kijken naar de HTML en niet naar de Inertia-props**, en dat
 * is het hele punt. Inertia zet de titel in de browser, ná het laden van
 * het JavaScript; die diensten voeren dat niet uit. Alles wat zij te zien
 * krijgen staat in de `<head>` van wat de server teruggeeft -- en dus is
 * dat wat hier wordt gecontroleerd.
 *
 * Zie App\Support\Page\Metagegevens en beslissing 018.
 */
class LinkvoorbeeldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
    }

    public function test_the_landing_page_carries_its_own_title_and_description(): void
    {
        $kop = SectionHeading::voor(PageSectionKey::Hero);

        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString(
            '<meta property="og:title" content="'.e((string) $kop->titel()).'"',
            $html,
        );

        $this->assertStringContainsString('<meta property="og:description"', $html);
        $this->assertStringContainsString('<meta name="description"', $html);
    }

    /**
     * De titel in de `<title>` is die van de pagina, niet de sitenaam.
     *
     * Stond daar de sitenaam, dan heet elke pagina van deze site hetzelfde
     * in de zoekresultaten.
     */
    public function test_the_title_tag_is_not_just_the_site_name(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        preg_match('#<title>(.*?)</title>#s', $html, $treffer);

        $this->assertNotEmpty($treffer, 'Er staat geen titel in de pagina.');
        $this->assertNotSame(config('app.name'), trim($treffer[1]));
    }

    /**
     * Een project krijgt zijn eigen voorbeeld.
     *
     * Dit is het geval dat er het meest toe doet: een project is precies
     * wat de eigenaar in een mail of op LinkedIn deelt.
     */
    public function test_a_project_gets_its_own_preview(): void
    {
        $project = Project::factory()->create([
            'published' => true,
            'title_nl' => 'Migratie naar Exchange Online',
            'summary_nl' => 'Van een eigen mailserver naar Microsoft 365, zonder dat iemand zijn mail kwijtraakt.',
        ]);

        $html = (string) $this->get('/projecten/'.$project->slug)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'content="Migratie naar Exchange Online"',
            $html,
        );

        $this->assertStringContainsString('zonder dat iemand zijn mail kwijtraakt', $html);
    }

    /** En het adres van de pagina zelf, zodat een voorbeeld terugverwijst. */
    public function test_the_canonical_address_is_the_page_itself(): void
    {
        $html = (string) $this->get('/privacy')->assertOk()->getContent();

        $this->assertStringContainsString(
            '<meta property="og:url" content="'.e(route('privacy')).'"',
            $html,
        );
    }

    /**
     * Elke publieke controller geeft een `meta` mee.
     *
     * Zelfde vorm als de bewaking op de voet: de prop gaat per controller
     * mee, dus elke nieuwe publieke pagina is een kans om het te vergeten.
     * Vergeet je het, dan heet die pagina stilletjes net als de site --
     * en dat merk je pas als iemand de link deelt.
     */
    public function test_every_public_controller_passes_metadata(): void
    {
        $gevonden = [];
        $ontbreekt = [];

        foreach (File::allFiles(app_path('Http/Controllers')) as $bestand) {
            if ($bestand->getExtension() !== 'php') {
                continue;
            }

            $inhoud = (string) file_get_contents($bestand->getPathname());

            $publiek = str_contains($inhoud, "Inertia::render('public/")
                || str_contains($inhoud, "Inertia::render('Welcome'");

            if (! $publiek) {
                continue;
            }

            $gevonden[] = $bestand->getFilename();

            if (! str_contains($inhoud, "'meta' =>")) {
                $ontbreekt[] = $bestand->getFilename();
            }
        }

        $this->assertNotEmpty($gevonden);

        $this->assertSame(
            [],
            $ontbreekt,
            'Deze publieke controllers geven geen linkvoorbeeld mee: '
                .implode(', ', $ontbreekt).'. Zet er "meta" bij, naast "voet".',
        );
    }

    /* --- Het afkappen --------------------------------------------------- */

    public function test_a_long_description_is_cut_on_a_word(): void
    {
        $lang = str_repeat('woord ', 60);

        $meta = Metagegevens::maak('Titel', $lang);

        $omschrijving = (string) $meta['omschrijving'];

        $this->assertLessThanOrEqual(
            Metagegevens::OMSCHRIJVING_MAX,
            mb_strlen($omschrijving),
        );

        $this->assertStringEndsWith('…', $omschrijving);

        // Niet middenin een woord: daarvoor is het afkappen op een spatie.
        $this->assertStringNotContainsString('woor…', $omschrijving);
    }

    public function test_an_empty_description_stays_empty(): void
    {
        $this->assertNull(Metagegevens::maak('Titel', '   ')['omschrijving']);
        $this->assertNull(Metagegevens::maak('Titel')['omschrijving']);
    }

    /** Regelafbrekingen zijn in een meta-tag geen opmaak maar ruis. */
    public function test_line_breaks_are_folded_into_spaces(): void
    {
        $meta = Metagegevens::maak('Titel', "Eerste regel.\n\nTweede regel.");

        $this->assertSame('Eerste regel. Tweede regel.', $meta['omschrijving']);
    }
}
