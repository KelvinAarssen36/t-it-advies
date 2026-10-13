<?php

namespace Tests\Feature;

use App\Models\Project;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * De sitemap.
 *
 * **De test die ertoe doet is `test_every_address_in_the_sitemap_exists`.**
 * Vier van de zes pagina's bestaan alleen als de eigenaar ze heeft
 * aangezet en geven anders een 404. De sitemap stelt dus per pagina
 * dezelfde vraag als de pagina zelf -- en die twee antwoorden kunnen
 * uiteen gaan lopen zodra iemand één van de twee aanpast.
 *
 * Deze test haalt elk adres uit de sitemap op en eist een 200. Daarmee kan
 * het nooit stil misgaan: gaat de voorwaarde van een pagina veranderen
 * zonder dat de sitemap meegaat, dan valt dit om.
 *
 * Zie docs/architecture/sitemap.md.
 */
class SitemapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
    }

    /**
     * De adressen uit de sitemap, als lijst.
     *
     * @return array<int, string>
     */
    private function adressen(): array
    {
        $xml = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->getContent();

        preg_match_all('#<loc>(.*?)</loc>#', (string) $xml, $treffers);

        return $treffers[1];
    }

    public function test_it_is_valid_xml(): void
    {
        $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringStartsWith('<?xml version="1.0"', $xml);

        /*
         * Echt parsen en niet alleen op tekst zoeken: een niet-ontsnapt
         * teken in een titel of een slug maakt het bestand ongeldig, en
         * dan negeert een crawler de hele sitemap in plaats van die ene
         * regel.
         */
        $this->assertInstanceOf(
            \SimpleXMLElement::class,
            simplexml_load_string($xml),
            'De sitemap is geen geldige XML.',
        );
    }

    /**
     * Elk adres in de sitemap bestaat ook echt.
     *
     * Dit is de kern: een sitemap die naar een 404 wijst is erger dan geen
     * sitemap, want dan ben je niet alleen onvindbaar maar ook
     * onbetrouwbaar.
     */
    public function test_every_address_in_the_sitemap_exists(): void
    {
        Project::factory()->count(3)->create(['published' => true]);

        $adressen = $this->adressen();

        $this->assertNotEmpty($adressen, 'De sitemap is leeg.');

        foreach ($adressen as $adres) {
            $pad = (string) parse_url($adres, PHP_URL_PATH);

            $this->get($pad)->assertOk();
        }
    }

    public function test_the_landing_page_and_the_privacy_statement_are_always_there(): void
    {
        $adressen = $this->adressen();

        $this->assertContains(route('home'), $adressen);
        $this->assertContains(route('privacy'), $adressen);
    }

    /**
     * Een onderdeel dat uit staat hoort er niet in.
     *
     * Hetzelfde geval als hierboven, maar van de andere kant bekeken: de
     * pagina geeft dan een 404, dus de sitemap mag hem niet noemen.
     */
    public function test_a_page_that_does_not_exist_is_left_out(): void
    {
        $this->assertNotContains(
            route('projecten'),
            $this->adressen(),
            'Er staan geen projecten online, dus /projecten geeft een 404.',
        );
    }

    public function test_published_projects_get_their_own_address(): void
    {
        $project = Project::factory()->create(['published' => true]);
        $verborgen = Project::factory()->create(['published' => false]);

        $adressen = $this->adressen();

        $this->assertContains(route('project', $project->slug), $adressen);

        $this->assertNotContains(
            route('project', $verborgen->slug),
            $adressen,
            'Een project dat offline staat geeft een 404 en hoort er dus niet in.',
        );
    }

    /**
     * Een project krijgt een `lastmod`, de vaste pagina's niet.
     *
     * Bij een project weten we wanneer hij is gewijzigd. Bij de voorpagina
     * zou het een optelsom van tien tabellen zijn, en een datum die we
     * gokken is een datum waarop een crawler ten onrechte vertrouwt.
     */
    public function test_only_a_project_carries_a_last_modified_date(): void
    {
        Project::factory()->create(['published' => true]);

        $xml = (string) $this->get('/sitemap.xml')->getContent();

        $this->assertSame(
            1,
            substr_count($xml, '<lastmod>'),
            'Alleen het project hoort een lastmod te hebben.',
        );
    }

    /**
     * De sitemap telt niet mee als bezoek.
     *
     * Een crawler is geen bezoeker. Zou deze route meetellen, dan beweegt
     * je bezoekcijfer mee met hoe vaak Google langskomt.
     */
    public function test_fetching_the_sitemap_is_not_counted_as_a_visit(): void
    {
        $voor = DB::table('site_day_totals')->sum('views');

        $this->get('/sitemap.xml')->assertOk();

        $this->assertSame(
            $voor,
            DB::table('site_day_totals')->sum('views'),
            'Het ophalen van de sitemap is als bezoek geteld.',
        );
    }

    /** En robots.txt wijst ernaar, anders vindt niemand hem. */
    public function test_robots_txt_points_to_it(): void
    {
        $robots = (string) file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Sitemap:', $robots);
        $this->assertStringContainsString('/sitemap.xml', $robots);
    }
}
