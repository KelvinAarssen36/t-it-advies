<?php

namespace App\Support;

use Carbon\CarbonImmutable;
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
     * Alleen de maand en het jaar: "mrt 2021".
     *
     * Voor een periode waarvan de dag niet is ingevuld en ook niets zou
     * betekenen -- een ervaring op de tijdlijn bijvoorbeeld. Er staat een
     * dag in de database omdat een datumkolom die nodig heeft, maar die
     * hier tonen zou een precisie suggereren die er niet is.
     */
    public static function maand(?CarbonInterface $datum): ?string
    {
        return $datum?->isoFormat('MMM YYYY');
    }

    /**
     * De twaalf maanden, in de taal van het portaal.
     *
     * Voor `MaandKiezer.vue`. Die namen komen van de server en niet uit
     * `Intl` in de browser, om dezelfde reden als alle andere datums:
     * anders hangt de taal van de lijst af van het besturingssysteem van
     * de bezoeker in plaats van van de taal die hij in het portaal heeft
     * gekozen. Zie docs/architecture/vertalingen.md.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function maanden(): array
    {
        return array_map(
            fn (int $maand) => [
                'value' => str_pad((string) $maand, 2, '0', STR_PAD_LEFT),
                'label' => (string) CarbonImmutable::create(2000, $maand, 1)?->isoFormat('MMMM'),
            ],
            range(1, 12),
        );
    }

    /**
     * De jaren waaruit je kunt kiezen, aflopend.
     *
     * Aflopend omdat je meestal iets recents invoert; een lijst die bij
     * 1965 begint laat je elke keer helemaal doorscrollen.
     *
     * `$vooruit` is er voor de geldigheidsdatum van een certificaat: die
     * ligt per definitie in de toekomst, en dan is een lijst die bij dit
     * jaar ophoudt onbruikbaar. Overal elders blijft hij nul, want een
     * ervaring die volgend jaar begint bestaat niet.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function jaren(int $vooruit = 0, int $terug = 60): array
    {
        $nu = (int) now()->format('Y');

        return array_map(
            fn (int $jaar) => ['value' => (string) $jaar, 'label' => (string) $jaar],
            range($nu + $vooruit, $nu - $terug),
        );
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
