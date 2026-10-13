<?php

namespace Tests\Feature\Website;

use App\Enums\PageSectionKey;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectionHeadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wat er van de projecten op de website terechtkomt.
 *
 * Online en offline, de volgorde, de terugval per taal, en of het blok
 * verdwijnt én terugkomt. Die laatste twee horen bij elkaar: zonder de
 * spiegeltest slaagt "het is weg" ook als het er nooit is geweest.
 *
 * Zie docs/architecture/modules/projecten.md.
 */
class ProjectPublishingTest extends TestCase
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

    /**
     * @return array<string, mixed>
     */
    private function landing(): array
    {
        return $this->get(route('home'))->viewData('page')['props'];
    }

    /** Een bezoeker met een Engelse browser. */
    private function engels(): self
    {
        $this->withHeaders(['Accept-Language' => 'en-GB,en;q=0.9']);

        return $this;
    }

    /* --- Het onderdeel op de pagina ---------------------------------------- */

    public function test_the_section_is_gone_without_any_project(): void
    {
        $this->assertNotContains(
            PageSectionKey::Projecten->value,
            $this->landing()['sections'],
        );
    }

    /** En komt terug zodra er één online staat. */
    public function test_the_section_comes_back_with_one_project(): void
    {
        Project::factory()->create();

        $this->assertContains(
            PageSectionKey::Projecten->value,
            $this->landing()['sections'],
        );
    }

    public function test_an_offline_project_does_not_bring_the_section_back(): void
    {
        Project::factory()->offline()->create();

        $this->assertNotContains(
            PageSectionKey::Projecten->value,
            $this->landing()['sections'],
        );
    }

    /* --- Wat er op de voorpagina staat ------------------------------------- */

    public function test_the_front_page_shows_at_most_three_besides_the_featured(): void
    {
        Project::factory()->uitgelicht()->create(['position' => 1]);
        Project::factory()->count(6)->sequence(
            fn ($reeks) => ['position' => $reeks->index + 2],
        )->create();

        $props = $this->landing();

        $this->assertNotNull($props['featuredProjects']);
        $this->assertCount(Project::OP_DE_VOORPAGINA, $props['projects']);
        $this->assertSame(7, $props['projectsTotal']);
    }

    /** Het uitgelichte project staat niet nog eens tussen de andere. */
    public function test_the_featured_project_is_not_repeated(): void
    {
        $uitgelicht = Project::factory()->uitgelicht()->create(['position' => 1]);
        Project::factory()->create(['position' => 2]);

        $props = $this->landing();

        $this->assertSame($uitgelicht->slug, $props['featuredProjects'][0]['slug']);
        $this->assertNotContains(
            $uitgelicht->slug,
            array_column($props['projects'], 'slug'),
        );
    }

    /** Zonder uitgelicht project staan er gewoon kaarten. */
    public function test_without_a_featured_project_there_are_only_cards(): void
    {
        Project::factory()->count(2)->create();

        $props = $this->landing();

        $this->assertSame([], $props['featuredProjects']);
        $this->assertCount(2, $props['projects']);
    }

    /* --- De volgorde -------------------------------------------------------- */

    public function test_the_landing_follows_the_order_from_the_portal(): void
    {
        $tweede = Project::factory()->create(['position' => 2]);
        $eerste = Project::factory()->create(['position' => 1]);

        $this->assertSame(
            [$eerste->slug, $tweede->slug],
            array_column($this->landing()['projects'], 'slug'),
        );
    }

    public function test_the_order_can_be_changed(): void
    {
        $een = Project::factory()->create(['position' => 1]);
        $twee = Project::factory()->create(['position' => 2]);

        $this->actingAs($this->beheerder())->put(
            route('website.projecten.volgorde'),
            ['projecten' => [['id' => $twee->id], ['id' => $een->id]]],
        )->assertSessionHasNoErrors();

        $this->assertSame(1, $twee->refresh()->position);
        $this->assertSame(2, $een->refresh()->position);
    }

    public function test_an_incomplete_order_is_refused(): void
    {
        $een = Project::factory()->create(['position' => 1]);
        Project::factory()->create(['position' => 2]);

        $this->actingAs($this->beheerder())->put(
            route('website.projecten.volgorde'),
            ['projecten' => [['id' => $een->id]]],
        );

        $this->assertSame(1, $een->refresh()->position);
    }

    public function test_an_unknown_id_in_the_order_is_refused(): void
    {
        $een = Project::factory()->create(['position' => 1]);

        $this->actingAs($this->beheerder())->put(
            route('website.projecten.volgorde'),
            ['projecten' => [['id' => $een->id], ['id' => 99999]]],
        )->assertSessionHasErrors();

        $this->assertSame(1, $een->refresh()->position);
    }

    /* --- Online en offline --------------------------------------------------- */

    public function test_the_switch_takes_a_project_off_the_site(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->beheerder())->patch(
            route('website.projecten.online', $project),
            ['published' => false],
        )->assertSessionHasNoErrors();

        $this->assertFalse($project->refresh()->published);
        $this->assertCount(0, $this->landing()['projects'] ?? []);
    }

    public function test_the_switch_puts_it_back(): void
    {
        $project = Project::factory()->offline()->create();

        $this->actingAs($this->beheerder())->patch(
            route('website.projecten.online', $project),
            ['published' => true],
        )->assertSessionHasNoErrors();

        $this->assertTrue($project->refresh()->published);
        $this->assertCount(1, $this->landing()['projects']);
    }

    /* --- De talen ------------------------------------------------------------ */

    /** De titel en de rol vallen terug: korte namen, en een kaart zonder titel is stuk. */
    public function test_the_title_and_role_fall_back_to_dutch(): void
    {
        Project::factory()->create([
            'title_nl' => 'Migratie',
            'title_en' => null,
            'role_nl' => 'Projectleider',
            'role_en' => null,
        ]);

        $kaart = $this->engels()->landing()['projects'][0];

        $this->assertSame('Migratie', $kaart['titel']);
        $this->assertSame('Projectleider', $kaart['rol']);
    }

    /** De samenvatting valt níet terug: proza in de verkeerde taal leest als een fout. */
    public function test_the_summary_does_not_fall_back(): void
    {
        Project::factory()->create([
            'summary_nl' => 'Een Nederlandse zin.',
            'summary_en' => null,
        ]);

        $this->assertNull($this->engels()->landing()['projects'][0]['samenvatting']);
    }

    public function test_the_english_summary_is_used_when_it_is_there(): void
    {
        Project::factory()->create([
            'summary_nl' => 'Een Nederlandse zin.',
            'summary_en' => 'An English sentence.',
        ]);

        $this->assertSame(
            'An English sentence.',
            $this->engels()->landing()['projects'][0]['samenvatting'],
        );
    }

    /* --- Het indelingsscherm -------------------------------------------------- */

    public function test_the_layout_screen_flags_an_empty_section(): void
    {
        $rij = collect($this->actingAs($this->beheerder())
            ->get(route('website.index'))
            ->viewData('page')['props']['sections'])
            ->firstWhere('key', PageSectionKey::Projecten->value);

        $this->assertNotNull($rij);
        $this->assertSame(0, $rij['count']);
        $this->assertFalse($rij['filled']);
        $this->assertFalse($rij['live']);
    }

    public function test_the_layout_screen_counts_what_is_online(): void
    {
        Project::factory()->count(2)->create();
        Project::factory()->offline()->create();

        $rij = collect($this->actingAs($this->beheerder())
            ->get(route('website.index'))
            ->viewData('page')['props']['sections'])
            ->firstWhere('key', PageSectionKey::Projecten->value);

        $this->assertSame(2, $rij['count']);
        $this->assertTrue($rij['live']);
    }
}
