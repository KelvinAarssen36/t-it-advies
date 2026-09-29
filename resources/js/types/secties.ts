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
export type SectieSleutel = 'diensten' | 'werkwijze' | 'ervaring' | 'contact';

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
};
