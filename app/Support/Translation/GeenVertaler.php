<?php

namespace App\Support\Translation;

/**
 * De vertaler die er niet is.
 *
 * Staat er geen API-sleutel ingesteld, dan komt deze in de container te
 * staan. Hij zegt netjes "niet beschikbaar", waarop de knop uit het scherm
 * verdwijnt en de route een 404 geeft.
 *
 * **Waarom een lege klasse en geen null.** Zou de container niets
 * teruggeven, dan moet elke aanroeper eerst controleren of er wel een
 * vertaler ís -- en die controle vergeet iemand een keer, met een foutmelding
 * op het scherm van de klant als gevolg. Nu is er altijd een vertaler; hij
 * kan alleen niets.
 *
 * Dit is ook de stand in de tests en in ontwikkeling zonder sleutel: het
 * hele portaal werkt dan gewoon, alleen de knop staat er niet. Zie
 * docs/architecture/automatisch-vertalen.md.
 */
class GeenVertaler implements Vertaler
{
    public function beschikbaar(): bool
    {
        return false;
    }

    public function naarEngels(array $teksten): array
    {
        throw new VertaalFout(VertaalFout::NIET_INGESTELD);
    }
}
