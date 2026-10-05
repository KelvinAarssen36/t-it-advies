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
        $this->assertContains('Koppel je authenticator', $gevondenSleutels, 'De koppen die onderweg wisselen worden niet meer gevonden.');

        $this->assertSame(
            [],
            $ontbreekt,
            'Deze sleutels staan niet in lang/en.json: '
                .json_encode($ontbreekt, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );
    }

    /**
     * De blinde vlek: een zin die nooit in een `$t()` is gezet.
     *
     * De twee tests hierboven controleren of elke sleutel die al door de
     * vertaling gaat een Engelse tekst heeft. Een kale Nederlandse zin in
     * een sjabloon glipt daar per definitie doorheen -- het is geen
     * sleutel, dus er ontbreekt ook niets. Dat is precies hoe de hele
     * landingspagina en de 2FA-schermen maandenlang in het Nederlands
     * bleven staan terwijl de rest netjes meeging.
     *
     * Deze test kijkt naar de andere kant op: welke tekst komt er op het
     * scherm zonder langs `$t()` te gaan. Dat kan niet waterdicht -- we
     * herkennen Nederlands aan een handvol woorden die in het Engels
     * niet zo voorkomen -- maar het vangt hele zinnen, en dat zijn de
     * gevallen die opvallen.
     */
    public function test_no_dutch_sentence_bypasses_the_translation(): void
    {
        /*
         * Woorden die vrijwel alleen in het Nederlands zo staan. `je`,
         * `is` en `in` staan er bewust niet bij: die komen in het Engels
         * ook voor, en dan meldt deze test dingen die kloppen.
         */
        $nederlands = '/(?:^|\W)(?:de|het|een|en|van|voor|met|naar|niet|maar|dat|dit|die|deze|zijn|wordt|worden|kun|kunt|jouw|wij|ons|onze|bij|door|over|aan|als|ook|nog|geen|wat|hoe|waar|waarom|meer|alle|elke|zelf|jij|je)(?:\W|$)/i';

        $gevonden = [];

        foreach (File::allFiles(resource_path('js')) as $bestand) {
            if ($bestand->getExtension() !== 'vue') {
                continue;
            }

            $inhoud = (string) file_get_contents($bestand->getPathname());

            if (! preg_match('/<template>(.*)<\/template>/s', $inhoud, $treffer)) {
                continue;
            }

            // Commentaar telt niet mee; dat komt niet op het scherm.
            $sjabloon = (string) preg_replace('/<!--.*?-->/s', '', $treffer[1]);

            $verdacht = [
                ...$this->kaleTekst($sjabloon, $nederlands),
                ...$this->kaleAttributen($sjabloon, $nederlands),
            ];

            foreach ($verdacht as $zin) {
                $gevonden[$zin] = $bestand->getRelativePathname();
            }
        }

        $this->assertSame(
            [],
            $gevonden,
            'Deze tekst komt op het scherm zonder door $t() te gaan: '
                .json_encode($gevonden, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );
    }

    /**
     * Nederlandse tekst die kaal tussen twee tags staat.
     *
     * @return list<string>
     */
    private function kaleTekst(string $sjabloon, string $nederlands): array
    {
        $gevonden = [];

        preg_match_all('/>([^<>{}]+)</', $sjabloon, $teksten);

        foreach ($teksten[1] as $zin) {
            $zin = trim($zin);

            // Losse woorden van drie tekens vallen af; dat zijn eerder
            // afkortingen en losse tekens dan zinnen.
            if (mb_strlen($zin) > 3 && preg_match($nederlands, $zin) === 1) {
                $gevonden[] = $zin;
            }
        }

        return $gevonden;
    }

    /**
     * Nederlandse tekst in een attribuut dat de bezoeker leest.
     *
     * Zonder dubbele punt ervoor, want met een dubbele punt is het een
     * uitdrukking en gaat hij waarschijnlijk wél door `$t()`.
     *
     * @return list<string>
     */
    private function kaleAttributen(string $sjabloon, string $nederlands): array
    {
        $gevonden = [];

        preg_match_all(
            '/\s(?:title|alt|placeholder|aria-label)="([^"{}]+)"/',
            $sjabloon,
            $attributen,
        );

        foreach ($attributen[1] as $zin) {
            if (preg_match($nederlands, $zin) === 1) {
                $gevonden[] = $zin;
            }
        }

        return $gevonden;
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
     * Haalt de titel en de omschrijving uit `defineOptions({ layout: … })`
     * en uit elke `setLayoutProps({ … })`.
     *
     * Net als bij de kruimelpaden staan die daar als kale Nederlandse zin,
     * omdat de moduleruimte nog geen taal heeft;
     * [`AuthSimpleLayout.vue`](../../resources/js/layouts/auth/AuthSimpleLayout.vue)
     * vertaalt ze bij het tekenen. Sleutels dus, en ze horen hier langs.
     *
     * **Ook de tweede vorm**, want de 2FA-schermen wisselen hun kop
     * onderweg met `setLayoutProps`. Die zinnen vielen hier eerst buiten,
     * en stonden dus in het Nederlands boven een verder Engels scherm.
     *
     * @return list<string>
     */
    private function layoutteksten(string $inhoud): array
    {
        $gevonden = [];

        preg_match_all(
            '/(?:layout:\s*\{|setLayoutProps\(\{)(.*?)\n\s*\}\)?[,;]/s',
            $inhoud,
            $blokken,
        );

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
     * **Ook de dubbele aanhalingstekens**, want een zin met een apostrof
     * of met een aanhaling erin staat in Prettier's opmaak vanzelf tussen
     * dubbele. Die vorm viel hier eerst buiten, en dus ontbrak er een
     * controle op precies de lastigste zinnen.
     *
     * @return list<string>
     */
    private function sleutels(string $inhoud): array
    {
        $gevonden = [];

        $patronen = [
            "/\\\$t\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/",
            "/(?<![\\w.\\\$])t\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/",
            '/\\$t\\(\\s*"((?:[^"\\\\]|\\\\.)*)"/',
            '/(?<![\\w.\\$])t\\(\\s*"((?:[^"\\\\]|\\\\.)*)"/',
        ];

        foreach ($patronen as $patroon) {
            preg_match_all($patroon, $inhoud, $treffers);

            foreach ($treffers[1] as $sleutel) {
                $gevonden[] = stripcslashes($sleutel);
            }
        }

        return $gevonden;
    }

    /**
     * Ook de serverkant vertaalt, en dat viel hier eerst buiten.
     *
     * Toasts, validatiemeldingen en de labels van de enums gaan door
     * `__()` en komen dus nooit langs de controle hierboven. Dat is geen
     * theoretisch gat: de meldingen bij het bewaren van de cijfers stonden
     * er maanden in het Nederlands, midden in een verder Engels scherm.
     *
     * `app/Console` valt er bewust buiten. Die tekst verschijnt op een
     * terminal bij ons en niet bij de klant, dus die hoeft geen Engels.
     *
     * **`resources/views` viel er óók buiten, en daar ging het mis.** De
     * bevestigingsmail is de enige mail die in twee talen de deur uit gaat,
     * en drie van zijn eigen zinnen stonden niet in `lang/en.json`. Een
     * Engelse bezoeker kreeg "Bedankt voor je bericht / Beste John Smith"
     * boven een Engelse tekst. Geen enkele test voelde dat: de sleutels
     * staan in een Blade en die werd niet ingelezen.
     *
     * Daarom staan de mailsjablonen er nu bij. Ook die aan de eigenaar:
     * `site.locale` staat op Nederlands, maar een instelling die je kunt
     * omzetten hoort te werken als iemand dat doet.
     */
    public function test_every_sentence_the_server_translates_has_an_english_translation(): void
    {
        /** @var array<string, string> $engels */
        $engels = json_decode(
            (string) file_get_contents(lang_path('en.json')),
            true,
        );

        $ontbreekt = [];
        $gevondenSleutels = [];

        $bestanden = [
            ...File::allFiles(app_path()),
            ...File::allFiles(database_path('seeders')),
            ...File::allFiles(resource_path('views')),
        ];

        foreach ($bestanden as $bestand) {
            if ($bestand->getExtension() !== 'php') {
                continue;
            }

            if (str_contains($bestand->getPathname(), DIRECTORY_SEPARATOR.'Console'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $inhoud = (string) file_get_contents($bestand->getPathname());

            foreach ($this->phpSleutels($inhoud) as $sleutel) {
                $gevondenSleutels[] = $sleutel;

                if (! array_key_exists($sleutel, $engels)) {
                    $ontbreekt[$sleutel] = $bestand->getRelativePathname();
                }
            }
        }

        // Weer eerst bewijzen dat er iets gevonden wordt; zie hierboven.
        $this->assertGreaterThan(50, count($gevondenSleutels));
        /*
         * Hier stond 'Kop boven de tijdlijn'. Die naam verdween toen de
         * drie koptabellen er één werden: het logboek zegt nu "Koptekst"
         * met het onderdeel als label. Een naam uit een model die er
         * altijd zal zijn is een betere kanarie.
         */
        $this->assertContains('Certificaat', $gevondenSleutels, 'De namen van de modellen worden niet meer gevonden.');

        $this->assertSame(
            [],
            $ontbreekt,
            'Deze sleutels staan niet in lang/en.json: '
                .json_encode($ontbreekt, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );
    }

    /**
     * Haalt elke `__('…')` en `trans('…')` uit een PHP-bestand.
     *
     * @return list<string>
     */
    private function phpSleutels(string $inhoud): array
    {
        $gevonden = [];

        $patronen = [
            "/\\b(?:__|trans)\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/",
            '/\\b(?:__|trans)\\(\\s*"((?:[^"\\\\]|\\\\.)*)"/',
        ];

        foreach ($patronen as $patroon) {
            preg_match_all($patroon, $inhoud, $treffers);

            foreach ($treffers[1] as $sleutel) {
                $gevonden[] = stripcslashes($sleutel);
            }
        }

        return $gevonden;
    }
}
