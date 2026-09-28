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

/**
 * Houdt bij hoe ver de bezoeker de pagina door is, als getal tussen 0 en 1.
 *
 * Het getal komt als CSS-variabele `--scroll-progress` op het meegegeven
 * element te staan; JavaScript raakt de opmaak verder niet aan. De balk
 * zelf schaalt daarop met `scaleX`, wat op de GPU draait en geen
 * herberekening van de pagina kost. Zou je in plaats daarvan `width`
 * aanpassen, dan rekent de browser bij elke scrollstap de hele pagina
 * opnieuw door en gaat het schokken.
 *
 * Twee dingen die hier anders zijn dan in het simpelste recept:
 *
 * - **Het schrijven gebeurt in een animation frame.** Een scroll-event kan
 *   vaker vuren dan het scherm ververst; zonder die bundeling doe je
 *   meerdere keren per beeldje hetzelfde werk voor niets.
 * - **De scrollbare hoogte wordt gemeten en onthouden**, niet bij elke
 *   scrollstap opnieuw opgevraagd. Dat opvragen dwingt de browser de
 *   opmaak door te rekenen, en dat is precies wat je tijdens het scrollen
 *   wilt vermijden. Een ResizeObserver houdt de maat bij wanneer de inhoud
 *   verandert -- bijvoorbeeld als een afbeelding binnenkomt.
 *
 * Geeft een opruimfunctie terug; roep die aan in onBeforeUnmount.
 */
export function trackScrollProgress(target: HTMLElement): () => void {
    if (typeof window === 'undefined') {
        return () => {};
    }

    let scrollable = 0;
    let frame = 0;

    const write = (): void => {
        frame = 0;

        const progress = scrollable > 0 ? window.scrollY / scrollable : 0;

        // Afkappen tussen 0 en 1: op een telefoon kun je voorbij het begin
        // en het einde doorveren, en dan schiet de balk anders door.
        const clamped = Math.min(Math.max(progress, 0), 1);

        target.style.setProperty('--scroll-progress', clamped.toFixed(4));
    };

    const schedule = (): void => {
        if (frame === 0) {
            frame = window.requestAnimationFrame(write);
        }
    };

    const measure = (): void => {
        scrollable = document.documentElement.scrollHeight - window.innerHeight;
        schedule();
    };

    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', measure);

    const observer = new ResizeObserver(measure);
    observer.observe(document.body);

    measure();

    return () => {
        window.removeEventListener('scroll', schedule);
        window.removeEventListener('resize', measure);
        observer.disconnect();

        if (frame !== 0) {
            window.cancelAnimationFrame(frame);
        }
    };
}

export { gsap, ScrollTrigger };
