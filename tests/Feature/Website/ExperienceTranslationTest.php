<?php

namespace Tests\Feature\Website;

use App\Models\Experience;
use App\Models\User;
use App\Support\Translation\VertaalFout;
use App\Support\Translation\Vertaler;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * De knop "Vertaal automatisch".
 *
 * **De echte dienst wordt hier nooit aangeroepen.** `phpunit.xml` zet
 * `TRANSLATE_ENABLED` op false, dus de applicatie draait in tests met
 * GeenVertaler; de tests die wél iets willen vertalen zetten zelf een
 * dubbel in de container. Dat is geen netheid maar noodzaak: een testsuite
 * die per ongeluk de echte dienst aanroept, eet het maandtegoed op en is
 * afhankelijk van het internet.
 *
 * Zie docs/architecture/automatisch-vertalen.md.
 */
class ExperienceTranslationTest extends TestCase
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

    /** Een vertaler die netjes antwoordt, zonder het internet op te gaan. */
    private function werkendeVertaler(): void
    {
        $this->app->instance(Vertaler::class, new class implements Vertaler
        {
            public function beschikbaar(): bool
            {
                return true;
            }

            public function naarEngels(array $teksten): array
            {
                $gevuld = array_filter($teksten, fn (?string $tekst) => filled($tekst));

                return array_map(fn (string $tekst) => 'EN: '.$tekst, $gevuld);
            }
        });
    }

    /** Een vertaler die onderuit gaat, zoals wanneer het tegoed op is. */
    private function kapotteVertaler(string $reden): void
    {
        $this->app->instance(Vertaler::class, new class($reden) implements Vertaler
        {
            public function __construct(private readonly string $reden) {}

            public function beschikbaar(): bool
            {
                return true;
            }

            public function naarEngels(array $teksten): array
            {
                throw new VertaalFout($this->reden);
            }
        });
    }

    public function test_with_translating_switched_off_the_button_is_not_there(): void
    {
        // De stand in elke test: het hele scherm werkt, alleen de knop
        // staat er niet.
        $this->actingAs($this->beheerder())
            ->get(route('website.ervaring.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('kanVertalen', false));
    }

    public function test_with_translating_switched_off_the_route_does_not_exist(): void
    {
        /*
         * Een 404 en geen foutmelding. Er is geen dienst, dus er is ook
         * niets om aan te roepen -- en dan hoort het adres er niet te zijn
         * in plaats van te bestaan en altijd te falen.
         */
        $this->actingAs($this->beheerder())
            ->post(route('website.vertalen'), ['role_nl' => 'Beheerder'])
            ->assertNotFound();
    }

    public function test_the_translation_comes_back_as_a_flash_prop(): void
    {
        $this->werkendeVertaler();

        $response = $this->actingAs($this->beheerder())
            ->from(route('website.ervaring.index'))
            ->post(route('website.vertalen'), [
                'role_nl' => 'Systeembeheerder',
                'location_nl' => 'Utrecht',
                'description_nl' => 'Netwerken beheerd.',
            ]);

        $response->assertRedirect(route('website.ervaring.index'));

        /*
         * De vertaling komt terug als flits-prop, hetzelfde mechanisme als
         * de meldingen. Inertia zet die in de sessie onder zijn eigen
         * sleutel; het formulier pikt hem op via de `flash`-gebeurtenis van
         * de router.
         */
        $vertaling = session(SessionKey::FLASH_DATA)['vertaling'] ?? null;

        // De sleutels gaan van _nl naar _en, want dat zijn de velden die
        // het formulier moet invullen.
        $this->assertSame([
            'role_en' => 'EN: Systeembeheerder',
            'location_en' => 'EN: Utrecht',
            'description_en' => 'EN: Netwerken beheerd.',
        ], $vertaling);
    }

    /**
     * Het woord onder één cijfer boven de tijdlijn.
     *
     * Hetzelfde adres als de grote knop, met een eigen veld. Een tweede
     * route ernaast zou dezelfde begrenzing en dezelfde foutafhandeling
     * moeten herhalen, en dat is precies waar twee dingen uiteen gaan
     * lopen.
     *
     * Bij welk cijfer het woord hoort weet de server niet, en hoeft hij
     * niet te weten: één woord erin, één woord eruit. Het venster heeft
     * de knop zelf ingedrukt en onthoudt de rest.
     */
    public function test_a_single_word_can_be_translated_on_its_own(): void
    {
        $this->werkendeVertaler();

        $this->actingAs($this->beheerder())
            ->from(route('website.ervaring.index'))
            ->post(route('website.vertalen'), ['woord_nl' => 'opdrachten'])
            ->assertRedirect(route('website.ervaring.index'));

        $this->assertSame(
            ['woord_en' => 'EN: opdrachten'],
            session(SessionKey::FLASH_DATA)['vertaling'] ?? null,
        );
    }

    public function test_a_word_that_is_too_long_is_refused(): void
    {
        // Veertig tekens, want zo lang mag het woord onder een cijfer in
        // de database ook zijn. Zou dit langer mogen, dan kost een lange
        // zin tegoed voor iets dat straks toch niet wordt opgeslagen.
        $this->werkendeVertaler();

        $this->actingAs($this->beheerder())
            ->post(route('website.vertalen'), [
                'woord_nl' => str_repeat('a', 41),
            ])
            ->assertSessionHasErrors('woord_nl');
    }

    public function test_an_empty_field_is_not_sent_along(): void
    {
        // Lege velden vertalen kost tekens van het tegoed en levert een
        // lege string terug die we al hadden.
        $this->app->instance(Vertaler::class, new class implements Vertaler
        {
            /** @var array<int, string> */
            public static array $gekregen = [];

            public function beschikbaar(): bool
            {
                return true;
            }

            public function naarEngels(array $teksten): array
            {
                self::$gekregen = array_keys(
                    array_filter($teksten, fn (?string $tekst) => filled($tekst)),
                );

                return [];
            }
        });

        $this->actingAs($this->beheerder())
            ->post(route('website.vertalen'), [
                'role_nl' => 'Beheerder',
                'location_nl' => '',
                'description_nl' => null,
            ]);

        $this->assertSame(['role_nl'], $this->app->make(Vertaler::class)::$gekregen);
    }

    public function test_a_failure_changes_nothing_and_says_what_to_do(): void
    {
        $this->kapotteVertaler(VertaalFout::TEGOED_OP);

        $ervaring = Experience::factory()->create(['role_en' => null]);

        $this->actingAs($this->beheerder())
            ->post(route('website.vertalen'), ['role_nl' => 'Beheerder'])
            ->assertRedirect();

        // Er wordt niets opgeslagen door de knop -- ook niet bij succes,
        // maar juist hier moet dat vaststaan.
        $this->assertNull($ervaring->fresh()?->role_en);
    }

    public function test_the_translate_route_is_rate_limited(): void
    {
        /*
         * Elke aanroep kost tekens van een maandtegoed. Een knop die per
         * ongeluk in een lus staat, kan dat tegoed anders in een paar
         * minuten opmaken.
         */
        $this->werkendeVertaler();

        $beheerder = $this->beheerder();

        for ($poging = 0; $poging < 20; $poging++) {
            $this->actingAs($beheerder)
                ->post(route('website.vertalen'), ['role_nl' => 'Beheerder']);
        }

        $this->actingAs($beheerder)
            ->post(route('website.vertalen'), ['role_nl' => 'Beheerder'])
            ->assertStatus(429);
    }

    public function test_a_machine_translation_is_marked_as_such(): void
    {
        $beheerder = $this->beheerder();

        $this->actingAs($beheerder)->post(route('website.ervaring.store'), [
            'icon' => 'werk',
            'role_nl' => 'Systeembeheerder',
            'role_en' => 'System administrator',
            'organisation' => '@T IT Advies',
            'started_on' => '2021-03',
            'machine_translated' => true,
        ]);

        $ervaring = Experience::query()->sole();

        $this->assertNotNull($ervaring->machine_translated_at);

        // En het scherm laat dat zien, zodat de eigenaar weet wat hij nog
        // moet nalopen.
        $this->actingAs($beheerder)
            ->get(route('website.ervaring.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('items.data.0.automatisch_vertaald', true));
    }

    public function test_editing_the_english_text_clears_the_mark(): void
    {
        $ervaring = Experience::factory()->create([
            'machine_translated_at' => now(),
        ]);

        $this->actingAs($this->beheerder())->put(
            route('website.ervaring.update', $ervaring),
            [
                'icon' => 'werk',
                'role_nl' => 'Systeembeheerder',
                'role_en' => 'Zelf getypt',
                'organisation' => '@T IT Advies',
                'started_on' => '2021-03',
                // De browser stuurt dit niet meer mee zodra de klant iets
                // in de Engelse velden verandert.
                'machine_translated' => false,
            ],
        );

        $this->assertNull($ervaring->fresh()?->machine_translated_at);
    }

    public function test_the_mark_survives_a_change_that_leaves_the_english_alone(): void
    {
        /*
         * Het merkje zegt "dit Engels komt van een machine en is nog niet
         * nagelopen". Verbetert de klant een typefout in de Nederlandse
         * tekst, dan is dat nog steeds waar -- en dan hoort het merkje te
         * blijven staan.
         *
         * Dit ging mis in het formulier, niet op de server: het venster
         * begon met een schone lei en stuurde daardoor bij elke opslag
         * `machine_translated: false` mee. Deze test legt vast wat de
         * server met het goede antwoord doet.
         */
        $ervaring = Experience::factory()->vertaald()->create([
            'machine_translated_at' => now(),
        ]);

        $this->actingAs($this->beheerder())->put(
            route('website.ervaring.update', $ervaring),
            [
                'icon' => 'werk',
                'role_nl' => 'Systeembeheerder met een typefout eruit',
                'role_en' => 'Translated role',
                'organisation' => '@T IT Advies',
                'started_on' => '2021-03',
                'machine_translated' => true,
            ],
        );

        $this->assertNotNull($ervaring->fresh()?->machine_translated_at);
    }

    public function test_an_experience_without_an_english_title_is_flagged(): void
    {
        Experience::factory()->create(['role_en' => null]);

        $this->actingAs($this->beheerder())
            ->get(route('website.ervaring.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('items.data.0.vertaald', false));
    }

    public function test_an_empty_description_is_not_a_missing_translation(): void
    {
        /*
         * Alleen de verplichte functietitel telt. Een lege Engelse
         * beschrijving bij een lege Nederlandse is geen ontbrekende
         * vertaling maar een lege beschrijving -- en daar hoort geen
         * waarschuwing bij.
         */
        Experience::factory()->create([
            'role_en' => 'System administrator',
            'description_nl' => null,
            'description_en' => null,
        ]);

        $this->actingAs($this->beheerder())
            ->get(route('website.ervaring.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('items.data.0.vertaald', true));
    }
}
