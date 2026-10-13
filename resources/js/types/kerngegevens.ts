/**
 * De kerngegevens: de harde feiten over zakendoen met de eigenaar.
 *
 * Zie docs/architecture/modules/kerngegevens.md.
 */

/** Zoals de bezoeker het krijgt: de taalkeuze is al gemaakt. */
export type KerngegevenOpDeSite = {
    id: number;
    /** De sleutel uit App\Enums\CoreFactIcon; zie KerngegevenIcoon.vue. */
    soort: string;
    label: string;
    waarde: string;
    notitie: string | null;
};

/** En zoals het beheerscherm het nodig heeft: allebei de talen los. */
export type KerngegevenRij = {
    id: number;
    /** SortableList werkt met tekstsleutels. */
    key: string;
    icon: string;
    published: boolean;
    position: number;
    label_nl: string;
    label_en: string | null;
    value_nl: string;
    value_en: string | null;
    note_nl: string | null;
    note_en: string | null;
    automatisch_vertaald: boolean;
};

export type KerngegevenSoort = {
    value: string;
    label: string;
    /** Een voorbeeld van wat er bij dit soort hoort; staat op het lege scherm. */
    voorbeeld: string;
};

export type KerngegevenOpties = {
    soorten: KerngegevenSoort[];
    waardeMax: number;
    notitieMax: number;
    maximum: number;
};
