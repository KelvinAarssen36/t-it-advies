<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Het scherm met de bezoekcijfers.
 *
 * Over het scherm en de perioden; het tellen zelf staat in
 * [VisitorStatsTest].
 *
 * Zie docs/architecture/bezoekcijfers.md.
 */
class VisitorScreenTest extends TestCase
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

    /** Een dag met cijfers, zoveel dagen terug. */
    private function dag(int $terug, int $weergaven, int $bezoekers, int $berichten = 0): void
    {
        DB::table('site_day_totals')->insert([
            'day' => now(config('site.timezone'))->subDays($terug)->toDateString(),
            'views' => $weergaven,
            'visitors' => $bezoekers,
            'contacts' => $berichten,
        ]);
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('admin.visitors.index'))->assertRedirect(route('login'));
    }

    public function test_a_user_without_the_permission_is_refused(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.visitors.index'))
            ->assertForbidden();
    }

    public function test_the_screen_shows_the_totals(): void
    {
        $this->dag(1, weergaven: 10, bezoekers: 6, berichten: 2);
        $this->dag(2, weergaven: 5, bezoekers: 3);

        $this->actingAs($this->beheerder())
            ->get(route('admin.visitors.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Bezoekers')
                ->where('cijfers.totalen.weergaven', 15)
                ->where('cijfers.totalen.bezoekers', 9)
                ->where('cijfers.totalen.berichten', 2));
    }

    /**
     * Een dag zonder bezoek staat toch in de reeks.
     *
     * Zonder die lege dagen tekent de grafiek een stille week even breed
     * als een drukke dag, en dan liegt de vorm.
     */
    public function test_days_without_visits_are_in_the_series(): void
    {
        $this->dag(1, weergaven: 10, bezoekers: 6);

        $this->actingAs($this->beheerder())
            ->get(route('admin.visitors.index', ['dagen' => 7]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('cijfers.dagen', 7)
                ->has('cijfers.reeks', 7));
    }

    public function test_the_period_can_be_changed(): void
    {
        $this->actingAs($this->beheerder())
            ->get(route('admin.visitors.index', ['dagen' => 90]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('cijfers.dagen', 90)
                ->has('cijfers.reeks', 90));
    }

    /** Een onzinnige periode valt terug op de standaard. */
    public function test_a_nonsense_period_falls_back(): void
    {
        $this->actingAs($this->beheerder())
            ->get(route('admin.visitors.index', ['dagen' => 9999]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('cijfers.dagen', 30));
    }

    /**
     * Van nul naar iets is geen percentage.
     *
     * Rekenkundig is dat oneindig; op een scherm is het onzin. Dan hoort er
     * niets te staan, en dat is `null`.
     */
    public function test_no_comparison_is_shown_when_the_previous_period_was_empty(): void
    {
        $this->dag(1, weergaven: 10, bezoekers: 6);

        $this->actingAs($this->beheerder())
            ->get(route('admin.visitors.index', ['dagen' => 7]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('cijfers.verschil.bezoekers', null));
    }

    /** En met een vorige periode wél. */
    public function test_the_comparison_with_the_previous_period(): void
    {
        // Deze week acht, de week ervoor vier: honderd procent meer.
        $this->dag(1, weergaven: 8, bezoekers: 8);
        $this->dag(8, weergaven: 4, bezoekers: 4);

        $this->actingAs($this->beheerder())
            ->get(route('admin.visitors.index', ['dagen' => 7]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('cijfers.verschil.bezoekers', 100));
    }

    /** Nog niets gemeten: dan weet het scherm dat ook. */
    public function test_an_empty_state_is_recognisable(): void
    {
        $this->actingAs($this->beheerder())
            ->get(route('admin.visitors.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('cijfers.eerste', null)
                ->where('cijfers.totalen.weergaven', 0));
    }

    /** De uitsplitsingen staan er altijd, ook als ze leeg zijn. */
    public function test_every_breakdown_is_present(): void
    {
        $this->actingAs($this->beheerder())
            ->get(route('admin.visitors.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('cijfers.dimensies.verwijzer')
                ->has('cijfers.dimensies.apparaat')
                ->has('cijfers.dimensies.taal'));
    }

    /** De grootste uitsplitsing staat vooraan. */
    public function test_a_breakdown_is_sorted_by_size(): void
    {
        $dag = now(config('site.timezone'))->toDateString();

        DB::table('site_day_dimensions')->insert([
            ['day' => $dag, 'kind' => 'verwijzer', 'name' => 'google', 'views' => 3],
            ['day' => $dag, 'kind' => 'verwijzer', 'name' => 'linkedin', 'views' => 12],
        ]);

        $this->actingAs($this->beheerder())
            ->get(route('admin.visitors.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('cijfers.dimensies.verwijzer.0.naam', 'linkedin')
                ->where('cijfers.dimensies.verwijzer.0.aantal', 12));
    }
}
