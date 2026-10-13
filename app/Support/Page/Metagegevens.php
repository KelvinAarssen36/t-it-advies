<?php

namespace App\Support\Page;

/**
 * De titel en de omschrijving die de server in de `<head>` zet.
 *
 * **Waarom dit bestaat terwijl Inertia al een titel zet.** Dat doet hij in
 * de browser, ná het laden van het JavaScript. WhatsApp, LinkedIn, Slack en
 * de crawlers van AI-diensten voeren dat JavaScript niet uit: die halen de
 * HTML op, lezen de `<head>` en gaan weer weg. Zonder deze gegevens zien ze
 * alleen de sitenaam en het standaardplaatje -- bij élke link, of je nu de
 * voorpagina deelt of een project.
 *
 * **Dit is niet hetzelfde als server-side rendering.** SSR staat uit; zie
 * beslissing 018. Een linkvoorbeeld heeft geen gerenderde pagina nodig maar
 * drie regels in de `<head>`, en die kan de server zelf zetten -- ook op
 * gedeelde hosting, waar geen proces mag blijven draaien.
 *
 * De waarden gaan als gewone prop mee en worden in app.blade.php
 * uitgelezen.
 */
class Metagegevens
{
    /**
     * De langste omschrijving die nog helemaal wordt getoond.
     *
     * Google kapt rond de 155 tekens af, LinkedIn eerder. Langer mag, maar
     * wat erachter staat leest niemand -- en een zin die middenin ophoudt
     * ziet er slordiger uit dan een korte zin.
     */
    public const OMSCHRIJVING_MAX = 160;

    /**
     * @return array{titel: string, omschrijving: string|null}
     */
    public static function maak(string $titel, ?string $omschrijving = null): array
    {
        return [
            'titel' => trim($titel),
            'omschrijving' => self::kort($omschrijving),
        ];
    }

    /**
     * Kort een omschrijving af op een woordgrens.
     *
     * Met een beletselteken erachter, zodat je ziet dat er meer was. Midden
     * in een woord afkappen leest als een fout.
     */
    private static function kort(?string $tekst): ?string
    {
        $tekst = trim((string) $tekst);

        if ($tekst === '') {
            return null;
        }

        // Nieuwe regels zijn in een meta-tag geen opmaak maar ruis.
        $tekst = (string) preg_replace('/\s+/u', ' ', $tekst);

        if (mb_strlen($tekst) <= self::OMSCHRIJVING_MAX) {
            return $tekst;
        }

        $afgekapt = mb_substr($tekst, 0, self::OMSCHRIJVING_MAX - 1);
        $spatie = mb_strrpos($afgekapt, ' ');

        if ($spatie !== false) {
            $afgekapt = mb_substr($afgekapt, 0, $spatie);
        }

        return rtrim($afgekapt, " \t\n\r\0\x0B.,;:").'…';
    }
}
