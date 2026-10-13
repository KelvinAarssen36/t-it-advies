<?php

namespace Tests\Feature\Website;

use App\Enums\PageSectionKey;
use App\Models\User;
use App\Models\WorkStep;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectionHeadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wat er van de werkwijze op de website terechtkomt.
 *
 * **Het belangrijkste hier is dat het blok kán verdwijnen.** Zolang de
 * stappen in het Vue-bestand stonden was dat onmogelijk: het onderdeel was
 * per definitie gevuld. Nu ze van de eigenaar zijn kan hij ze allemaal
 * offline zetten, en dan hoort het hele blok van zijn site -- een kopje met
 * niets eronder is slordiger dan geen kopje.
 *
 * Zie docs/architecture/modules/werkwijze.md.
 */
class WorkStepPublishingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
        $this->seed(SectionHeadingSeeder::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function landing(): array
    {
        return $this->get(route('home'))->viewData('page')['props'];
    }

    /**
     * @return array<int, string>
     */
    private function secties(): array
    {
        return $this->landing()['sections'];
    }

    /* --- Het blok verschijnt en verdwijnt ----------------------------------- */

    public function test_without_steps_the_section_is_gone(): void
    {
        $this->assertNotContains(PageSectionKey::Werkwijze->value, $this->secties());
    }

    public function test_with_one_step_the_section_is_there(): void
    {
        WorkStep::factory()->create();

        $this->assertContains(PageSectionKey::Werkwijze->value, $this->secties());
    }

    /** Alles offline telt als leeg: de bezoeker ziet dan niets. */
    public function test_only_offline_steps_counts_as_empty(): void
    {
        WorkStep::factory()->offline()->count(2)->create();

        $this->assertNotContains(PageSectionKey::Werkwijze->value, $this->secties());
    }

    /* --- Wat er meekomt ----------------------------------------------------- */

    public function test_only_online_steps_reach_the_site(): void
    {
        $online = WorkStep::factory()->create();
        WorkStep::factory()->offline()->create();

        $this->assertSame(
            [$online->title_nl],
            array_column($this->landing()['workSteps'], 'titel'),
        );
    }

    public function test_the_steps_follow_the_order(): void
    {
        $tweede = WorkStep::factory()->create(['position' => 2]);
        $eerste = WorkStep::factory()->create(['position' => 1]);

        $this->assertSame(
            [$eerste->title_nl, $tweede->title_nl],
            array_column($this->landing()['workSteps'], 'titel'),
        );
    }

    public function test_the_heading_comes_along(): void
    {
        WorkStep::factory()->create();

        $this->assertNotNull($this->landing()['workHeading']);
    }

    /* --- De knop naar de eigen pagina --------------------------------------- */

    /**
     * Zonder verhaal geen knop.
     *
     * Een pagina die precies dezelfde zinnen herhaalt als de kaarten is
     * een omweg; de server houdt die regel bij zodat de knop en de pagina
     * niet uit elkaar kunnen lopen.
     */
    public function test_without_a_story_there_is_no_page(): void
    {
        WorkStep::factory()->create();

        $this->assertFalse($this->landing()['workPage']);
    }

    public function test_one_story_is_enough_for_the_page(): void
    {
        WorkStep::factory()->create();
        WorkStep::factory()->uitgebreid()->create();

        $this->assertTrue($this->landing()['workPage']);
    }

    /** Een verhaal bij een offline stap telt niet mee. */
    public function test_a_story_on_an_offline_step_does_not_count(): void
    {
        WorkStep::factory()->create();
        WorkStep::factory()->uitgebreid()->offline()->create();

        $this->assertFalse($this->landing()['workPage']);
    }

    /* --- De terugval tussen de talen ---------------------------------------- */

    /** De titel valt terug op het Nederlands. */
    public function test_the_title_falls_back_to_dutch(): void
    {
        $stap = WorkStep::factory()->create(['title_en' => null]);

        $this->get(route('locale.switch', 'en'));

        $this->assertSame(
            $stap->title_nl,
            $this->landing()['workSteps'][0]['titel'],
        );
    }

    /**
     * De korte tekst niet.
     *
     * Een stap met een Engelse titel en géén zin eronder leest nog steeds
     * als een werkwijze -- het nummer en de titel dragen hem. Een losse
     * Nederlandse zin tussen Engelse tekst leest als een fout.
     */
    public function test_the_summary_does_not_fall_back(): void
    {
        WorkStep::factory()->create([
            'title_en' => 'Getting to know each other',
            'summary_en' => null,
        ]);

        $this->post(route('locale.switch', 'en'));

        $this->assertNull($this->landing()['workSteps'][0]['samenvatting']);
    }

    /** En de duur al helemaal niet: daar zit een Nederlands woord in. */
    public function test_the_duration_does_not_fall_back(): void
    {
        WorkStep::factory()->create([
            'duration_nl' => '1-2 weken',
            'duration_en' => null,
        ]);

        $this->post(route('locale.switch', 'en'));

        $this->assertNull($this->landing()['workSteps'][0]['duur']);
    }

    /* --- Het indelingsscherm ------------------------------------------------ */

    /**
     * Het indelingsscherm telt wat er online staat.
     *
     * Zou daar het totaal staan, dan meldt het scherm dat het onderdeel
     * gevuld is terwijl er op de website niets verschijnt -- en dan is die
     * melding erger dan geen melding.
     */
    public function test_the_layout_screen_counts_what_is_online(): void
    {
        WorkStep::factory()->count(2)->create();
        WorkStep::factory()->offline()->create();

        $gebruiker = User::factory()->create();
        $gebruiker->givePermissionTo('manage portal');

        $props = $this->actingAs($gebruiker)
            ->get(route('website.index'))
            ->viewData('page')['props'];

        $werkwijze = collect($props['sections'])
            ->firstWhere('key', PageSectionKey::Werkwijze->value);

        $this->assertSame(2, $werkwijze['count']);
        $this->assertTrue($werkwijze['live']);
    }
}
