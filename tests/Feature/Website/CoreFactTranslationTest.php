<?php

namespace Tests\Feature\Website;

use App\Models\User;
use App\Support\Translation\Vertaler;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Support\SessionKey;
use Tests\TestCase;

/**
 * De vertaalknop bij een kerngegeven.
 *
 * **Deze test bestaat door een fout die eerder is gemaakt.** De
 * vertaalroute heeft een witte lijst van velden, en bij de werkwijze
 * stonden `duration_nl` en `result_nl` er niet in. `validate()` gooit weg
 * wat er niet in staat, dus de knop vulde een deel van de velden wél en de
 * rest niet -- zonder enige melding. Je merkt dat pas als je toevallig
 * naar het Engels kijkt.
 *
 * Wat hier wordt bewaakt is dus niet "het vertalen werkt" maar: **elk veld
 * dat dit venster meestuurt komt ook vertaald terug.** Dat is de fout die
 * terugkomt zodra iemand er een veld bij zet.
 *
 * De echte dienst wordt nooit aangeroepen; zie ExperienceTranslationTest
 * voor waarom er een dubbel in de container gaat.
 *
 * Zie docs/architecture/automatisch-vertalen.md.
 */
class CoreFactTranslationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Precies wat KerngegevenDialoog.vue meestuurt.
     *
     * Staat hier als lijst zodat de test omvalt op het moment dat iemand
     * er in het venster een veld bij zet zonder de route bij te werken.
     *
     * @var array<int, string>
     */
    private const VELDEN = [
        'label_nl',
        'waarde_nl',
        'notitie_nl',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->app->instance(Vertaler::class, new class implements Vertaler
        {
            public function beschikbaar(): bool
            {
                return true;
            }

            /**
             * @param  array<string, string|null>  $teksten
             * @return array<string, string>
             */
            public function naarEngels(array $teksten): array
            {
                $gevuld = array_filter($teksten, fn (?string $tekst) => filled($tekst));

                return array_map(fn (string $tekst) => 'EN: '.$tekst, $gevuld);
            }
        });
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
    private function vertaling(array $anders = []): array
    {
        $this->actingAs($this->beheerder())
            ->post(route('website.vertalen'), [
                'label_nl' => 'Beschikbaar',
                'waarde_nl' => 'Vanaf januari',
                'notitie_nl' => 'Twee tot drie dagen per week.',
                ...$anders,
            ])
            ->assertSessionHasNoErrors();

        /*
         * De vertaling komt terug als flits-prop, hetzelfde mechanisme als
         * de meldingen. Inertia zet die in de sessie onder zijn eigen
         * sleutel; het formulier pikt hem op via de `flash`-gebeurtenis
         * van de router.
         */
        /** @var array<string, mixed> $vertaling */
        $vertaling = session(SessionKey::FLASH_DATA)['vertaling'] ?? [];

        return $vertaling;
    }

    /* --- Elk veld komt terug ------------------------------------------- */

    public function test_every_field_the_dialog_sends_comes_back(): void
    {
        $vertaling = $this->vertaling();

        foreach (self::VELDEN as $veld) {
            $engels = str_replace('_nl', '_en', $veld);

            $this->assertArrayHasKey(
                $engels,
                $vertaling,
                "Het veld [{$veld}] wordt wel meegestuurd maar komt niet vertaald terug. "
                    .'Staat het in de witte lijst van TranslateController?',
            );
        }
    }

    /**
     * De waarde is het veld dat nieuw was, en dus het gevoeligst.
     *
     * `label_nl` en `notitie_nl` stonden al in de witte lijst voor de
     * statistieken; `waarde_nl` is er voor deze module bij gekomen.
     */
    public function test_the_value_is_translated(): void
    {
        $this->assertSame(
            'EN: Vanaf januari',
            $this->vertaling()['waarde_en'] ?? null,
        );
    }

    public function test_the_label_is_translated(): void
    {
        $this->assertSame(
            'EN: Beschikbaar',
            $this->vertaling()['label_en'] ?? null,
        );
    }

    public function test_the_note_is_translated(): void
    {
        $this->assertSame(
            'EN: Twee tot drie dagen per week.',
            $this->vertaling()['notitie_en'] ?? null,
        );
    }

    /**
     * Een leeg veld gaat niet naar de dienst en komt er dus ook niet uit.
     *
     * Dat scheelt een aanroep voor niets, en het voorkomt dat er "EN: "
     * in een veld komt te staan dat de eigenaar bewust leeg liet.
     */
    public function test_an_empty_field_comes_back_empty(): void
    {
        $vertaling = $this->vertaling(['notitie_nl' => '']);

        $this->assertArrayNotHasKey('notitie_en', $vertaling);
        $this->assertSame('EN: Vanaf januari', $vertaling['waarde_en'] ?? null);
    }

    /** En de route blijft dicht voor wie het recht niet heeft. */
    public function test_a_user_without_the_permission_cannot_translate(): void
    {
        $zonder = User::factory()->create();

        $this->actingAs($zonder)
            ->post(route('website.vertalen'), ['waarde_nl' => 'Vanaf januari'])
            ->assertForbidden();
    }
}
