<?php

namespace Tests\Feature\Website;

use App\Models\ActivityEntry;
use App\Models\User;
use App\Models\WorkStep;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectionHeadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Het beheer van de stappen in de werkwijze.
 *
 * De dertien vaste gevallen uit docs/development/testen.md, plus wat deze
 * module eigen is: **er is geen veld voor het nummer.** Dat volgt uit de
 * volgorde, en dat wordt hier bewaakt -- een nieuwe stap hoort achteraan te
 * komen en niet ergens tussen.
 *
 * Zie docs/architecture/modules/werkwijze.md.
 */
class WorkStepCrudTest extends TestCase
{
    use RefreshDatabase;

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

    /**
     * @param  array<string, mixed>  $anders
     * @return array<string, mixed>
     */
    private function invoer(array $anders = []): array
    {
        return [
            'title_nl' => 'Kennismaken',
            'summary_nl' => 'Wat speelt er, wat is er al, en waar loopt het vast.',
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
            ->post(route('website.werkwijze.store'), $this->invoer($anders));
    }

    /* --- Rechten ------------------------------------------------------------ */

    public function test_a_visitor_cannot_reach_the_screen(): void
    {
        $this->get(route('website.werkwijze.index'))
            ->assertRedirect(route('login'));
    }

    public function test_a_user_without_the_permission_cannot_reach_it(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('website.werkwijze.index'))
            ->assertForbidden();
    }

    public function test_a_visitor_cannot_create_one(): void
    {
        $this->post(route('website.werkwijze.store'), $this->invoer())
            ->assertRedirect(route('login'));

        $this->assertSame(0, WorkStep::query()->count());
    }

    public function test_a_user_without_the_permission_cannot_create_one(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('website.werkwijze.store'), $this->invoer())
            ->assertForbidden();

