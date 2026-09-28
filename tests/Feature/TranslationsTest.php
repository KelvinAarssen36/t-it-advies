<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * De vertaallaag van de frontend.
 *
 * Het interessante deel is de laatste test. Een ontbrekende sleutel levert
 * geen fout op maar een Nederlandse zin midden in een Engels scherm, en dat
 * merk je alleen als je toevallig in het Engels rondklikt.
 */
class TranslationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dictionary_is_shared_with_the_frontend(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get(route('home'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('translations.Uitloggen', 'Log out'));
    }

    public function test_the_default_language_sends_nothing(): void
    {
        // Nederlands is de sleuteltaal, dus lang/nl.json is leeg. Er hoeft
        // dan ook niets over de lijn.
        $this->get(route('home'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('translations', []));
    }

    public function test_every_key_used_in_the_frontend_has_an_english_translation(): void
    {
        /** @var array<string, string> $engels */
        $engels = json_decode(
            (string) file_get_contents(lang_path('en.json')),
            true,
        );

        $ontbreekt = [];
        $gevondenSleutels = [];

        foreach (File::allFiles(resource_path('js')) as $bestand) {
            if (! in_array($bestand->getExtension(), ['vue', 'ts'], true)) {
                continue;
            }

            // De vertaalfunctie zelf noemt `t(` in zijn eigen voorbeelden.
            if (str_ends_with($bestand->getPathname(), 'lib/i18n.ts')) {
                continue;
            }

            $inhoud = (string) file_get_contents($bestand->getPathname());

            $gebruikt = [
                ...$this->sleutels($inhoud),
                ...$this->kruimeltitels($inhoud),
                ...$this->layoutteksten($inhoud),
            ];

            foreach ($gebruikt as $sleutel) {
                $gevondenSleutels[] = $sleutel;

                if (! array_key_exists($sleutel, $engels)) {
                    $ontbreekt[$sleutel] = $bestand->getRelativePathname();
                }
            }
        }

        /*
         * Eerst bewijzen dat de zoektocht iets oplevert. Zonder deze twee
         * regels zou een kapotte reguliere expressie een groene test geven:
         * nul sleutels gevonden is ook nul sleutels die ontbreken.
         */
        $this->assertGreaterThan(50, count($gevondenSleutels));
        $this->assertContains('Beheer', $gevondenSleutels, 'De kruimelpaden worden niet meer gevonden.');
        $this->assertContains('Welkom terug', $gevondenSleutels, 'De koppen van de inlogschermen worden niet meer gevonden.');

        $this->assertSame(
            [],
            $ontbreekt,
            'Deze sleutels staan niet in lang/en.json: '
                .json_encode($ontbreekt, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );
    }

    /**
     * Haalt de titels uit de kruimelpaden.
     *
     * Die staan in `defineOptions` als kale Nederlandse zin en niet in een
     * `t()`, omdat die plek wordt uitgevoerd voordat Inertia een pagina
     * heeft; [`Breadcrumbs.vue`](../../resources/js/components/Breadcrumbs.vue)
     * vertaalt ze pas bij het tekenen. Ze zijn dus wél sleutels, en horen
     * hier dus ook langs de controle.
     *
     * @return list<string>
     */
    private function kruimeltitels(string $inhoud): array
    {
        $gevonden = [];

        preg_match_all('/breadcrumbs:\s*\[(.*?)\]/s', $inhoud, $blokken);

        foreach ($blokken[1] as $blok) {
            preg_match_all("/title:\s*'((?:[^'\\\\]|\\\\.)*)'/", $blok, $treffers);

            foreach ($treffers[1] as $titel) {
                $gevonden[] = stripcslashes($titel);
            }
        }

        return $gevonden;
    }

    /**
     * Haalt de titel en de omschrijving uit `defineOptions({ layout: … })`.
     *
     * Net als bij de kruimelpaden staan die daar als kale Nederlandse zin,
     * omdat de moduleruimte nog geen taal heeft;
     * [`AuthSimpleLayout.vue`](../../resources/js/layouts/auth/AuthSimpleLayout.vue)
     * vertaalt ze bij het tekenen. Sleutels dus, en ze horen hier langs.
     *
     * @return list<string>
     */
    private function layoutteksten(string $inhoud): array
    {
        $gevonden = [];

        preg_match_all('/layout:\s*\{(.*?)\n    \},/s', $inhoud, $blokken);

        foreach ($blokken[1] as $blok) {
            preg_match_all(
                "/(?:title|description):\s*\n?\s*'((?:[^'\\\\]|\\\\.)*)'/",
                $blok,
                $treffers,
            );

            foreach ($treffers[1] as $zin) {
                $gevonden[] = stripcslashes($zin);
            }
        }

        return $gevonden;
    }

    /**
     * Haalt elke `$t('…')` en `t('…')` uit een bestand.
     *
     * De tweede vorm heeft een terugblik nodig, anders vangt hij ook het
     * staartje van namen als `format(` of `print(`.
     *
     * @return list<string>
     */
    private function sleutels(string $inhoud): array
    {
        $gevonden = [];

        foreach (["/\\\$t\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", "/(?<![\\w.\\\$])t\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/"] as $patroon) {
            preg_match_all($patroon, $inhoud, $treffers);

            foreach ($treffers[1] as $sleutel) {
                $gevonden[] = stripcslashes($sleutel);
            }
        }

        return $gevonden;
    }
}
