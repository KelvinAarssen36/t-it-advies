<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Het bezoekblok linksboven op het dashboard.
 *
 * **Waar het hier om gaat is dat het grote getal nooit terugloopt.** Het is
 * het totaal van altijd, en de dagtotalen worden nergens opgeruimd -- ze
 * gaan over niemand in het bijzonder. Zou er ooit een opruimtaak op
 * `site_day_totals` komen, dan hoort
 * `test_the_total_is_not_limited_to_a_period` om te vallen.
 *
 * Zie docs/architecture/dashboard.md.
 */
class DashboardVisitBlockTest extends TestCase
{
    use RefreshDatabase;

    private function dag(int $terug, int $weergaven, int $bezoekers): void
    {
        DB::table('site_day_totals')->insert([
            'day' => now(config('site.timezone'))->subDays($terug)->toDateString(),
            'views' => $weergaven,
            'visitors' => $bezoekers,
            'contacts' => 0,
        ]);
    }

    public function test_the_dashboard_shows_the_block(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->has('bezoek.weergavenTotaal')
                ->has('bezoek.bezoekersVandaag')
                ->has('bezoek.reeks'));
    }

    /**
     * Het totaal gaat over álle dagen en niet over een periode.
     *
     * Een blok dat elke maand op nul begint voelt als iets dat je
     * kwijtraakt, en daar is geen reden voor: deze cijfers hoeven nooit
     * opgeruimd te worden.
     */
    public function test_the_total_is_not_limited_to_a_period(): void
    {
        $this->dag(terug: 400, weergaven: 100, bezoekers: 40);
        $this->dag(terug: 1, weergaven: 5, bezoekers: 3);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('bezoek.weergavenTotaal', 105));
    }

    public function test_today_is_shown_separately(): void
    {
        $this->dag(terug: 0, weergaven: 9, bezoekers: 4);
        $this->dag(terug: 3, weergaven: 50, bezoekers: 20);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('bezoek.bezoekersVandaag', 4)
                ->where('bezoek.weergavenVandaag', 9));
    }

    /** De reeks heeft een waarde per dag, ook voor de stille dagen. */
    public function test_the_series_has_a_value_for_every_day(): void
    {
        $this->dag(terug: 0, weergaven: 9, bezoekers: 4);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('bezoek.reeks', 14)
                ->where('bezoek.reeks.13', 9)
                ->where('bezoek.reeks.0', 0));
    }

    /**
     * Nog niets gemeten is iets anders dan nul bezoekers.
     *
     * Het blok zegt dan "nog geen bezoek gemeten" in plaats van een nul,
     * want een nul leest als "er komt niemand".
     */
    public function test_nothing_measured_yet_is_recognisable(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('bezoek.meet', false)
                ->where('bezoek.weergavenTotaal', 0));
    }

    public function test_once_there_is_a_day_it_counts_as_measuring(): void
    {
        $this->dag(terug: 2, weergaven: 1, bezoekers: 1);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('bezoek.meet', true));
    }
}
