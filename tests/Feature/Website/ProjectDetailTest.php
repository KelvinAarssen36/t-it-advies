<?php

namespace Tests\Feature\Website;

use App\Enums\PageSectionKey;
use App\Http\Middleware\TelBezoek;
use App\Models\PageSection;
use App\Models\Project;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectionHeadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * De publieke pagina's: het overzicht en één project.
 *
 * **De twee grendels zijn hier het belangrijkst.** Een project dat offline
 * staat of waarvan het hele onderdeel is uitgezet, mag niet via het adres
 * alsnog te bekijken zijn -- dat zou een achterdeur zijn naar iets wat de
 * eigenaar bewust van zijn site heeft gehaald.
 *
 * Zie docs/architecture/modules/projecten.md.
 */
class ProjectDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
        $this->seed(SectionHeadingSeeder::class);
    }

    private function zetSectieUit(): void
    {
        PageSection::query()
            ->where('key', PageSectionKey::Projecten->value)
            ->update(['visible' => false]);
    }

    /* --- Het overzicht ------------------------------------------------------ */

    public function test_the_overview_shows_every_online_project(): void
    {
        Project::factory()->count(3)->create();
        Project::factory()->offline()->create();

        $this->get(route('projecten'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('public/Projecten')
                ->has('projects', 3)
                ->has('kop')
                ->has('navigation')
                ->etc());
    }

    /** In de volgorde uit het beheer, zonder grens. */
    public function test_the_overview_follows_the_order(): void
    {
        $tweede = Project::factory()->create(['position' => 2]);
        $eerste = Project::factory()->create(['position' => 1]);

        $props = $this->get(route('projecten'))->viewData('page')['props'];

        $this->assertSame(
            [$eerste->slug, $tweede->slug],
            array_column($props['projects'], 'slug'),
        );
    }

    public function test_the_overview_does_not_exist_without_projects(): void
    {
        $this->get(route('projecten'))->assertNotFound();
    }

    public function test_the_overview_does_not_exist_with_only_offline_projects(): void
    {
        Project::factory()->offline()->create();

        $this->get(route('projecten'))->assertNotFound();
    }

    public function test_the_overview_does_not_exist_when_the_section_is_off(): void
    {
        Project::factory()->create();
        $this->zetSectieUit();

        $this->get(route('projecten'))->assertNotFound();
    }

    /* --- Eén project --------------------------------------------------------- */

    public function test_a_project_has_its_own_page(): void
    {
        $project = Project::factory()->uitgebreid()->create();

        $this->get(route('project', $project->slug))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('public/ProjectDetail')
                ->where('project.slug', $project->slug)
                ->where('project.titel', $project->title_nl)
                ->has('project.duur')
                ->has('project.omschrijving')
                ->has('project.resultaat')
                ->etc());
    }

    public function test_an_offline_project_is_not_reachable(): void
    {
        $project = Project::factory()->offline()->create();

        $this->get(route('project', $project->slug))->assertNotFound();
    }

    public function test_a_project_is_not_reachable_when_the_section_is_off(): void
    {
        $project = Project::factory()->create();
        $this->zetSectieUit();

        $this->get(route('project', $project->slug))->assertNotFound();
    }

    public function test_an_unknown_address_is_not_found(): void
    {
        $this->get('/projecten/bestaat-niet')->assertNotFound();
    }

    /* --- De weg terug -------------------------------------------------------- */

    /**
     * De knop onderaan wijst terug naar waar de bezoeker vandaan kwam.
     *
     * Deze pagina is de enige op de site met twee bovenliggende plekken:
     * de voorpagina en `/projecten`. Eén vaste knop zou in de helft van
     * de gevallen naar een pagina wijzen waar de bezoeker nooit is
     * geweest.
     */
    public function test_coming_from_the_front_page_is_remembered(): void
    {
        $project = Project::factory()->create();

        $this->get(route('project', $project->slug).'?van=start')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('van', 'start')
                ->etc());
    }

    /** Zonder die aanwijzing is de lijst het juiste antwoord. */
    public function test_without_the_hint_it_falls_back_to_the_list(): void
    {
        $project = Project::factory()->create();

        $this->get(route('project', $project->slug))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('van', null)
                ->etc());
    }

    /**
     * Een verzonnen waarde komt niet op de knop terecht.
     *
     * Zonder die witte lijst zet iemand `?van=<wat dan ook>` in het adres
     * en staat dat in het scherm.
     */
    public function test_an_unknown_origin_is_ignored(): void
    {
        $project = Project::factory()->create();

        $this->get(route('project', $project->slug).'?van=ergensanders')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('van', null)
                ->etc());
    }

    /* --- Het bezoek wordt geteld --------------------------------------------- */

    /**
     * Allebei de pagina's tellen mee in de bezoekcijfers.
     *
     * Op de middleware en niet op een rij in de database: het tellen zelf
     * is al getest bij de bezoekcijfers, en wat hier mis kan gaan is dat
     * iemand een nieuwe publieke route toevoegt zonder `TelBezoek` --
     * precies zoals dat bij `/privacy` ooit is gebeurd.
     */
    public function test_both_pages_count_their_visits(): void
    {
        foreach (['projecten', 'project'] as $naam) {
            $route = Route::getRoutes()->getByName($naam);

            $this->assertNotNull($route, "De route {$naam} bestaat niet.");
            $this->assertContains(
                TelBezoek::class,
                $route->gatherMiddleware(),
                "De route {$naam} telt zijn bezoek niet mee.",
            );
        }
    }
}
