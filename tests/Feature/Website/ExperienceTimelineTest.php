<?php

namespace Tests\Feature\Website;

use App\Enums\PageSectionKey;
use App\Models\Experience;
use App\Models\PageSection;
use Database\Seeders\PageSectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * De tijdlijn zoals de bezoeker hem krijgt.
 *
 * Twee dingen staan hier centraal, en allebei zijn ze een expliciete wens:
 *
 * 1. **Een leeg veld levert niets op.** Geen kopje, geen streepje, geen
 *    lege regel. Wat er niet is, staat er niet.
 * 2. **Een leeg onderdeel verdwijnt.** Staat er geen enkele ervaring, dan
 *    laat de website de hele sectie weg -- met een uitroepteken in het
 *    portaal, zodat de eigenaar weet waarom.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
class ExperienceTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PageSectionSeeder::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function landing(?string $taal = null): array
    {
        $response = $taal === null
            ? $this->get(route('home'))
            : $this->withSession(['locale' => $taal])->get(route('home'));

        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Welcome'));

        return $response->viewData('page')['props'];
    }

    public function test_the_section_is_absent_while_there_are_no_experiences(): void
    {
        $props = $this->landing();

        $this->assertNotContains(PageSectionKey::Ervaring->value, $props['sections']);

        // En dan hoeft de inhoud ook niet mee de lijn over.
        $this->assertSame([], $props['experiences']);
    }

    public function test_the_section_appears_as_soon_as_there_is_one(): void
    {
        // De spiegel van de test hierboven. Zonder deze zou die ook slagen
        // als de sectie nooit verschijnt.
        Experience::factory()->create();

        $props = $this->landing();

        $this->assertContains(PageSectionKey::Ervaring->value, $props['sections']);
        $this->assertCount(1, $props['experiences']);
    }

    public function test_what_is_running_comes_first_and_the_rest_by_date(): void
    {
        Experience::factory()->create([
            'role_nl' => 'Oud',
            'started_on' => '2015-01-01',
            'ended_on' => '2018-01-01',
        ]);
        Experience::factory()->create([
            'role_nl' => 'Recent',
            'started_on' => '2019-01-01',
            'ended_on' => '2022-01-01',
        ]);
        Experience::factory()->loopt()->create([
            'role_nl' => 'Nu',
            // Bewust eerder begonnen dan "Recent": wat nu loopt hoort
            // bovenaan te staan, ook als het ouder is.
            'started_on' => '2017-01-01',
        ]);

        $this->assertSame(
            ['Nu', 'Recent', 'Oud'],
            array_column($this->landing()['experiences'], 'functie'),
        );
    }

    public function test_an_experience_without_extras_has_nothing_extra(): void
    {
        /*
         * De kern van dit onderdeel: er komt geen lege beschrijving mee en
         * geen metaregel met een streepje erin. Wat de klant niet invulde,
         * bestaat aan deze kant niet.
         */
        Experience::factory()->kaal()->create();

        $item = $this->landing()['experiences'][0];

        $this->assertNull($item['beschrijving']);
        $this->assertSame([], $item['meta']);
    }

    public function test_the_meta_line_only_contains_what_was_filled_in(): void
    {
        Experience::factory()->create([
            'location_nl' => 'Utrecht',
            'workplace' => 'hybride',
            'employment' => null,
        ]);

        // Twee delen, en geen derde lege plek waar het dienstverband had
        // gestaan. De puntjes ertussen zet de CSS.
        $this->assertSame(
            ['Utrecht', 'Hybride'],
            $this->landing()['experiences'][0]['meta'],
        );
    }

    public function test_a_required_field_falls_back_to_dutch(): void
    {
        Experience::factory()->create([
            'role_nl' => 'Systeembeheerder',
            'role_en' => null,
        ]);

        // Een kaart zonder functietitel is stuk; dan is Nederlands beter
        // dan niets.
        $this->assertSame(
            'Systeembeheerder',
            $this->landing('en')['experiences'][0]['functie'],
        );
    }

    public function test_an_optional_field_is_left_out_instead(): void
    {
        Experience::factory()->create([
            'description_nl' => 'Nederlandse tekst.',
            'description_en' => null,
            'location_nl' => 'Utrecht',
            'location_en' => null,
        ]);

        $item = $this->landing('en')['experiences'][0];

        // Half Nederlands op een Engelse pagina is slordiger dan geen
        // beschrijving.
        $this->assertNull($item['beschrijving']);
        $this->assertNotContains('Utrecht', $item['meta']);
    }

    public function test_the_english_translation_is_used_when_it_is_there(): void
    {
        Experience::factory()->vertaald()->create();

        $item = $this->landing('en')['experiences'][0];

        $this->assertSame('Translated role', $item['functie']);
        $this->assertSame('Translated description.', $item['beschrijving']);
    }

    public function test_the_timeline_gets_a_year_to_group_on(): void
    {
        /*
         * Het jaartal dat links meeloopt, groepeert op het beginjaar. De
         * groepering zelf gebeurt in de browser, maar zonder dit veld valt
         * er niets te groeperen -- en dat is precies het soort ding dat
         * stil wegvalt als iemand de payload opschoont.
         */
        Experience::factory()->create(['started_on' => '2021-03-01']);

        $this->assertSame('2021', $this->landing()['experiences'][0]['jaar']);
    }

    public function test_a_running_experience_says_until_today(): void
    {
        Experience::factory()->loopt()->create(['started_on' => '2021-03-01']);

        $item = $this->landing()['experiences'][0];

        $this->assertTrue($item['loopt']);
        $this->assertStringContainsString('heden', $item['periode']);
    }

    public function test_the_period_shows_only_month_and_year(): void
    {
        Experience::factory()->create([
            'started_on' => '2021-03-01',
            'ended_on' => '2024-06-01',
        ]);

        $periode = $this->landing()['experiences'][0]['periode'];

        $this->assertSame('mrt. 2021 – jun. 2024', $periode);
        // De dag in de database is 1 en betekent niets; hij hoort dus ook
        // nergens op het scherm te staan.
        $this->assertStringNotContainsString('1 mrt', $periode);
    }

    public function test_the_duration_counts_the_first_month_too(): void
    {
        // Maart tot en met maart is één maand werk en niet nul. Zonder die
        // plus staat er bij een kort dienstverband "0 maanden".
        Experience::factory()->create([
            'started_on' => '2021-03-01',
            'ended_on' => '2021-03-01',
        ]);

        $this->assertSame('1 maand', $this->landing()['experiences'][0]['duur']);
    }

    public function test_the_owner_can_switch_the_section_off_even_when_it_is_filled(): void
    {
        Experience::factory()->create();

        PageSection::query()
            ->where('key', PageSectionKey::Ervaring)
            ->update(['visible' => false]);

        $this->assertNotContains(
            PageSectionKey::Ervaring->value,
            $this->landing()['sections'],
        );
    }
}
