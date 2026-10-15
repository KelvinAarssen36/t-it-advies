import { reactive } from 'vue';

/**
 * Welke beelden niet te laden zijn, voor de hele publieke site.
 *
 * **Een beeld dat niet laadt, laat de alt-tekst zien in een leeg kader.**
 * Dat gebeurde bij een projectbeeld dat van de schijf was verdwenen: de
 * server gaf een 403, en op de landing stond "Beeld bij Migratie naar
 * Exchange Online" in een groot donker vlak. Een bezoeker leest dat als een
 * kapotte site.
 *
 * Nu meldt een `<img>` met `@error` zijn adres hier, en tonen de
 * componenten in hetzelfde kader de letter van de organisatie -- dezelfde
 * terugval als bij een project zónder beeld. Het kader houdt zijn maat: de
 * verhouding staat op het beeld zelf, en een kapot beeld houdt die doos.
 *
 * **Eén lijst voor de hele site, en niet per component**, omdat hetzelfde
 * beeld op meer plekken staat (de kaart, het tegeltje, het zwevende beeld,
 * de projectpagina). Weet één ervan dat het niet laadt, dan weten ze het
 * allemaal, ook na een navigatie naar de projectpagina.
 *
 * Alleen voor de publieke site. Zie docs/architecture/modules/projecten.md.
 */
const kapot = reactive(new Set<string>());

/** Is dit beeld al eens niet geladen? */
export function beeldKapot(adres: string | null | undefined): boolean {
    return Boolean(adres) && kapot.has(adres as string);
}

/** Meld een beeld dat niet laadt; aanroepen vanuit `@error` op een `<img>`. */
export function meldKapot(adres: string | null | undefined): void {
    if (adres) {
        kapot.add(adres);
    }
}
