<?php

namespace Tests\Feature\Website;

use App\Models\User;
use App\Support\Translation\Vertaler;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Support\SessionKey;
use Tests\TestCase;

/**
 * De vertaalknop bij de voettekst.
 *
 * **Deze test bestaat door een fout die eerder is gemaakt.** De
 * vertaalroute heeft een witte lijst van velden, en bij de werkwijze
 * stonden `duration_nl` en `result_nl` er niet in. `validate()` gooit weg
 * wat er niet in staat, dus de knop vulde een deel van de velden wél en de
 * rest niet -- zonder enige melding.
 *
 * Hier zijn het er twee, en ze zitten in **twee verschillende vensters**.
 * Juist dan is de kans groot dat er eentje wordt vergeten: de tweede wordt
 * gebouwd nadat de eerste al werkt.
 *
 * De echte dienst wordt nooit aangeroepen; zie ExperienceTranslationTest
 * voor waarom er een dubbel in de container gaat.
 *
 * Zie docs/architecture/automatisch-vertalen.md.
 */
class FooterTranslationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Precies wat de twee vensters meesturen.
     *
     * Staat hier als lijst zodat de test omvalt op het moment dat iemand
     * er een veld bij zet zonder de route bij te werken.
     *
     * @var array<int, string>
     */
    private const VELDEN = [
        'tagline_nl',
        'region_nl',
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
     * @param  array<string, mixed>  $velden
     * @return array<string, mixed>
     */
    private function vertaling(array $velden): array
    {
        $this->actingAs($this->beheerder())
            ->post(route('website.vertalen'), $velden)
            ->assertSessionHasNoErrors();

        /** @var array<string, mixed> $vertaling */
        $vertaling = session(SessionKey::FLASH_DATA)['vertaling'] ?? [];

        return $vertaling;
    }

    /* --- Elk veld komt terug ------------------------------------------- */

    public function test_every_field_the_dialogs_send_comes_back(): void
    {
        /*
         * Alle velden in één verzoek, ook al sturen de twee vensters ze
         * apart. Het gaat hier om de witte lijst, en die is voor allebei
         * dezelfde.
         */
        $vertaling = $this->vertaling([
            'tagline_nl' => 'IT-advies en realisatie.',
            'region_nl' => 'Noord-Brabant en omstreken',
        ]);

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

    /** Het venster met de zin onder het logo stuurt er maar één. */
    public function test_the_tagline_is_translated_on_its_own(): void
    {
        $vertaling = $this->vertaling([
            'tagline_nl' => 'IT-advies en realisatie.',
        ]);

        $this->assertSame('EN: IT-advies en realisatie.', $vertaling['tagline_en'] ?? null);
    }

    /** En het contactvenster ook. */
    public function test_the_region_is_translated_on_its_own(): void
    {
        $vertaling = $this->vertaling([
            'region_nl' => 'Noord-Brabant en omstreken',
        ]);

        $this->assertSame(
            'EN: Noord-Brabant en omstreken',
            $vertaling['region_en'] ?? null,
        );
    }

    /**
     * De nummers worden niet vertaald, en horen er ook niet in te staan.
     *
     * Een KvK-nummer is in elke taal hetzelfde. Zou het veld in de witte
     * lijst staan, dan zou de vertaaldienst er een poging op doen -- en
     * dan krijg je een "vertaald" nummer.
     */
    public function test_the_numbers_are_not_translatable(): void
    {
        $vertaling = $this->vertaling([
            'tagline_nl' => 'IT-advies en realisatie.',
            'kvk' => '12345678',
            'vat' => 'NL001234567B01',
        ]);

        $this->assertArrayNotHasKey('kvk_en', $vertaling);
        $this->assertArrayNotHasKey('vat_en', $vertaling);
    }
}
