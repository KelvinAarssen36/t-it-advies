<?php

namespace Tests\Feature\Website;

use App\Models\Service;
use App\Models\ServicePoint;
use App\Models\User;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * De expertisepunten onder een dienst.
 *
 * Het interessante zit in het gelijktrekken: de lijst die binnenkomt is
 * de nieuwe waarheid, dus wat er niet bij zit hoort weg. En in de
 * terugval: een punt zonder Engelse tekst valt wég en niet terug, want
 * een lijstje met drie Engelse en twee Nederlandse labels is slordiger
 * dan een lijstje van drie.
 *
 * Zie docs/architecture/modules/diensten.md.
 */
class ServicePointsTest extends TestCase
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
     * @param  array<int, array<string, mixed>>  $punten
     * @return array<string, mixed>
     */
    private function invoer(array $punten): array
    {
        return [
            'icon' => 'beheer',
            'published' => true,
            'title_nl' => 'Beheer',
            'summary_nl' => 'Draaiend houden.',
            'punten' => $punten,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $punten
     */
    private function bewaar(Service $dienst, array $punten): TestResponse
    {
        return $this->actingAs($this->beheerder())->put(
            route('website.diensten.update', $dienst),
            $this->invoer($punten),
        );
    }

    private function dienst(): Service
    {
        return Service::factory()->create([
            'title_nl' => 'Beheer',
            'summary_nl' => 'Draaiend houden.',
        ]);
    }

    /**
     * De punten zoals een bezoeker ze krijgt.
     *
     * @return array<int, string>
     */
    private function opDeSite(string $taal = 'nl'): array
    {
        $punten = [];

        $this->withSession(['locale' => $taal])
            ->get('/')
            ->assertInertia(function (AssertableInertia $page) use (&$punten) {
                $diensten = $page->toArray()['props']['services'];
                $punten = $diensten[0]['punten'] ?? [];
            });

        return $punten;
    }

    public function test_points_are_stored_in_the_order_they_came_in(): void
    {
        $dienst = $this->dienst();

        $this->bewaar($dienst, [
            ['text_nl' => 'monitoring', 'text_en' => 'monitoring'],
            ['text_nl' => 'back-ups', 'text_en' => 'backups'],
            ['text_nl' => 'updates', 'text_en' => 'updates'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            ['monitoring', 'back-ups', 'updates'],
            $dienst->fresh()?->points->pluck('text_nl')->all(),
        );

        $this->assertSame(
            [1, 2, 3],
            $dienst->fresh()?->points->pluck('position')->all(),
        );
    }

    public function test_a_point_that_is_left_out_is_removed(): void
    {
        $dienst = $this->dienst();

        $this->bewaar($dienst, [
            ['text_nl' => 'monitoring', 'text_en' => null],
            ['text_nl' => 'back-ups', 'text_en' => null],
        ]);

        $this->bewaar($dienst, [
            ['text_nl' => 'monitoring', 'text_en' => null],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, ServicePoint::query()->count());
        $this->assertSame('monitoring', ServicePoint::query()->value('text_nl'));
    }

    public function test_all_points_can_be_removed_at_once(): void
    {
        /*
         * Een lege lijst betekent "haal ze allemaal weg" en niet "laat
         * maar staan". Vandaar `present` in de validatie: een
         * ontbrekende sleutel zou dat verschil wegpoetsen.
         */
        $dienst = $this->dienst();

        $this->bewaar($dienst, [['text_nl' => 'monitoring', 'text_en' => null]]);

        $this->bewaar($dienst, [])->assertSessionHasNoErrors();

        $this->assertSame(0, ServicePoint::query()->count());
    }

    public function test_more_than_the_maximum_is_refused(): void
    {
        $dienst = $this->dienst();

        $teveel = array_fill(
            0,
            Service::PUNTEN_MAXIMUM + 1,
            ['text_nl' => 'iets', 'text_en' => null],
        );

        $this->bewaar($dienst, $teveel)->assertSessionHasErrors('punten');

        $this->assertSame(0, ServicePoint::query()->count());
    }

    public function test_the_maximum_itself_is_allowed(): void
    {
        // De grens zelf moet er wél in passen. Zonder deze test slaagt
        // de vorige ook bij een grens die er eentje naast zit.
        $dienst = $this->dienst();

        $precies = array_fill(
            0,
            Service::PUNTEN_MAXIMUM,
            ['text_nl' => 'iets', 'text_en' => null],
        );

        $this->bewaar($dienst, $precies)->assertSessionHasNoErrors();

        $this->assertSame(Service::PUNTEN_MAXIMUM, ServicePoint::query()->count());
    }

    public function test_an_empty_point_is_refused(): void
    {
        $dienst = $this->dienst();

        $this->bewaar($dienst, [
            ['text_nl' => 'monitoring', 'text_en' => null],
            ['text_nl' => '   ', 'text_en' => 'backups'],
        ])->assertSessionHasErrors('punten.1.text_nl');

        $this->assertSame(0, ServicePoint::query()->count());
    }

    public function test_a_point_that_is_too_long_is_refused(): void
    {
        $dienst = $this->dienst();

        $this->bewaar($dienst, [
            ['text_nl' => str_repeat('a', 61), 'text_en' => null],
        ])->assertSessionHasErrors('punten.0.text_nl');
    }

    public function test_the_visitor_gets_the_points_in_their_language(): void
    {
        $dienst = $this->dienst();

        $this->bewaar($dienst, [
            ['text_nl' => 'monitoring', 'text_en' => 'monitoring'],
            ['text_nl' => 'back-ups', 'text_en' => 'backups'],
        ]);

        $this->assertSame(['monitoring', 'back-ups'], $this->opDeSite());
        $this->assertSame(['monitoring', 'backups'], $this->opDeSite('en'));
    }

    public function test_a_point_without_english_drops_out_instead_of_falling_back(): void
    {
        /*
         * Dit is het verschil met de titel van een dienst, die wél
         * terugvalt. Een lijstje waarin twee van de drie labels ineens
         * Nederlands zijn leest slechter dan een lijstje van één.
         */
        $dienst = $this->dienst();

        $this->bewaar($dienst, [
            ['text_nl' => 'monitoring', 'text_en' => 'monitoring'],
            ['text_nl' => 'storingen', 'text_en' => null],
        ]);

        $this->assertSame(['monitoring', 'storingen'], $this->opDeSite());
        $this->assertSame(['monitoring'], $this->opDeSite('en'));
    }

    public function test_the_management_screen_shows_both_languages_apart(): void
    {
        $dienst = $this->dienst();

        $this->bewaar($dienst, [
            ['text_nl' => 'monitoring', 'text_en' => 'monitoring'],
        ]);

        $this->actingAs($this->beheerder())
            ->get(route('website.diensten.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('items.0.punten.0.text_nl', 'monitoring')
                ->where('items.0.punten.0.text_en', 'monitoring')
                ->etc());
    }

    public function test_a_missing_english_point_makes_the_service_incomplete(): void
    {
        // Het merkje op het overzicht. Het wordt op de server bepaald,
        // want de regel welke velden meetellen hoort bij het model.
        $dienst = $this->dienst();

        $this->bewaar($dienst, [
            ['text_nl' => 'monitoring', 'text_en' => null],
        ]);

        $this->actingAs($this->beheerder())
            ->get(route('website.diensten.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('items.0.vertaald', false)
                ->etc());
    }
}
