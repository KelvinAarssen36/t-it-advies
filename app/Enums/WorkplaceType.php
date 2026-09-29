<?php

namespace App\Enums;

/**
 * Waar het werk plaatsvond: op locatie, hybride of op afstand.
 *
 * Los van de plaats zelf. "Utrecht" zegt waar het kantoor stond, "Op
 * afstand" zegt dat je er nauwelijks kwam -- dat zijn twee verschillende
 * dingen, en LinkedIn houdt ze om die reden ook uit elkaar.
 *
 * Net als het dienstverband centraal vertaald; zie EmploymentType voor
 * waarom.
 */
enum WorkplaceType: string
{
    case OnSite = 'op-locatie';
    case Hybrid = 'hybride';
    case Remote = 'op-afstand';

    public function label(): string
    {
        return match ($this) {
            self::OnSite => __('Op locatie'),
            self::Hybrid => __('Hybride'),
            self::Remote => __('Op afstand'),
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function opties(): array
    {
        return array_map(
            fn (self $soort) => ['value' => $soort->value, 'label' => $soort->label()],
            self::cases(),
        );
    }
}
