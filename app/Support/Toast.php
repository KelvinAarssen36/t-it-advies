<?php

namespace App\Support;

use Inertia\Inertia;

/**
 * De meldingen die rechtsonder verschijnen nadat je iets hebt gedaan.
 *
 * Er zijn vijf soorten, en dat aantal is geen toeval: het zijn precies de
 * dingen die je van elkaar moet kunnen onderscheiden zonder te lezen. Een
 * melding dat er iets **weg** is hoort er anders uit te zien dan een melding
 * dat er iets bij is gekomen -- ook als je net wegkeek.
 *
 *     Toast::aangemaakt(__('De dienst is toegevoegd.'));
 *     Toast::bijgewerkt(__('De tekst is aangepast.'), __('Hij staat nu live.'));
 *     Toast::verwijderd(__('De dienst is verwijderd.'));
 *     Toast::melding(__('Er is een e-mail onderweg.'));
 *     Toast::fout(__('Dat is niet gelukt.'));
 *
 * De omschrijving is optioneel en hoort iets toe te voegen, niet de titel te
 * herhalen. Gebruik hem voor het gevolg: wat er nu op de website staat, of
 * wat de volgende stap is.
 *
 * **Een melding is geen bevestiging.** Dit is wat er is gebeurd, niet een
 * vraag of het mag. Bevestigen doe je vooraf, met een venster; zie
 * docs/architecture/meldingen.md.
 */
class Toast
{
    public const AANGEMAAKT = 'aangemaakt';

    public const BIJGEWERKT = 'bijgewerkt';

    public const VERWIJDERD = 'verwijderd';

    public const MELDING = 'melding';

    public const FOUT = 'fout';

    public static function aangemaakt(string $bericht, ?string $omschrijving = null): void
    {
        self::stuur(self::AANGEMAAKT, $bericht, $omschrijving);
    }

    public static function bijgewerkt(string $bericht, ?string $omschrijving = null): void
    {
        self::stuur(self::BIJGEWERKT, $bericht, $omschrijving);
    }

    public static function verwijderd(string $bericht, ?string $omschrijving = null): void
    {
        self::stuur(self::VERWIJDERD, $bericht, $omschrijving);
    }

    public static function melding(string $bericht, ?string $omschrijving = null): void
    {
        self::stuur(self::MELDING, $bericht, $omschrijving);
    }

    public static function fout(string $bericht, ?string $omschrijving = null): void
    {
        self::stuur(self::FOUT, $bericht, $omschrijving);
    }

    /**
     * Zet de melding klaar voor de volgende respons.
     *
     * Via `Inertia::flash` en niet via de gewone sessie-flash: die laatste
     * overleeft alleen een omleiding, en de helft van deze meldingen komt
     * uit een handeling die op dezelfde pagina blijft.
     */
    private static function stuur(string $soort, string $bericht, ?string $omschrijving): void
    {
        Inertia::flash('toast', array_filter([
            'type' => $soort,
            'message' => $bericht,
            'description' => $omschrijving,
        ], fn ($waarde) => $waarde !== null));
    }
}
