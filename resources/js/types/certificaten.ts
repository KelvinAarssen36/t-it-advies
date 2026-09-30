/**
 * De certificaten en de opleidingen op de landingspagina.
 *
 * Twee vormen van hetzelfde item, dezelfde verdeling als bij de diensten
 * en de ervaring:
 *
 * - `CertificaatOpDeSite` is wat de **bezoeker** krijgt. De keuze tussen
 *   Nederlands en Engels is op de server al gemaakt, en wat leeg mag
 *   blijven is gewoon weg.
 * - `CertificaatRij` is wat het **beheerscherm** krijgt. Daar staan
 *   allebei de talen los in, want het formulier bewerkt ze allebei.
 *
 * Zou de site dezelfde vorm krijgen als het portaal, dan moet elk
 * component zelf beslissen welk veld terugvalt op het Nederlands en welk
 * niet -- en dan staat er vroeg of laat half Nederlands op een Engelse
 * pagina. Die regels staan in App\Models\Certificate.
 *
 * Zie docs/architecture/modules/certificaten.md.
 */

export type CertificaatOpDeSite = {
    id: number;
    naam: string;
    uitgever: string;
    /** Het logo van de uitgever, of null; dan komt er een pictogram. */
    logo: string | null;
    /** Alleen het jaartal, voor op de tegel. */
    jaar: string;
    /** "maart 2024", in de taal van de bezoeker. */
    behaald: string | null;
    /**
     * "maart 2027", of null.
     *
     * Null betekent twee dingen tegelijk: het verloopt niet, óf het is
     * al verlopen. Dat verschil is op de website met opzet niet te
     * zien -- er staat nooit dat een certificaat verlopen is. Zie
     * HomeController.
     */
    geldigTot: string | null;
    nummer: string | null;
    toelichting: string | null;
    /**
     * Of er iets te lezen valt als je erop klikt.
     *
     * Is dit false, dan is de tegel geen knop. Dezelfde regel als bij
     * een dienst zonder lang verhaal: een venster dat opengaat met niets
     * erin is erger dan geen venster.
     */
    details: boolean;
};

export type OpleidingOpDeSite = {
    id: number;
    naam: string;
    instelling: string;
    niveau: string | null;
    /** "2018 — 2022", of "2022 — heden". */
    periode: string;
};

export type CertificaatRij = {
    id: number;
    /** Dezelfde waarde als `id`, als tekst. SortableList vraagt erom. */
    key: string;
    published: boolean;
    logo: string | null;
    title_nl: string;
    title_en: string | null;
    issuer: string;
    /** "2024-03", zoals de maandkiezer hem wil. */
    issued_on: string;
    /** "maart 2024", zoals de lijst hem leest. */
    behaald: string | null;
    expires_on: string | null;
    geldig_tot: string | null;
    verlopen: boolean;
    credential_id: string | null;
    body_nl: string | null;
    body_en: string | null;
    automatisch_vertaald: boolean;
};

export type OpleidingRij = {
    id: number;
    published: boolean;
    title_nl: string;
    title_en: string | null;
    institution: string;
    level_nl: string | null;
    level_en: string | null;
    started_on: string;
    ended_on: string | null;
    periode: string;
    automatisch_vertaald: boolean;
};

export type CertificatenOpties = {
    maanden: Array<{ value: string; label: string }>;
    jaren: Array<{ value: string; label: string }>;
    /** Dezelfde lijst maar met de toekomst erbij, voor "geldig tot". */
    jarenVooruit: Array<{ value: string; label: string }>;
};

/** De kop boven het blok, zoals de bezoeker hem krijgt. */
export type CertificatenKop = {
    opschrift: string | null;
    titel: string;
    inleiding: string | null;
};
