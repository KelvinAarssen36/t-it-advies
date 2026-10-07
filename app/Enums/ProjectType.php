<?php

namespace App\Enums;

/**
 * Wat voor soort werk een project was.
 *
 * **De module heet Projecten, maar niet elk item is een project.** Het
 * kan een interim-opdracht zijn, een migratie, een audit of een stage.
 * Dit veld laat de eigenaar per item het juiste woord kiezen, zodat de
 * module breed bruikbaar is zonder dat er een tweede module naast komt.
 *
 * **Een vaste lijst met één uitweg, en geen vrij tekstveld.** Dat is
 * dezelfde afweging als bij de onderwerpen van het contactformulier:
 * een vrij veld levert binnen een jaar "Migratie", "migratie" en
 * "Migratieproject" op, en dan staan er drie badges op de site die
 * hetzelfde betekenen. Een vaste lijst vertaalt bovendien centraal --
 * de eigenaar hoeft "Consultancy" niet tien keer in twee talen te
 * typen.
 *
 * De uitweg is `Anders`: dan vult hij zijn eigen label in, in beide
 * talen. Zie `Project::typeLabel()`.
 *
 * De sleutels zijn Nederlands en beschrijvend, zoals overal in dit
 * project. Een nieuw soort erbij is één case en één regel in `label()`;
 * de database hoeft niet mee.
 *
 * Zie docs/architecture/modules/projecten.md.
 */
enum ProjectType: string
{
    case Project = 'project';
    case Opdracht = 'opdracht';
    case Interim = 'interim';
    case Consultancy = 'consultancy';
    case Implementatie = 'implementatie';
    case Migratie = 'migratie';
    case Audit = 'audit';
    case Transformatie = 'transformatie';
    case Stage = 'stage';
    case Anders = 'anders';

    /** Het woord dat op de badge komt, in de taal van de lezer. */
    public function label(): string
    {
        return match ($this) {
            self::Project => __('Project'),
            self::Opdracht => __('Opdracht'),
            self::Interim => __('Interim-opdracht'),
            self::Consultancy => __('Consultancy'),
            self::Implementatie => __('Implementatie'),
            self::Migratie => __('Migratie'),
            self::Audit => __('Audit'),
            self::Transformatie => __('Transformatie'),
            self::Stage => __('Stage'),
            self::Anders => __('Anders'),
        };
    }

    /**
     * Of dit soort een eigen label van de eigenaar vraagt.
     *
     * Eén plek, want de validatie, het model en het scherm stellen
     * dezelfde vraag -- en als die drie uiteen lopen staat er "Anders"
     * op de website.
     */
    public function eigenLabel(): bool
    {
        return $this === self::Anders;
    }

    /**
     * De keuzes voor de keuzelijst in het beheerscherm.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function opties(): array
    {
        return array_map(
            fn (self $soort) => [
                'value' => $soort->value,
                'label' => $soort->label(),
            ],
            self::cases(),
        );
    }
}
