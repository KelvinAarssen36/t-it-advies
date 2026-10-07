/**
 * De vormen van de module "Projecten".
 *
 * Twee soorten per ding, net als bij de andere modules: `...OpDeSite` is
 * wat de bezoeker krijgt (taal al gekozen, periode al opgemaakt),
 * `...Rij` is wat het beheerscherm krijgt (beide talen los).
 *
 * Zie docs/architecture/modules/projecten.md.
 */

/** Eén project zoals een kaart op de website het toont. */
export type ProjectOpDeSite = {
    slug: string;
    /** Het woord op de badge: uit de lijst, of het eigen label. */
    type: string;
    titel: string;
    organisatie: string;
    rol: string;
    /** "mrt 2021 – heden", al opgemaakt door de server. */
    periode: string;
    /** "2 jaar 3 maanden", ook al opgemaakt door de server. */
    duur: string;
    loopt: boolean;
    samenvatting: string | null;
    beeld: string | null;
    /**
     * De eerste letter van de organisatie.
     *
     * Staat er geen beeld, dan vult deze letter die plek. Van de server
     * en niet uit `titel[0]` in het sjabloon: dan klopt hij ook bij een
     * naam die met een accent begint.
     */
    letter: string;
};

/** En zoals de detailpagina het nodig heeft. */
export type ProjectOpDePagina = ProjectOpDeSite & {
    omschrijving: string | null;
    resultaat: string | null;
};

/**
 * Waar de bezoeker vandaan kwam toen hij een project opende.
 *
 * Staat als `?van=` in het adres en bepaalt alleen waar de knop onderaan
 * de detailpagina naartoe wijst. `null` betekent: via `/projecten`, via
 * een gedeelde link, of rechtstreeks uit een zoekresultaat -- en dan is
 * "Terug naar alle projecten" het juiste antwoord.
 */
export type ProjectHerkomst = 'start' | null;

/** Alle velden van één project, zoals het beheerscherm ze bewerkt. */
export type ProjectRij = {
    id: number;
    /** SortableList eist een string als sleutel. */
    key: string;

    published: boolean;
    featured: boolean;
    slug: string;

    type: string;
    /** Het woord uit de lijst, voor de regel in de tabel. */
    type_naam: string;
    /** Of dit soort een eigen label vraagt. */
    eigen_type: boolean;
    type_label_nl: string | null;
    type_label_en: string | null;

    title_nl: string;
    title_en: string | null;
    organisation: string;
    role_nl: string;
    role_en: string | null;

    /** "2021-03", zoals MaandKiezer het verwacht. */
    start: string;
    eind: string | null;
    loopt: boolean;
    periode: string;

    summary_nl: string;
    summary_en: string | null;
    body_nl: string | null;
    body_en: string | null;
    result_nl: string | null;
    result_en: string | null;

    beeld: string | null;
    letter: string;
    automatisch_vertaald: boolean;
};

/**
 * Hoe de pagina met alle projecten eruitziet.
 *
 * `lijst` = elk project een eigen regel onder elkaar; `raster` = dezelfde
 * projecten als kaarten naast elkaar. De eigenaar kiest; zie
 * `App\Enums\ProjectWeergave`.
 */
export type ProjectWeergave = 'lijst' | 'raster';

/** Eén keuze in een lijst. */
export type ProjectOptie = {
    value: string;
    label: string;
};

/** Eén weergave, met de regel uitleg die eronder staat. */
export type ProjectWeergaveOptie = {
    value: ProjectWeergave;
    label: string;
    omschrijving: string;
};

/** Wat de server aan keuzes en grenzen meestuurt. */
export type ProjectOpties = {
    soorten: ProjectOptie[];
    /** De twee vormen van de projectenpagina, voor het weergavevenster. */
    weergaven: ProjectWeergaveOptie[];
    maanden: ProjectOptie[];
    jaren: ProjectOptie[];
    /**
     * De grenzen, van de server.
     *
     * Ze staan hier niet apart maar komen uit ProjectRequest, want het
     * scherm laat er tellers op meelopen. Zou een getal hier eigen staan,
     * dan krijgt de eigenaar een foutmelding op iets wat het scherm net
     * nog goedkeurde.
     */
    samenvattingMax: number;
    tekstMax: number;
    /** Hoeveel projecten er naast het uitgelichte op de voorpagina staan. */
    opDeVoorpagina: number;
};
