<?php

namespace Tests\Feature\Website;

use App\Enums\PageSectionKey;
use App\Models\Experience;
use App\Models\User;
use Database\Seeders\ExperienceStatSeeder;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Een ervaring online of offline zetten.
 *
 * De kern: **wat offline staat, bestaat voor de bezoeker niet** -- en dus
 * ook niet voor de vraag of het onderdeel nog inhoud heeft. Zou de teller
 * op het indelingsscherm het totaal gebruiken, dan meldt hij dat de
 * tijdlijn gevuld is terwijl er op de website niets verschijnt, en dan is
 * die melding erger dan geen melding.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
class ExperiencePublishingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);

        // De cijfers boven de tijdlijn zijn rijen in de database geworden
        // die de klant beheert. Zonder deze seeder staat die lijst leeg, en
        // dan rekent de website terecht niets uit -- ook niet in een test
        // die juist over dat rekenen gaat.
        $this->seed(ExperienceStatSeeder::class);
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

    public function test_the_switch_puts_an_experience_online_and_offline(): void
    {
        $ervaring = Experience::factory()->create();

        $this->actingAs($this->beheerder())
            ->patch(route('website.ervaring.online', $ervaring), ['published' => false])
            ->assertSessionHasNoErrors();

        $this->assertFalse($ervaring->fresh()?->published);

        $this->actingAs($this->beheerder())
            ->patch(route('website.ervaring.online', $ervaring), ['published' => true]);

        $this->assertTrue($ervaring->fresh()?->published);
    }

    public function test_the_switch_leaves_everything_else_alone(): void
    {
        // Eén waarde omzetten hoort niet het hele item langs de validatie
        // te sturen, en al helemaal niet iets anders te wijzigen.
        $ervaring = Experience::factory()->create(['role_nl' => 'Systeembeheerder']);

        $this->actingAs($this->beheerder())
            ->patch(route('website.ervaring.online', $ervaring), ['published' => false]);

        $this->assertSame('Systeembeheerder', $ervaring->fresh()?->role_nl);
    }

    public function test_what_is_offline_is_not_on_the_website(): void
    {
        Experience::factory()->create(['role_nl' => 'Zichtbaar']);
        Experience::factory()->offline()->create(['role_nl' => 'Verborgen']);

        $functies = array_column($this->landing()['experiences'], 'functie');

        $this->assertContains('Zichtbaar', $functies);
        $this->assertNotContains('Verborgen', $functies);
    }

    public function test_the_whole_section_disappears_when_everything_is_offline(): void
    {
        /*
         * Een tijdlijn waarvan alles offline staat is voor de bezoeker net
         * zo leeg als een tijdlijn zonder ervaringen -- en een kopje met
         * niets eronder is slordiger dan geen kopje.
         */
        Experience::factory()->count(3)->offline()->create();

        $props = $this->landing();

        $this->assertNotContains(PageSectionKey::Ervaring->value, $props['sections']);
        $this->assertSame([], $props['experiences']);
    }

    public function test_the_numbers_above_the_timeline_only_count_what_is_online(): void
    {
        Experience::factory()->count(2)->create(['organisation' => 'Eni']);
        Experience::factory()->offline()->create(['organisation' => 'Valtech']);

        $cijfers = $this->landing()['experienceSummary'];

        // Twee functies bij één organisatie; de offline regel telt niet mee.
        $this->assertSame(2, $cijfers[1]['waarde']);
        $this->assertSame(1, $cijfers[2]['waarde']);
    }

    public function test_the_numbers_are_gone_when_there_is_nothing(): void
    {
        $this->assertSame([], $this->landing()['experienceSummary']);
    }

    public function test_the_years_run_from_the_first_start_to_today(): void
    {
        /*
         * Niet de som van alle periodes: functies overlappen, en dan tel je
         * jezelf rijk. Het is de afstand van de vroegste startdatum tot het
         * laatste einde -- of tot vandaag als er nog iets loopt.
         */
        Experience::factory()->create([
            'started_on' => now()->subYears(20)->startOfMonth(),
            'ended_on' => now()->subYears(12)->startOfMonth(),
        ]);
        Experience::factory()->loopt()->create([
            'started_on' => now()->subYears(11)->startOfMonth(),
        ]);

        $this->assertSame(20, $this->landing()['experienceSummary'][0]['waarde']);
    }

    public function test_a_new_experience_can_be_stored_without_going_live(): void
    {
        $this->actingAs($this->beheerder())
            ->post(route('website.ervaring.store'), [
                'icon' => 'werk',
                'published' => false,
                'role_nl' => 'Systeembeheerder',
                'organisation' => 'Eni',
                'started_on' => '2021-03',
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse(Experience::query()->sole()->published);
    }

    public function test_leaving_the_field_out_puts_it_online(): void
    {
        /*
         * De terugval staat bewust op "wel online": een ervaring die je
         * invoert wil je op je website hebben. Zou hij op false staan, dan
         * verdwijnt alles wat langs een andere weg binnenkomt stilletjes
         * van de site.
         */
        $this->actingAs($this->beheerder())
            ->post(route('website.ervaring.store'), [
                'icon' => 'werk',
                'role_nl' => 'Systeembeheerder',
                'organisation' => 'Eni',
                'started_on' => '2021-03',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Experience::query()->sole()->published);
    }
}
