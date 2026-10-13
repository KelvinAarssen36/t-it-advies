<?php

namespace App\Enums;

/**
 * De tijdzones waaruit de klok op het dashboard kan kiezen.
 *
 * **Een vaste lijst en niet alle vierhonderd zones van de wereld.** Een
 * keuzelijst met elke IANA-zone erin is onbruikbaar: je scrolt langs
 * `America/Argentina/Catamarca` op zoek naar Amsterdam. Dit zijn de vijf
 * die ergens op slaan voor dit bedrijf, met Nederland als standaard.
 *
 * **De waarde is de IANA-naam en niet een offset in uren.** Dat is geen
 * detail: `Europe/Amsterdam` weet zelf wanneer de zomertijd ingaat, `+1`
 * niet. Een klok die in de zomer een uur mis staat is erger dan geen klok.
 *
 * Komt er ooit een zone bij, dan is dat één `case` hier plus een regel in
 * `label()`. De validatie en de keuzelijst gaan automatisch mee.
 *
 * Zie docs/architecture/dashboard.md.
 */
enum DashboardTimezone: string
{
    case Amsterdam = 'Europe/Amsterdam';
    case Londen = 'Europe/London';
    case NewYork = 'America/New_York';
    case LosAngeles = 'America/Los_Angeles';
    case Tokio = 'Asia/Tokyo';

    /** De zone waar het portaal op staat zolang niemand iets koos. */
    public const STANDAARD = self::Amsterdam;

    /**
     * Wat er in de keuzelijst staat.
     *
     * De stad én het land, want "Londen" alleen is duidelijk en
     * "Los Angeles" vraagt om context. Nederland staat er met het land
     * vooraan, want dat is de thuiszone en geen stad die je uitzoekt.
     */
    public function label(): string
    {
        return match ($this) {
            self::Amsterdam => __('Nederland (Amsterdam)'),
            self::Londen => __('Verenigd Koninkrijk (Londen)'),
            self::NewYork => __('Oostkust VS (New York)'),
            self::LosAngeles => __('Westkust VS (Los Angeles)'),
            self::Tokio => __('Japan (Tokio)'),
        };
    }

    /**
     * De keuzes voor een `BrandSelect`.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function opties(): array
    {
        return array_map(
            fn (self $zone) => [
                'value' => $zone->value,
                'label' => $zone->label(),
            ],
            self::cases(),
        );
    }
}
