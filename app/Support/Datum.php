<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Datums en tijden zoals je ze in het portaal leest.
 *
 * Er stond overal `toDateTimeString()`, en dat geeft `2026-09-28 10:14:03`.
 * Dat is het formaat van een database, niet van een mens: in Nederland komt
 * de dag eerst, en de seconden interesseren niemand die in een logboek
 * kijkt.
 *
 * **De maand staat er met letters, in allebei de talen.** `28-09-2026` is
 * voor een Nederlander duidelijk, maar een Engelstalige leest daar
 * gemakkelijk een maand in die er niet staat -- en andersom net zo. Met
 * "28 sep 2026" bestaat die verwarring niet, en het scant sneller ook.
 *
 * Carbon vertaalt de maandnamen zelf, mits zijn taal is gezet. Dat gebeurt
 * in [`SetLocale`](../Http/Middleware/SetLocale.php), op dezelfde plek waar
 * de taal van de applicatie wordt bepaald.
 *
 * Zie docs/architecture/vertalingen.md.
 */
class Datum
{
    /**
     * Dag, maand, jaar en de tijd: "28 sep 2026, 10:14".
     *
     * Voor een logboekregel, waar je wilt weten wánneer precies.
     */
    public static function tijdstip(?CarbonInterface $datum): ?string
    {
        return $datum?->isoFormat('D MMM YYYY, HH:mm');
    }

    /**
     * Alleen de dag: "28 sep 2026".
     *
     * Voor iets dat een dag betreft en geen moment -- wanneer een account
     * is aangemaakt bijvoorbeeld. De tijd erbij suggereert een precisie die
     * niemand nodig heeft.
     */
    public static function dag(?CarbonInterface $datum): ?string
    {
        return $datum?->isoFormat('D MMM YYYY');
    }

    /**
     * Hoe lang geleden: "2 uur geleden".
     *
     * Dit is meestal wat je écht wilt weten, en het exacte tijdstip is de
     * tweede vraag. Zet ze daarom naast elkaar in plaats van te kiezen.
     */
    public static function geleden(?CarbonInterface $datum): ?string
    {
        return $datum?->diffForHumans();
    }
}
