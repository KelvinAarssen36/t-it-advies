<?php

namespace Tests\Feature\Website;

use App\Models\User;
use App\Support\Translation\Vertaler;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Support\SessionKey;
use Tests\TestCase;

/**
 * De vertaalknop bij een stap in de werkwijze.
 *
 * **Deze test bestaat door een fout.** De vertaalroute heeft een witte
 * lijst van velden, en `duration_nl` en `result_nl` stonden er niet in.
 * `validate()` gooit weg wat er niet in staat, dus de knop vulde de titel,
 * de korte tekst en het verhaal wél en die twee niet -- zonder enige
 * melding. Je merkt het pas als je toevallig naar het Engels kijkt.
 *
 * Wat hier wordt bewaakt is dus niet "het vertalen werkt" maar: **elk veld
 * dat dit venster meestuurt komt ook vertaald terug.** Dat is de fout die
 * terug kan komen zodra iemand er een veld bij zet.
 *
 * De echte dienst wordt nooit aangeroepen; zie ExperienceTranslationTest
 * voor waarom er een dubbel in de container gaat.
 *
 * Zie docs/architecture/automatisch-vertalen.md.
 */
class WorkStepTranslationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Precies wat StapDialoog.vue meestuurt.
     *
     * Staat hier als lijst zodat de test omvalt op het moment dat iemand
     * er in het venster een veld bij zet zonder de route bij te werken.
     *
     * @var array<int, string>
     */
    private const VELDEN = [
        'title_nl',
        'summary_nl',
        'duration_nl',
        'result_nl',
        'body_nl',
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
                'title_nl' => 'Kennismaken',
                'summary_nl' => 'Wat speelt er en waar loopt het vast.',
                'duration_nl' => 'Een gesprek',
                'result_nl' => 'Een eerlijk beeld van wat er nodig is.',
                'body_nl' => 'We beginnen met kijken en luisteren.',
                ...$anders,
            ])
            ->assertSessionHasNoErrors();

        /*
         * De vertaling komt terug als flits-prop, hetzelfde mechanisme als
         * de meldingen. Inertia zet die in de sessie onder zijn eigen
         * sleutel; het formulier pikt hem op via de `flash`-gebeurtenis
         * van de router. Zie ExperienceTranslationTest.
         */
        /** @var array<string, mixed> $vertaling */
        $vertaling = session(SessionKey::FLASH_DATA)['vertaling'] ?? [];

        return $vertaling;
    }

    /* --- Elk veld komt terug ------------------------------------------- */

    /**
     * Alle vijf de velden die het venster stuurt, komen vertaald terug.
     *
     * Dit is de test die er niet was toen de duur en het resultaat stil
     * werden weggegooid.
     */
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

    public function test_the_duration_is_translated(): void
    {
        $this->assertSame(
            'EN: Een gesprek',
            $this->vertaling()['duration_en'] ?? null,
        );
    }

    public function test_the_result_is_translated(): void
    {
        $this->assertSame(
            'EN: Een eerlijk beeld van wat er nodig is.',
            $this->vertaling()['result_en'] ?? null,
        );
    }

    /* --- De grenzen ---------------------------------------------------- */

    /**
     * Een verhaal van de volle lengte gaat er doorheen.
     *
     * `body_nl` stond op tweeduizend tekens -- de grens van een
     * certificaattoelichting -- terwijl een stapverhaal er vierduizend mag
     * hebben. Een lang verhaal liet dan de hele aanroep omvallen, dus kwam
     * ook de titel niet terug.
     */
    public function test_a_full_length_story_is_accepted(): void
    {
        $vertaling = $this->vertaling([
            'body_nl' => str_repeat('a', 4000),
        ]);

        $this->assertArrayHasKey('body_en', $vertaling);
    }

    /** En een leeg veld komt niet terug; er valt niets te vertalen. */
    public function test_an_empty_field_does_not_come_back(): void
    {
        $vertaling = $this->vertaling([
            'duration_nl' => '',
            'result_nl' => '',
        ]);

        $this->assertArrayNotHasKey('duration_en', $vertaling);
        $this->assertArrayNotHasKey('result_en', $vertaling);
        $this->assertArrayHasKey('title_en', $vertaling);
    }
}
