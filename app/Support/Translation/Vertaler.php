<?php

namespace App\Support\Translation;

/**
 * Vertaalt Nederlandse tekst naar Engels.
 *
 * Er zit bewust een eigen contract tussen de applicatie en de dienst, net
 * als bij de mail: alleen MyMemoryVertaler weet iets van de dienst zelf.
 * Wisselen we ooit van dienst -- of naar een taalmodel -- dan is dat één
 * nieuwe klasse en een regel in de provider, en verandert er niets aan de
 * formulieren. Dat is niet theoretisch: deze module is begonnen met DeepL
 * en omgezet toen bleek dat daar een account voor nodig was.
 *
 * **Dit is hulp, geen vervanging.** Wat hieruit komt is een voorstel dat de
 * klant nog naleest en aanpast. De applicatie slaat het nooit ongezien op:
 * de knop vult alleen de velden in het formulier, en pas als hij zelf op
 * Opslaan drukt gaat er iets naar de database. Zie
 * docs/architecture/automatisch-vertalen.md.
 */
interface Vertaler
{
    /**
     * Of er überhaupt vertaald kan worden.
     *
     * Zonder API-sleutel is het antwoord nee, en dan verdwijnt de knop uit
     * het scherm in plaats van een fout te geven als je erop drukt.
     */
    public function beschikbaar(): bool;

    /**
     * Vertaal een aantal teksten in één keer.
     *
     * De sleutels van de array blijven staan, zodat de aanroeper weet welk
     * antwoord bij welk veld hoort. Lege waarden gaan er niet heen en komen
     * er ook niet uit: een leeg veld vertalen kost tekens en levert niets.
     *
     * @param  array<string, string|null>  $teksten  veldnaam => Nederlandse tekst
     * @return array<string, string> dezelfde veldnamen => Engelse tekst
     *
     * @throws VertaalFout als de dienst niet bereikbaar is, de sleutel niet
     *                     klopt of het tegoed op is
     */
    public function naarEngels(array $teksten): array;
}
