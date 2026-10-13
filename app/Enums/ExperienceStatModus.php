<?php

namespace App\Enums;

/**
 * Wat er met een cijfer boven de tijdlijn gebeurt.
 *
 * **Dit bestond eerst niet, en daar zat het probleem.** Een leeg
 * getalveld betekende "reken het uit", en dus kon de klant niet zeggen
 * dat hij een cijfer helemaal niet wil. Eén veld met twee betekenissen
 * kan geen derde aan.
 *
 * Nu zegt de modus wat er hoort te gebeuren en zegt `value` alleen nog
 * hoeveel:
 *
 * - **Automatisch** -- wij tellen het uit de tijdlijn. De stand waarin een
 *   cijfer vanzelf blijft kloppen zodra er een functie bij komt, en
 *   daarom de standaard.
 * - **Eigen** -- het getal dat de klant zelf invulde.
 * - **Verborgen** -- staat niet op de website. Het cijfer blijft wel
 *   bestaan, met zijn naam en zijn getal, zodat hij hem later weer aan kan
 *   zetten zonder alles opnieuw in te vullen.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
enum ExperienceStatModus: string
{
    case Automatisch = 'automatisch';
    case Eigen = 'eigen';
    case Verborgen = 'verborgen';

    public function label(): string
    {
        return match ($this) {
            self::Automatisch => __('Automatisch'),
            self::Eigen => __('Eigen getal'),
            self::Verborgen => __('Niet tonen'),
        };
    }

    public function omschrijving(): string
    {
        return match ($this) {
            self::Automatisch => __('Wij tellen het uit je tijdlijn, dus het blijft vanzelf kloppen.'),
            self::Eigen => __('Het getal dat je zelf invult blijft staan, ook als je tijdlijn verandert.'),
            self::Verborgen => __('Dit cijfer staat niet op je website. Je naam en getal blijven bewaard.'),
        };
    }

    /**
     * De keuzes voor een keuzelijst, op volgorde.
     *
     * @return array<int, array{value: string, label: string, omschrijving: string}>
     */
    public static function keuzes(): array
    {
        return array_map(
            fn (self $modus) => [
                'value' => $modus->value,
                'label' => $modus->label(),
                'omschrijving' => $modus->omschrijving(),
            ],
            self::cases(),
        );
    }
}
