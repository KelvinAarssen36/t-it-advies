<?php

namespace Tests\Feature\Website;

use App\Models\Education;
use App\Models\User;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectionHeadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * De opleidingen onder de certificaten.
 *
 * Een kleinere lijst dan de certificaten, met één eigenaardigheid die
 * het waard is om vast te leggen: **de volgorde is niet instelbaar.** Ze
 * ordenen zichzelf op periode, met wat nog loopt bovenaan. Dat is
 * dezelfde keuze als bij de tijdlijn, en hij is stilletjes om te draaien
 * door iemand die er een `position` bij zet.
 *
 * Zie docs/architecture/modules/certificaten.md.
 */
class EducationCrudTest extends TestCase
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
        return array_merge([
            'published' => true,
            'title_nl' => 'Informatica',
            'title_en' => 'Computer Science',
            'institution' => 'Avans Hogeschool',
            'level_nl' => 'HBO bachelor',
            'level_en' => "Bachelor's degree",
            'started_on' => '2014-09',
            'ended_on' => '2018-07',
        ], $anders);
    }

    /**
     * @param  array<string, mixed>  $anders
     */
    private function maak(array $anders = []): TestResponse
    {
        return $this->actingAs($this->beheerder())->post(
            route('website.certificaten.opleidingen.store'),
            $this->invoer($anders),
        );
    }

    /** De opleidingen zoals de website ze meestuurt. */
    private function opDeSite(string $taal = 'nl'): array
    {
        $rijen = [];

        $this->withSession(['locale' => $taal])
            ->get('/')
            ->assertInertia(function (AssertableInertia $page) use (&$rijen) {
                $rijen = $page->toArray()['props']['educations'];
            });

        return $rijen;
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $opleiding = Education::factory()->create();

        $this->post(route('website.certificaten.opleidingen.store'))
            ->assertRedirect(route('login'));
        $this->put(route('website.certificaten.opleidingen.update', $opleiding))
            ->assertRedirect(route('login'));
        $this->patch(route('website.certificaten.opleidingen.online', $opleiding))
            ->assertRedirect(route('login'));
        $this->delete(route('website.certificaten.opleidingen.destroy', $opleiding))
            ->assertRedirect(route('login'));

        $this->assertSame(1, Education::query()->count());
    }

    public function test_every_route_needs_the_permission(): void
    {
        $opleiding = Education::factory()->create();
        $gebruiker = User::factory()->create();

        $this->actingAs($gebruiker)
            ->post(route('website.certificaten.opleidingen.store'))->assertForbidden();
        $this->actingAs($gebruiker)
            ->put(route('website.certificaten.opleidingen.update', $opleiding))->assertForbidden();
        $this->actingAs($gebruiker)
            ->patch(route('website.certificaten.opleidingen.online', $opleiding))->assertForbidden();
        $this->actingAs($gebruiker)
            ->delete(route('website.certificaten.opleidingen.destroy', $opleiding))->assertForbidden();

        $this->assertSame(1, Education::query()->count());
    }

    public function test_creating_stores_both_languages(): void
    {
        $this->maak()->assertSessionHasNoErrors();

        $opleiding = Education::query()->firstOrFail();

        $this->assertSame('Informatica', $opleiding->title_nl);
        $this->assertSame('Computer Science', $opleiding->title_en);
        $this->assertSame('Avans Hogeschool', $opleiding->institution);
        $this->assertSame('HBO bachelor', $opleiding->level_nl);
        $this->assertSame('2014-09-01', $opleiding->started_on->toDateString());
        $this->assertSame('2018-07-01', $opleiding->ended_on?->toDateString());
    }

    public function test_the_name_the_institution_and_the_start_are_required(): void
    {
        $this->maak(['title_nl' => '', 'institution' => '', 'started_on' => ''])
            ->assertSessionHasErrors(['title_nl', 'institution', 'started_on']);

        $this->assertSame(0, Education::query()->count());
    }

    public function test_an_empty_optional_field_becomes_null(): void
    {
        $this->maak(['title_en' => '', 'level_nl' => '', 'level_en' => '', 'ended_on' => ''])
            ->assertSessionHasNoErrors();

        $opleiding = Education::query()->firstOrFail();

        $this->assertNull($opleiding->title_en);
        $this->assertNull($opleiding->level_nl);
        $this->assertNull($opleiding->level_en);
        $this->assertNull($opleiding->ended_on);
    }

    public function test_a_period_that_runs_backwards_is_refused(): void
    {
        $this->maak(['started_on' => '2018-09', 'ended_on' => '2014-07'])
            ->assertSessionHasErrors('ended_on');

        $this->assertSame(0, Education::query()->count());
    }

    /**
     * De volgorde is niet instelbaar en ordent zichzelf.
     *
     * Wat nog loopt bovenaan, daarna op einddatum van nieuw naar oud.
     */
    public function test_the_list_orders_itself_with_the_ongoing_one_first(): void
    {
        Education::factory()->create([
            'title_nl' => 'Oud',
            'started_on' => '2010-09-01',
            'ended_on' => '2014-07-01',
        ]);
        Education::factory()->create([
            'title_nl' => 'Nieuw',
            'started_on' => '2014-09-01',
            'ended_on' => '2018-07-01',
        ]);
        Education::factory()->loopt()->create([
            'title_nl' => 'Loopt nog',
            'started_on' => '2024-09-01',
        ]);

        $namen = array_map(fn (array $rij) => $rij['naam'], $this->opDeSite());

        $this->assertSame(['Loopt nog', 'Nieuw', 'Oud'], $namen);
    }

    /** Een lopende opleiding leest als "2024 — heden". */
    public function test_an_ongoing_education_says_present(): void
    {
        Education::factory()->loopt()->create(['started_on' => '2024-09-01']);

        $this->assertSame('2024 — heden', $this->opDeSite()[0]['periode']);
        $this->assertSame('2024 — present', $this->opDeSite('en')[0]['periode']);
    }

    /**
     * Het niveau valt niet terug op het Nederlands.
     *
     * Dat is de vaste regel: verplicht valt terug, optioneel wordt
     * weggelaten. Half Nederlands op een Engelse pagina is slordiger dan
     * geen niveau.
     */
    public function test_a_missing_english_level_drops_out_instead_of_falling_back(): void
    {
        Education::factory()->create([
            'title_nl' => 'Informatica',
            'title_en' => null,
            'level_nl' => 'HBO bachelor',
            'level_en' => null,
        ]);

        $rij = $this->opDeSite('en')[0];

        // De naam valt wél terug -- een regel zonder opleiding is geen
        // regel.
        $this->assertSame('Informatica', $rij['naam']);
        $this->assertNull($rij['niveau']);
    }

    public function test_what_is_offline_is_not_on_the_website(): void
    {
        Education::factory()->create(['title_nl' => 'Zichtbaar']);
        Education::factory()->offline()->create(['title_nl' => 'Verborgen']);

        $namen = array_map(fn (array $rij) => $rij['naam'], $this->opDeSite());

        $this->assertSame(['Zichtbaar'], $namen);
    }

    public function test_the_switch_puts_an_education_online_and_offline(): void
    {
        $opleiding = Education::factory()->offline()->create();

        $this->actingAs($this->beheerder())->patch(
            route('website.certificaten.opleidingen.online', $opleiding),
            ['published' => true],
        );

        $this->assertTrue($opleiding->fresh()?->published);
    }

    public function test_deleting_leaves_the_rest_standing(): void
    {
        $opleiding = Education::factory()->create();
        Education::factory()->create();

        $this->actingAs($this->beheerder())
            ->delete(route('website.certificaten.opleidingen.destroy', $opleiding));

        $this->assertSame(1, Education::query()->count());
        $this->assertNull($opleiding->fresh());
    }

    public function test_saving_the_same_thing_says_nothing_changed(): void
    {
        $opleiding = Education::factory()->create([
            'published' => true,
            'title_nl' => 'Informatica',
            'title_en' => 'Computer Science',
            'institution' => 'Avans Hogeschool',
            'level_nl' => 'HBO bachelor',
            'level_en' => "Bachelor's degree",
            'started_on' => '2014-09-01',
            'ended_on' => '2018-07-01',
        ]);

        $this->travel(1)->minute();

        $this->actingAs($this->beheerder())->put(
            route('website.certificaten.opleidingen.update', $opleiding),
            $this->invoer(),
        )->assertSessionHasNoErrors();

        $this->assertEquals(
            $opleiding->updated_at,
            $opleiding->fresh()?->updated_at,
        );
    }
}
