<?php

namespace Tests\Feature\Website;

use App\Enums\ExperienceStatKey;
use App\Models\Experience;
use App\Models\ExperienceStat;
use App\Models\User;
use Database\Seeders\ExperienceStatSeeder;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * De drie cijfers boven de tijdlijn.
 *
 * De kern is één regel: **ingevuld wint, leeg wordt berekend.** Dat maakt
 * twee dingen belangrijk om te bewaken. Ten eerste dat een leeg veld écht
 * terugvalt op de berekening, want anders staat er straks een verouderd
 * getal op de voorpagina dat niemand meer bijwerkt. Ten tweede dat je een
 * ingevuld cijfer weer léég kunt maken -- een instelling waar je niet meer
 * uit komt, is erger dan geen instelling.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
class ExperienceStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
        $this->seed(ExperienceStatSeeder::class);
    }

    private function beheerder(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage portal');

        return $user;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function opDeSite(): array
    {
        return $this->get(route('home'))->viewData('page')['props']['experienceSummary'];
    }

    /**
     * @param  array<string, int|null>  $waarden
     */
    private function bewaar(array $waarden): TestResponse
    {
        return $this->actingAs($this->beheerder())
            ->put(route('website.ervaring.cijfers'), ['waarden' => $waarden]);
    }

    public function test_a_guest_and_a_user_without_the_permission_are_stopped(): void
    {
        $this->put(route('website.ervaring.cijfers'), ['waarden' => ['jaren' => 99]])
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->put(route('website.ervaring.cijfers'), ['waarden' => ['jaren' => 99]])
            ->assertForbidden();

        $this->assertNull(
            ExperienceStat::query()->where('key', ExperienceStatKey::Years)->value('value'),
        );
    }

    public function test_the_route_is_not_swallowed_by_the_detail_page(): void
    {
        /*
         * `PUT website/ervaring/cijfers` en `PUT website/ervaring/{id}` zijn
         * allebei een PUT op hetzelfde patroon, en de eerste die past wint.
         * Staat de volgorde in routes/website.php ooit andersom, dan gaat
         * dit verzoek naar `update()` en faalt het op een ontbrekende
         * functietitel -- zonder dat iemand begrijpt waarom.
         */
        $this->bewaar(['jaren' => 40])->assertSessionHasNoErrors();

        $this->assertSame(40, ExperienceStat::query()
            ->where('key', ExperienceStatKey::Years)
            ->value('value'));
    }

    public function test_an_empty_field_is_calculated_from_the_timeline(): void
    {
        Experience::factory()->count(2)->create(['organisation' => 'Eni']);

        $cijfers = $this->opDeSite();

        $this->assertSame(2, $cijfers[1]['waarde']);
        $this->assertSame(1, $cijfers[2]['waarde']);
    }

    public function test_a_filled_in_number_wins_from_the_calculation(): void
    {
        Experience::factory()->count(2)->create(['organisation' => 'Eni']);

        $this->bewaar(['functies' => 30, 'organisaties' => null, 'jaren' => null])
            ->assertSessionHasNoErrors();

        $cijfers = $this->opDeSite();

        $this->assertSame(30, $cijfers[1]['waarde']);
        // En de rest blijft gewoon meerekenen.
        $this->assertSame(1, $cijfers[2]['waarde']);
    }

    public function test_emptying_it_again_brings_the_calculation_back(): void
    {
        // Een instelling waar je niet meer uit komt, is erger dan geen
        // instelling. Daarom slaat het formulier `null` ook echt op.
        Experience::factory()->count(2)->create();

        $this->bewaar(['functies' => 30]);
        $this->assertSame(30, $this->opDeSite()[1]['waarde']);

        $this->bewaar(['functies' => null]);
        $this->assertSame(2, $this->opDeSite()[1]['waarde']);
    }

    public function test_a_nonsense_value_is_refused_and_nothing_changes(): void
    {
        $this->bewaar(['jaren' => 35]);

        foreach (['veertig', -3, 100000] as $onzin) {
            $this->bewaar(['jaren' => $onzin])->assertSessionHasErrors('waarden.jaren');
        }

        $this->assertSame(35, ExperienceStat::query()
            ->where('key', ExperienceStatKey::Years)
            ->value('value'));
    }

    public function test_the_management_screen_shows_both_numbers(): void
    {
        /*
         * Het berekende getal staat als tijdelijke tekst in het lege veld.
         * Zonder dat tweede getal is "automatisch" een belofte die de klant
         * niet kan controleren.
         */
        Experience::factory()->count(3)->create(['organisation' => 'Eni']);

        $this->bewaar(['jaren' => 35]);

        $cijfers = $this->actingAs($this->beheerder())
            ->get(route('website.ervaring.index'))
            ->viewData('page')['props']['cijfers'];

        $this->assertSame('jaren', $cijfers[0]['key']);
        $this->assertSame(35, $cijfers[0]['waarde']);

        $this->assertNull($cijfers[1]['waarde']);
        $this->assertSame(3, $cijfers[1]['berekend']);
    }

    public function test_the_calculation_only_counts_what_is_online(): void
    {
        Experience::factory()->count(2)->create(['organisation' => 'Eni']);
        Experience::factory()->offline()->create(['organisation' => 'Valtech']);

        $cijfers = $this->actingAs($this->beheerder())
            ->get(route('website.ervaring.index'))
            ->viewData('page')['props']['cijfers'];

        $this->assertSame(2, $cijfers[1]['berekend']);
        $this->assertSame(1, $cijfers[2]['berekend']);
    }

    public function test_seeding_again_keeps_what_the_owner_filled_in(): void
    {
        // Elke deploy draait de seeders opnieuw. Zou hij terugzetten op
        // automatisch, dan gooit die deploy zijn werk weg -- en dat ziet
        // hij op zijn eigen voorpagina.
        $this->bewaar(['jaren' => 35]);

        $this->seed(ExperienceStatSeeder::class);

        $this->assertSame(35, ExperienceStat::query()
            ->where('key', ExperienceStatKey::Years)
            ->value('value'));

        $this->assertSame(3, ExperienceStat::query()->count());
    }

    public function test_there_are_no_numbers_without_experiences(): void
    {
        // Drie nullen boven een lege lijst is erger dan geen cijfers --
        // ook als de klant er zelf iets heeft ingevuld.
        $this->bewaar(['jaren' => 35]);

        $this->assertSame([], $this->opDeSite());
    }
}
