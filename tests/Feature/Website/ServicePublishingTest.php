<?php

namespace Tests\Feature\Website;

use App\Enums\PageSectionKey;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Een dienst online of offline zetten, en de volgorde.
 *
 * De kern is dezelfde als bij de tijdlijn: **wat offline staat, bestaat
 * voor de bezoeker niet** -- en dus ook niet voor de vraag of het
 * onderdeel nog inhoud heeft. Zou de teller op het indelingsscherm het
 * totaal gebruiken, dan meldt hij dat het blok gevuld is terwijl er op
 * de website niets verschijnt.
 *
 * Zie docs/architecture/modules/diensten.md.
 */
class ServicePublishingTest extends TestCase
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
     * @return array<string, mixed>
     */
    private function landing(): array
    {
        return $this->get(route('home'))->viewData('page')['props'];
    }

    public function test_the_switch_puts_a_service_online_and_offline(): void
    {
        $dienst = Service::factory()->create();

        $this->actingAs($this->beheerder())
            ->patch(route('website.diensten.online', $dienst), ['published' => false])
            ->assertSessionHasNoErrors();

        $this->assertFalse($dienst->fresh()?->published);

        $this->actingAs($this->beheerder())
            ->patch(route('website.diensten.online', $dienst), ['published' => true]);

        $this->assertTrue($dienst->fresh()?->published);
    }

    public function test_what_is_offline_is_not_on_the_website(): void
    {
        Service::factory()->create(['title_nl' => 'Zichtbaar']);
        Service::factory()->offline()->create(['title_nl' => 'Verborgen']);

        $titels = array_column($this->landing()['services'], 'titel');

        $this->assertSame(['Zichtbaar'], $titels);
    }

    public function test_the_whole_section_disappears_when_everything_is_offline(): void
    {
        /*
         * Een blok met een kop en geen kaarten eronder is voor de
         * bezoeker net zo leeg als geen diensten -- en een kopje met
         * niets eronder is slordiger dan geen kopje.
         */
        Service::factory()->count(3)->offline()->create();

        $props = $this->landing();

        $this->assertNotContains(PageSectionKey::Diensten->value, $props['sections']);
        $this->assertSame([], $props['services']);
        $this->assertNull($props['serviceHeading']);
    }

    public function test_the_section_comes_back_as_soon_as_there_is_one_online(): void
    {
        // Geen overbodige spiegel van de test hierboven: zonder deze
        // slaagt die ook als het onderdeel nóóit verschijnt.
        Service::factory()->create();

        $this->assertContains(
            PageSectionKey::Diensten->value,
            $this->landing()['sections'],
        );
    }

    public function test_the_services_are_in_the_order_the_owner_set(): void
    {
        Service::factory()->create(['title_nl' => 'Derde', 'position' => 3]);
        Service::factory()->create(['title_nl' => 'Eerste', 'position' => 1]);
        Service::factory()->create(['title_nl' => 'Tweede', 'position' => 2]);

        $this->assertSame(
            ['Eerste', 'Tweede', 'Derde'],
            array_column($this->landing()['services'], 'titel'),
        );
    }

    public function test_the_order_can_be_changed(): void
    {
        $een = Service::factory()->create(['title_nl' => 'Een', 'position' => 1]);
        $twee = Service::factory()->create(['title_nl' => 'Twee', 'position' => 2]);

        $this->actingAs($this->beheerder())
            ->put(route('website.diensten.volgorde'), [
                'diensten' => [$twee->id, $een->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['Twee', 'Een'],
            array_column($this->landing()['services'], 'titel'),
        );
    }

    public function test_an_incomplete_order_is_refused(): void
    {
        /*
         * Zou een verzoek met twee van de drie ids doorgaan, dan krijgen
         * die twee positie 1 en 2 en botsen ze met de derde. Dan staat
         * de volgorde op de website ineens anders dan in het scherm.
         */
        $een = Service::factory()->create(['position' => 1]);
        Service::factory()->create(['position' => 2]);
        Service::factory()->create(['position' => 3]);

        $this->actingAs($this->beheerder())
            ->put(route('website.diensten.volgorde'), ['diensten' => [$een->id]]);

        $this->assertSame(
            [1, 2, 3],
            Service::query()->orderBy('id')->pluck('position')->all(),
        );
    }

    public function test_an_unknown_id_in_the_order_is_refused(): void
    {
        Service::factory()->create(['position' => 1]);

        $this->actingAs($this->beheerder())
            ->put(route('website.diensten.volgorde'), ['diensten' => [9999]])
            ->assertSessionHasErrors('diensten.0');
    }

    public function test_the_layout_screen_counts_only_what_is_online(): void
    {
        Service::factory()->create();
        Service::factory()->count(2)->offline()->create();

        /*
         * De plek wordt uitgerekend en niet opgeschreven. Hier stond een
         * vast getal, en dat klopte tot er een module vóór de diensten bij
         * kwam -- toen viel deze test om op iets dat niets met zijn
         * onderwerp te maken had.
         */
        $alles = PageSectionKey::cases();

        usort(
            $alles,
            fn (PageSectionKey $a, PageSectionKey $b) => $a->standaardPositie() <=> $b->standaardPositie(),
        );

        $plek = (int) array_search(PageSectionKey::Diensten, $alles, true);

        $this->actingAs($this->beheerder())
            ->get(route('website.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where("sections.{$plek}.key", 'diensten')
                ->where("sections.{$plek}.count", 1)
                ->where("sections.{$plek}.filled", true)
                ->etc());
    }

    public function test_the_layout_screen_flags_an_empty_section(): void
    {
        Service::factory()->count(2)->offline()->create();

        $this->actingAs($this->beheerder())
            ->get(route('website.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('sections.1.count', 0)
                ->where('sections.1.filled', false)
                ->where('sections.1.live', false)
                ->etc());
    }
}
