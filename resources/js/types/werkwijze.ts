/**
 * De vormen van de module "Werkwijze".
 *
 * Twee soorten, net als bij de andere modules: `...OpDeSite` is wat de
 * bezoeker krijgt (taal al gekozen), `...Rij` is wat het beheerscherm
 * krijgt (beide talen los).
 *
 * **Er zit geen nummer bij.** Dat telt het scherm af aan de plek in de
 * lijst; zou de server het meesturen, dan kan het afwijken van de volgorde
 * waarin de kaarten staan. Zie `App\Models\WorkStep`.
 *
 * Zie docs/architecture/modules/werkwijze.md.
 */

/** Eén stap zoals de website hem toont. */
export type StapOpDeSite = {
    id: number;
    titel: string;
    /** De zin onder de titel. Kan ontbreken als het Engels leeg is. */
    samenvatting: string | null;
    /** "1-2 weken", of niets. */
    duur: string | null;
    /** Wat je na deze stap in handen hebt, of niets. */
    resultaat: string | null;
    /** Het hele verhaal; alleen gebruikt op /werkwijze. */
    verhaal: string | null;
};

/** Alle velden van één stap, zoals het beheerscherm ze bewerkt. */
export type StapRij = {
    id: number;
    /** SortableList eist een string als sleutel. */
    key: string;

    published: boolean;

    title_nl: string;
    title_en: string | null;
    summary_nl: string;
    summary_en: string | null;
    duration_nl: string | null;
    duration_en: string | null;
    result_nl: string | null;
    result_en: string | null;
    body_nl: string | null;
    body_en: string | null;

    automatisch_vertaald: boolean;
};

/**
 * Wat de server aan grenzen meestuurt.
 *
 * Ze staan hier niet apart maar komen uit `WorkStepRequest`, want het
 * scherm laat er tellers op meelopen. Zou een getal hier eigen staan, dan
 * krijgt de eigenaar een foutmelding op iets wat het scherm net nog
 * goedkeurde.
 */
export type StapOpties = {
    /** Hoeveel stappen er hoogstens mogen; zie `WorkStep::MAXIMUM`. */
    maximum: number;
    samenvattingMax: number;
    resultaatMax: number;
    verhaalMax: number;
};
