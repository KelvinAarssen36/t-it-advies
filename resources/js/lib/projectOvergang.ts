import { gsap, prefersReducedMotion } from '@/lib/motion';

/**
 * Van een projectkaart naar de projectpagina, en terug: het beeld vliegt mee.
 *
 * Klik je op een project, dan maakt dit een kopie van het beeld (of de
 * letter) van die kaart op precies dezelfde plek, vast op het scherm. De
 * oude pagina vervaagt eronder, de nieuwe komt op, en de kopie vliegt naar
 * de plek van het grote beeld op de projectpagina. Daar neemt het echte
 * beeld het over. Terug (de terugknop van de browser of een link terug)
 * gebeurt hetzelfde omgekeerd: van het grote beeld naar de kaart, als die
 * kaart na het terugscrollen in beeld staat.
 *
 * **Een eigen kopie en geen View Transitions**, en dat is uitgezocht. Inertia
 * 3.7 kent `viewTransition`, maar dan bevriest de browser het scherm terwijl
 * de nieuwe pagina wordt opgebouwd -- en precies in die tijd wacht deze site
 * op beeldjes (`requestAnimationFrame`) en op het eind van de overgang van
 * `<main>` om de scrollpositie terug te zetten. Heen ging dat nog net, terug
 * liep het vast: Inertia gebruikt bij de terugknop geen view transition, en
 * een eigen view transition legt het "oude" beeld pas een beeldje later
 * vast, als Inertia de pagina al heeft gewisseld. Een kopie op het scherm
 * werkt in elke browser, raakt de paginaovergang niet, en valt bij elke
 * fout terug op niets bijzonders: dan vervaagt hij gewoon.
 *
 * **De titel beweegt niet mee.** Een titel van een kaartje naar een paginakop
 * vergroten kan alleen met een schaal, en dan staan de letters halverwege
 * wazig. Het beeld draagt de beweging; de titel komt op met de pagina.
 *
 * Wanneer het niet speelt: onder reduced motion, bij een klik die een nieuw
 * tabblad opent (die gaat niet via Inertia), als de kaart niet zichtbaar is,
 * en als het doel er na de wissel niet is of buiten beeld staat -- dan lost
 * de kopie op waar hij is.
 *
 * Het beeld van een kaart, de casus en de projectpagina draagt
 * `data-project-beeld` met de slug van het project; zo vindt dit het doel,
 * ook als dezelfde kaart op de pagina twee keer voorkomt.
 *
 * Zie docs/architecture/modules/projecten.md.
 */

const ATTR = 'data-project-beeld';

/** Hoe lang hoogstens op de nieuwe pagina gewacht wordt. */
const GEDULD = 4000;

type Lopend = {
    kloon: HTMLElement;
    verborgen: HTMLElement[];
    stop: () => void;
};

let lopend: Lopend | null = null;

/** Het zichtbare beeld met deze slug binnen `binnen`, of niets. */
function zichtbaarBeeld(binnen: ParentNode, slug: string): HTMLElement | null {
    const kandidaten = binnen.querySelectorAll<HTMLElement>(
        `[${ATTR}="${CSS.escape(slug)}"]`,
    );

    for (const kandidaat of kandidaten) {
        const vlak = kandidaat.getBoundingClientRect();

        if (vlak.width < 2 || vlak.height < 2) {
            continue;
        }

        /*
         * Een dia die niet aan de beurt is, staat er wel maar is `inert`
         * en onzichtbaar. Niet op doorzichtigheid toetsen: de nieuwe pagina
         * komt zelf op vanaf nul, en dan zou het doel op het moment van
         * landen altijd "onzichtbaar" zijn.
         */
        if (
            kandidaat.closest(
                '[inert], [aria-hidden="true"]:not([data-project-beeld])',
            ) !== null ||
            getComputedStyle(kandidaat).visibility === 'hidden'
        ) {
            continue;
        }

        return kandidaat;
    }

    return null;
}

/** Staat dit vlak (grotendeels) in beeld? */
function inBeeld(vlak: DOMRect): boolean {
    return (
        vlak.bottom > 40 &&
        vlak.top < window.innerHeight - 40 &&
        vlak.right > 0 &&
        vlak.left < window.innerWidth
    );
}

/**
 * Een kopie van het beeld of de letter, vast op het scherm op `vlak`.
 *
 * Een beeld dat geladen is wordt opnieuw getoond met `object-fit: cover`:
 * de kaart is 2:1, het tegeltje vierkant en de projectpagina 16:9 of
 * vierkant, en zo snijdt de kopie onderweg mee in plaats van te vervormen.
 */
function maakKloon(bron: HTMLElement, vlak: DOMRect): HTMLElement {
    const kloon = document.createElement('div');
    const stijl = getComputedStyle(bron);
    const beeld = bron.querySelector('img');

    kloon.className = 'brand-site-projectkloon';
    kloon.setAttribute('aria-hidden', 'true');

    if (
        beeld instanceof HTMLImageElement &&
        beeld.complete &&
        beeld.naturalWidth > 0 &&
        getComputedStyle(beeld).visibility !== 'hidden'
    ) {
        const kopie = document.createElement('img');

        kopie.src = beeld.currentSrc || beeld.src;
        kopie.alt = '';
        kloon.append(kopie);
    } else {
        kloon.dataset.letter = '';
        kloon.textContent = (bron.textContent ?? '').trim().slice(0, 2);
    }

    Object.assign(kloon.style, {
        left: `${vlak.left}px`,
        top: `${vlak.top}px`,
        width: `${vlak.width}px`,
        height: `${vlak.height}px`,
        borderRadius: stijl.borderRadius,
    });

    // In de wortel van de site, zodat de kleuren en letters gelden.
    (document.querySelector('.brand-site') ?? document.body).append(kloon);

    return kloon;
}

