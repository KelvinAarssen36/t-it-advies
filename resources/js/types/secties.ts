/**
 * Wat elk versleepbaar onderdeel van de landingspagina van buiten krijgt.
 *
 * Een sectie weet zelf niet waar hij staat, en dat is precies de bedoeling:
 * de klant kan hem verslepen, dus de achtergrondkleur kan niet in het
 * onderdeel zelf staan. Welcome.vue kiest die op basis van de plek in de
 * rij, zodat de afwisseling licht-verhoogd-licht klopt in elke volgorde.
 *
 * Zie docs/architecture/pagina-indeling.md.
 */
export type SectieProps = {
    /** Op welk niveau de sectie ligt. Zie SiteSection.vue. */
    tone: 'base' | 'raised';
    /** Een lijn erboven, om hem van de vorige te scheiden. */
    divided: boolean;
};

/**
 * De sleutels die de server meegeeft, gelijk aan App\Enums\PageSectionKey.
 *
 * De kop en de voettekst staan er bewust niet bij: die zitten niet in de
 * rij die de klant kan verslepen.
 */
export type SectieSleutel =
    | 'diensten'
    | 'werkwijze'
    | 'ervaring'
    | 'certificaten'
    | 'contact'
    | 'linkedin';

/**
 * De kop boven een onderdeel, zoals het beheerscherm hem bewerkt.
 *
 * Allebei de talen los, want het formulier vult ze allebei. Eén vorm
 * voor alle onderdelen, net als de tabel erachter -- zie
 * `App\Models\SectionHeading` en KoptekstDialoog.vue.
 *
 * Het opschrift mag hier `null` zijn, want niet elk onderdeel heeft er
 * een: de tijdlijn heeft nooit een opschrift gehad. Of het verplicht is
 * staat per onderdeel in de FormRequest, niet in dit type.
 */
export type KoptekstRij = {
    eyebrow_nl: string | null;
    eyebrow_en: string | null;
    title_nl: string;
    title_en: string | null;
    intro_nl: string | null;
    intro_en: string | null;
    automatisch_vertaald: boolean;
};

/**
 * Eén regel op het indelingsscherm in het portaal.
 *
 * Komt uit App\Http\Controllers\Website\LayoutController. Let op het
 * verschil tussen `visible`, `filled` en `live`: het eerste is wat de klant
 * heeft aangezet, het tweede of er inhoud in zit, en het derde of het
 * daadwerkelijk op de website staat. Dat zijn drie vragen en geen één.
 */
export type SectieRij = {
    key: string;
    label: string;
    description: string;
    /** De kop en de voettekst: niet te verslepen en niet uit te zetten. */
    fixed: boolean;
    visible: boolean;
    filled: boolean;
    /** Het aantal items, of null als dit onderdeel niets telbaars heeft. */
    count: number | null;
    live: boolean;
    /** De weg naar het scherm met de inhoud, zodra die module er is. */
    manageUrl: string | null;
    /**
     * Of er überhaupt iets te beheren valt.
     *
     * Niet hetzelfde als `manageUrl === null`: dat betekent "nog geen
     * scherm". Bij LinkedIn ligt de enige inhoud -- de link -- bewust
     * vast, en dan is "nog niet" een verkeerde mededeling.
     */
    manageable: boolean;
};
