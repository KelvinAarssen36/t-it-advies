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
 * Laat een tijdlijn zichzelf tekenen terwijl je erdoorheen scrolt.
 *
 * De lijn groeit mee met de scrollpositie en de punten kleuren op zodra de
 * lijn ze passeert. Dat laatste is het verschil met los animeren per punt:
 * nu is het één doorlopende beweging in plaats van een reeks trucjes die
 * toevallig na elkaar afgaan.
 *
 * Drie dingen die hier bewust zo zijn:
 *
 * - **JavaScript raakt de opmaak niet aan.** Het zet alleen de variabele
 *   `--tijdlijn-voortgang` en een attribuut op de punten; de kleuren en de
 *   gloed staan in CSS. Zo blijft de huisstijl op één plek, en schaalt de
 *   lijn met `transform` -- dat draait op de GPU en kost de browser geen
 *   herberekening van de pagina.
 * - **De posities worden gemeten en onthouden**, niet bij elke scrollstap
 *   opnieuw opgevraagd. Opvragen dwingt de browser de opmaak door te
 *   rekenen, en dat is precies wat je tijdens het scrollen wilt vermijden.
 *   Bij een refresh van ScrollTrigger -- na het laden van een afbeelding,
 *   of als de lijst wordt uitgeklapt -- wordt er opnieuw gemeten.
 * - **Rechten worden met `getBoundingClientRect` gemeten en niet met
 *   `offsetTop`.** De punten zitten in hun eigen `position: relative`-kaart,
 *   dus hun `offsetTop` gaat over die kaart en niet over de lijn.
 *
 * Bij reduced motion staat de lijn meteen vol en zijn alle punten
 * gemarkeerd: de inhoud is dan gewoon compleet, zonder beweging.
 *
 * Geeft een opruimfunctie terug; roep die aan in onBeforeUnmount.
 */
export function drawTimeline(
    rail: HTMLElement,
    punten: HTMLElement[],
): () => void {
    const markeerAlles = (): void => {
        rail.style.setProperty('--tijdlijn-voortgang', '1');
        punten.forEach((punt) => punt.setAttribute('data-bereikt', ''));
    };

    if (prefersReducedMotion()) {
        markeerAlles();

        return () => {};
    }

    // Waar elk punt staat, als deel van de lijn tussen 0 en 1.
    let posities: number[] = [];

    const meet = (): void => {
        const lijn = rail.getBoundingClientRect();
        const hoogte = lijn.height || 1;

        posities = punten.map((punt) => {
            const vlak = punt.getBoundingClientRect();

            return (vlak.top + vlak.height / 2 - lijn.top) / hoogte;
        });
    };

    const markeer = (voortgang: number): void => {
        punten.forEach((punt, index) => {
            // Een kleine marge, zodat het punt oplicht op het moment dat de
            // lijn hem raakt en niet een tel nadat hij eroverheen is.
            if (voortgang >= (posities[index] ?? 1) - 0.02) {
                punt.setAttribute('data-bereikt', '');
            } else {
                punt.removeAttribute('data-bereikt');
            }
        });
    };

    meet();

    const trigger = ScrollTrigger.create({
        trigger: rail,
        start: 'top 80%',
        end: 'bottom 65%',
        // Een klein beetje naijlen laat de lijn vloeiend meelopen in plaats
        // van te springen bij een schokkerige scroll.
        scrub: 0.4,
        onRefresh: meet,
        onUpdate: (self) => {
            rail.style.setProperty(
                '--tijdlijn-voortgang',
                self.progress.toFixed(4),
            );
            markeer(self.progress);
        },
    });

    return () => trigger.kill();
}

/**
 * Laat kaarten naast een tijdlijn binnenschuiven vanaf de kant van de rail.
 *
 * Twee dingen waarin dit verschilt van `revealOnScroll`, en allebei met
 * reden:
 *
 * - **Ze komen van opzij en niet van onderen.** Naast een verticale lijn
 *   leest dat als "de kaart groeit uit de lijn"; van onderen komen zou een
 *   tweede richting toevoegen aan een beweging die al verticaal is.
 * - **Ze komen in groepjes binnen, met een stagger.** `ScrollTrigger.batch`
 *   bundelt alles wat tegelijk in beeld schuift, zodat je één golf ziet in
 *   plaats van een paar elementen die toevallig los van elkaar afgaan.
 *
 * Met `direct` animeren ze meteen in plaats van op scrollen te wachten.
 * Dat is voor kaarten die verschijnen doordat de bezoeker iets aanklikt --
 * de rest van een uitgeklapte lijst: die staat al in beeld, dus een
 * scroll-trigger zou nooit afvuren en dan blijft alles op `opacity: 0`
 * hangen. Precies het soort stille fout waar `revealOnScroll` een herscan
 * voor heeft.
 *
 * Geeft een opruimfunctie terug; roep die aan in onBeforeUnmount.
 */
