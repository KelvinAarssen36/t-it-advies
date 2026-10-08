<?php

namespace App\Support\Backup;

/**
 * Wat er uit een controle van een back-upbestand komt.
 *
 * Een eigen klasje en geen losse `bool`: bij een afkeuring wil je weten
 * wáárom, en die reden gaat ongewijzigd naar het scherm. "Het bestand is
 * beschadigd" zonder te zeggen waar, is geen melding maar een schouderophalen.
 *
 * Zie docs/operations/back-ups.md.
 */
class Controleuitkomst
{
    /**
     * @param  array<int, string>  $opmerkingen  dingen die kloppen maar het vermelden waard zijn
     */
    public function __construct(
        public readonly bool $geslaagd,
        public readonly ?string $melding = null,
        public readonly array $opmerkingen = [],
        public readonly bool $proefGedraaid = false,
    ) {}

    /**
     * @param  array<int, string>  $opmerkingen
     */
    public static function goed(array $opmerkingen = [], bool $proefGedraaid = false): self
    {
        return new self(true, null, $opmerkingen, $proefGedraaid);
    }

    /**
     * @param  array<int, string>  $opmerkingen
     */
    public static function fout(string $melding, array $opmerkingen = []): self
    {
        return new self(false, $melding, $opmerkingen);
    }
}
