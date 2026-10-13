<?php

namespace Tests\Feature\Website;

use App\Models\Project;
use App\Models\User;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectionHeadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De uitgelichte projecten.
 *
 * **Er mogen er meerdere zijn.** Samen draaien ze als slideshow bovenaan
 * `/projecten`; op de voorpagina staat het bovenste uit de volgorde van
 * de eigenaar groot, met de rest ernaast als kaart.
 *
 * Wat deze tests vooral bewaken is dat de site nooit iets uitlicht wat de
 * eigenaar niet heeft gekozen. Zet hij zijn bovenste uitgelichte project
 * offline, dan schuift het volgende *uitgelichte* project naar voren --
 * nooit zomaar het eerstvolgende project uit de lijst.
 *
 * Zie docs/architecture/modules/projecten.md.
 */
class ProjectUitgelichtTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
        $this->seed(SectionHeadingSeeder::class);
    }

    private function beheerder(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage portal');

        return $user;
    }

    private function licht(Project $project): void
    {
        $this->actingAs($this->beheerder())
            ->patch(route('website.projecten.uitlichten', $project))
            ->assertSessionHasNoErrors();
    }

    /**
     * @return array<string, mixed>
     */
    private function landing(): array
    {
        return $this->get(route('home'))->viewData('page')['props'];
    }

    /**
     * @return array<string, mixed>
     */
    private function overzicht(): array
    {
        return $this->get(route('projecten'))->viewData('page')['props'];
    }

    /* --- Uitlichten --------------------------------------------------------- */

    public function test_a_project_can_be_featured(): void
    {
        $project = Project::factory()->create();

        $this->licht($project);

        $this->assertTrue($project->refresh()->featured);
    }

    /** En het staat dan groot op de voorpagina. */
    public function test_a_featured_project_reaches_the_front_page(): void
    {
        $project = Project::factory()->create();

        $this->licht($project);

        $this->assertSame(
            $project->slug,
            $this->landing()['featuredProjects'][0]['slug'],
        );
    }

    /* --- Meerdere mogen ------------------------------------------------------ */

    /**
     * Een tweede uitlichten laat de eerste staan.
     *
     * Dit was ooit andersom: er mocht er precies één zijn en een nieuwe
     * zette de vorige uit. Dat is bewust omgedraaid toen de slideshow
     * erbij kwam -- een slideshow heeft meer dan één dia nodig.
     */
    public function test_featuring_a_second_project_keeps_the_first(): void
    {
        $eerste = Project::factory()->uitgelicht()->create(['position' => 1]);
        $tweede = Project::factory()->create(['position' => 2]);

        $this->licht($tweede);

        $this->assertTrue($eerste->refresh()->featured);
        $this->assertTrue($tweede->refresh()->featured);
        $this->assertSame(2, Project::query()->where('featured', true)->count());
    }

    /** Op de voorpagina staat het bovenste uit de eigen volgorde groot. */
    public function test_the_front_page_shows_the_first_in_the_order(): void
    {
        Project::factory()->uitgelicht()->create(['position' => 2]);
        $bovenste = Project::factory()->uitgelicht()->create(['position' => 1]);

        $this->assertSame(
            $bovenste->slug,
            $this->landing()['featuredProjects'][0]['slug'],
        );
    }

    /**
     * De andere uitgelichte projecten zijn dia's en geen kaarten.
     *
     * Een project staat in één van de twee, nooit in allebei -- op de
     * voorpagina net zo goed als op `/projecten`. Zou een uitgelicht
     * project ook nog tussen de kaarten staan, dan lijkt het alsof de
     * eigenaar het twee keer heeft ingevoerd.
     */
    public function test_featured_projects_are_slides_and_not_also_cards(): void
    {
        $eerste = Project::factory()->uitgelicht()->create(['position' => 1]);
        $tweede = Project::factory()->uitgelicht()->create(['position' => 2]);
        $gewoon = Project::factory()->create(['position' => 3]);

        $props = $this->landing();

        $this->assertSame(
            [$eerste->slug, $tweede->slug],
            array_column($props['featuredProjects'], 'slug'),
        );

        $this->assertSame(
            [$gewoon->slug],
            array_column($props['projects'], 'slug'),
        );
    }

    /* --- De slideshow op /projecten ------------------------------------------ */

    /**
     * Op het overzicht staat een project in één van de twee, nooit in
     * allebei.
     *
     * Zou het uitgelichte project ook nog tussen de kaarten staan, dan
     * lijkt het alsof de eigenaar het twee keer heeft ingevoerd.
     */
    public function test_the_overview_splits_featured_from_the_rest(): void
    {
        $uitgelicht = Project::factory()->uitgelicht()->create(['position' => 1]);
        $gewoon = Project::factory()->create(['position' => 2]);

        $props = $this->overzicht();

        $this->assertSame(
            [$uitgelicht->slug],
            array_column($props['featured'], 'slug'),
        );
        $this->assertSame(
            [$gewoon->slug],
            array_column($props['projects'], 'slug'),
        );
    }

    /** Meerdere uitgelichte komen in de volgorde van de eigenaar. */
    public function test_the_slideshow_follows_the_order(): void
    {
        $tweede = Project::factory()->uitgelicht()->create(['position' => 2]);
        $eerste = Project::factory()->uitgelicht()->create(['position' => 1]);

        $this->assertSame(
            [$eerste->slug, $tweede->slug],
            array_column($this->overzicht()['featured'], 'slug'),
        );
    }

    /** Zonder uitgelicht project is er geen slideshow en staat alles in de lijst. */
    public function test_without_featured_projects_there_is_no_slideshow(): void
    {
        Project::factory()->count(2)->create();

        $props = $this->overzicht();

        $this->assertSame([], $props['featured']);
        $this->assertCount(2, $props['projects']);
    }

    /* --- Niets uitlichten mag ------------------------------------------------ */

    public function test_featuring_the_same_project_again_turns_it_off(): void
    {
        $project = Project::factory()->uitgelicht()->create();

        $this->licht($project);

        $this->assertFalse($project->refresh()->featured);
        $this->assertSame(0, Project::query()->where('featured', true)->count());
    }

    public function test_without_a_featured_project_the_prop_is_null(): void
    {
        Project::factory()->create();

        $this->assertSame([], $this->landing()['featuredProjects']);
    }

    /* --- De volgorde blijft -------------------------------------------------- */

    /**
     * Uitlichten en sorteren zijn twee verschillende dingen.
     *
     * Een uitgelicht project houdt zijn plek in de rij -- die volgorde
     * bepaalt juist welke dia het eerst komt en welk project groot op de
     * voorpagina staat.
     */
    public function test_featuring_does_not_touch_the_order(): void
    {
        $een = Project::factory()->create(['position' => 1]);
        $twee = Project::factory()->create(['position' => 2]);
        $drie = Project::factory()->create(['position' => 3]);

        $this->licht($twee);

        $this->assertSame(1, $een->refresh()->position);
        $this->assertSame(2, $twee->refresh()->position);
        $this->assertSame(3, $drie->refresh()->position);
    }

    /* --- Offline ------------------------------------------------------------- */

    /**
     * Een uitgelicht project dat offline staat verdwijnt van de site.
     *
     * De vlag blijft gewoon staan; alleen het blok is weg tot het weer
     * online komt.
     */
    public function test_an_offline_featured_project_stays_off_the_site(): void
    {
        $project = Project::factory()->uitgelicht()->offline()->create();

        $this->assertSame([], $this->landing()['featuredProjects']);
        $this->assertTrue($project->refresh()->featured);
    }

    /**
     * Een project zonder sterretje neemt die plek nooit over.
     *
     * Dat is de hele regel: de site toont alleen wat de eigenaar zelf
     * heeft uitgelicht. Staat zijn enige uitgelichte project offline, dan
     * is er geen groot blok -- en niet stil het eerstvolgende project uit
     * de lijst.
     */
    public function test_a_project_without_a_star_never_takes_over(): void
    {
        Project::factory()->uitgelicht()->offline()->create(['position' => 1]);
        Project::factory()->create(['position' => 2]);

        $props = $this->landing();

        $this->assertSame([], $props['featuredProjects']);
        $this->assertCount(1, $props['projects']);
    }

    /**
     * Maar het volgende *uitgelichte* project schuift wel door.
     *
     * Dat is geen eigen keuze van de site: de eigenaar heeft dat project
     * zelf uitgelicht, dus het hoort vooraan te staan zodra het bovenste
     * er niet is.
     */
    public function test_the_next_featured_project_does_move_up(): void
    {
        Project::factory()->uitgelicht()->offline()->create(['position' => 1]);
        $tweede = Project::factory()->uitgelicht()->create(['position' => 2]);

        $this->assertSame(
            $tweede->slug,
            $this->landing()['featuredProjects'][0]['slug'],
        );
    }

    /** Een offline project mag wel vast worden uitgelicht. */
    public function test_an_offline_project_can_be_featured(): void
    {
        $project = Project::factory()->offline()->create();

        $this->licht($project);

        $this->assertTrue($project->refresh()->featured);
        $this->assertSame([], $this->landing()['featuredProjects']);
    }

    /* --- Verwijderen --------------------------------------------------------- */

    /** Het enige uitgelichte project weggooien laat er geen achter. */
    public function test_removing_the_only_featured_project_leaves_none(): void
    {
        $uitgelicht = Project::factory()->uitgelicht()->create();
        Project::factory()->create();

        $this->actingAs($this->beheerder())
            ->delete(route('website.projecten.destroy', $uitgelicht))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Project::query()->where('featured', true)->count());
        $this->assertSame([], $this->landing()['featuredProjects']);
    }

    /** En er één weggooien laat de andere uitgelicht staan. */
    public function test_removing_one_leaves_the_other_featured(): void
    {
        $eerste = Project::factory()->uitgelicht()->create(['position' => 1]);
        $tweede = Project::factory()->uitgelicht()->create(['position' => 2]);

        $this->actingAs($this->beheerder())
            ->delete(route('website.projecten.destroy', $eerste))
            ->assertSessionHasNoErrors();

        $this->assertTrue($tweede->refresh()->featured);
        $this->assertSame(
            $tweede->slug,
            $this->landing()['featuredProjects'][0]['slug'],
        );
    }
}
