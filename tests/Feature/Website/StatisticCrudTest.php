<?php

namespace Tests\Feature\Website;

use App\Enums\ActivityAction;
use App\Models\ActivityEntry;
use App\Models\Statistic;
use App\Models\User;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * De statistieken: aanmaken, wijzigen, verwijderen.
 *
 * De lijst met wat een CRUD moet afdekken staat in
 * docs/development/testen.md; dit bestand loopt hem af. De weergaven en
 * de groepen hebben hun eigen bestanden, want daar zit genoeg eigen
 * gedrag in om het hier onleesbaar te maken.
 *
 * Zie docs/architecture/modules/statistieken.md.
 */
class StatisticCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
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
        return array_merge([
            'display' => 'balk',
            'published' => true,
            'label_nl' => 'Microsoft 365',
            'label_en' => 'Microsoft 365',
            'value' => 90,
            'prefix' => '',
            'suffix' => '',
            'note_nl' => 'Van Entra tot Intune.',
            'note_en' => 'From Entra to Intune.',
            'group_nl' => 'Cloud',
            'group_en' => 'Cloud',
        ], $anders);
    }

    /**
     * @param  array<string, mixed>  $anders
     */
    private function maak(array $anders = []): TestResponse
    {
        return $this->actingAs($this->beheerder())
            ->post(route('website.statistieken.store'), $this->invoer($anders));
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $statistiek = Statistic::factory()->create();

        $this->get(route('website.statistieken.index'))->assertRedirect(route('login'));
        $this->post(route('website.statistieken.store'))->assertRedirect(route('login'));
        $this->put(route('website.statistieken.update', $statistiek))->assertRedirect(route('login'));
        $this->put(route('website.statistieken.kop'))->assertRedirect(route('login'));
        $this->put(route('website.statistieken.volgorde'))->assertRedirect(route('login'));
        $this->patch(route('website.statistieken.online', $statistiek))->assertRedirect(route('login'));
        $this->delete(route('website.statistieken.destroy', $statistiek))->assertRedirect(route('login'));

        // En er is niets gebeurd.
        $this->assertSame(1, Statistic::query()->count());
    }

    public function test_every_route_needs_the_permission(): void
    {
        $statistiek = Statistic::factory()->create();
        $gebruiker = User::factory()->create();

        $this->actingAs($gebruiker)->get(route('website.statistieken.index'))->assertForbidden();
        $this->actingAs($gebruiker)->post(route('website.statistieken.store'))->assertForbidden();
        $this->actingAs($gebruiker)->put(route('website.statistieken.update', $statistiek))->assertForbidden();
        $this->actingAs($gebruiker)->put(route('website.statistieken.kop'))->assertForbidden();
        $this->actingAs($gebruiker)->put(route('website.statistieken.volgorde'))->assertForbidden();
        $this->actingAs($gebruiker)->patch(route('website.statistieken.online', $statistiek))->assertForbidden();
        $this->actingAs($gebruiker)->delete(route('website.statistieken.destroy', $statistiek))->assertForbidden();

        $this->assertSame(1, Statistic::query()->count());
    }

    public function test_the_screen_lists_the_statistics(): void
    {
        Statistic::factory()->count(3)->create();

        $this->actingAs($this->beheerder())
            ->get(route('website.statistieken.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('website/Statistieken')
                ->has('items', 3)
                ->has('opties.weergave', 3)
                ->where('leeg', false)
                ->where('kanVertalen', false)
                ->etc());
    }

    /**
     * De groepen die er al zijn gaan mee naar het scherm.
     *
     * Zonder die suggesties belanden "netwerk" en "Netwerk" naast
     * elkaar als twee groepen, en dan valt het blok uit elkaar zonder
     * dat iemand ziet waarom.
     */
    public function test_the_screen_suggests_the_groups_that_already_exist(): void
    {
        Statistic::factory()->inGroep('Netwerk')->create();
        Statistic::factory()->inGroep('Netwerk')->create();
        Statistic::factory()->inGroep('Cloud')->create();
        Statistic::factory()->create(['group_nl' => null]);

        $this->actingAs($this->beheerder())
            ->get(route('website.statistieken.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                // Elk één keer, op alfabet, en zonder de lege.
                ->where('opties.groepen', ['Cloud', 'Netwerk'])
                ->etc());
    }

    public function test_creating_stores_both_languages(): void
    {
        $this->maak()->assertSessionHasNoErrors();

        $statistiek = Statistic::query()->firstOrFail();

        $this->assertSame('Microsoft 365', $statistiek->label_nl);
        $this->assertSame('Van Entra tot Intune.', $statistiek->note_nl);
        $this->assertSame('From Entra to Intune.', $statistiek->note_en);
        $this->assertSame('Cloud', $statistiek->group_nl);
        $this->assertSame(90, $statistiek->value);
    }

    public function test_a_new_statistic_goes_to_the_end_of_the_row(): void
    {
        Statistic::factory()->create(['position' => 7]);

        $this->maak();

        $this->assertSame(
            8,
            (int) Statistic::query()->latest('id')->value('position'),
        );
    }

    public function test_an_empty_optional_field_becomes_null_and_not_an_empty_string(): void
    {
        $this->maak([
            'label_en' => '',
            'prefix' => '',
            'suffix' => '',
            'note_nl' => '',
            'note_en' => '',
            'group_nl' => '',
            'group_en' => '',
        ])->assertSessionHasNoErrors();

        $statistiek = Statistic::query()->firstOrFail();

        $this->assertNull($statistiek->label_en);
        $this->assertNull($statistiek->prefix);
        $this->assertNull($statistiek->suffix);
        $this->assertNull($statistiek->note_nl);
        $this->assertNull($statistiek->note_en);
        $this->assertNull($statistiek->group_nl);
        $this->assertNull($statistiek->group_en);
    }

    public function test_the_name_the_display_and_the_value_are_required(): void
    {
        $this->maak(['label_nl' => '', 'display' => '', 'value' => ''])
            ->assertSessionHasErrors(['label_nl', 'display', 'value']);

        // En er is niets in de database beland.
        $this->assertSame(0, Statistic::query()->count());
    }

    public function test_text_that_is_too_long_is_refused(): void
    {
        $this->maak([
            'label_nl' => str_repeat('a', 61),
            'note_nl' => str_repeat('b', 121),
            'group_nl' => str_repeat('c', 61),
            'suffix' => str_repeat('d', 9),
        ])->assertSessionHasErrors([
            'label_nl',
            'note_nl',
            'group_nl',
            'suffix',
        ]);

        $this->assertSame(0, Statistic::query()->count());
    }

    public function test_a_negative_value_is_refused(): void
    {
        $this->maak(['value' => -5])->assertSessionHasErrors('value');

        $this->assertSame(0, Statistic::query()->count());
    }

    public function test_changing_one_statistic_leaves_the_others_alone(): void
    {
        $statistiek = Statistic::factory()->create(['label_nl' => 'Eerste']);
        $ander = Statistic::factory()->create(['label_nl' => 'Tweede']);

        $this->actingAs($this->beheerder())->put(
            route('website.statistieken.update', $statistiek),
            $this->invoer(['label_nl' => 'Aangepast']),
        )->assertSessionHasNoErrors();

        $this->assertSame('Aangepast', $statistiek->fresh()?->label_nl);
        $this->assertSame('Tweede', $ander->fresh()?->label_nl);
    }

    public function test_saving_the_same_thing_says_nothing_changed(): void
    {
        $statistiek = Statistic::factory()->create($this->alsModel());

        $this->travel(1)->minute();

        $this->actingAs($this->beheerder())
            ->put(route('website.statistieken.update', $statistiek), $this->invoer())
            ->assertSessionHasNoErrors();

        $this->assertEquals(
            $statistiek->updated_at,
            $statistiek->fresh()?->updated_at,
        );
    }

    public function test_deleting_leaves_the_rest_standing(): void
    {
        $statistiek = Statistic::factory()->create();
        Statistic::factory()->create();

        $this->actingAs($this->beheerder())
            ->delete(route('website.statistieken.destroy', $statistiek));

        $this->assertSame(1, Statistic::query()->count());
        $this->assertNull($statistiek->fresh());
    }

    public function test_every_change_is_written_to_the_activity_log(): void
    {
        $this->maak();

        $statistiek = Statistic::query()->firstOrFail();

        $this->actingAs($this->beheerder())->put(
            route('website.statistieken.update', $statistiek),
            $this->invoer(['label_nl' => 'Anders']),
        );

        $this->actingAs($this->beheerder())
            ->delete(route('website.statistieken.destroy', $statistiek));

        $regels = ActivityEntry::query()
            ->where('subject_type', Statistic::class)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $regels);
        $this->assertSame(ActivityAction::Created, $regels[0]->action);
        $this->assertSame(ActivityAction::Updated, $regels[1]->action);
        $this->assertSame(ActivityAction::Deleted, $regels[2]->action);
        $this->assertSame('Microsoft 365', $regels[0]->subject_label);
    }

    /**
     * Dezelfde gegevens als `invoer()`, maar als modelvelden.
     *
     * @return array<string, mixed>
     */
    private function alsModel(): array
    {
        return [
            'display' => 'balk',
            'published' => true,
            'label_nl' => 'Microsoft 365',
            'label_en' => 'Microsoft 365',
            'value' => 90,
            'prefix' => null,
            'suffix' => null,
            'note_nl' => 'Van Entra tot Intune.',
            'note_en' => 'From Entra to Intune.',
            'group_nl' => 'Cloud',
            'group_en' => 'Cloud',
        ];
    }
}
