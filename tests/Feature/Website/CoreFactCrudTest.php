<?php

namespace Tests\Feature\Website;

use App\Http\Requests\Website\CoreFactRequest;
use App\Models\ActivityEntry;
use App\Models\CoreFact;
use App\Models\User;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * De kerngegevens: aanmaken, wijzigen, verwijderen, ordenen.
 *
 * Volgt de tabel uit docs/development/testen.md. Het bijzondere geval zit
 * in het **maximum van acht**: een feitenstrook met vijftien regels is
 * geen strook meer maar een tabel, en dan leest niemand hem.
 *
 * Zie docs/architecture/modules/kerngegevens.md.
 */
class CoreFactCrudTest extends TestCase
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
        return [
            'icon' => 'beschikbaarheid',
            'label_nl' => 'Beschikbaar',
            'label_en' => 'Available',
            'value_nl' => 'Vanaf januari',
            'value_en' => 'From January',
            'note_nl' => 'Twee tot drie dagen per week.',
            'note_en' => 'Two to three days a week.',
            'published' => true,
            ...$anders,
        ];
    }

    /* --- Rechten ------------------------------------------------------- */

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('website.kerngegevens.index'))
            ->assertRedirect(route('login'));
    }

    public function test_a_user_without_the_permission_is_refused(): void
    {
        $zonder = User::factory()->create();

        $this->actingAs($zonder)
            ->get(route('website.kerngegevens.index'))
            ->assertForbidden();

        $this->actingAs($zonder)
            ->post(route('website.kerngegevens.store'), $this->invoer())
            ->assertForbidden();
    }

    /* --- Het scherm ----------------------------------------------------- */

    public function test_the_screen_shows_the_items_and_the_options(): void
    {
        $gegeven = CoreFact::factory()->create(['label_nl' => 'Werkgebied']);

        $this->actingAs($this->beheerder())
            ->get(route('website.kerngegevens.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('website/Kerngegevens')
                ->has('items', 1)
                ->where('items.0.label_nl', $gegeven->label_nl)
                ->where('leeg', false)
                ->where('opties.maximum', CoreFact::MAXIMUM)
                // De acht soorten, elk met een voorbeeld voor het lege scherm.
                ->has('opties.soorten', 8)
                ->has('opties.soorten.0.voorbeeld')
            );
    }

    public function test_an_empty_screen_says_so(): void
    {
        $this->actingAs($this->beheerder())
            ->get(route('website.kerngegevens.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('leeg', true)
                ->has('items', 0)
            );
    }

    /* --- Aanmaken -------------------------------------------------------- */

    public function test_a_core_fact_can_be_created(): void
    {
        $this->actingAs($this->beheerder())
            ->post(route('website.kerngegevens.store'), $this->invoer())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('core_facts', [
            'icon' => 'beschikbaarheid',
            'label_nl' => 'Beschikbaar',
            'value_nl' => 'Vanaf januari',
            'published' => true,
        ]);
    }

    public function test_a_new_one_lands_at_the_end(): void
    {
        CoreFact::factory()->create(['position' => 7]);

        $this->actingAs($this->beheerder())
            ->post(route('website.kerngegevens.store'), $this->invoer());

        $this->assertSame(
            8,
            (int) CoreFact::query()->where('label_nl', 'Beschikbaar')->value('position'),
        );
    }

    public function test_the_label_and_the_value_are_required(): void
    {
        $this->actingAs($this->beheerder())
            ->post(route('website.kerngegevens.store'), $this->invoer([
                'label_nl' => '',
                'value_nl' => '',
            ]))
            ->assertSessionHasErrors(['label_nl', 'value_nl']);

        $this->assertSame(0, CoreFact::query()->count());
    }

    public function test_an_unknown_kind_is_refused(): void
    {
        $this->actingAs($this->beheerder())
            ->post(route('website.kerngegevens.store'), $this->invoer([
                'icon' => 'verzonnen',
            ]))
            ->assertSessionHasErrors('icon');

        $this->assertSame(0, CoreFact::query()->count());
    }

    public function test_a_value_that_is_too_long_is_refused(): void
    {
        $this->actingAs($this->beheerder())
            ->post(route('website.kerngegevens.store'), $this->invoer([
                'value_nl' => str_repeat('a', CoreFactRequest::WAARDE_MAX + 1),
            ]))
            ->assertSessionHasErrors('value_nl');
    }

    /**
     * Er passen er acht, en de negende wordt geweigerd.
     *
     * **Dit wordt in de controller afgevangen en niet in de FormRequest.**
     * Het gaat niet over de geldigheid van wat er is ingevuld maar over
     * hoeveel er al staan; een foutmelding onder een veld zou daar niets
     * over zeggen. De eigenaar krijgt dus een melding en geen veldfout.
     */
    public function test_a_ninth_one_is_refused(): void
    {
        CoreFact::factory()->count(CoreFact::MAXIMUM)->create();

        $this->actingAs($this->beheerder())
            ->post(route('website.kerngegevens.store'), $this->invoer())
            ->assertSessionHasNoErrors();

        $this->assertSame(
            CoreFact::MAXIMUM,
            CoreFact::query()->count(),
            'Er is er een negende aangemaakt terwijl het maximum acht is.',
        );
    }

    /* --- Wijzigen --------------------------------------------------------- */

    public function test_a_core_fact_can_be_changed(): void
    {
        $gegeven = CoreFact::factory()->create();

        $this->actingAs($this->beheerder())
            ->put(route('website.kerngegevens.update', $gegeven), $this->invoer([
                'value_nl' => 'Vanaf maart',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Vanaf maart', $gegeven->refresh()->value_nl);
    }

    public function test_the_online_switch_works_both_ways(): void
    {
        $gegeven = CoreFact::factory()->create(['published' => true]);

        $this->actingAs($this->beheerder())
            ->patch(route('website.kerngegevens.online', $gegeven), ['published' => false]);

        $this->assertFalse($gegeven->refresh()->published);

        $this->actingAs($this->beheerder())
            ->patch(route('website.kerngegevens.online', $gegeven), ['published' => true]);

        $this->assertTrue($gegeven->refresh()->published);
    }

    /* --- Verwijderen ------------------------------------------------------- */

    public function test_a_core_fact_can_be_deleted(): void
    {
        $gegeven = CoreFact::factory()->create();

        $this->actingAs($this->beheerder())
            ->delete(route('website.kerngegevens.destroy', $gegeven))
            ->assertSessionHasNoErrors();

        $this->assertNull($gegeven->fresh());
    }

    /* --- Volgorde ----------------------------------------------------------- */

    public function test_the_order_is_renumbered_from_one(): void
    {
        $een = CoreFact::factory()->create(['position' => 10]);
        $twee = CoreFact::factory()->create(['position' => 20]);
        $drie = CoreFact::factory()->create(['position' => 30]);

        $this->actingAs($this->beheerder())
            ->put(route('website.kerngegevens.volgorde'), [
                'items' => [
                    ['id' => $drie->id],
                    ['id' => $een->id],
                    ['id' => $twee->id],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $drie->refresh()->position);
        $this->assertSame(2, $een->refresh()->position);
        $this->assertSame(3, $twee->refresh()->position);
    }

    /**
     * Een halve lijst wordt geweigerd.
     *
     * Zou een verzoek met twee van de drie ids binnenkomen, dan krijgen
     * die twee positie 1 en 2 en botsen ze met de derde.
     */
    public function test_a_partial_order_is_refused(): void
    {
        $een = CoreFact::factory()->create(['position' => 1]);
        CoreFact::factory()->create(['position' => 2]);

        $this->actingAs($this->beheerder())
            ->put(route('website.kerngegevens.volgorde'), [
                'items' => [['id' => $een->id]],
            ]);

        $this->assertSame(1, $een->refresh()->position);
    }

    /* --- Het activiteitenlogboek ---------------------------------------------- */

    public function test_creating_is_logged(): void
    {
        $this->actingAs($this->beheerder())
            ->post(route('website.kerngegevens.store'), $this->invoer());

        $this->assertDatabaseHas('activity_entries', [
            'subject_type' => CoreFact::class,
        ]);

        $regel = ActivityEntry::query()->latest('id')->firstOrFail();

        $this->assertSame('Beschikbaar', $regel->subject_label);
    }
}
