/**
 * De vormen van de module Contact.
 *
 * Twee soorten per ding, net als bij de andere modules: `...OpDeSite` is
 * wat de bezoeker krijgt (taal al gekozen), `...Rij` is wat het
 * beheerscherm krijgt (beide talen los).
 *
 * Zie docs/architecture/modules/contact.md.
 */

/** Eén onderwerp, zoals het beheerscherm het toont. */
export type OnderwerpRij = {
    id: number;
    /** SortableList eist een string als sleutel. */
    key: string;
    label_nl: string;
    label_en: string | null;
    published: boolean;
    /**
     * Uitgelicht door de eigenaar.
     *
     * Dan springt een aanvraag met dit onderwerp eruit onder Beheer →
     * Aanvragen. Op de website verandert er niets; zie `AanvraagRij`.
     */
    featured: boolean;
    automatischVertaald: boolean;
    /** Hoeveel aanvragen aan dit onderwerp hangen. */
    aanvragen: number;
};

/**
 * Eén onderwerp, zoals de bezoeker het ziet.
 *
 * **Geen `uitgelicht` hier.** Dat is een merkje op het scherm Aanvragen en
 * niet iets dat het formulier verandert; zie `AanvraagRij`.
 */
export type OnderwerpOpDeSite = {
    id: number;
    naam: string;
};

/** Eén onderwerp om op te filteren in Beheer → Aanvragen. */
export type OnderwerpFilter = {
    id: number;
    naam: string;
};

/** De stand van een veld. Zie App\Enums\ContactVeldStatus. */
export type VeldStatus = 'uit' | 'optioneel' | 'verplicht';

/** Hoe een veld wordt getekend. Zie App\Enums\ContactVeldType. */
export type VeldType =
    | 'tekst'
    | 'email'
    | 'telefoon'
    | 'onderwerp'
    | 'tekstvak';

/** Eén veld, zoals het beheerscherm het toont. */
export type VeldRij = {
    key: string;
    label: string;
    status: VeldStatus;
    /** Altijd aan en altijd verplicht; het schuifje staat vergrendeld. */
    vast: boolean;
    /** Of de bezoeker een eigen antwoord mag typen. Alleen bij het onderwerp. */
    eigenToegestaan: boolean;
    heeftEigenKeuze: boolean;
};

/** Eén veld, zoals het formulier het nodig heeft. */
export type VeldOpDeSite = {
    naam: string;
    label: string;
    type: VeldType;
    verplicht: boolean;
    autocomplete: string | null;
    maximum: number;
    positie: number;
};

/** Hoe contact op de website wordt aangeboden. */
export type ContactWeergave = 'pagina' | 'eigen';

export type ContactInstellingen = {
    weergave: ContactWeergave;
    bevestigingOnderwerpNl: string | null;
    bevestigingOnderwerpEn: string | null;
    bevestigingTekstNl: string | null;
    bevestigingTekstEn: string | null;
    automatischVertaald: boolean;
};

export type ContactOpties = {
    weergave: Array<{
        value: ContactWeergave;
        label: string;
        omschrijving: string;
    }>;
    standen: Array<{ value: VeldStatus; label: string }>;
};

/** Eén binnengekomen aanvraag, zoals het scherm Aanvragen die toont. */
export type AanvraagRij = {
    id: number;
    naam: string;
    email: string;
    bedrijf: string | null;
    telefoon: string | null;
    onderwerp: string;
    /** Of de bezoeker het onderwerp zelf typte. */
    onderwerpZelf: boolean;
    /** Of het gekozen onderwerp inmiddels is verwijderd. */
    onderwerpVerdwenen: boolean;
    /**
     * Of de eigenaar dit onderwerp heeft uitgelicht.
     *
     * Dan springt deze regel eruit in zijn postbus. Het verandert niets op
     * de site; de bezoeker merkt er niets van.
     */
    onderwerpUitgelicht: boolean;
    bericht: string;
    taal: string;
    wanneer: string | null;
    gelezen: boolean;
    beantwoord: boolean;
    /** Welke velden er aan stonden toen dit binnenkwam. */
    velden: string[];
};