export function revealCards(
    kaarten: HTMLElement[],
    { direct = false }: { direct?: boolean } = {},
): () => void {
    if (kaarten.length === 0) {
        return () => {};
    }

    if (prefersReducedMotion()) {
        gsap.set(kaarten, { opacity: 1, x: 0 });

        return () => {};
    }

    gsap.set(kaarten, { opacity: 0, x: -24 });

    const binnen = (groep: Element[]): void => {
        gsap.to(groep, {
            opacity: 1,
            x: 0,
            duration: 0.6,
            stagger: 0.08,
            ease: 'power3.out',
        });
    };

    if (direct) {
        binnen(kaarten);

        return () => {};
    }

    const triggers = ScrollTrigger.batch(kaarten, {
        start: 'top 88%',
        once: true,
        onEnter: binnen,
    });

    return () => triggers.forEach((trigger) => trigger.kill());
}

/**
 * Laat getallen omhoogtellen zodra ze in beeld komen.
 *
 * Bedoeld voor de cijfers boven een lijst: "35 jaar ervaring". Een getal
 * dat oploopt vraagt precies lang genoeg aandacht om gelezen te worden, en
 * dat is het enige wat zo'n cijfer moet doen.
 *
 * **Het eindgetal staat al in de HTML.** Deze functie zet hem op nul en
 * telt terug omhoog. Andersom -- leeg beginnen en door JavaScript laten
 * vullen -- betekent dat er niets staat als er iets misgaat, en dat een
 * zoekmachine een lege plek ziet.
 *
 * Het getal komt uit `data-tot`, want de tekst zelf is halverwege de
 * animatie niet meer te vertrouwen.
 *
 * Geeft een opruimfunctie terug; roep die aan in onBeforeUnmount.
 */
export function countUp(tellers: HTMLElement[]): () => void {
    if (tellers.length === 0) {
        return () => {};
    }

    const schrijf = (element: HTMLElement, waarde: number): void => {
        element.textContent = String(Math.round(waarde));
    };

    const eind = (element: HTMLElement): number =>
        Number(element.dataset.tot ?? element.textContent ?? 0);

    if (prefersReducedMotion()) {
        tellers.forEach((teller) => schrijf(teller, eind(teller)));

        return () => {};
    }

    const tweens = tellers.map((teller) => {
        // Een los object om te animeren: GSAP tweent getallen, geen tekst.
        const stand = { waarde: 0 };

        schrijf(teller, 0);

        return gsap.to(stand, {
            waarde: eind(teller),
            duration: 1.4,
            ease: 'power2.out',
            onUpdate: () => schrijf(teller, stand.waarde),
            scrollTrigger: {
                trigger: teller,
                start: 'top 90%',
                once: true,
            },
        });
    });

    return () => {
        tweens.forEach((tween) => {
            tween.scrollTrigger?.kill();
            tween.kill();
        });
    };
}

/**
 * Markeert het jaartal van de groep waar je doorheen scrolt.
 *
 * Het jaartal blijft al hangen zolang je in zijn groep zit -- dat is
 * `position: sticky` en kost geen JavaScript. Wat hier bijkomt is dat het
 * ook oplicht zodra die groep aan de beurt is, zodat je ziet welk jaar je
 * aan het lezen bent en niet alleen welk jaar er toevallig links staat.
 *
 * De grens ligt op het midden van het scherm: het jaartal is actief zolang
 * die lijn binnen zijn groep valt. Daarmee is er altijd precies één actief,
 * ook bij een groep die korter is dan het scherm.
 *
 * Net als bij de tijdlijn zet JavaScript alleen een attribuut; de kleur
 * staat in CSS.
 *
 * Geeft een opruimfunctie terug; roep die aan in onBeforeUnmount.
 */
