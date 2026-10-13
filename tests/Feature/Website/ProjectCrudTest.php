<?php

namespace Tests\Feature\Website;

use App\Enums\ProjectType;
use App\Http\Requests\Website\ProjectRequest;
use App\Models\ActivityEntry;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectionHeadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * De projecten: aanmaken, wijzigen, verwijderen.
 *
 * Volgt de tabel uit docs/development/testen.md. De gevallen die deze
 * module eigen zijn: het soort met zijn uitweg "anders", de periode met
 * "loopt nog", en de slug die bij het aanmaken wordt gemaakt en daarna
 * blijft staan.
 *
 * Zie docs/architecture/modules/projecten.md.
 */
class ProjectCrudTest extends TestCase
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
     * @param  array<string, mixed>  $anders
     * @return array<string, mixed>
     */
    private function invoer(array $anders = []): array
    {
        return [
            'type' => ProjectType::Migratie->value,
            'title_nl' => 'Migratie naar Exchange Online',
            'title_en' => 'Migration to Exchange Online',
            'organisation' => 'Zorgkoepel Midden',
            'role_nl' => 'Technisch projectleider',
            'role_en' => 'Technical project lead',
            'started_on' => '2023-03',
            'ended_on' => '2023-11',
            'summary_nl' => 'Driehonderd postbussen over, zonder dat iemand een mail miste.',
            'summary_en' => 'Three hundred mailboxes moved without anyone missing an email.',
            'body_nl' => 'De oude omgeving stond op twee servers die niemand meer durfde aan te raken.',
            'result_nl' => 'Sindsdien draait het beheer bij de klant zelf.',
            'published' => true,
            ...$anders,
        ];
    }

    /**
     * @param  array<string, mixed>  $anders
     */
    private function maak(array $anders = []): TestResponse
    {
        return $this->actingAs($this->beheerder())
            ->post(route('website.projecten.store'), $this->invoer($anders));
    }

    /* --- Rechten ----------------------------------------------------------- */

    public function test_a_guest_is_sent_to_the_login_screen(): void
    {
        $project = Project::factory()->create();

        $this->get(route('website.projecten.index'))->assertRedirect(route('login'));
        $this->post(route('website.projecten.store'))->assertRedirect(route('login'));
        $this->put(route('website.projecten.kop'))->assertRedirect(route('login'));
        $this->put(route('website.projecten.volgorde'))->assertRedirect(route('login'));
        $this->put(route('website.projecten.update', $project))->assertRedirect(route('login'));
        $this->patch(route('website.projecten.online', $project))->assertRedirect(route('login'));
        $this->patch(route('website.projecten.uitlichten', $project))->assertRedirect(route('login'));
        $this->delete(route('website.projecten.destroy', $project))->assertRedirect(route('login'));

        $this->assertSame(1, Project::query()->count());
    }

    public function test_a_user_without_the_permission_is_refused(): void
    {
        $project = Project::factory()->create();
        $gebruiker = User::factory()->create();

        $this->actingAs($gebruiker)->get(route('website.projecten.index'))->assertForbidden();
        $this->actingAs($gebruiker)->post(route('website.projecten.store'))->assertForbidden();
        $this->actingAs($gebruiker)->put(route('website.projecten.kop'))->assertForbidden();
        $this->actingAs($gebruiker)->put(route('website.projecten.volgorde'))->assertForbidden();
        $this->actingAs($gebruiker)->put(route('website.projecten.update', $project))->assertForbidden();
        $this->actingAs($gebruiker)->patch(route('website.projecten.online', $project))->assertForbidden();
        $this->actingAs($gebruiker)->patch(route('website.projecten.uitlichten', $project))->assertForbidden();
        $this->actingAs($gebruiker)->delete(route('website.projecten.destroy', $project))->assertForbidden();

        $this->assertSame(1, Project::query()->count());
    }

    /* --- Aanmaken ---------------------------------------------------------- */

    public function test_a_project_can_be_created(): void
    {
        $this->maak()->assertSessionHasNoErrors();

        $project = Project::query()->sole();

        $this->assertSame('Migratie naar Exchange Online', $project->title_nl);
        $this->assertSame('Zorgkoepel Midden', $project->organisation);
        $this->assertSame(ProjectType::Migratie, $project->type);
        $this->assertSame('2023-03', $project->started_on->format('Y-m'));
        $this->assertSame('2023-11', $project->ended_on?->format('Y-m'));
        $this->assertTrue($project->published);
        $this->assertFalse($project->featured);
    }

    /** Achteraan in de rij; waar het komt te staan bepaalt de eigenaar. */
    public function test_a_new_project_lands_at_the_end(): void
    {
        Project::factory()->create(['position' => 7]);

        $this->maak();

        $this->assertSame(8, Project::query()->latest('id')->first()?->position);
    }

    /* --- De slug ----------------------------------------------------------- */

    public function test_the_slug_comes_from_the_dutch_title(): void
    {
        $this->maak();

        $this->assertSame(
            'migratie-naar-exchange-online',
            Project::query()->sole()->slug,
        );
    }

    /** Twee projecten met dezelfde titel krijgen niet hetzelfde adres. */
    public function test_a_second_project_with_the_same_title_gets_its_own_slug(): void
    {
        $this->maak();
        $this->maak();

        $slugs = Project::query()->orderBy('id')->pluck('slug')->all();

        $this->assertSame(
            ['migratie-naar-exchange-online', 'migratie-naar-exchange-online-2'],
            $slugs,
        );
    }

    /**
     * Een titel zonder bruikbare letters levert toch een adres op.
     *
     * Zonder terugval zou de slug leeg zijn en klaagt de unieke index over
     * iets wat de eigenaar niet kan zien.
     */
    public function test_a_title_without_letters_still_gets_an_address(): void
    {
        $this->maak(['title_nl' => '???']);

        $this->assertSame('project', Project::query()->sole()->slug);
    }

    /**
     * En het adres blijft staan als de titel verandert.
     *
     * Een link die iemand heeft gedeeld hoort te blijven werken; dat weegt
     * zwaarder dan een adres dat de huidige titel spiegelt.
     */
    public function test_the_slug_stays_when_the_title_changes(): void
    {
        $this->maak();

        $project = Project::query()->sole();

        $this->actingAs($this->beheerder())->put(
            route('website.projecten.update', $project),
            $this->invoer(['title_nl' => 'Een heel andere titel']),
        )->assertSessionHasNoErrors();

        $project->refresh();

        $this->assertSame('Een heel andere titel', $project->title_nl);
        $this->assertSame('migratie-naar-exchange-online', $project->slug);
    }

    /* --- Het soort --------------------------------------------------------- */

    public function test_an_own_label_is_required_for_the_other_type(): void
    {
        $this->maak(['type' => ProjectType::Anders->value])
            ->assertSessionHasErrors('type_label_nl');

        $this->assertSame(0, Project::query()->count());
    }

    public function test_an_own_label_is_saved_in_both_languages(): void
    {
        $this->maak([
            'type' => ProjectType::Anders->value,
            'type_label_nl' => 'Haalbaarheidsonderzoek',
            'type_label_en' => 'Feasibility study',
        ])->assertSessionHasNoErrors();

        $project = Project::query()->sole();

        $this->assertSame('Haalbaarheidsonderzoek', $project->type_label_nl);
        $this->assertSame('Feasibility study', $project->type_label_en);
        $this->assertSame('Haalbaarheidsonderzoek', $project->typeLabel());
    }

    /**
     * Terug naar een gewoon soort laat geen weesveld achter.
     *
     * Zou het oude eigen label blijven staan, dan komt het terug zodra
     * iemand ooit weer "Anders" kiest -- met een woord van een project van
     * twee jaar geleden erin.
     */
    public function test_switching_back_clears_the_own_label(): void
    {
        $project = Project::factory()->eigenType()->create();

        $this->actingAs($this->beheerder())->put(
            route('website.projecten.update', $project),
            $this->invoer(['type' => ProjectType::Audit->value]),
        )->assertSessionHasNoErrors();

        $project->refresh();

        $this->assertSame(ProjectType::Audit, $project->type);
        $this->assertNull($project->type_label_nl);
        $this->assertNull($project->type_label_en);
    }

    /* --- De periode -------------------------------------------------------- */

    public function test_a_project_can_still_be_running(): void
    {
        $this->maak(['ended_on' => ''])->assertSessionHasNoErrors();

        $project = Project::query()->sole();

        $this->assertNull($project->ended_on);
        $this->assertTrue($project->loopt());
    }

    public function test_an_end_before_the_start_is_refused(): void
    {
        $this->maak(['started_on' => '2023-06', 'ended_on' => '2023-01'])
            ->assertSessionHasErrors('ended_on');

        $this->assertSame(0, Project::query()->count());
    }

    public function test_a_period_without_a_start_is_refused(): void
    {
        $this->maak(['started_on' => ''])->assertSessionHasErrors('started_on');

        $this->assertSame(0, Project::query()->count());
    }

    /** De dag betekent niets en staat altijd op 1. */
    public function test_the_day_is_always_the_first(): void
    {
        $this->maak();

        $this->assertSame('2023-03-01', Project::query()->sole()->started_on->toDateString());
    }

    /* --- Validatie --------------------------------------------------------- */

    public function test_the_required_fields_are_checked(): void
    {
        $this->actingAs($this->beheerder())
            ->post(route('website.projecten.store'), [])
            ->assertSessionHasErrors([
                'type',
                'title_nl',
                'organisation',
                'role_nl',
                'started_on',
                'summary_nl',
            ]);

        $this->assertSame(0, Project::query()->count());
    }

    public function test_a_summary_that_is_too_long_is_refused(): void
    {
        $this->maak([
            'summary_nl' => str_repeat('a', ProjectRequest::SAMENVATTING_MAX + 1),
        ])->assertSessionHasErrors('summary_nl');

        $this->assertSame(0, Project::query()->count());
    }

    public function test_a_body_that_is_too_long_is_refused(): void
    {
        $this->maak([
            'body_nl' => str_repeat('a', ProjectRequest::TEKST_MAX + 1),
        ])->assertSessionHasErrors('body_nl');

        $this->assertSame(0, Project::query()->count());
    }

    /* --- De twee talen ----------------------------------------------------- */

    public function test_both_languages_are_stored(): void
    {
        $this->maak();

        $project = Project::query()->sole();

        $this->assertSame('Migration to Exchange Online', $project->title_en);
        $this->assertSame('Technical project lead', $project->role_en);
    }

    /**
     * Een leeg Engels veld wordt null en geen lege string.
     *
     * De website beslist op `filled()` of een veld wordt getoond, en een
     * lege string is gevuld -- dan staat er een kopje boven niets.
     */
    public function test_an_empty_english_field_becomes_null(): void
    {
        $this->maak([
            'title_en' => '   ',
            'summary_en' => '',
            'body_en' => ' ',
        ]);

        $project = Project::query()->sole();

        $this->assertNull($project->title_en);
        $this->assertNull($project->summary_en);
        $this->assertNull($project->body_en);
    }

    /* --- Wijzigen en verwijderen ------------------------------------------- */

    public function test_a_project_can_be_changed(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->beheerder())->put(
            route('website.projecten.update', $project),
            $this->invoer(['title_nl' => 'Aangepast']),
        )->assertSessionHasNoErrors();

        $this->assertSame('Aangepast', $project->refresh()->title_nl);
    }

    public function test_a_project_can_be_removed(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->beheerder())
            ->delete(route('website.projecten.destroy', $project))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Project::query()->count());
    }

    /* --- Het activiteitenlogboek ------------------------------------------- */

    public function test_the_activity_log_records_the_changes(): void
    {
        $this->maak();

        $project = Project::query()->sole();

        $this->actingAs($this->beheerder())->put(
            route('website.projecten.update', $project),
            $this->invoer(['title_nl' => 'Aangepast']),
        );

        $this->actingAs($this->beheerder())
            ->delete(route('website.projecten.destroy', $project));

        $this->assertSame(
            3,
            ActivityEntry::query()->where('subject_type', Project::class)->count(),
        );
    }

    /** Een opslag zonder wijziging levert geen regel op. */
    public function test_saving_without_a_change_logs_nothing(): void
    {
        $this->maak();

        $project = Project::query()->sole();

        ActivityEntry::query()->delete();

        $this->actingAs($this->beheerder())->put(
            route('website.projecten.update', $project),
            $this->invoer(),
        );

        $this->assertSame(
            0,
            ActivityEntry::query()->where('subject_type', Project::class)->count(),
        );
    }
}
