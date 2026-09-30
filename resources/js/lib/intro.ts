/**
 * Of de merkintro bij binnenkomst afspeelt.
 *
 * De intro is een laag over de hero heen die in acht tienden van een
 * seconde wegvloeit. Dat is het enige moment op de hele site waarop de
 * bezoeker even niets kan; alles hieronder gaat erover wanneer je dat
 * níet doet.
 *
 * **De beslissing is synchroon en wordt één keer genomen.** Dat is met
 * opzet geen belofte waar de hero op wacht: zou de introlaag om welke
 * reden dan ook niet gemonteerd worden, dan wacht de hero eeuwig en blijft
 * zijn tekst op doorzichtigheid nul staan. Onzichtbare inhoud is een
 * ergere fout dan een gemiste animatie, dus beide kanten kunnen deze vraag
 * los van elkaar stellen en krijgen hetzelfde antwoord.
 *
 * Zie SiteIntro.vue en HeroSection.vue.
 */

import { prefersReducedMotion } from '@/lib/motion';

/** Hoe lang de intro in beeld is, in seconden. */
export const INTRO_DUUR = 0.8;

const SLEUTEL = 'brand-intro-gezien';

let besluit: boolean | undefined;

/**
 * Of de pagina snel genoeg binnenkwam om er nog iets voor te zetten.
 *
 * Duurde het laden zelf al lang -- trage verbinding, oude telefoon -- dan
 * is acht tienden seconde er bovenop geen entree meer maar een straf. In
 * dat geval slaan we hem over en ziet de bezoeker meteen de site.
 */
function snelGenoeg(): boolean {
    if (typeof performance === 'undefined') {
        return true;
    }

    const [navigatie] = performance.getEntriesByType(
        'navigation',
    ) as PerformanceNavigationTiming[];

    // Zonder meting het voordeel van de twijfel: liever een intro te veel
    // dan een lege beslissing op basis van niets.
    if (navigatie === undefined) {
        return true;
    }

    return navigatie.domContentLoadedEventEnd < 2000;
}

/**
 * Speelt de intro deze keer af?
 *
 * Het antwoord wordt bij de eerste vraag vastgelegd, zodat de hero en de
 * introlaag gegarandeerd hetzelfde krijgen -- ook al vragen ze het op een
 * ander moment.
 *
 * Het onthouden gebeurt in `sessionStorage`: precies waar dat voor bedoeld
 * is, een gemak voor deze ene bezoeker in dit ene tabblad. Lukt lezen of
 * schrijven niet -- een privévenster, geblokkeerde opslag -- dan speelt de
 * intro gewoon af. Dat is de goede kant om op te falen.
 */
export function introSpeeltAf(): boolean {
    if (besluit !== undefined) {
        return besluit;
    }

    if (
        typeof window === 'undefined' ||
        prefersReducedMotion() ||
        !snelGenoeg()
    ) {
        besluit = false;

        return besluit;
    }

    try {
        if (sessionStorage.getItem(SLEUTEL) === 'ja') {
            besluit = false;

            return besluit;
        }

        sessionStorage.setItem(SLEUTEL, 'ja');
    } catch {
        // Niet kunnen onthouden mag de intro niet tegenhouden.
    }

    besluit = true;

    return besluit;
}
