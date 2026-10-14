/**
 * De welkomstanimatie: wanneer hij speelt, en het moment waarop hij de hero
 * het stokje overgeeft.
 *
 * De animatie zelf staat in SiteIntro.vue; de hero (HeroSection.vue) wacht
 * op het signaal hieronder. Zie "De intro bij binnenkomst" in
 * docs/architecture/frontend-en-animatie.md.
 *
 * **Eén keer per browser, en alleen als je echt op de voorpagina landt.**
 * Hij speelt bij het eerste bezoek aan de landing, en daarna niet meer: niet
 * bij verversen, niet na een taalwissel, niet als je via het menu terugkomt
 * van een andere pagina. Wat er onthouden wordt is één vlag in
 * `localStorage` ("gezien"), in deze browser, voor deze bezoeker. Geen
 * datum, geen teller, niets wat naar de server gaat.
 *
 * **De beslissing is synchroon en wordt één keer genomen**, bij de eerste
 * vraag, zodat de intro en de hero gegarandeerd hetzelfde antwoord krijgen.
 */

import { opEenTelefoon, prefersReducedMotion } from '@/lib/motion';

const SLEUTEL = 'brand-intro-gezien';

/**
 * Met `?intro` in het adres speelt hij altijd, ook als hij al gezien is.
 * Voor de eigenaar en voor ons, om hem opnieuw te bekijken zonder de
 * opslag van de browser te wissen; de parameter gaat daarna uit het adres.
 */
const OPNIEUW = 'intro';

let besluit: boolean | undefined;

/** Vanaf welk laadmoment we de intro overslaan, in milliseconden. */
const GEDULD_BREED = 2500;
const GEDULD_SMAL = 1500;

/**
 * Of de pagina snel genoeg binnenkwam om er nog iets voor te zetten.
 *
 * Duurde het laden zelf al lang -- trage verbinding, oude telefoon -- dan
 * is twee seconden er bovenop geen entree meer maar een straf. Dan slaan we
 * hem over, en onthouden we niets: bij een volgend, sneller bezoek mag hij
 * alsnog.
 */
function snelGenoeg(): boolean {
    if (typeof performance === 'undefined') {
        return true;
    }

    const [navigatie] = performance.getEntriesByType(
        'navigation',
    ) as PerformanceNavigationTiming[];

    if (navigatie === undefined) {
        return true;
    }

    return (
        navigatie.domContentLoadedEventEnd <
        (opEenTelefoon() ? GEDULD_SMAL : GEDULD_BREED)
    );
}

/** Staat `?intro` in het adres? Haalt hem er meteen weer uit. */
function opnieuwGevraagd(): boolean {
    const adres = new URL(window.location.href);

    if (!adres.searchParams.has(OPNIEUW)) {
        return false;
    }

    adres.searchParams.delete(OPNIEUW);
    window.history.replaceState(
        window.history.state,
        '',
        `${adres.pathname}${adres.search}${adres.hash}`,
    );

    return true;
}

/**
 * Speelt de intro deze keer af?
 *
 * Nee als:
 * - de bezoeker minder beweging wil (dan staat de site er meteen);
 * - de eerste pagina niet de voorpagina is (`opDeLanding`);
 * - er een anker in het adres staat: wie een link naar #contact volgt, wil
 *   bij Contact uitkomen en niet eerst naar een entree kijken;
 * - hij al eens gezien is in deze browser;
 * - de browser niets kan onthouden (privévenster, geblokkeerde opslag): dan
 *   zou hij bij elke verversing opnieuw spelen, en dat is erger dan nooit;
 * - het laden al te lang duurde.
 *
 * Het antwoord wordt bij de eerste vraag vastgelegd. Daarom kan het ook bij
 * een Inertia-navigatie naar de voorpagina nooit meer "ja" worden: de vraag
 * is dan allang gesteld.
 */
export function introSpeeltAf(opDeLanding = true): boolean {
    if (besluit !== undefined) {
        return besluit;
    }

    besluit = beslis(opDeLanding);

    return besluit;
}

function beslis(opDeLanding: boolean): boolean {
    if (typeof window === 'undefined' || prefersReducedMotion()) {
        return false;
    }

    const opnieuw = opnieuwGevraagd();

    if (!opDeLanding || window.location.hash.length > 1) {
        return false;
    }

    if (opnieuw) {
        return true;
    }

    try {
        if (window.localStorage.getItem(SLEUTEL) !== null) {
            return false;
        }

        if (!snelGenoeg()) {
            return false;
        }

        window.localStorage.setItem(SLEUTEL, '1');
    } catch {
        return false;
    }

    return true;
}

/* --- Het stokje naar de hero ------------------------------------------- */

/**
 * Hoe lang de hero hoogstens op de intro wacht, in milliseconden.
 *
 * Een vangnet en geen planning: normaal geeft de intro het signaal zelf,
 * rond 1,3 seconde, of meteen als de bezoeker overslaat. Komt het signaal
 * om welke reden dan ook niet -- een fout in de intro, een tabblad op de
 * achtergrond -- dan begint de hero toch. Onzichtbare inhoud is een ergere
 * fout dan een gemiste animatie.
 */
const UITERLIJK = 2600;

let overgedragen = false;
const wachtenden: Array<() => void> = [];

/**
 * De intro geeft het stokje over: de hero mag beginnen. Mag vaker worden
 * aangeroepen; alleen de eerste keer telt.
 */
export function introDraagtOver(): void {
    if (overgedragen) {
        return;
    }

    overgedragen = true;
    wachtenden.splice(0).forEach((verder) => verder());
}

/**
 * Roept `verder` aan zodra de hero mag beginnen: meteen als er geen intro
 * speelt of hij al voorbij is, anders bij het signaal of uiterlijk na
 * `UITERLIJK`. Geeft een opruimfunctie terug voor als de hero eerder
 * verdwijnt.
 */
export function naDeIntro(verder: () => void): () => void {
    if (!introSpeeltAf() || overgedragen) {
        verder();

        return () => {};
    }

    let gedaan = false;

    const eenmaal = (): void => {
        if (gedaan) {
            return;
        }

        gedaan = true;
        window.clearTimeout(vangnet);
        verder();
    };

    const vangnet = window.setTimeout(eenmaal, UITERLIJK);
    wachtenden.push(eenmaal);

    return () => {
        gedaan = true;
        window.clearTimeout(vangnet);
    };
}
