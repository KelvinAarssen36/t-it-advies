<?php

namespace App\Support\Security;

/**
 * Eén signaal uit de alarmering: wat er is gezien, hoe vaak, en vanaf welk
 * aantal we het de moeite waard vinden om iemand wakker te maken.
 *
 * De drempel gaat mee in het signaal zelf, zodat de melding kan zeggen "37
 * van de 25" in plaats van alleen een getal. Zonder die context weet de
 * ontvanger niet of hij zich zorgen moet maken.
 */
final readonly class Anomaly
{
    /**
     * @param  string  $key  stabiele sleutel, gebruikt voor de afkoeltijd
     * @param  array<int, string>  $details  korte regels, nooit iets gevoeligs
     */
    public function __construct(
        public string $key,
        public string $title,
        public int $count,
        public int $threshold,
        public array $details = [],
    ) {}

    public function exceedsThreshold(): bool
    {
        return $this->threshold > 0 && $this->count >= $this->threshold;
    }
}
