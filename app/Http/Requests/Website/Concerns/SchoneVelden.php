<?php

namespace App\Http\Requests\Website\Concerns;

use Illuminate\Support\Carbon;

/**
 * Twee omzettingen die elk formulier van de website nodig heeft.
 *
 * Ze zijn allebei klein, en ze zijn allebei het soort ding dat op de
 * tweede plek net iets anders wordt overgeschreven. Vandaar één plek.
 */
trait SchoneVelden
{
    /**
     * Een optioneel tekstveld: leeg is null en niet "".
     *
     * Dat onderscheid doet ertoe. De website beslist op `filled()` of
     * een veld getoond wordt, en een lege string is gevuld -- dan krijg
     * je een kopje boven niets.
     */
    protected function tekst(string $veld): ?string
    {
        $waarde = $this->string($veld)->trim()->toString();

        return $waarde === '' ? null : $waarde;
    }

    /**
     * "2021-03" wordt 1 maart 2021; de dag betekent niets.
     *
     * Het formulier laat maand en jaar kiezen, want dat is wat er bekend
     * is -- de dag zou een precisie suggereren die er niet is. In de
     * database staat het toch als datum, zodat je erop kunt sorteren en
     * vergelijken.
     */
    protected function maand(string $veld): ?Carbon
    {
        $waarde = $this->string($veld)->toString();

        return $waarde === ''
            ? null
            : Carbon::createFromFormat('Y-m-d', $waarde.'-01')->startOfDay();
    }
}
