/**
 * De tijdlijn met ervaringen.
 *
 * Er zijn twee vormen van hetzelfde item, en dat is met opzet:
 *
 * - `ErvaringRij` is wat het **beheerscherm** krijgt. Daar staan allebei de
 *   talen los in, want het formulier bewerkt ze allebei.
 * - `ErvaringOpDeSite` is wat de **publieke tijdlijn** krijgt. Daar is de
 *   taal al gekozen door de server, en wat leeg mag blijven is gewoon weg.
 *
 * Zou de site dezelfde vorm krijgen als het portaal, dan moet elk
 * Vue-component zelf beslissen welk veld terugvalt op het Nederlands en
 * welk niet -- en dan staat er vroeg of laat half Nederlands op een Engelse
 * pagina. Die regels staan in App\Models\Experience.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */

export type ErvaringRij = {
    id: number;
    icon: string;

    /** Staat hij op de website? Het schuifje in het overzicht zet dit om. */
    published: boolean;

    /** De volledige URL van het geüploade logo, of null. */
    logo: string | null;

    role_nl: string;
    role_en: string | null;
    organisation: string;
    organisation_url: string | null;
    employment: string | null;
    workplace: string | null;
    location_nl: string | null;
    location_en: string | null;
    description_nl: string | null;
    description_en: string | null;

    /** Als "2021-03"; de dag doet er niet toe. */
    started_on: string;
    ended_on: string | null;

    /** Kant-en-klaar opgemaakt door de server, in de taal van het portaal. */
    periode: string;
    duur: string;
    loopt: boolean;

    /** Of de verplichte Engelse functietitel er is. */
    vertaald: boolean;
    automatisch_vertaald: boolean;
    vertaald_op: string | null;
};

/**
 * Eén ervaring op zijn eigen pagina in het portaal.
 *
 * Alles van de rij, plus de dingen die je alleen ziet als je doorklikt: de
 * uitgeschreven labels en wanneer er voor het laatst iets veranderde.
 */
export type ErvaringDetail = ErvaringRij & {
    icon_label: string;
    employment_label: string | null;
    workplace_label: string | null;
    /** Alleen gevuld als het een veilige http(s)-link is. */
    website: string | null;
    gewijzigd: string | null;
};

/**
 * De ervaring ervoor en erna op de tijdlijn, om doorheen te bladeren.
 *
 * De periode gaat mee omdat hij op de knop staat: bij iemand die drie
 * keer "Service Manager" is geweest zegt alleen een functietitel niets
 * over waar je heen gaat.
 */
export type ErvaringBuur = {
    id: number;
    functie: string;
    organisatie: string;
    periode: string;
};

export type ErvaringBuren = {
    vorige: ErvaringBuur | null;
    volgende: ErvaringBuur | null;
};

export type ErvaringOpDeSite = {
    id: number;
    icon: string;
    functie: string;
    organisatie: string;
    website: string | null;
    /** Het logo van de organisatie; null betekent: gebruik het pictogram. */
    logo: string | null;
    /**
     * De losse delen van de metaregel: plaats, werkvorm, dienstverband.
     *
     * Als **array** en niet als één string met puntjes ertussen. De
     * scheidingstekens zet de CSS, zodat er nooit een puntje overblijft
     * voor een deel dat de klant niet heeft ingevuld. Precies dat soort
     * restje is waar dit onderdeel niet aan mag lijden.
     */
    meta: string[];
    periode: string;
    duur: string;
    /** Het beginjaar, alleen om de tijdlijn in groepen te zetten. */
    jaar: string;
    loopt: boolean;
    beschrijving: string | null;
};

export type Optie = {
    value: string;
    label: string;
};

/**
 * De drie cijfers boven de tijdlijn op de website.
 *
 * Ze worden op de server geteld: dan staat er niet twee keer dezelfde
 * rekensom in de applicatie. Zie App\Support\Loopbaan.
 */
export type ErvaringCijfer = {
    waarde: number;
    label: string;
};

/**
 * Hetzelfde cijfer, zoals het beheerscherm het toont.
 *
 * `waarde` is wat de klant zelf invulde en mag `null` zijn -- dat betekent
 * "reken het uit". `berekend` is wat er dan komt te staan, en dat staat als
 * tijdelijke tekst in het lege veld.
 */
export type ErvaringCijferRij = {
    key: string;
    label: string;
    omschrijving: string;
    waarde: number | null;
    berekend: number;
};

export type ErvaringOpties = {
    icon: Optie[];
    employment: Optie[];
    workplace: Optie[];
    maanden: Optie[];
    jaren: Optie[];
};
