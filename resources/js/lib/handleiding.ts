import { computed, reactive, ref } from 'vue';

/**
 * Zoeken in de handleiding.
 *
 * **Er is met opzet geen zoekindex.** De voor de hand liggende aanpak is
 * een lijst met per kaart een titel en wat trefwoorden, en die lijst is
 * precies het probleem: hij staat náást de kaarten, dus hij veroudert
 * zodra iemand een kaart toevoegt of een zin herschrijft. En je merkt het
 * niet -- de kaart staat er gewoon, hij is alleen niet meer te vinden.
 *
 * In plaats daarvan leest elke kaart **zijn eigen getoonde tekst** en
 * bepaalt hij zelf of hij bij de zoekterm past. De doorzoekbare tekst is
 * dus per definitie de tekst die er staat. Een nieuwe kaart is meteen
 * vindbaar zonder dat iemand ergens iets bijwerkt, en dat is het hele
 * punt van deze opzet.
 *
 * Zie resources/js/components/settings/UitlegKaart.vue.
 */

/** Wat er in het zoekveld staat. */
export const zoekterm = ref('');

/**
 * Hoeveel tekens er nodig zijn voordat er gefilterd wordt.
 *
 * Bij één letter blijft er niets over om te zien: dan verdwijnen er
 * willekeurig kaarten terwijl je nog aan het typen bent.
 */
const MINIMUM = 2;

/** Of er op dit moment gefilterd wordt. */
export const zoekt = computed(
    () => genormaliseerd(zoekterm.value).length >= MINIMUM,
);

/**
 * Tekst klaarmaken om te vergelijken.
 *
 * Kleine letters en zonder accenten, zodat "prive" ook "privé" vindt en
 * "Beveiliging" hetzelfde doet als "beveiliging". Wie iets zoekt, denkt
 * niet aan trema's.
 */
function genormaliseerd(tekst: string): string {
    return tekst
        .toLowerCase()
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .trim();
}

/**
 * Of een stuk tekst bij de huidige zoekterm past.
 *
 * Alle losse woorden moeten erin voorkomen, en niet de hele zin achter
 * elkaar: zo vindt "mail bezoeker" ook een kaart waar die twee woorden ver
 * uit elkaar staan. Dat is bijna altijd wat iemand bedoelt.
 */
export function past(tekst: string): boolean {
    if (!zoekt.value) {
        return true;
    }

    const doel = genormaliseerd(tekst);

    return genormaliseerd(zoekterm.value)
        .split(/\s+/)
        .every((woord) => doel.includes(woord));
}

/**
 * Welke kaarten op dit moment passen.
 *
 * Een kaart meldt zich hier aan bij het tekenen en af bij het opruimen.
 * De pagina gebruikt dit alleen om te weten of er iets gevonden is -- zonder
 * dat zou je bij nul treffers naar een leeg scherm kijken zonder uitleg.
 */
const treffers = reactive(new Map<string, boolean>());

export function meldAan(sleutel: string, raak: boolean): void {
    treffers.set(sleutel, raak);
}

export function meldAf(sleutel: string): void {
    treffers.delete(sleutel);
}

export const aantalTreffers = computed(
    () => [...treffers.values()].filter(Boolean).length,
);

/** Het zoekveld legen, bijvoorbeeld met de knop ernaast of met Escape. */
export function leegZoekterm(): void {
    zoekterm.value = '';
}
