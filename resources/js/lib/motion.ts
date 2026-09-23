import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import Lenis from 'lenis';

/**
 * De animatielaag van de site: GSAP voor de animaties zelf, Lenis voor
 * smooth scrolling.
 *
 * Twee dingen zijn hier belangrijk.
 *
 * 1. Lenis en ScrollTrigger moeten dezelfde scrollpositie gebruiken. Daarom
 *    laten we Lenis meelopen op de GSAP-ticker en melden we elke scroll bij
 *    ScrollTrigger. Doe je dat niet, dan lopen animaties achter op de scroll.
 *
 * 2. Alles is uit bij `prefers-reduced-motion`. Dat is geen extraatje: voor
 *    bezoekers met bewegingsklachten is een vloeiend scrollende pagina
 *    onbruikbaar tot misselijkmakend. De inhoud moet dan gewoon meteen
 *    zichtbaar zijn, niet onzichtbaar wachtend op een animatie die nooit komt.
 *
 * Zie docs/architecture/frontend-en-animatie.md.
 */

gsap.registerPlugin(ScrollTrigger);

export function prefersReducedMotion(): boolean {
    if (typeof window === 'undefined' || !window.matchMedia) {
        return false;
    }

    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Zet smooth scrolling aan. Geeft een opruimfunctie terug die je in
 * onUnmounted moet aanroepen, anders blijft Lenis draaien na een
 * Inertia-navigatie.
 */
export function startSmoothScroll(): () => void {
    if (prefersReducedMotion()) {
        return () => {};
    }

    const lenis = new Lenis({
        duration: 1.1,
        smoothWheel: true,
    });

    const onScroll = () => ScrollTrigger.update();
    lenis.on('scroll', onScroll);

    const ticker = (time: number) => lenis.raf(time * 1000);
    gsap.ticker.add(ticker);
    gsap.ticker.lagSmoothing(0);

    return () => {
        gsap.ticker.remove(ticker);
        lenis.off('scroll', onScroll);
        lenis.destroy();
    };
}

/**
 * Laat elementen binnenkomen zodra ze in beeld scrollen.
 *
 * Bij reduced motion zetten we de elementen direct op hun eindtoestand in
 * plaats van de animatie over te slaan: anders blijven ze onzichtbaar.
 */
export function revealOnScroll(
    selector: string,
    scope?: Element | null,
): () => void {
    const targets = gsap.utils.toArray<HTMLElement>(
        selector,
        scope ?? undefined,
    );

    if (targets.length === 0) {
        return () => {};
    }

    if (prefersReducedMotion()) {
        gsap.set(targets, { opacity: 1, y: 0 });

        return () => {};
    }

    const tweens = targets.map((target) =>
        gsap.fromTo(
            target,
            { opacity: 0, y: 24 },
            {
                opacity: 1,
                y: 0,
                duration: 0.8,
                ease: 'power2.out',
                scrollTrigger: {
                    trigger: target,
                    start: 'top 85%',
                    once: true,
                },
            },
        ),
    );

    return () => {
        tweens.forEach((tween) => {
            tween.scrollTrigger?.kill();
            tween.kill();
        });
    };
}

export { gsap, ScrollTrigger };
