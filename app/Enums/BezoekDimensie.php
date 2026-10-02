<?php

namespace App\Enums;

/**
 * De uitsplitsingen van de bezoekcijfers.
 *
 * Drie soorten, en elk antwoordt op een vraag die de eigenaar echt heeft:
 *
 * | Soort         | De vraag erachter                                      |
 * | ------------- | ------------------------------------------------------ |
 * | **Verwijzer** | Levert mijn LinkedIn daadwerkelijk bezoek op?          |
 * | **Apparaat**  | Is de mobiele versie inderdaad de belangrijkste?       |
 * | **Taal**      | Is het Engels de moeite waard?                         |
 *
 * De waarde van dit enum gaat in de kolom `kind` van
 * `site_day_dimensions`. Een vierde soort is dus een `case` erbij en geen
 * migratie.
 *
 * **Hier staat geen `label()`, anders dan bij de andere enums in dit
 * project.** De opschriften van de uitsplitsingen staan in de frontend,
 * want daar staan ook de opschriften van de wáárden ('mobiel',
 * 'linkedin'). Die twee bij elkaar houden is leesbaarder dan de helft hier
 * en de helft daar.
 *
 * Zie docs/architecture/bezoekcijfers.md.
 */
enum BezoekDimensie: string
{
    case Verwijzer = 'verwijzer';
    case Apparaat = 'apparaat';
    case Taal = 'taal';
}