export function followYears(groepen: HTMLElement[]): () => void {
    if (groepen.length === 0 || prefersReducedMotion()) {
        return () => {};
    }

    const triggers = groepen.map((groep) =>
        ScrollTrigger.create({
            trigger: groep,
            start: 'top center',
            end: 'bottom center',
            onToggle: (self) => {
                const jaar = groep.querySelector<HTMLElement>('[data-jaar]');

                if (jaar === null) {
                    return;
                }

                if (self.isActive) {
                    jaar.setAttribute('data-actief', '');
                } else {
                    jaar.removeAttribute('data-actief');
                }
            },
        }),
    );

    return () => triggers.forEach((trigger) => trigger.kill());
}

/**
 * Laat de bezoeker met zijn muiswiel door een reeks bladeren, maar alleen
 * binnen één vak.
 *
 * Dit is de tegenhanger van een blok dat vastzit terwijl de pagina
 * doorloopt. Hier blijft de pagina staan waar hij staat: zolang je muis
 * **in het vak** hangt gaat je scroll naar de reeks, en zodra die op is
 * neemt de pagina het weer over. Dat is precies zo gebouwd omdat het vak
 * dan ook is wat het lijkt -- je ziet een omlijnd gebied, en dat gebied is
 * ook waar het gebeurt.
 *
 * `onVerplaats` krijgt de verschuiving en geeft terug of hij hem heeft
 * gebruikt. Zo niet -- je bent aan het begin of het eind van de reeks --
 * dan laten we de gebeurtenis los en scrolt de pagina gewoon door. Zonder
 * die uitweg zit de bezoeker vast in het vak, en dat is het ergste wat een
 * onderdeel als dit kan doen.
 *
 * **Drie dingen die hier bewust zo zijn:**
 *
 * - **Het vak moet het midden van het scherm beslaan** voordat er iets
 *   gebeurt. Anders pakt het je scroll af terwijl je er alleen maar
 *   langsschuift, en dat voelt als een pagina die tegenwerkt.
 * - **`data-lenis-prevent` hoort op het element.** Zonder dat neemt Lenis
 *   het wiel als eerste af en komt onze afhandeling nooit aan de beurt.
 * - **Een vinger telt dubbel.** Een veegbeweging legt minder afstand af
 *   dan een muiswiel, en zonder die factor voelt het op een telefoon alsof
 *   er niets beweegt.
 *
 * Geeft een opruimfunctie terug; roep die aan in onBeforeUnmount.
 */
export function scrollThrough(
    vak: HTMLElement,
    { onVerplaats }: { onVerplaats: (verschuiving: number) => boolean },
): () => void {
    /** Hoeveel van het vak in beeld moet staan voor we de scroll pakken. */
    const inBeeld = (): boolean => {
        const vlak = vak.getBoundingClientRect();
        const midden = window.innerHeight / 2;

        return vlak.top < midden && vlak.bottom > midden;
    };

    const opWiel = (gebeurtenis: WheelEvent): void => {
        if (!inBeeld() || !onVerplaats(gebeurtenis.deltaY)) {
            return;
        }

        gebeurtenis.preventDefault();
    };

    let vorigeY: number | null = null;

    const opAanraking = (gebeurtenis: TouchEvent): void => {
        vorigeY = gebeurtenis.touches[0]?.clientY ?? null;
    };

    const opVeeg = (gebeurtenis: TouchEvent): void => {
        const y = gebeurtenis.touches[0]?.clientY;

        if (y === undefined || vorigeY === null) {
            return;
        }

        // Vinger omhoog betekent vooruit, net als bij scrollen.
        const verschuiving = (vorigeY - y) * 2.2;

        vorigeY = y;

        if (!inBeeld() || !onVerplaats(verschuiving)) {
            return;
        }

        gebeurtenis.preventDefault();
    };

    vak.setAttribute('data-lenis-prevent', '');

    // `passive: false`, anders mag je de gebeurtenis niet tegenhouden.
    vak.addEventListener('wheel', opWiel, { passive: false });
    vak.addEventListener('touchstart', opAanraking, { passive: true });
    vak.addEventListener('touchmove', opVeeg, { passive: false });

    return () => {
        vak.removeAttribute('data-lenis-prevent');
        vak.removeEventListener('wheel', opWiel);
        vak.removeEventListener('touchstart', opAanraking);
        vak.removeEventListener('touchmove', opVeeg);
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
