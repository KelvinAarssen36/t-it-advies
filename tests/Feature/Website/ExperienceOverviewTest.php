<?php

namespace Tests\Feature\Website;

use App\Models\Experience;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Het overzicht en de detailpagina van de tijdlijn.
 *
 * Het overzicht is een lijst waarin je zoekt en doorklikt, en dat brengt
 * drie dingen mee die stil kapot kunnen gaan: de zoekopdracht, de
 * bladwijzer, en de vraag waar je belandt nadat je iets hebt weggegooid.
 * Dat laatste is de vervelendste -- verwijder je iets vanaf zijn eigen
 * pagina, dan bestaat de pagina waar je op stond niet meer.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
class ExperienceOverviewTest extends TestCase
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
     * @return array<string, mixed>
     */
    private function overzicht(array $parameters = []): array
    {
        return $this->actingAs($this->beheerder())
            ->get(route('website.ervaring.index', $parameters))
            ->viewData('page')['props'];
    }

    /*
     * De controle op gast en recht staat niet hier maar in
     * ExperienceCrudTest: daar staat één lijst met álle routes van deze
     * module bij elkaar. Twee halve lijsten op twee plekken is precies hoe
     * er ooit een route tussenuit valt.
     */

    /* --- Zoeken ------------------------------------------------------ */

    public function test_searching_looks_at_the_role_the_organisation_and_the_place(): void
    {
        Experience::factory()->create([
            'role_nl' => 'Systeembeheerder',
            'organisation' => 'Eni',
            'location_nl' => 'Amsterdam',
        ]);
        Experience::factory()->create([
            'role_nl' => 'Teamleider',
            'organisation' => 'Valtech',
            'location_nl' => 'Utrecht',
        ]);

        foreach (['beheer', 'Valtech', 'Utrecht'] as $term) {
            $gevonden = $this->overzicht(['zoek' => $term])['items']['data'];

            $this->assertCount(1, $gevonden, "Zoeken op '{$term}' gaf niet één resultaat.");
        }
    }

    public function test_searching_also_finds_the_english_title(): void
    {
        // Wie zijn Engelse teksten nakijkt, zoekt op de Engelse titel.
        Experience::factory()->create([
            'role_nl' => 'Systeembeheerder',
            'role_en' => 'System administrator',
        ]);

        $this->assertCount(
            1,
            $this->overzicht(['zoek' => 'administrator'])['items']['data'],
        );
    }

    public function test_a_wildcard_in_the_search_term_is_just_a_character(): void
    {
        /*
         * In een LIKE betekent `%` "wat dan ook". Zonder ontsnapping geeft
         * een zoekterm met een procentteken erin dus de hele lijst terug --
         * geen lek, want de waarde is gebonden, maar wel een zoekveld dat
         * raar doet zodra iemand "100%" intypt.
         */
        Experience::factory()->count(3)->create(['role_nl' => 'Beheerder']);

        $this->assertCount(0, $this->overzicht(['zoek' => '%'])['items']['data']);
    }

    public function test_a_search_term_with_a_backslash_finds_what_it_should(): void
    {
        /*
         * De ontsnapping van `%` en `_` gebeurt met een backslash, en
         * welke betekenis die heeft verschilt per database: MySQL neemt
         * hem vanzelf, SQLite alleen met een `ESCAPE`-clausule erbij.
         * Zonder die clausule doet het zoekveld in een test iets anders
         * dan in productie -- en dan geloof je die test op een dag ten
         * onrechte.
         */
        Experience::factory()->create(['role_nl' => 'Beheer 100% van het netwerk']);
        Experience::factory()->create(['role_nl' => 'Teamleider']);

        $this->assertCount(1, $this->overzicht(['zoek' => '100%'])['items']['data']);
        $this->assertCount(0, $this->overzicht(['zoek' => 'Beheer%van'])['items']['data']);
    }

    public function test_an_empty_search_shows_everything_again(): void
    {
        Experience::factory()->count(3)->create();

        $props = $this->overzicht(['zoek' => '  ']);

        $this->assertCount(3, $props['items']['data']);
        $this->assertNull($props['filters']['zoek']);
    }

    /* --- Bladeren ---------------------------------------------------- */

    public function test_a_long_career_is_spread_over_pages(): void
    {
        Experience::factory()->count(18)->create();

        $eerste = $this->overzicht();
        $tweede = $this->overzicht(['page' => 2]);

        $this->assertCount(15, $eerste['items']['data']);
        $this->assertCount(3, $tweede['items']['data']);
        $this->assertSame(18, $eerste['items']['total']);
    }

    public function test_the_search_term_survives_a_page_turn(): void
    {
        // Zonder `withQueryString` valt de zoekterm van de paginalinks af,
        // en dan sta je op pagina twee van iets anders.
        Experience::factory()->count(20)->create(['organisation' => 'Eni']);
        Experience::factory()->count(3)->create(['organisation' => 'Valtech']);

        $links = $this->overzicht(['zoek' => 'Eni'])['items']['links'];

        $volgende = collect($links)->firstWhere('label', '2');

        $this->assertStringContainsString('zoek=Eni', (string) $volgende['url']);
    }

    /* --- Leeg of niets gevonden -------------------------------------- */

    public function test_an_empty_module_is_something_else_than_an_empty_search(): void
    {
        /*
         * Twee heel verschillende schermen. Het eerste legt uit waarom het
         * onderdeel niet op de website staat; het tweede zegt alleen dat je
         * anders moet zoeken.
         */
        $this->assertTrue($this->overzicht()['leeg']);

        Experience::factory()->create(['role_nl' => 'Systeembeheerder']);

        $this->assertFalse($this->overzicht()['leeg']);
        $this->assertFalse($this->overzicht(['zoek' => 'bestaatniet'])['leeg']);
        $this->assertCount(0, $this->overzicht(['zoek' => 'bestaatniet'])['items']['data']);
    }

    /* --- De detailpagina --------------------------------------------- */

    public function test_the_detail_page_shows_both_languages_and_the_labels(): void
    {
        $ervaring = Experience::factory()->create([
            'role_nl' => 'Systeembeheerder',
            'role_en' => 'System administrator',
            'employment' => 'vast',
            'workplace' => 'hybride',
        ]);

        $this->actingAs($this->beheerder())
            ->get(route('website.ervaring.show', $ervaring))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('website/ErvaringDetail')
                ->where('item.role_nl', 'Systeembeheerder')
                ->where('item.role_en', 'System administrator')
                // De labels komen van de server; het scherm zoekt ze niet
                // zelf op in de keuzelijst.
                ->where('item.employment_label', 'Vaste aanstelling')
                ->where('item.workplace_label', 'Hybride')
                ->has('opties.maanden', 12));
    }

    public function test_the_detail_page_links_to_the_neighbours_on_the_timeline(): void
    {
        $oud = Experience::factory()->create([
            'role_nl' => 'Oud',
            'started_on' => '2015-01-01',
            'ended_on' => '2018-01-01',
        ]);
        $midden = Experience::factory()->create([
            'role_nl' => 'Midden',
            'started_on' => '2019-01-01',
            'ended_on' => '2022-01-01',
        ]);
        $nieuw = Experience::factory()->loopt()->create(['role_nl' => 'Nieuw']);

        $props = $this->actingAs($this->beheerder())
            ->get(route('website.ervaring.show', $midden))
            ->viewData('page')['props'];

        // "Nieuwer" is de buur erboven op de tijdlijn, "ouder" die eronder.
        $this->assertSame($nieuw->id, $props['buren']['vorige']['id']);
        $this->assertSame($oud->id, $props['buren']['volgende']['id']);

        /*
         * Functie, organisatie én periode gaan mee. Die periode is geen
         * versiering: bij iemand die drie keer "Service Manager" is
         * geweest zegt alleen een titel niets over waar de knop heen gaat.
         */
        $this->assertSame('Nieuw', $props['buren']['vorige']['functie']);
        $this->assertNotEmpty($props['buren']['vorige']['organisatie']);
        $this->assertStringContainsString(
            'heden',
            $props['buren']['vorige']['periode'],
        );
    }

    public function test_the_first_and_the_last_have_only_one_neighbour(): void
    {
        $eerste = Experience::factory()->loopt()->create();
        Experience::factory()->create();

        $props = $this->actingAs($this->beheerder())
            ->get(route('website.ervaring.show', $eerste))
            ->viewData('page')['props'];

        $this->assertNull($props['buren']['vorige']);
        $this->assertNotNull($props['buren']['volgende']);
    }

    /* --- Waar je belandt na het verwijderen -------------------------- */

    public function test_deleting_from_the_detail_page_lands_on_the_overview(): void
    {
        /*
         * De pagina waar je vandaan komt bestaat straks niet meer. Zonder
         * deze omleiding kijkt de klant na het verwijderen naar een 404 van
         * het item dat hij zojuist zelf weggooide.
         */
        $ervaring = Experience::factory()->create();

        $this->actingAs($this->beheerder())
            ->from(route('website.ervaring.show', $ervaring))
            ->delete(route('website.ervaring.destroy', $ervaring))
            ->assertRedirect(route('website.ervaring.index'));
    }

    public function test_deleting_from_the_overview_keeps_your_place(): void
    {
        // Verwijder je iets terwijl je zoekt, dan hoor je met diezelfde
        // zoekterm terug te komen en niet bovenaan de hele lijst.
        $ervaring = Experience::factory()->create();

        $terug = route('website.ervaring.index', ['zoek' => 'Eni', 'page' => 2]);

        $this->actingAs($this->beheerder())
            ->from($terug)
            ->delete(route('website.ervaring.destroy', $ervaring))
            ->assertRedirect($terug);
    }

    public function test_deleting_never_sends_you_to_another_website(): void
    {
        /*
         * "Terug waar je vandaan kwam" komt uit de Referer-header, en die
         * stuurt de browser mee. Zonder controle op de host kun je iemand
         * via een geprepareerde header naar een vreemd domein sturen. Laag
         * risico -- er is een geldig CSRF-token voor nodig -- maar we
         * geven hier zelf een adres mee, en dan hoort het het onze te zijn.
         */
        $ervaring = Experience::factory()->create();

        $this->actingAs($this->beheerder())
            ->from('https://kwaadaardig.example/pagina')
            ->delete(route('website.ervaring.destroy', $ervaring))
            ->assertRedirect(route('website.ervaring.index'));
    }
}
