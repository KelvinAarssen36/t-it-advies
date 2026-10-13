<?php

namespace App\Enums;

/**
 * Hoe het contact op de website wordt aangeboden.
 *
 * Twee smaken, en de keuze is van de eigenaar: het formulier onderaan de
 * landingspagina, of alleen een knop daar en het formulier op een eigen
 * pagina.
 *
 * **Dat is geen smaakverschil.** Een formulier onderaan de pagina nodigt
 * uit -- je scrolt erheen en het staat er. Een eigen pagina houdt de
 * landing korter en is te delen als link, wat handig is als hij hem op
 * LinkedIn zet. Welke beter werkt hangt af van wat hij met de site wil, en
 * dat weet hij beter dan wij.
 *
 * Zie docs/architecture/modules/contact.md.
 */
enum ContactWeergave: string
{
    case OpDePagina = 'pagina';
    case EigenPagina = 'eigen';

    public function label(): string
    {
        return __($this->sleutel());
    }

    public function sleutel(): string
    {
        return match ($this) {
            self::OpDePagina => 'Het formulier onderaan je website',
            self::EigenPagina => 'Een knop naar een eigen contactpagina',
        };
    }

    public function omschrijving(): string
    {
        return match ($this) {
            self::OpDePagina => __('Bezoekers scrollen naar beneden en het formulier staat er. De kortste weg naar een bericht.'),
            self::EigenPagina => __('Je website blijft korter en je krijgt een eigen adres dat je kunt delen, bijvoorbeeld op LinkedIn.'),
        };
    }

    /**
     * De twee keuzes voor het instellingenvenster.
     *
     * @return array<int, array<string, string>>
     */
    public static function opties(): array
    {
        return array_map(fn (self $weergave) => [
            'value' => $weergave->value,
            'label' => $weergave->label(),
            'omschrijving' => $weergave->omschrijving(),
        ], self::cases());
    }
}
