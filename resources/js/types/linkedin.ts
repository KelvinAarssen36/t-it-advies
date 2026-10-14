import type { SectieKop } from '@/types/secties';

/**
 * Het LinkedIn-blok. Zie docs/architecture/modules/linkedin.md.
 */

/**
 * Wat de bezoeker krijgt: de kop en de tekst op de knop, al in zijn taal.
 *
 * Het adres staat hier niet in; dat is de gedeelde prop `linkedin`, zodat
 * de knop in de hero en die in dit blok uit één bron komen.
 */
export type LinkedinBlok = {
    kop: SectieKop;
    knop: string;
};

/** De knoptekst zoals het beheerscherm hem bewerkt: beide talen los. */
export type LinkedinInstelling = {
    button_nl: string | null;
    button_en: string | null;
    automatisch_vertaald: boolean;
};

/** Wat de knop zegt als er niets is ingevuld, per taal. */
export type LinkedinStandaardKnop = {
    nl: string;
    en: string;
};

export type LinkedinOpties = {
    knopMax: number;
};