/** Alles van een lopende overgang opruimen, meteen. */
function ruimOp(): void {
    if (lopend === null) {
        return;
    }

    lopend.stop();
    lopend.kloon.remove();
    lopend.verborgen.forEach((element) => (element.style.visibility = ''));
    document.querySelector('.brand-site')?.removeAttribute('data-overgang');
    lopend = null;
}

/**
 * Begin de overgang vanuit `bron` (het beeld van een kaart of de
 * projectpagina) naar het beeld met dezelfde slug op de volgende pagina.
 *
 * Aanroepen op het moment van de klik of de terugknop, terwijl de oude
 * pagina er nog staat; de navigatie zelf doet Inertia.
 */
export function vliegNaarProject(bron: HTMLElement | null, slug: string): void {
    // Een vorige die nog liep (snel achter elkaar klikken): meteen klaar.
    ruimOp();

    if (bron === null || prefersReducedMotion()) {
        return;
    }

    const vlak = bron.getBoundingClientRect();

    if (vlak.width < 2 || !inBeeld(vlak)) {
        return;
    }

    const kloon = maakKloon(bron, vlak);
    const verborgen = [bron];

    bron.style.visibility = 'hidden';

    /*
     * Meteen een fractie opgetild: je ziet dat het beeld is opgepakt, ook
     * als de nieuwe pagina nog even op zich laat wachten.
     */
    let tween: gsap.core.Tween | undefined = gsap.to(kloon, {
        scale: 1.02,
        duration: 0.2,
        ease: 'power2.out',
    });

    /*
     * Zolang de kopie vliegt, schuift de nieuwe pagina niet mee omhoog bij
     * het opkomen (zie `.brand-site[data-overgang]` in projecten.css): het
     * doel moet stilstaan op de plek die gemeten wordt.
     */
    document
        .querySelector('.brand-site')
        ?.setAttribute('data-overgang', 'project');

    /** Geen doel: de kopie lost op waar hij is. */
    const losOp = (): void => {
        tween?.kill();
        tween = gsap.to(kloon, {
            opacity: 0,
            scale: 0.96,
            duration: 0.3,
            ease: 'power2.out',
            onComplete: ruimOp,
        });
    };

    const landen = (): void => {
        window.clearTimeout(vangnet);

        const doel = zichtbaarBeeld(document, slug);
        const doelVlak = doel?.getBoundingClientRect();

        if (doel === null || doelVlak === undefined || !inBeeld(doelVlak)) {
            losOp();

            return;
        }

        doel.style.visibility = 'hidden';
        verborgen.push(doel);

        const telefoon = window.matchMedia('(max-width: 49.999rem)').matches;

        /*
         * Breedte en hoogte, en geen schaal: de verhouding verandert
         * onderweg, en met `object-fit: cover` snijdt het beeld daarin mee
         * zonder te vervormen. Het is één vast element zonder kinderen in
         * de opmaak van de pagina, dus de browser rekent alleen dat ene
         * vlak opnieuw uit.
         */
        tween?.kill();
        tween = gsap.to(kloon, {
            scale: 1,
            left: doelVlak.left,
            top: doelVlak.top,
            width: doelVlak.width,
            height: doelVlak.height,
            borderRadius: getComputedStyle(doel).borderRadius,
            duration: telefoon ? 0.45 : 0.55,
            ease: 'expo.inOut',
            onComplete: () => {
                doel.style.visibility = '';
                tween = gsap.to(kloon, {
                    opacity: 0,
                    duration: 0.15,
                    ease: 'none',
                    onComplete: ruimOp,
                });
            },
        });
    };

    const vangnet = window.setTimeout(losOp, GEDULD);

    window.addEventListener('brand:pagina-gewisseld', landen, { once: true });

    lopend = {
        kloon,
        verborgen,
        stop: () => {
            window.clearTimeout(vangnet);
            window.removeEventListener('brand:pagina-gewisseld', landen);
            tween?.kill();
        },
    };
}

/**
 * Een klik op een projectkaart: vlieg alleen als Inertia de klik afhandelt.
 *
 * Inertia's `<Link>` roept `preventDefault` aan voor een gewone klik en laat
 * een klik met Ctrl, Cmd, Shift of de middelste knop met rust -- die opent
 * een nieuw tabblad, en dan hoort hier niets te gebeuren. Daarom aan de kaart
 * en niet aan de link: de link is dan al geweest.
 */
export function opProjectKlik(
    gebeurtenis: MouseEvent,
    kaart: EventTarget | null,
    slug: string,
): void {
    if (!gebeurtenis.defaultPrevented || !(kaart instanceof HTMLElement)) {
        return;
    }

    vliegNaarProject(zichtbaarBeeld(kaart, slug), slug);
}

/**
 * Op de projectpagina: bij de terugknop vliegt het grote beeld terug naar
 * zijn kaart. Geeft een opruimfunctie terug voor `onBeforeUnmount`.
 *
 * Een `popstate` komt binnen terwijl de projectpagina er nog staat -- Inertia
 * wisselt hem pas daarna -- dus het beeld is hier nog te meten.
 */
export function vliegTerugBijGeschiedenis(slug: string): () => void {
    const opTerug = (): void => {
        const beeld = zichtbaarBeeld(document, slug);

        vliegNaarProject(beeld, slug);
    };

    window.addEventListener('popstate', opTerug);

    return () => window.removeEventListener('popstate', opTerug);
}
