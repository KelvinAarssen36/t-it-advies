import { usePage } from '@inertiajs/vue3';

/**
 * Vertalen in de frontend.
 *
 * Dezelfde bestanden als de serverkant: `lang/nl.json` en `lang/en.json`, met
 * de Nederlandse tekst als sleutel. Er is dus geen tweede woordenlijst en
 * geen verzonnen sleutels als `portal.nav.dashboard` -- je leest in het
 * sjabloon gewoon wat er komt te staan.
 *
 * De keerzijde van die keuze: wijzig je een Nederlandse zin, dan wijzigt de
 * sleutel mee en valt de Engelse vertaling terug op het Nederlands. Dat is
 * zichtbaar en te repareren; een verweesde sleutel in een aparte lijst is
 * dat niet.
 *
 * Zie docs/architecture/vertalingen.md.
 */
export function t(
    tekst: string,
    vervangingen: Record<string, string | number> = {},
): string {
    /*
     * usePage() leest uit een reactieve store op moduleniveau, dus dit werkt
     * ook buiten een component en verandert vanzelf mee zodra je van taal
     * wisselt.
     *
     * De optionele ketting is geen overdaad. Roep je dit aan voordat Inertia
     * een pagina heeft gezet -- bijvoorbeeld vanuit de moduleruimte van een
     * paginacomponent -- dan is `props` undefined. Zonder `?.` gooit dat een
     * fout tijdens het laden van de module, en dan rendert Inertia de pagina
     * helemaal niet: je kijkt naar een leeg scherm. Nu valt hij terug op het
     * Nederlands, en dat is te zien in plaats van fataal.
     */
    const vertalingen = usePage()?.props?.translations ?? {};

    let uitkomst = vertalingen[tekst] ?? tekst;

    // Zelfde vorm als Laravel: `:naam` in de tekst, een sleutel zonder
    // dubbele punt in de vervangingen.
    for (const [sleutel, waarde] of Object.entries(vervangingen)) {
        uitkomst = uitkomst.replaceAll(`:${sleutel}`, String(waarde));
    }

    return uitkomst;
}
