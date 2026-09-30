<?php

namespace Tests\Feature\Website;

use App\Enums\ActivityAction;
use App\Models\ActivityEntry;
use App\Models\Experience;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Het beheerscherm van de tijdlijn: aanmaken, wijzigen, verwijderen.
 *
 * Dit loopt de lijst uit docs/development/testen.md af. De twee die er hier
 * bovenop komen zijn de tweetaligheid -- allebei de talen moeten worden
 * opgeslagen en teruggegeven -- en de omgekeerde periode, want een ervaring
 * die eindigt voordat hij begint zet de hele tijdlijn op zijn kop.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
class ExperienceCrudTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $anders
     * @return array<string, mixed>
     */
    private function invoer(array $anders = []): array
    {
        return [
            'icon' => 'werk',
            'role_nl' => 'Systeembeheerder',
            'role_en' => 'System administrator',
            'organisation' => '@T IT Advies',
            'organisation_url' => 'https://t-it-advies.nl',
            'employment' => 'fulltime',
            'workplace' => 'hybride',
            'location_nl' => 'Utrecht',
            'location_en' => 'Utrecht',
            'started_on' => '2021-03',
            'ended_on' => '2024-06',
            'description_nl' => 'Netwerken en werkplekken beheerd.',
            'description_en' => 'Managed networks and workstations.',
            ...$anders,
        ];
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        /*
         * Elke route apart, en niet alleen het overzicht. Het overzicht is
         * de route waarvan je zéker weet dat hij dichtzit, want daar kijk
         * je naar; een schrijfroute die per ongeluk buiten de groep valt,
         * merk je nooit.
         */
        $ervaring = Experience::factory()->create();

        $this->get(route('website.ervaring.index'))->assertRedirect(route('login'));
        $this->get(route('website.ervaring.show', $ervaring))->assertRedirect(route('login'));
        $this->post(route('website.ervaring.store'), $this->invoer())->assertRedirect(route('login'));
        $this->put(route('website.ervaring.update', $ervaring), $this->invoer())->assertRedirect(route('login'));
        $this->patch(route('website.ervaring.online', $ervaring), ['published' => false])->assertRedirect(route('login'));
        $this->put(route('website.ervaring.kop'), ['waarden' => []])->assertRedirect(route('login'));
        $this->delete(route('website.ervaring.destroy', $ervaring))->assertRedirect(route('login'));
        $this->post(route('website.vertalen'))->assertRedirect(route('login'));

        $this->assertSame(1, Experience::query()->count());
        $this->assertTrue($ervaring->fresh()?->published);
    }

    public function test_every_route_needs_the_permission(): void
    {
        $user = User::factory()->create();
        $ervaring = Experience::factory()->create();

        $this->actingAs($user)->get(route('website.ervaring.index'))->assertForbidden();
        $this->actingAs($user)->get(route('website.ervaring.show', $ervaring))->assertForbidden();
        $this->actingAs($user)->post(route('website.ervaring.store'), $this->invoer())->assertForbidden();
        $this->actingAs($user)->put(route('website.ervaring.update', $ervaring), $this->invoer())->assertForbidden();
        $this->actingAs($user)->patch(route('website.ervaring.online', $ervaring), ['published' => false])->assertForbidden();
        $this->actingAs($user)->put(route('website.ervaring.kop'), ['waarden' => []])->assertForbidden();
        $this->actingAs($user)->delete(route('website.ervaring.destroy', $ervaring))->assertForbidden();
        $this->actingAs($user)->post(route('website.vertalen'))->assertForbidden();
    }

    public function test_the_screen_lists_the_experiences(): void
    {
        Experience::factory()->count(3)->create();

        $this->actingAs($this->beheerder())
            ->get(route('website.ervaring.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('website/Ervaring')
                ->has('items.data', 3)
                ->has('opties.maanden', 12)
                // Zonder API-sleutel hoort de knop er niet te staan.
                ->where('kanVertalen', false));
    }

    public function test_creating_stores_both_languages(): void
    {
        $this->actingAs($this->beheerder())
            ->post(route('website.ervaring.store'), $this->invoer())
            ->assertSessionHasNoErrors();

        $ervaring = Experience::query()->sole();

        $this->assertSame('Systeembeheerder', $ervaring->role_nl);
        $this->assertSame('System administrator', $ervaring->role_en);
        $this->assertSame('Netwerken en werkplekken beheerd.', $ervaring->description_nl);
        $this->assertSame('Managed networks and workstations.', $ervaring->description_en);

        // De maand wordt de eerste van die maand; de dag betekent niets.
        $this->assertSame('2021-03-01', $ervaring->started_on->format('Y-m-d'));
        $this->assertSame('2024-06-01', $ervaring->ended_on?->format('Y-m-d'));
    }

    public function test_an_empty_optional_field_becomes_null_and_not_an_empty_string(): void
    {
        /*
         * Dit onderscheid is het hele verschil tussen een nette kaart en een
         * kopje boven niets: de website beslist op `filled()` of een veld
         * wordt getoond, en een lege string is gevuld.
         */
        $this->actingAs($this->beheerder())
            ->post(route('website.ervaring.store'), $this->invoer([
                'location_nl' => '',
                'description_nl' => '  ',
                'organisation_url' => '',
            ]))
            ->assertSessionHasNoErrors();

        $ervaring = Experience::query()->sole();

        $this->assertNull($ervaring->location_nl);
        $this->assertNull($ervaring->description_nl);
        $this->assertNull($ervaring->organisation_url);
    }

    public function test_an_experience_without_an_end_date_is_still_running(): void
    {
        $this->actingAs($this->beheerder())
            ->post(route('website.ervaring.store'), $this->invoer(['ended_on' => null]))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Experience::query()->sole()->loopt());
    }

    public function test_updating_changes_only_that_experience(): void
    {
        $doel = Experience::factory()->create(['role_nl' => 'Oud']);
        $ander = Experience::factory()->create(['role_nl' => 'Blijft staan']);

        $this->actingAs($this->beheerder())
            ->put(route('website.ervaring.update', $doel), $this->invoer(['role_nl' => 'Nieuw']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Nieuw', $doel->fresh()?->role_nl);
        $this->assertSame('Blijft staan', $ander->fresh()?->role_nl);
    }

    public function test_deleting_removes_only_that_experience(): void
    {
        $doel = Experience::factory()->create();
        $ander = Experience::factory()->create();

        $this->actingAs($this->beheerder())
            ->delete(route('website.ervaring.destroy', $doel));

        $this->assertDatabaseMissing('experiences', ['id' => $doel->id]);
        $this->assertDatabaseHas('experiences', ['id' => $ander->id]);
    }

    public function test_invalid_input_is_refused_and_nothing_is_stored(): void
    {
        $this->actingAs($this->beheerder())
            ->post(route('website.ervaring.store'), $this->invoer([
                'role_nl' => '',
                'organisation' => '',
                'started_on' => 'maart 2021',
            ]))
            ->assertSessionHasErrors(['role_nl', 'organisation', 'started_on']);

        $this->assertSame(0, Experience::query()->count());
    }

    public function test_a_long_description_is_stored_whole(): void
    {
        /*
         * De grens stond op tweeduizend tekens, en dat is te krap voor een
         * functie met een lijstje verantwoordelijkheden eronder. Erger nog
         * was wat erop volgde: de opslag werd geweigerd, het venster ging
         * opnieuw open, en daarbij vulde het zich uit de opgeslagen
         * ervaring -- dus je tekst was weg. Dat laatste zit in het scherm
         * en niet hier; deze test bewaakt alleen dat de grens ruim genoeg
         * is en dat er niets wordt afgekapt.
         */
        $lang = str_repeat('Beheer van netwerken en werkplekken. ', 120);

        $this->assertGreaterThan(2000, strlen($lang));

        $this->actingAs($this->beheerder())
            ->post(route('website.ervaring.store'), $this->invoer([
                'description_nl' => $lang,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(trim($lang), Experience::query()->sole()->description_nl);
    }

    public function test_a_description_that_is_far_too_long_is_still_refused(): void
    {
        // Ruim is niet grenzeloos: zonder bovengrens kan iemand een
        // romanhoofdstuk in de database zetten, en dan is het de website
        // die traag wordt.
        $this->actingAs($this->beheerder())
            ->post(route('website.ervaring.store'), $this->invoer([
                'description_nl' => str_repeat('a', 5001),
            ]))
            ->assertSessionHasErrors('description_nl');

        $this->assertSame(0, Experience::query()->count());
    }

    public function test_an_end_date_before_the_start_date_is_refused(): void
    {
        /*
         * Zonder deze grens kun je een ervaring invoeren die eindigt voordat
         * hij begint. Dat levert geen foutmelding op maar een tijdlijn die
         * niet klopt, en dat zie je pas op de website.
         */
        $this->actingAs($this->beheerder())
            ->post(route('website.ervaring.store'), $this->invoer([
                'started_on' => '2024-06',
                'ended_on' => '2021-03',
            ]))
            ->assertSessionHasErrors('ended_on');

        $this->assertSame(0, Experience::query()->count());
    }

    public function test_a_link_that_is_not_a_link_is_refused(): void
    {
        $this->actingAs($this->beheerder())
            ->post(route('website.ervaring.store'), $this->invoer([
                'organisation_url' => 'wwwpuntiets',
            ]))
            ->assertSessionHasErrors('organisation_url');
    }

    /**
     * @param  array<int, string>  $gevaarlijk
     */
    #[DataProvider('gevaarlijkeLinks')]
    public function test_a_link_that_runs_code_is_refused(string $link): void
    {
        /*
         * Dít is waarom er `url:http,https` staat en niet alleen `url`. Een
         * `javascript:`-adres in een `href` wordt door de browser gewoon
         * uitgevoerd, en Vue schoont een `:href` niet. Op een publieke site
         * is dat het verschil tussen een rare link en een lek.
         */
        $this->actingAs($this->beheerder())
            ->post(route('website.ervaring.store'), $this->invoer([
                'organisation_url' => $link,
            ]))
            ->assertSessionHasErrors('organisation_url');

        $this->assertSame(0, Experience::query()->count());
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function gevaarlijkeLinks(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'javascript met hoofdletters' => ['JaVaScRiPt:alert(1)'],
            'data-url' => ['data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='],
            'bestand op de server' => ['file:///etc/passwd'],
        ];
    }

    public function test_a_dangerous_link_never_reaches_the_website(): void
    {
        /*
         * Het tweede slot, op de uitvoer. De validatie hierboven houdt de
         * gewone weg dicht, maar een waarde die er langs een andere weg in
         * komt -- een seeder, een import, een regel die iemand later
         * toevoegt -- mag nog steeds niet als link op de site verschijnen.
         */
        $ervaring = Experience::factory()->create();
        $ervaring->forceFill(['organisation_url' => 'javascript:alert(1)'])->save();

        $this->assertNull($ervaring->fresh()?->website());

        $props = $this->get(route('home'))->viewData('page')['props'];

        $this->assertNull($props['experiences'][0]['website']);
    }

    public function test_an_unknown_icon_is_refused(): void
    {
        $this->actingAs($this->beheerder())
            ->post(route('website.ervaring.store'), $this->invoer(['icon' => 'eenhoorn']))
            ->assertSessionHasErrors('icon');
    }

    public function test_every_change_ends_up_in_the_activity_log(): void
    {
        $beheerder = $this->beheerder();

        $this->actingAs($beheerder)->post(route('website.ervaring.store'), $this->invoer());
        $ervaring = Experience::query()->sole();

        $this->actingAs($beheerder)->put(
            route('website.ervaring.update', $ervaring),
            $this->invoer(['role_nl' => 'Iets anders']),
        );

        $this->actingAs($beheerder)->delete(route('website.ervaring.destroy', $ervaring));

        $regels = ActivityEntry::query()
            ->where('subject_type', Experience::class)
            ->get();

        $this->assertCount(3, $regels);
        $this->assertEqualsCanonicalizing(
            [ActivityAction::Created, ActivityAction::Updated, ActivityAction::Deleted],
            $regels->pluck('action')->all(),
        );

        // Het label blijft leesbaar nadat het item weg is; dat is juist de
        // regel die je later terugzoekt.
        $this->assertStringContainsString(
            '@T IT Advies',
            (string) $regels->last()?->subject_label,
        );
    }

    public function test_saving_without_a_change_says_so(): void
    {
        $ervaring = Experience::factory()->create();

        $this->actingAs($this->beheerder())->post(
            route('website.ervaring.store'),
            $this->invoer(),
        );

        $nieuw = Experience::query()->latest('id')->firstOrFail();

        $this->actingAs($this->beheerder())->put(
            route('website.ervaring.update', $nieuw),
            $this->invoer(),
        );

        // Alleen de twee regels van het aanmaken en van $ervaring zelf; de
        // opslag zonder wijziging voegt er niets aan toe.
        $this->assertSame(
            1,
            ActivityEntry::query()
                ->where('subject_type', Experience::class)
                ->where('subject_id', $nieuw->id)
                ->count(),
        );

        $this->assertNotNull($ervaring->fresh());
    }
}
