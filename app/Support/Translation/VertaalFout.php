<?php

namespace App\Support\Translation;

use RuntimeException;

/**
 * Het vertalen is niet gelukt.
 *
 * De `reden` is een korte sleutel voor onszelf -- voor het logboek en om te
 * kunnen zien of het aan het tegoed of aan de verbinding lag. Wat de klant
 * te zien krijgt staat in `melding()` en zegt vooral wat hij nu kan doen;
 * "HTTP 456" helpt hem niet verder.
 */
class VertaalFout extends RuntimeException
{
    public const NIET_INGESTELD = 'niet-ingesteld';

    public const GEEN_VERBINDING = 'geen-verbinding';

    public const TEGOED_OP = 'tegoed-op';

    public const SLEUTEL_ONGELDIG = 'sleutel-ongeldig';

    public const ONBEKEND = 'onbekend';

    public function __construct(public readonly string $reden, string $technisch = '')
    {
        parent::__construct($technisch !== '' ? $technisch : $reden);
    }

    /**
     * De zin die de klant te zien krijgt.
     *
     * Elke variant eindigt met wat hij nu kan doen. Een foutmelding zonder
     * uitweg laat iemand alleen maar opnieuw op dezelfde knop drukken.
     */
    public function melding(): string
    {
        return match ($this->reden) {
            self::TEGOED_OP => __('Het vertaaltegoed voor deze maand is op. Vul de Engelse tekst zelf in, of probeer het volgende maand opnieuw.'),
            self::GEEN_VERBINDING => __('De vertaaldienst is even niet bereikbaar. Probeer het zo nog eens, of vul de Engelse tekst zelf in.'),
            self::SLEUTEL_ONGELDIG => __('De vertaaldienst weigert onze sleutel. Neem even contact op; je kunt de Engelse tekst intussen zelf invullen.'),
            default => __('Het automatisch vertalen lukt nu niet. Vul de Engelse tekst zelf in, of probeer het later opnieuw.'),
        };
    }
}
