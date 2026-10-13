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
 * Eén cijfer boven de tijdlijn, zoals het beheerscherm hem nodig heeft.
 *
 * **Alleen wat de klant zelf heeft ingesteld.** Het woord van het soort,
 * de uitleg erbij en wat wij zouden tellen staan hier bewust níet in: die
 * horen bij het *soort* en niet bij deze rij, en het scherm zoekt ze op in
 * `ErvaringCijferSoort`.
 *
 * Dat onderscheid is met schade en schande geleerd. Toen die gegevens hier
 * wél in stonden, klopten ze niet meer zodra de klant in het venster een
 * ander soort koos -- en dan stond er "nu zouden wij er 0 tellen" boven een
 * tijdlijn van vijfendertig jaar.
 *
 * `id` is null bij een cijfer dat zojuist is toegevoegd en dus nog niet in
 * de database staat. `label_nl` leeg betekent: gebruik het standaardwoord
 * van het soort. Dat is de stand waarin het woord vanzelf meegaat met de
 * taal van de bezoeker.
 */
export type ErvaringCijferRij = {
    id: number | null;
    key: string;
    label_nl: string | null;
    label_en: string | null;
    modus: string;
    waarde: number | null;
};

/** Een keuze in een lijst: de waarde, het woord en wat het betekent. */
export type ErvaringKeuze = {
    value: string;
    label: string;
    omschrijving: string;
};

/**
 * Een soort cijfer, met wat wij er nu voor zouden tellen.
 *
 * `berekend` is null bij een eigen cijfer: dat kunnen wij niet uitrekenen,
 * en nul zou suggereren dat we het geprobeerd hebben.
 */
export type ErvaringCijferSoort = ErvaringKeuze & {
    berekenbaar: boolean;
    berekend: number | null;
};

export type ErvaringOpties = {
    icon: Optie[];
    employment: Optie[];
    workplace: Optie[];
    maanden: Optie[];
    jaren: Optie[];
};

/**
 * De kop van de landingspagina, zoals de bezoeker hem krijgt.
 *
 * De keuze tussen Nederlands en Engels is op de server al gemaakt; hier
 * staat wat er komt te staan. Zie App\Models\SectionHeading.
 */
export type SiteKop = {
    opschrift: string;
    titel: string;
    inleiding: string | null;
};

/**
 * Dezelfde kop, maar dan zoals het beheerscherm hem nodig heeft: allebei
 * de talen los, want de klant vult ze allebei zelf in.
 */
export type SiteKopRij = {
    eyebrow_nl: string;
    eyebrow_en: string | null;
    title_nl: string;
    title_en: string | null;
    intro_nl: string | null;
    intro_en: string | null;
    automatisch_vertaald: boolean;
};

/**
 * De kop boven de tijdlijn, zoals de bezoeker hem krijgt.
 *
 * De keuze tussen Nederlands en Engels is op de server al gemaakt; hier
 * staat wat er komt te staan. Zie App\Models\SectionHeading.
 */
export type ErvaringKop = {
    titel: string;
    inleiding: string | null;
};

/**
 * Dezelfde kop, maar dan zoals het beheerscherm hem nodig heeft: allebei
 * de talen los, want de klant vult ze allebei zelf in.
 */
export type ErvaringKopRij = {
    title_nl: string;
    title_en: string | null;
    intro_nl: string | null;
    intro_en: string | null;
    automatisch_vertaald: boolean;
};
