<?php

namespace App\Enums;

/**
 * Wat er met een onderdeel is gebeurd.
 *
 * Bewust maar drie waarden. Het logboek beantwoordt één vraag -- "wat is er
 * met de inhoud van de website gedaan" -- en daar zijn aanmaken, wijzigen en
 * verwijderen het complete antwoord op.
 *
 * Alles wat over toegang gaat (inloggen, 2FA, rollen, geblokkeerde pogingen)
 * hoort in het beveiligingslogboek en niet hier. Zie
 * docs/security/logging.md.
 */
enum ActivityAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Created => __('Aangemaakt'),
            self::Updated => __('Gewijzigd'),
            self::Deleted => __('Verwijderd'),
        };
    }
}
