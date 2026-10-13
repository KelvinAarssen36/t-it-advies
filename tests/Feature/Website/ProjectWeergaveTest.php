<?php

namespace Tests\Feature\Website;

use App\Enums\ProjectWeergave;
use App\Models\ActivityEntry;
use App\Models\Project;
use App\Models\ProjectSetting;
use App\Models\User;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectionHeadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * De weergave van de projectenpagina.
 *
 * Twee vormen, en de eigenaar kiest: afwisselend groot en klein, of
 * allemaal even grote kaarten. Welke beter werkt hangt af van wat er in
 * zijn projecten staat -- het ritme leeft van de afbeeldingen, het raster
 * werkt juist als die er niet zijn.
 *
 * Wat deze tests bewaken: dat een verse database gewoon werkt zonder dat
 * er ooit iets is geseed, dat de keuze op de publieke pagina terechtkomt,
 * en dat niemand zonder recht eraan kan komen.
 *
 * Zie docs/architecture/modules/projecten.md.
 */
class ProjectWeergaveTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Eén beheerder voor de hele test, en dat is niet voor de netheid.
     *
     * Een gebruiker aanmaken zet zelf een regel in het activiteitenlogboek.
     * Zou elke aanroep een nieuwe beheerder maken, dan telt die regel mee
     * en bewijzen de twee logboektests hieronder niets: ze zouden slagen op
     * de gebruiker in plaats van op de instelling.
     */
    private User $gebruiker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
        $this->seed(SectionHeadingSeeder::class);

        $this->gebruiker = User::factory()->create();
        $this->gebruiker->givePermissionTo('manage portal');
    }

    private function beheerder(): User
    {
        return $this->gebruiker;
    }

    private function zet(string $weergave): TestResponse
    {
        return $this->actingAs($this->beheerder())->put(
            route('website.projecten.weergave'),
            ['weergave' => $weergave],
        );
    }

    /* --- De standaard -------------------------------------------------------- */

    /**
     * Zonder rij in de database werkt alles gewoon.
     *
     * `huidige()` geeft dan een niet-opgeslagen exemplaar met de
     * standaardwaarden. Zo hoeft het tonen van de publieke site niets weg
     * te schrijven, en hoeft er geen controle op `null` door de
     * applicatie heen te lopen.
     */
    public function test_an_unseeded_database_falls_back_to_the_rhythm(): void
    {
        $this->assertSame(0, ProjectSetting::query()->count());
        $this->assertSame(ProjectWeergave::Lijst, ProjectSetting::weergave());
    }

    public function test_the_public_page_gets_the_rhythm_by_default(): void
    {
        Project::factory()->create();

        $props = $this->get(route('projecten'))->viewData('page')['props'];

        $this->assertSame('lijst', $props['weergave']);
    }

    /* --- Omzetten ------------------------------------------------------------ */

    public function test_the_owner_can_switch_to_the_grid(): void
    {
        $this->zet('raster')->assertSessionHasNoErrors();

        $this->assertSame(ProjectWeergave::Raster, ProjectSetting::weergave());
    }

    /** En de publieke pagina volgt meteen. */
    public function test_the_public_page_follows_the_choice(): void
    {
        Project::factory()->create();

        $this->zet('raster');

        $props = $this->get(route('projecten'))->viewData('page')['props'];

        $this->assertSame('raster', $props['weergave']);
    }

    /** Terugzetten kan ook. */
    public function test_it_can_go_back(): void
    {
        $this->zet('raster');
        $this->zet('lijst')->assertSessionHasNoErrors();

        $this->assertSame(ProjectWeergave::Lijst, ProjectSetting::weergave());
    }

    /**
     * Dezelfde weergave nog een keer kiezen verandert niets.
     *
     * Geen fout en geen tweede regel in het logboek: er is niets gebeurd.
     */
    public function test_choosing_the_same_one_changes_nothing(): void
    {
        $this->zet('raster');

        $voor = ActivityEntry::query()->count();

        $this->zet('raster')->assertSessionHasNoErrors();

        $this->assertSame($voor, ActivityEntry::query()->count());
    }

    /* --- Wat er niet mag ----------------------------------------------------- */

    public function test_a_made_up_value_is_refused(): void
    {
        $this->zet('tijdschrift')->assertSessionHasErrors('weergave');

        $this->assertSame(ProjectWeergave::Lijst, ProjectSetting::weergave());
    }

    public function test_an_empty_value_is_refused(): void
    {
        $this->zet('')->assertSessionHasErrors('weergave');
    }

    public function test_a_visitor_cannot_change_it(): void
    {
        $this->put(route('website.projecten.weergave'), ['weergave' => 'raster'])
            ->assertRedirect(route('login'));

        $this->assertSame(ProjectWeergave::Lijst, ProjectSetting::weergave());
    }

    public function test_a_user_without_the_permission_cannot_change_it(): void
    {
        $this->actingAs(User::factory()->create()) // bewust zonder recht
            ->put(route('website.projecten.weergave'), ['weergave' => 'raster'])
            ->assertForbidden();

        $this->assertSame(ProjectWeergave::Lijst, ProjectSetting::weergave());
    }

    /* --- Het beheerscherm ---------------------------------------------------- */

    public function test_the_admin_screen_knows_the_current_choice(): void
    {
        $this->zet('raster');

        $props = $this->actingAs($this->beheerder())
            ->get(route('website.projecten.index'))
            ->viewData('page')['props'];

        $this->assertSame('raster', $props['weergave']);
        $this->assertCount(2, $props['opties']['weergaven']);
    }

    /* --- Het activiteitenlogboek --------------------------------------------- */

    public function test_the_change_is_logged(): void
    {
        $voor = ActivityEntry::query()->count();

        $this->zet('raster');

        $this->assertGreaterThan($voor, ActivityEntry::query()->count());
    }
}
