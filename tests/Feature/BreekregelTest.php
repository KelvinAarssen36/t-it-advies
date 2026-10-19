<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * De breekregel voor een lang woord, en waar hij overal moet gelden.
 *
 * **Waarom dit een test verdient.** De klant typt zijn eigen teksten.
 * Zet hij er een samengesteld woord in van veertig tekens --
 * "Bedrijfsinformatievoorzieningsbeheerder" is een echte functietitel --
 * dan past dat op een telefoon niet op een regel. Zonder breekregel
 * breekt de browser het niet af maar laat hij het uit zijn vak steken, en
 * dan komt er een horizontale schuifbalk.
 *
 * Dat is twee keer gebeurd. Eerst op de kaarten van de publieke site; dat
 * is opgelost met één regel op `.brand-dark-page`, want `overflow-wrap`
 * erft. En daarna in een venster: dat staat in een portaal aan het eind
 * van `<body>`, buiten die omhulling, dus daar erfde het niets. Gemeten
 * op een telefoon van 390 pixels was het venster er 499 breed.
 *
 * Deze test bewaakt de twee dingen die daarbij misgaan:
 *
 * 1. Een nieuw oppervlak in een portaal dat vergeten wordt.
 * 2. De twee regels die uit elkaar lopen. Ze horen bij elkaar --
 *    `break-word` laat een woord afbreken, maar een flex-kind bepaalt
 *    zijn breedte vóór dat moment, dus `min-width: 0` moet mee. Staat de
 *    ene op drie plekken en de andere op twee, dan werkt het op die derde
 *    plek half.
 *
 * Zie resources/css/app.css en docs/development/valkuilen.md,
 * "Een venster staat niet in `.brand-site`".
 */
class BreekregelTest extends TestCase
{
    /**
     * De oppervlakken waar de breekregel moet gelden.
     *
     * `.brand-dark-page` is de publieke site zelf. De andere twee staan
     * in een portaal aan het eind van `<body>` en erven dus niets:
     * `.brand-blad` is een venster, `.brand-site-nav-meer` het lijstje
     * achter "Meer" in de kop. Allebei tonen ze tekst van de klant.
     *
     * @var list<string>
     */
    private const OPPERVLAKKEN = [
        '.brand-dark-page',
        '.brand-blad',
        '.brand-site-nav-meer',
    ];

    /**
     * Het stijlblad zonder commentaar.
     *
     * De toelichtingen in dit bestand noemen de klassen waar ze over
     * gaan. Laat je ze staan, dan leest de test die namen mee en is hij
     * altijd groen -- ook als de regel eronder ze niet noemt.
     */
    private function stijlblad(): string
    {
        $inhoud = (string) file_get_contents(resource_path('css/app.css'));

        return (string) preg_replace('{/\*.*?\*/}s', '', $inhoud);
    }

    /**
     * De kiezers vóór een regel die deze eigenschap bevat.
     *
     * @return list<string>
     */
    private function kiezersVoor(string $inhoud, string $eigenschap): array
    {
        // Van het begin van de regel tot de eigenschap erin.
        $patroon = '/([^{}]*)\{[^{}]*'.preg_quote($eigenschap, '/').'/';

        if (! preg_match($patroon, $inhoud, $gevonden)) {
            return [];
        }

        preg_match_all('/\.[\w-]+/', $gevonden[1], $klassen);

        return array_values(array_unique($klassen[0]));
    }

    public function test_the_search_finds_something(): void
    {
        /*
         * Zonder deze test zou een kapotte reguliere expressie alles
         * hieronder groen maken: nul gevonden kiezers is ook nul fouten.
         */
        $kiezers = $this->kiezersVoor(
            $this->stijlblad(),
            'overflow-wrap: break-word',
        );

        $this->assertNotEmpty(
            $kiezers,
            'De breekregel wordt niet meer gevonden in app.css.',
        );
    }

    public function test_a_long_word_breaks_everywhere_the_owner_can_type(): void
    {
        $kiezers = $this->kiezersVoor(
            $this->stijlblad(),
            'overflow-wrap: break-word',
        );

        foreach (self::OPPERVLAKKEN as $oppervlak) {
            $this->assertContains(
                $oppervlak,
                $kiezers,
                "{$oppervlak} staat niet in de breekregel. Een lang woord ".
                'steekt daar uit zijn vak en geeft een horizontale '.
                'schuifbalk.',
            );
        }
    }

    public function test_the_box_around_that_word_may_not_grow_along(): void
    {
        /*
         * `break-word` alleen is niet genoeg: een flex-kind bepaalt zijn
         * breedte vóór het woord afbreekt. De twee regels horen dus
         * dezelfde oppervlakken te noemen.
         */
        $inhoud = $this->stijlblad();

        $breken = $this->kiezersVoor($inhoud, 'overflow-wrap: break-word');
        $dozen = $this->kiezersVoor($inhoud, 'min-width: 0');

        sort($breken);
        sort($dozen);

        $this->assertSame(
            $breken,
            $dozen,
            'De breekregel en de regel die de doos tegenhoudt noemen niet '.
                'dezelfde oppervlakken. Op het verschil werkt het half: '.
                'het woord mag afbreken, maar de doos groeit eerst mee.',
        );
    }
}
