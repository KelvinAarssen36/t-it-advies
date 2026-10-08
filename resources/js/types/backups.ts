/**
 * Wat het scherm Beheer → Back-ups van de server krijgt.
 *
 * Zie docs/operations/back-ups.md.
 */

export type BackupSoort = 'handmatig' | 'automatisch' | 'geupload';

export type BackupRij = {
    id: number;
    naam: string;
    /**
     * De vier cijfers die je overtypt om terug te zetten.
     *
     * Geen geheim: hij staat in het venster om overgetypt te worden. Hij
     * maakt de handeling bewust; buiten houden doet de authenticator.
     */
    bevestigcode: string | null;
    /** De bovenste in de lijst: de verste stand die je hebt. */
    nieuwste: boolean;
    /** De oudste die weg zou mogen -- alleen getoond als de lijst vol is. */
    oudste: boolean;
    /** Zelf gemaakt of geüpload, en dus meetellend voor het maximum. */
    eigen: boolean;
    soort: BackupSoort;
    grootte: number;
    groottePrettig: string;
    /** Het aantal rijen per tabel, zoals het bij het maken was. */
    aantallen: Record<string, number>;
    totaal: number;
    vastgezet: boolean;
    /** Ligt het bestand er nog? Zonder bestand valt er niets terug te zetten. */
    bestaat: boolean;
    /**
     * Wanneer de inhoud is vastgelegd.
     *
     * **Niet wanneer de rij is gemaakt.** Bij een geüpload bestand lopen
     * die twee uiteen: de rij is van vandaag, de inhoud van januari. De
     * volgorde en de merkjes "Nieuwste" en "Oudste" horen bij de inhoud.
     */
    gemaakt: string;
    gemaaktGeleden: string | null;
    /** Dezelfde datum voluit, want "twee dagen geleden" is te vaag om op te kiezen. */
    gemaaktOp: string;
    gecontroleerd: string | null;
    gecontroleerdGeleden: string | null;
    gedownload: string | null;
};

export type BackupCijfers = {
    ruimte: string;
    /** Hoeveel eigen back-ups er mogen staan; veiligheidskopieën tellen niet mee. */
    maximumEigen: number;
    maximumVast: number;
    /** Hoeveel er nu staan die meetellen. */
    eigen: number;
    /** Zit de lijst vol? Dan moet er eerst een weg. */
    vol: boolean;
    /** Hoeveel er te veel staan. Boven nul gaat het opruimvenster meteen open. */
    teveel: number;
    /** Hoeveel veiligheidskopieën er bewaard blijven; die ruimen zichzelf op. */
    maximumAutomatisch: number;
    vastgezet: number;
    /** Staat de nieuwste back-up alleen nog op deze server? */
    nooitGedownload: boolean;
    oud: boolean;
    oudNaDagen: number;
};

/** Eén regel van het verschil: hoeveel er nu zijn, en hoeveel straks. */
export type VerschilRegel = {
    tabel: string;
    label: string;
    nu: number;
    straks: number;
    verschil: number;
};

export type Verschil = {
    regels: VerschilRegel[];
    erbij: number;
    eraf: number;
    /** Komt deze back-up van een ander schema? Dan kunnen er velden ontbreken. */
    anderSchema: boolean;
    /** Of zelfs van een andere installatie -- bijna altijd een vergissing. */
    andereSite: boolean;
    bestaat: boolean;
};
