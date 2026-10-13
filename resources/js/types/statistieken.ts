/**
 * De statistieken op de landingspagina.
 *
 * Twee vormen van hetzelfde item, dezelfde verdeling als bij de andere
 * modules:
 *
 * - `StatistiekOpDeSite` is wat de **bezoeker** krijgt. De keuze tussen
 *   Nederlands en Engels is op de server al gemaakt, en de indeling in
 *   groepen ook -- zie `HomeController::statistiekGroepen()`.
 * - `StatistiekRij` is wat het **beheerscherm** krijgt. Daar staan
 *   allebei de talen los in, want het formulier bewerkt ze allebei.
 *
 * Zie docs/architecture/modules/statistieken.md.
 */

/** De sleutels uit App\Enums\StatisticDisplay. */
export type Weergave = 'balk' | 'ring' | 'teller';

export type StatistiekOpDeSite = {
    id: number;
    naam: string;
    /** Bij een balk of ring 0 tot 100; bij een teller het hele getal. */
    waarde: number;
    /** Het teken vóór het getal, zoals een euroteken. */
    voor: string | null;
    /** Het teken erachter: "+", "%", "/7". */
    na: string | null;
    notitie: string | null;
};

/**
 * Eén band met statistieken van dezelfde vorm.
 *
 * Binnen een groep worden **opeenvolgende** statistieken van dezelfde
 * vorm samen op één rij gezet. Verandert de vorm, dan begint er een
 * nieuwe band. Zet de klant ring, ring, balk, ring neer, dan zijn dat
 * dus drie banden.
 *
 * Zo volgt de pagina zijn sleepvolgorde en staan er toch nooit twee
 * vormen op één rij. Zie `HomeController::statistiekGroepen()`.
 */
export type StatistiekBundel = {
    weergave: Weergave;
    items: StatistiekOpDeSite[];
};

/**
 * Eén groep, met zijn statistieken al in banden verdeeld.
 *
 * Die verdeling komt van de server en niet uit dit component: het is een
 * inhoudelijke beslissing en geen opmaak.
 *
 * `naam` is null bij de naamloze groep, die vooraan staat -- daar horen
 * de losse cijfers die nergens bij horen.
 */
export type StatistiekGroep = {
    sleutel: string;
    naam: string | null;
    bundels: StatistiekBundel[];
};

export type StatistiekRij = {
    id: number;
    /** Dezelfde waarde als `id`, als tekst. SortableList vraagt erom. */
    key: string;
    display: Weergave;
    display_label: string;
    published: boolean;
    label_nl: string;
    label_en: string | null;
    value: number;
    prefix: string | null;
    suffix: string | null;
    note_nl: string | null;
    note_en: string | null;
    group_nl: string | null;
    group_en: string | null;
    automatisch_vertaald: boolean;
};

/**
 * Eén groep zoals het indelingsvenster ermee werkt.
 *
 * **Hier is de groep een vak en geen veld op een item.** Dat is het hele
 * idee achter dat venster: zit een statistiek in dit vak, dan zit hij in
 * deze groep -- er is geen tweede plek waar dat ook nog staat en die
 * ermee uit de pas kan lopen. Slepen naar een ander vak *is* verhuizen,
 * en bij het opslaan krijgt elk item de namen van het vak waarin het
 * ligt.
 *
 * `naam` is `null` bij het eerste vak, dat van de losse cijfers. Dat vak
 * bestaat altijd, heeft geen naam en kan niet weg.
 *
 * `id` is een eigen sleutel en niet de naam: hernoemen mag de vakken
 * niet laten verspringen, en twee nieuwe vakken zonder naam moeten van
 * elkaar te onderscheiden zijn.
 */
export type StatistiekVak = {
    id: string;
    naam: string | null;
    naamEn: string | null;

    /**
     * De Nederlandse naam zoals het venster hem kreeg.
     *
     * Alleen om te zien of de klant de groep hernoemt. Is dat zo, dan
     * verschijnt het kleine vertaalknopje naast het Engelse veld -- want
     * dan klopt het Engels dat er staat niet meer bij de naam ervoor.
     * Bij een nieuw vak is dit `null`.
     */
    naamBron: string | null;

    items: StatistiekRij[];
};

export type StatistiekenOpties = {
    weergave: Array<{
        value: Weergave;
        label: string;
        omschrijving: string;
        /** 100 bij een balk of ring, zeven cijfers bij een teller. */
        maximum: number;
    }>;
    /** De groepen die er al zijn, als suggestie in het formulier. */
    groepen: string[];
};