        $this->assertSame(0, WorkStep::query()->count());
    }

    /* --- Aanmaken ----------------------------------------------------------- */

    public function test_a_step_can_be_created(): void
    {
        $this->maak()->assertSessionHasNoErrors();

        $stap = WorkStep::query()->sole();

        $this->assertSame('Kennismaken', $stap->title_nl);
        $this->assertTrue($stap->published);
    }

    /**
     * Een nieuwe stap komt achteraan, niet ertussen.
     *
     * Dat is hier meer dan een voorkeur: de positie bepaalt het nummer op
     * de kaart. Zou een nieuwe stap ergens tussen landen, dan hernummert
     * hij stilletjes de rest van de werkwijze.
     */
    public function test_a_new_step_goes_to_the_end(): void
    {
        WorkStep::factory()->create(['position' => 1]);
        WorkStep::factory()->create(['position' => 2]);

        $this->maak();

        $nieuw = WorkStep::query()->where('title_nl', 'Kennismaken')->sole();

        $this->assertSame(3, $nieuw->position);
    }

    public function test_the_optional_fields_can_be_filled(): void
    {
        $this->maak([
            'duration_nl' => '1-2 weken',
            'result_nl' => 'Een plan met een prijs.',
            'body_nl' => 'Het hele verhaal.',
        ])->assertSessionHasNoErrors();

        $stap = WorkStep::query()->sole();

        $this->assertSame('1-2 weken', $stap->duration_nl);
        $this->assertSame('Een plan met een prijs.', $stap->result_nl);
        $this->assertSame('Het hele verhaal.', $stap->body_nl);
    }

    /** En leeg gelaten worden ze `null` en geen lege string. */
    public function test_an_empty_optional_field_becomes_null(): void
    {
        $this->maak([
            'duration_nl' => '',
            'result_nl' => '   ',
            'body_nl' => '',
        ])->assertSessionHasNoErrors();

        $stap = WorkStep::query()->sole();

        $this->assertNull($stap->duration_nl);
        $this->assertNull($stap->result_nl);
        $this->assertNull($stap->body_nl);
    }

    /* --- Het maximum -------------------------------------------------------- */

    /**
     * Voorbij het maximum komt er geen stap meer bij.
     *
     * **De grens is inhoudelijk en niet technisch.** Een werkwijze is een
     * verhaal dat een bezoeker moet kunnen onthouden; voorbij een stuk of
     * zes lukt dat niet meer. Zie `WorkStep::MAXIMUM`.
     *
     * Getoetst op de server en niet op de knop: het scherm schakelt die
     * uit, maar een uitgeschakelde knop is geen grendel.
     */
    public function test_beyond_the_maximum_nothing_is_added(): void
    {
        WorkStep::factory()->count(WorkStep::MAXIMUM)->create();

        $this->maak()->assertSessionHasNoErrors();

        $this->assertSame(WorkStep::MAXIMUM, WorkStep::query()->count());
    }

    /** Eén onder het maximum kan er nog wel een bij. */
    public function test_one_below_the_maximum_still_works(): void
    {
        WorkStep::factory()->count(WorkStep::MAXIMUM - 1)->create();

        $this->maak();

        $this->assertSame(WorkStep::MAXIMUM, WorkStep::query()->count());
    }

    /** En het scherm weet waar de grens ligt, zodat het de knop kan uitzetten. */
    public function test_the_screen_knows_the_maximum(): void
    {
        $props = $this->actingAs($this->beheerder())
            ->get(route('website.werkwijze.index'))
            ->viewData('page')['props'];

        $this->assertSame(WorkStep::MAXIMUM, $props['opties']['maximum']);
    }

    /* --- Wat er verplicht is ------------------------------------------------ */

    public function test_a_title_is_required(): void
    {
        $this->maak(['title_nl' => ''])->assertSessionHasErrors('title_nl');

        $this->assertSame(0, WorkStep::query()->count());
    }

    /**
     * En de korte tekst ook.
     *
     * Een stap met alleen een titel zegt niets over wat er gebeurt, en dan
     * is de hele werkwijze een rijtje woorden.
     */
    public function test_a_summary_is_required(): void
    {
        $this->maak(['summary_nl' => ''])->assertSessionHasErrors('summary_nl');

        $this->assertSame(0, WorkStep::query()->count());
    }

    public function test_a_title_that_is_too_long_is_refused(): void
    {
        $this->maak(['title_nl' => str_repeat('a', 81)])
            ->assertSessionHasErrors('title_nl');
    }

    public function test_a_duration_that_is_too_long_is_refused(): void
    {
        $this->maak(['duration_nl' => str_repeat('a', 41)])
            ->assertSessionHasErrors('duration_nl');
    }

    public function test_a_summary_that_is_too_long_is_refused(): void
    {
        $this->maak(['summary_nl' => str_repeat('a', 301)])
            ->assertSessionHasErrors('summary_nl');
    }

    /* --- Wijzigen ----------------------------------------------------------- */

    public function test_a_step_can_be_changed(): void
    {
        $stap = WorkStep::factory()->create();

        $this->actingAs($this->beheerder())->put(
            route('website.werkwijze.update', $stap),
            $this->invoer(['title_nl' => 'Voorstel']),
        )->assertSessionHasNoErrors();

        $this->assertSame('Voorstel', $stap->refresh()->title_nl);
    }

    /** Wijzigen raakt de plek in de rij niet, en dus ook het nummer niet. */
    public function test_changing_does_not_touch_the_position(): void
    {
        $stap = WorkStep::factory()->create(['position' => 2]);

        $this->actingAs($this->beheerder())->put(
            route('website.werkwijze.update', $stap),
            $this->invoer(['title_nl' => 'Iets anders']),
        );

        $this->assertSame(2, $stap->refresh()->position);
    }

    public function test_a_visitor_cannot_change_one(): void
    {
        $stap = WorkStep::factory()->create();
        $titel = $stap->title_nl;

        $this->put(
            route('website.werkwijze.update', $stap),
            $this->invoer(['title_nl' => 'Gekaapt']),
        )->assertRedirect(route('login'));

        $this->assertSame($titel, $stap->refresh()->title_nl);
    }

    /* --- Verwijderen -------------------------------------------------------- */

    public function test_a_step_can_be_deleted(): void
    {
        $stap = WorkStep::factory()->create();

        $this->actingAs($this->beheerder())
            ->delete(route('website.werkwijze.destroy', $stap))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, WorkStep::query()->count());
    }

    public function test_a_visitor_cannot_delete_one(): void
    {
        $stap = WorkStep::factory()->create();

        $this->delete(route('website.werkwijze.destroy', $stap))
            ->assertRedirect(route('login'));

        $this->assertSame(1, WorkStep::query()->count());
    }

    /* --- De volgorde -------------------------------------------------------- */

    public function test_the_order_can_be_changed(): void
    {
        $een = WorkStep::factory()->create(['position' => 1]);
        $twee = WorkStep::factory()->create(['position' => 2]);

        $this->actingAs($this->beheerder())->put(
            route('website.werkwijze.volgorde'),
            ['stappen' => [['id' => $twee->id], ['id' => $een->id]]],
        )->assertSessionHasNoErrors();

        $this->assertSame(1, $twee->refresh()->position);
        $this->assertSame(2, $een->refresh()->position);
    }

    /**
     * Een halve lijst wordt geweigerd.
     *
     * Zou een verzoek met één van de twee ids binnenkomen, dan krijgt die
     * ene positie 1 en botst hij met de ander.
     */
    public function test_a_partial_order_is_refused(): void
    {
        $een = WorkStep::factory()->create(['position' => 1]);
        WorkStep::factory()->create(['position' => 2]);

        $this->actingAs($this->beheerder())->put(
            route('website.werkwijze.volgorde'),
            ['stappen' => [['id' => $een->id]]],
        );

        $this->assertSame(1, $een->refresh()->position);
    }

    /* --- Beide talen -------------------------------------------------------- */

    public function test_the_english_fields_are_stored(): void
    {
        $this->maak([
            'title_en' => 'Getting to know each other',
            'summary_en' => 'What is going on.',
            'duration_en' => 'One conversation',
        ])->assertSessionHasNoErrors();

        $stap = WorkStep::query()->sole();

        $this->assertSame('Getting to know each other', $stap->title_en);
        $this->assertSame('One conversation', $stap->duration_en);
    }

    /* --- Het activiteitenlogboek -------------------------------------------- */

    public function test_the_activity_log_records_the_changes(): void
    {
        $voor = ActivityEntry::query()
            ->where('subject_type', WorkStep::class)
            ->count();

        $this->maak();

        $this->assertGreaterThan(
            $voor,
            ActivityEntry::query()
                ->where('subject_type', WorkStep::class)
                ->count(),
        );
    }
}
