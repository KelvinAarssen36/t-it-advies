<?php

namespace App\Support;

/**
 * Een zoekterm van een gebruiker veilig in een LIKE krijgen.
 *
 * **Dit stond eerst alleen in `Experience::scopeZoek()`, en toen het
 * zoekveld op het scherm Juridisch erbij kwam ontbrak het daar.** Dat is
 * precies waarom het nu één plek is: een trucje dat je op elke nieuwe
 * zoekopdracht opnieuw moet onthouden, vergeet je een keer.
 *
 * Zie AGENTS.md en docs/security/verzoeken-van-bezoekers.md.
 */
class Zoekterm
{
    /**
     * Het ontsnappingsteken dat bij `patroon()` hoort.
     *
     * **Een uitroepteken en geen backslash.** MySQL verwerkt backslashes
     * ín een tekst tussen aanhalingstekens, SQLite niet, dus `escape '\\'`
     * betekent daar niet hetzelfde. Een uitroepteken is in allebei gewoon
     * een uitroepteken.
     */
    public const TEKEN = '!';

    /**
     * Maak er een patroon van waarin de jokertekens onschadelijk zijn.
     *
     * In een LIKE betekent `%` "wat dan ook" en `_` "één willekeurig
     * teken". Zonder dit geeft een zoekterm met een procentteken erin de
     * hele lijst terug, en vindt `jan_de_vries@...` ook
     * `janXdeYvries@...`. Geen lek -- de waarde wordt gebonden -- maar wel
     * een zoekveld dat meer teruggeeft dan je vroeg, en op een scherm waar
     * je iemands gegevens opzoekt zijn dat de regels van een ander.
     *
     * Zet er in de query altijd `escape '!'` bij; zie `TEKEN`. Zonder die
     * clausule neemt MySQL de backslash als ontsnappingsteken en kent
     * SQLite -- waar de tests op draaien -- er helemaal geen. Dan doet het
     * zoekveld in een test iets anders dan in productie, en dat is het
     * soort verschil waardoor je een test op een dag ten onrechte gelooft.
     */
    public static function patroon(string $term): string
    {
        // Het ontsnappingsteken zelf gaat als eerste, anders ontsnapt hij
        // straks de tekens die we er net voor hebben gezet.
        return '%'.str_replace(
            [self::TEKEN, '%', '_'],
            [self::TEKEN.self::TEKEN, self::TEKEN.'%', self::TEKEN.'_'],
            $term,
        ).'%';
    }
}
