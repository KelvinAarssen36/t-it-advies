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

    /**
     * Een cijfer dat wij niet kunnen uitrekenen.
     *
     * Hiermee komt de klant aan een vierde cijfer dat niets met de
     * tijdlijn te maken heeft -- "12 certificeringen", bijvoorbeeld. Er
     * mogen er meer van dit soort zijn; van de andere drie precies één,
     * want twee keer "jaar ervaring" boven dezelfde lijst slaat nergens
     * op.
     */
    case Eigen = 'eigen';

    /** Het woord onder het getal, op de website. */
    public function label(): string
    {
        return match ($this) {
            self::Years => __('jaar ervaring'),
            self::Roles => __('functies'),
            self::Organisations => __('organisaties'),
            self::Eigen => __('eigen cijfer'),
        };
    }

    /** Wat er in het beheerscherm bij staat. */
    public function omschrijving(): string
    {
        return match ($this) {
            self::Years => __('Van je eerste startdatum tot vandaag. Niet de som van alle periodes, want functies overlappen.'),
            self::Roles => __('Het aantal ervaringen dat op je website staat.'),
            self::Organisations => __('Het aantal verschillende organisaties in die lijst.'),
            self::Eigen => __('Een cijfer dat je zelf bepaalt. Dit kunnen wij niet uitrekenen, dus vul er een getal bij in.'),
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

    /** Of wij dit soort uit de tijdlijn kunnen tellen. */
    public function berekenbaar(): bool
    {
        return $this !== self::Eigen;
    }

    /**
     * De soorten die de klant aan een nieuw cijfer kan hangen.
     *
     * @return array<int, array{value: string, label: string, omschrijving: string}>
     */
    public static function keuzes(): array
    {
        return array_map(
            fn (self $soort) => [
                'value' => $soort->value,
                'label' => $soort->label(),
                'omschrijving' => $soort->omschrijving(),
            ],
            self::cases(),
        );
    }
}
