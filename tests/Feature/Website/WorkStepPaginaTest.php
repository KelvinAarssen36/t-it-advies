<?php

namespace Tests\Feature\Website;

use App\Enums\PageSectionKey;
use App\Http\Middleware\TelBezoek;
use App\Models\PageSection;
use App\Models\WorkStep;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectionHeadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * De pagina /werkwijze.
 *
 * **Drie grendels, en de derde is wat deze pagina onderscheidt van
 * /projecten.** Daar is elk item op zichzelf de moeite; hier zijn de
 * kaarten op de voorpagina het hele verhaal zolang niemand er iets bij
 * heeft geschreven. Een pagina die precies dezelfde zinnen herhaalt is een
 * omweg, dus die bestaat dan niet.
 *
 * Zie docs/architecture/modules/werkwijze.md.
 */
class WorkStepPaginaTest extends TestCase
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
            ->where('key', PageSectionKey::Werkwijze->value)
            ->update(['visible' => false]);
    }

    /* --- Wanneer de pagina bestaat ------------------------------------------ */

    public function test_the_page_exists_with_a_story(): void
    {
        WorkStep::factory()->uitgebreid()->create();

        $this->get(route('werkwijze'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('public/Werkwijze')
                ->has('stappen', 1)
                ->has('kop')
                ->has('navigation')
                ->etc());
    }

    /** Alle stappen staan erop, ook die zonder verhaal. */
    public function test_every_online_step_is_on_the_page(): void
    {
        WorkStep::factory()->uitgebreid()->create(['position' => 1]);
        WorkStep::factory()->create(['position' => 2]);
        WorkStep::factory()->offline()->create(['position' => 3]);

        $props = $this->get(route('werkwijze'))->viewData('page')['props'];

        $this->assertCount(2, $props['stappen']);
    }

    /**
     * Een stap zonder verhaal weglaten zou de nummering breken.
     *
     * Dan klopt "stap 2" op deze pagina niet meer met "stap 2" op de
     * voorpagina, en dat is erger dan een stap met alleen zijn korte
     * tekst.
     */
    public function test_a_step_without_a_story_keeps_its_place(): void
    {
        $eerste = WorkStep::factory()->create(['position' => 1]);
        WorkStep::factory()->uitgebreid()->create(['position' => 2]);

        $props = $this->get(route('werkwijze'))->viewData('page')['props'];

        $this->assertSame($eerste->title_nl, $props['stappen'][0]['titel']);
        $this->assertNull($props['stappen'][0]['verhaal']);
    }

    /* --- Wanneer hij niet bestaat ------------------------------------------- */

    public function test_without_steps_there_is_no_page(): void
    {
        $this->get(route('werkwijze'))->assertNotFound();
    }

    public function test_without_a_story_there_is_no_page(): void
    {
        WorkStep::factory()->count(3)->create();

        $this->get(route('werkwijze'))->assertNotFound();
    }

    public function test_a_story_on_an_offline_step_is_not_enough(): void
    {
        WorkStep::factory()->create();
        WorkStep::factory()->uitgebreid()->offline()->create();

        $this->get(route('werkwijze'))->assertNotFound();
    }

    public function test_the_page_is_gone_when_the_section_is_off(): void
    {
        WorkStep::factory()->uitgebreid()->create();
        $this->zetSectieUit();

        $this->get(route('werkwijze'))->assertNotFound();
    }

    /* --- De taal ------------------------------------------------------------ */

    /**
     * Staat het verhaal alleen in het Nederlands, dan bestaat de pagina
     * niet voor een Engelse bezoeker.
     *
     * Hij zou anders een pagina krijgen met alleen titels erop -- precies
     * de lege huls die deze grendel moet voorkomen.
     */
    public function test_a_dutch_only_story_does_not_make_an_english_page(): void
    {
        WorkStep::factory()->uitgebreid()->create();

        $this->post(route('locale.switch', 'en'));

        $this->get(route('werkwijze'))->assertNotFound();
    }

    public function test_an_english_story_does(): void
    {
        WorkStep::factory()->uitgebreid()->tweetalig()->create();

        $this->post(route('locale.switch', 'en'));

        $this->get(route('werkwijze'))->assertOk();
    }

    /* --- Het bezoek wordt geteld -------------------------------------------- */

    /**
     * De pagina telt mee in de bezoekcijfers.
     *
     * Op de middleware en niet op een rij in de database: het tellen zelf
     * is al getest bij de bezoekcijfers, en wat hier mis kan gaan is dat
     * iemand een nieuwe publieke route toevoegt zonder `TelBezoek` --
     * precies zoals dat bij `/privacy` ooit is gebeurd.
     */
    public function test_the_page_counts_its_visits(): void
    {
        $route = Route::getRoutes()->getByName('werkwijze');

        $this->assertNotNull($route);
        $this->assertContains(TelBezoek::class, $route->gatherMiddleware());
    }
}
