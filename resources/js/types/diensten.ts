/**
 * De diensten op de landingspagina.
 *
 * Twee vormen van hetzelfde item, en dat is met opzet -- dezelfde
 * verdeling als bij de ervaring:
 *
 * - `DienstOpDeSite` is wat de **bezoeker** krijgt. De keuze tussen
 *   Nederlands en Engels is op de server al gemaakt, en wat leeg mag
 *   blijven is gewoon weg.
 * - `DienstRij` is wat het **beheerscherm** krijgt. Daar staan allebei de
 *   talen los in, want het formulier bewerkt ze allebei.
 *
 * Zou de site dezelfde vorm krijgen als het portaal, dan moet elk
 * component zelf beslissen welk veld terugvalt op het Nederlands en welk
 * niet -- en dan staat er vroeg of laat half Nederlands op een Engelse
 * pagina. Die regels staan in App\Models\Service.
 *
 * Zie docs/architecture/modules/diensten.md.
 */

export type DienstOpDeSite = {
    id: number;
    /** De sleutel uit App\Enums\ServiceIcon; zie DienstIcoon.vue. */
    icon: string;
    titel: string;
    samenvatting: string;
    /**
     * Het hele verhaal, of null.
     *
     * Null betekent: geen venster. De kaart is dan niet aanklikbaar en
     * er staat geen "Lees meer" op. Zo bepaalt de klant per dienst of er
     * meer te vertellen valt.
     */
    verhaal: string | null;
    /** De expertisepunten, al gefilterd op de taal van de bezoeker. */
    punten: string[];
};

/** Eén expertisepunt in het beheerscherm: allebei de talen los. */
export type DienstPuntRij = {
    text_nl: string;
    text_en: string | null;
};

export type DienstRij = {
    id: number;
    /** Dezelfde waarde als `id`, als tekst. SortableList vraagt erom. */
    key: string;
    icon: string;
    icon_label: string;
    published: boolean;
    title_nl: string;
    title_en: string | null;
    summary_nl: string;
    summary_en: string | null;
    body_nl: string | null;
    body_en: string | null;
    punten: DienstPuntRij[];
    /** Of alles wat in het Nederlands staat ook in het Engels staat. */
    vertaald: boolean;
    automatisch_vertaald: boolean;
};

/**
 * De kop boven het blok, zoals de bezoeker hem krijgt.
 */
export type DienstenKop = {
    opschrift: string;
    titel: string;
    inleiding: string | null;
};

/** Dezelfde kop in het beheerscherm: allebei de talen los. */
export type DienstenKopRij = {
    eyebrow_nl: string;
    eyebrow_en: string | null;
    title_nl: string;
    title_en: string | null;
    intro_nl: string | null;
    intro_en: string | null;
    automatisch_vertaald: boolean;
};

export type DienstenOpties = {
    icon: Array<{ value: string; label: string }>;
    puntenMaximum: number;
};
