<?php

namespace App\Enums;

/**
 * De drie cijfers boven de tijdlijn op de website.
 *
 * **Welke cijfers er bestaan staat hier en niet in de database.** Dat is
 * dezelfde keuze als bij PageSectionKey, en om dezelfde reden: het is code,
 * geen inhoud. Wat de klant beheert is de *waarde* -- en zelfs die alleen
 * als hij het niet eens is met wat wij uitrekenen.
 *
 * Standaard worden ze **berekend uit de tijdlijn**. Dat is wat je wilt: de
 * cijfers kloppen dan vanzelf zodra er een functie bij komt, zonder dat
 * iemand eraan hoeft te denken. Laat de klant een veld leeg, dan blijft het
 * berekend; vult hij iets in, dan staat dat er.
 *
 * Een nieuw cijfer toevoegen kost drie dingen:
 *
 * 1. een case hier, met een label en een omschrijving;
 * 2. een regel in App\Support\Loopbaan::berekend();
 * 3. `php artisan db:seed --class=ExperienceStatSeeder`, of gewoon de
 *    volgende deploy.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
enum ExperienceStatKey: string
{
    case Years = 'jaren';
    case Roles = 'functies';
    case Organisations = 'organisaties';

    /** Het woord onder het getal, op de website. */
    public function label(): string
    {
        return match ($this) {
            self::Years => __('jaar ervaring'),
            self::Roles => __('functies'),
            self::Organisations => __('organisaties'),
        };
    }

    /** Wat er in het beheerscherm bij staat. */
    public function omschrijving(): string
    {
        return match ($this) {
            self::Years => __('Van je eerste startdatum tot vandaag. Niet de som van alle periodes, want functies overlappen.'),
            self::Roles => __('Het aantal ervaringen dat op je website staat.'),
            self::Organisations => __('Het aantal verschillende organisaties in die lijst.'),
        };
    }

    /**
     * In de volgorde waarin ze op de website staan.
     *
     * @return array<int, self>
     */
    public static function opVolgorde(): array
    {
        return self::cases();
    }
}
