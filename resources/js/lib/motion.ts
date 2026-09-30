import gsap from 'gsap';
import { DrawSVGPlugin } from 'gsap/DrawSVGPlugin';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { SplitText } from 'gsap/SplitText';
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

/*
 * Sinds GSAP 3.13 zitten de vroegere Club-plugins gewoon in het pakket.
 * Ze worden hier geregistreerd en nergens anders: dit bestand is het
 * enige dat GSAP importeert, waardoor de bundler alles in de chunk van de
 * publieke site houdt. Het portaal laadt geen enkele byte hiervan.
 */
gsap.registerPlugin(ScrollTrigger, SplitText, DrawSVGPlugin);

/**
 * Of dit apparaat een precieze aanwijzer heeft.
 *
 * Alles wat op de muis reageert hangt hieraan. Op een aanraakscherm
 * bestaat "de cursor volgen" niet, en een `pointermove` die daar toch
 * afgaat zet een effect aan dat de bezoeker nooit meer uit krijgt -- hij
 * kan zijn vinger immers niet "weg bewegen".
 */
export function heeftMuis(): boolean {
    if (typeof window === 'undefined' || !window.matchMedia) {
        return false;
    }

    return window.matchMedia('(pointer: fine)').matches;
}

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
let lenis: Lenis | undefined;

export function startSmoothScroll(): () => void {
    if (prefersReducedMotion()) {
        return () => {};
    }

    /*
     * Eerst in een eigen variabele en dan pas in de module.
     *
     * `lenis` op moduleniveau kan ondertussen door iemand anders leeggezet
     * zijn -- bij een navigatie loopt de opruimfunctie van de vorige
     * layout er doorheen. TypeScript ziet dat terecht als "kan undefined
     * zijn"; met een eigen verwijzing weet zowel de compiler als deze
     * functie zeker over wélke Lenis het gaat.
     */
    const huidige = new Lenis({
        duration: 1.1,
        smoothWheel: true,
    });

    lenis = huidige;

    const onScroll = () => ScrollTrigger.update();
    huidige.on('scroll', onScroll);

    const ticker = (time: number) => huidige.raf(time * 1000);
    gsap.ticker.add(ticker);
    gsap.ticker.lagSmoothing(0);

    return () => {
        gsap.ticker.remove(ticker);
        huidige.off('scroll', onScroll);
        huidige.destroy();

        // Alleen leegmaken als er ondertussen geen nieuwe is gestart.
        if (lenis === huidige) {
            lenis = undefined;
        }
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

/**
 * Laat tekst regel voor regel achter een masker omhoogkomen.
 *
 * Het verschil met `revealOnScroll` is groter dan het klinkt. Daar schuift
 * een heel tekstblok als één vlak omhoog; hier worden de regels los
 * opgetild vanachter hun eigen onderrand. Dat leest als "de tekst wordt
 * gezet" in plaats van "er verschijnt tekst", en dat is precies het effect
 * dat je op een kop wilt.
 *
 * Drie dingen die hier bewust zo zijn:
 *
 * - **`mask: 'lines'` doet het maskeren, niet wij.** SplitText zet sinds
 *   3.13 zelf een omhulsel met `overflow: hidden` om elke regel. Zelf divs
 *   eromheen bouwen werkt ook, maar breekt zodra de tekst over andere
 *   regels wordt verdeeld.
 * - **`autoSplit` hersplitst bij een andere breedte of een later geladen
 *   lettertype.** Zonder dat staan de regels na het draaien van een
 *   telefoon op de verkeerde plek, of -- erger -- splitst hij op het
 *   terugvallettertype en springt de tekst zodra het echte binnenkomt. De
 *   animatie hoort daarom ín `onSplit`, want die draait bij elke
 *   hersplitsing opnieuw.
 * - **SplitText zet zelf een `aria-label` op het element.** Een schermlezer
 *   leest daardoor de hele zin voor en niet los-los per regel. Haal dat er
 *   niet af.
 *
 * Het element draagt `opacity-0` in zijn klassen, net als bij de gewone
 * reveal; we zetten hem hier zichtbaar zodra het masker klaarstaat.
 *
 * Geeft een opruimfunctie terug; roep die aan in onBeforeUnmount.
 */
export function splitReveal(
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

    const splits = targets.map((target) => {
        gsap.set(target, { opacity: 1 });

        return SplitText.create(target, {
            type: 'lines',
            mask: 'lines',
            autoSplit: true,
            onSplit: (self) =>
                gsap.from(self.lines, {
                    yPercent: 115,
                    duration: 0.9,
                    stagger: 0.09,
                    ease: 'power3.out',
                    scrollTrigger: {
                        trigger: target,
                        start: 'top 88%',
                        once: true,
                    },
                }),
        });
    });

    return () => splits.forEach((split) => split.revert());
}

/**
 * Laat een element trager of sneller meelopen dan de pagina.
 *
 * Dit is wat diepte geeft: blijft de achtergrond achter terwijl de tekst
 * wegschuift, dan lijkt hij verder weg te liggen. Meer is het niet, en meer
 * moet het ook niet worden -- parallax die opvalt is parallax die misselijk
 * maakt.
 *
 * `afstand` is in beeldpunten. Het element begint op de helft ervan omhoog
 * en eindigt op de helft omlaag, zodat hij halverwege het scherm op zijn
 * eigen plek staat en de opmaak niet verschuift.
 *
 * We animeren `y` en niet `top` of `background-position`, zodat het op de
 * GPU blijft en de browser de pagina niet bij elke scrollstap opnieuw hoeft
 * door te rekenen.
 *
 * Geeft een opruimfunctie terug; roep die aan in onBeforeUnmount.
 */
export function parallax(
    element: HTMLElement,
    { afstand = 80, vanaf = 'top bottom', tot = 'bottom top' } = {},
): () => void {
    if (prefersReducedMotion()) {
        return () => {};
    }

    const tween = gsap.fromTo(
        element,
        { y: -afstand / 2 },
        {
            y: afstand / 2,
            ease: 'none',
            scrollTrigger: {
                trigger: element,
                start: vanaf,
                end: tot,
                scrub: true,
                invalidateOnRefresh: true,
            },
        },
    );

    return () => {
        tween.scrollTrigger?.kill();
        tween.kill();
    };
}

/**
 * Laat een vlak zachtjes meebewegen met de muis.
 *
 * Bedoeld voor de gloed achter de hero: die verschuift een paar pixels met
 * de cursor mee, wat de pagina diepte geeft zonder dat er iets beweegt waar
 * je op wilt klikken.
 *
 * Het schrijft twee variabelen op het element, `--muis-x` en `--muis-y`,
 * allebei tussen -1 en 1. Wat daarmee gebeurt staat in CSS -- JavaScript
 * bepaalt hier alleen waar de muis is, niet hoe het eruitziet.
 *
 * `gsap.quickTo` doet het naijlen. Zonder dat plakt de gloed aan de cursor
 * en beweegt hij net zo schokkerig als je hand; met een kleine vertraging
 * voelt het alsof er iets zwaars achteraan komt.
 *
 * Gebeurt niets op een aanraakscherm en bij reduced motion.
 *
 * Geeft een opruimfunctie terug; roep die aan in onBeforeUnmount.
 */
export function volgDeMuis(element: HTMLElement): () => void {
    if (prefersReducedMotion() || !heeftMuis()) {
        return () => {};
    }

    /*
     * De variabelen worden hier rechtstreeks geschreven, en niet door
     * GSAP geanimeerd.
     *
     * Dat stond er eerst wel, met `gsap.quickTo` op `--muis-x`. Dat is
     * precies het soort code waarvan je pas merkt dat hij niets doet als
     * iemand zegt "ik zie niks bij mijn cursor": een CSS-variabele heeft
     * geen betrouwbare beginwaarde om vanaf te rekenen, dus er komt geen
     * foutmelding -- er gebeurt alleen niets.
     *
     * Het naijlen doet CSS nu, met een `transition` op de verschuiving.
     * Dat is één regel in de stylesheet, werkt gegarandeerd, en houdt
     * zich aan de afspraak dat JavaScript hier alleen zegt wáár de muis
     * is en niet hoe het eruitziet.
     */
    let frame = 0;
    let doelX = 0;
    let doelY = 0;

    const schrijf = (): void => {
        frame = 0;

        element.style.setProperty('--muis-x', doelX.toFixed(3));
        element.style.setProperty('--muis-y', doelY.toFixed(3));
    };

    /*
     * Eén schrijfbeurt per beeldje. Een `pointermove` kan vaker vuren dan
     * het scherm ververst, en dan doe je meerdere keren per beeldje
     * hetzelfde werk voor niets.
     */
    const plan = (): void => {
        if (frame === 0) {
            frame = window.requestAnimationFrame(schrijf);
        }
    };

    const opBeweging = (gebeurtenis: PointerEvent): void => {
        const vlak = element.getBoundingClientRect();

        if (vlak.width === 0 || vlak.height === 0) {
            return;
        }

        // Afkappen tussen -1 en 1: buiten het vlak loopt de waarde anders
        // door en schuift de gloed het beeld uit.
        doelX = Math.max(
            -1,
            Math.min(
                1,
                ((gebeurtenis.clientX - vlak.left) / vlak.width) * 2 - 1,
            ),
        );
        doelY = Math.max(
            -1,
            Math.min(
                1,
                ((gebeurtenis.clientY - vlak.top) / vlak.height) * 2 - 1,
            ),
        );

        plan();
    };

    const opVertrek = (): void => {
        doelX = 0;
        doelY = 0;

        plan();
    };

    // Op window en niet op het element: de gloed hoort ook te reageren als
    // je ernaast beweegt, anders springt hij pas aan zodra je de rand raakt.
    window.addEventListener('pointermove', opBeweging, { passive: true });
    window.addEventListener('pointerleave', opVertrek);

    return () => {
        window.removeEventListener('pointermove', opBeweging);
        window.removeEventListener('pointerleave', opVertrek);

        if (frame !== 0) {
            window.cancelAnimationFrame(frame);
        }
    };
}

/**
 * Laat kaarten licht meekantelen met de cursor, met een glans die meeloopt.
 *
 * Vier graden, niet meer. Een kaart die ver doorkantelt wordt een speeltje;
 * een kaart die nét meegeeft wordt een oppervlak met diepte, en dat is het
 * verschil tussen indruk en afleiding.
 *
 * De kanteling staat op de kaart zelf, de glans in twee variabelen
 * (`--glans-x` en `--glans-y`) zodat de opmaak ervan in CSS blijft.
 *
 * **Houd de graden laag, juist omdat er iets aanklikbaars in kan zitten.**
 * Bij een grote kanteling schuift een knop onder je cursor vandaan op het
 * moment dat je ernaartoe beweegt, en dan werkt de kaart je tegen.
 *
 * Gebeurt niets op een aanraakscherm en bij reduced motion.
 *
 * Geeft een opruimfunctie terug; roep die aan in onBeforeUnmount.
 */
export function kantelKaarten(
    kaarten: HTMLElement[],
    { graden = 4 } = {},
): () => void {
    if (kaarten.length === 0 || prefersReducedMotion() || !heeftMuis()) {
        return () => {};
    }

    /*
     * Zonder perspectief is `rotateX` een platte samendrukking in plaats
     * van een kanteling. `transformPerspective` zet die `perspective()`
     * in de transform van de kaart zelf; de CSS-eigenschap `perspective`
     * zou op de ouder moeten staan, en die deelt hij met de andere
     * kaarten -- dan kantelen ze alsof ze in één doos zitten.
     */
    gsap.set(kaarten, { transformPerspective: 800 });

    const opruimers = kaarten.map((kaart) => {
        const naarX = gsap.quickTo(kaart, 'rotateX', {
            duration: 0.5,
            ease: 'power3.out',
        });
        const naarY = gsap.quickTo(kaart, 'rotateY', {
            duration: 0.5,
            ease: 'power3.out',
        });

        const opBeweging = (gebeurtenis: PointerEvent): void => {
            const vlak = kaart.getBoundingClientRect();

            const x = (gebeurtenis.clientX - vlak.left) / vlak.width;
            const y = (gebeurtenis.clientY - vlak.top) / vlak.height;

            // Naar boven kijken is negatief draaien om de x-as, vandaar het min.
            naarX(-(y - 0.5) * 2 * graden);
            naarY((x - 0.5) * 2 * graden);

            kaart.style.setProperty('--glans-x', `${(x * 100).toFixed(1)}%`);
            kaart.style.setProperty('--glans-y', `${(y * 100).toFixed(1)}%`);
        };

        const opVertrek = (): void => {
            naarX(0);
            naarY(0);
        };

        kaart.addEventListener('pointermove', opBeweging, { passive: true });
        kaart.addEventListener('pointerleave', opVertrek);

        return () => {
            kaart.removeEventListener('pointermove', opBeweging);
            kaart.removeEventListener('pointerleave', opVertrek);
            gsap.killTweensOf(kaart);
        };
    });

    return () => opruimers.forEach((opruimen) => opruimen());
}

/**
 * Laat een SVG-pad zichzelf tekenen terwijl je scrolt, met een lichtpuntje
 * dat eroverheen reist.
 *
 * Dit is de rijkere broer van `drawTimeline`. Die schaalt een rechte balk;
 * hier tekent een echt pad zich uit, dus mag het bochten hebben. Het
 * lichtpuntje volgt datzelfde pad, wat het verschil maakt tussen "er
 * verschijnt een lijn" en "er gaat iets langs".
 *
 * Drie dingen die hier bewust zo zijn:
 *
 * - **De stappen worden gemeten, niet geteld.** Waar stap drie oplicht
 *   hangt af van waar hij staat, niet van "de derde van de vier". Zou je
 *   delen door het aantal, dan klopt het alleen bij gelijke afstanden --
 *   en bij een laatste stap met een langere tekst klopt het al niet meer.
 * - **Er wordt in de x-richting gemeten.** Dit pad loopt horizontaal boven
 *   een rij; voor een verticale variant is dit de regel die je omzet.
 * - **JavaScript zet alleen een attribuut op de stap.** De kleur en de
 *   gloed staan in CSS, zodat de huisstijl op één plek blijft.
 *
 * Bij reduced motion staat het pad meteen vol getekend, is het lichtpuntje
 * weg en zijn alle stappen gemarkeerd.
 *
 * Geeft een opruimfunctie terug; roep die aan in onBeforeUnmount.
 */
export function tekenPad(
    pad: SVGPathElement,
    punt: SVGElement | null,
    stappen: HTMLElement[],
): () => void {
    if (prefersReducedMotion()) {
        gsap.set(pad, { drawSVG: '100%' });
        gsap.set(punt, { opacity: 0 });
        stappen.forEach((stap) => stap.setAttribute('data-bereikt', ''));

        return () => {};
    }

    // Waar elke stap staat, als deel van de breedte van het pad.
    let posities: number[] = [];

    const meet = (): void => {
        const vlak = pad.getBoundingClientRect();
        const breedte = vlak.width || 1;

        posities = stappen.map((stap) => {
            const eigen = stap.getBoundingClientRect();

            return (eigen.left + eigen.width / 2 - vlak.left) / breedte;
        });
    };

    const markeer = (voortgang: number): void => {
        stappen.forEach((stap, index) => {
            if (voortgang >= (posities[index] ?? 1) - 0.02) {
                stap.setAttribute('data-bereikt', '');
            } else {
                stap.removeAttribute('data-bereikt');
            }
        });
    };

    gsap.set(pad, { drawSVG: '0%' });

    meet();

    /*
     * Het lichtpuntje wordt met `getPointAtLength` neergezet en niet
     * met MotionPath.
     *
     * Die plugin meet in schermpixels en zet daarna een verschuiving in
     * de coördinaten van de SVG. Dat klopt zolang die twee dezelfde
     * verhouding hebben -- en dat is hier juist niet zo: het pad staat
     * op `preserveAspectRatio="none"` zodat de lijn met de breedte
     * meerekt, dus horizontaal wordt hij heel anders geschaald dan
     * verticaal. Het puntje liep daardoor scheef van de lijn af, en hoe
     * erger naarmate het scherm breder was.
     *
     * `getPointAtLength` geeft een punt in de coördinaten van het pad
     * zelf. Zet je dat op `cx` en `cy` van een cirkel in diezelfde SVG,
     * dan ondergaat hij exact dezelfde rek als de lijn -- hoe scheef die
     * ook is.
     */
    const zetPunt = (voortgang: number): void => {
        if (punt === null) {
            return;
        }

        const lengte = pad.getTotalLength();

        if (lengte === 0) {
            return;
        }

        const plek = pad.getPointAtLength(lengte * voortgang);

        punt.setAttribute('cx', plek.x.toFixed(2));
        punt.setAttribute('cy', plek.y.toFixed(2));
    };

    zetPunt(0);

    const tijdlijn = gsap.timeline({
        scrollTrigger: {
            trigger: pad,
            start: 'top 78%',
            end: 'bottom 55%',
            scrub: 0.4,
            onRefresh: meet,
            onUpdate: (self) => {
                markeer(self.progress);
                zetPunt(self.progress);
            },
        },
    });

    tijdlijn.to(pad, { drawSVG: '100%', ease: 'none' }, 0);

    return () => {
        tijdlijn.scrollTrigger?.kill();
        tijdlijn.kill();
    };
}

/**
 * Laat een rij kaarten één voor één binnenkomen zodra ze in beeld komen.
 *
 * Ze komen van onderen op, iets te klein en licht van je af gekanteld, en
 * zetten zich dan recht. Die kanteling is wat het meer maakt dan een fade:
 * de kaart lijkt naar je toe te draaien in plaats van te verschijnen.
 *
 * **Hier stond eerst een vastgezette sectie waarin de kaarten uit een
 * stapel openwaaierden.** Dat is eruit gehaald, en dat was de goede keuze:
 * de pin duwde de sectiekop achter de plakkende balk, en het spacer-vak
 * dat ScrollTrigger ervoor nodig heeft liet een schermhoogte aan lege navy
 * achter onder de kaarten. Twee problemen die je alleen ziet als je het
 * echt opent, en allebei erger dan het effect mooi was. Wat overblijft is
 * dit: geen pin, geen spacer, geen lege ruimte.
 *
 * De kaarten dragen géén `data-reveal` meer als deze functie ze pakt --
 * anders animeren twee dingen dezelfde doorzichtigheid. Dat is de reden
 * dat deze functie de begintoestand zelf zet.
 *
 * Geeft een opruimfunctie terug; roep die aan in onBeforeUnmount.
 */
export function kaartenBinnen(kaarten: HTMLElement[]): () => void {
    if (kaarten.length === 0) {
        return () => {};
    }

    if (prefersReducedMotion()) {
        gsap.set(kaarten, { opacity: 1, y: 0, rotateX: 0, scale: 1 });

        return () => {};
    }

    gsap.set(kaarten, { transformPerspective: 900 });

    const tween = gsap.fromTo(
        kaarten,
        { opacity: 0, y: 40, rotateX: -12, scale: 0.96 },
        {
            opacity: 1,
            y: 0,
            rotateX: 0,
            scale: 1,
            duration: 0.85,
            stagger: 0.12,
            ease: 'power3.out',
            scrollTrigger: {
                trigger: kaarten[0],
                start: 'top 85%',
                once: true,
            },
        },
    );

    return () => {
        tween.scrollTrigger?.kill();
        tween.kill();
    };
}

/**
 * Tikt een kop letter voor letter in beeld, met een cursor die meeloopt.
 *
 * **De cursor is wat het typen typen maakt.** Zonder hem zie je letters
 * verschijnen, en dat kan van alles zijn. Met een knipperend streepje dat
 * telkens één plek opschuift, kijk je naar iemand die typt. Dat was de
 * eerste versie hier: losse tekens die aansprongen, veel te snel, en
 * zonder cursor -- en dat las als "er verschijnt tekst" in plaats van als
 * typen.
 *
 * **De letters staan er al, onzichtbaar.** Dat is het verschil met de
 * voor de hand liggende aanpak, waarbij je steeds een teken aan de inhoud
 * plakt (GSAP heeft daar TextPlugin voor). Die laat de kop bij elk woord
 * opnieuw afbreken, en dan springt alles eronder mee. Met SplitText staat
 * de ruimte er vanaf het begin en verandert alleen de doorzichtigheid.
 *
 * **Het ritme is niet gelijkmatig.** Een mens typt niet als een metronoom,
 * en precies gelijke tussenpozen zijn wat een typeanimatie machinaal laat
 * aanvoelen. Elke aanslag krijgt hier een kleine willekeurige afwijking,
 * en na een spatie of een punt valt er een korte adempauze.
 *
 * `autoSplit` staat bewust uit. Hersplitsen midden in het typen zou de
 * animatie opnieuw laten beginnen, en een kop die twee keer wordt ingetikt
 * lijkt kapot. De prijs is dat de regelafbreking blijft staan zoals hij
 * bij het laden was; voor één kop van een paar woorden is dat de betere
 * ruil.
 *
 * Geeft een opruimfunctie terug; roep die aan in onBeforeUnmount.
 */
export function typMachine(
    kop: HTMLElement,
    { wachten = 0, perTeken = 0.09 } = {},
): () => void {
    gsap.set(kop, { opacity: 1 });

    if (prefersReducedMotion()) {
        return () => {};
    }

    const split = SplitText.create(kop, { type: 'chars,lines' });

    /*
     * De cursor is een los element dat door de tekst wandelt: na elke
     * aanslag wordt hij achter het zojuist getikte teken gezet. Dat is
     * een DOM-verplaatsing per letter -- een paar tientallen keer, en
     * daar merkt niemand iets van.
     *
     * Hij staat in de tekst en niet los eroverheen. Zou hij zweven, dan
     * moet je zijn plek uitrekenen, en dan klopt hij niet meer zodra de
     * regel anders afbreekt.
     */
    const cursor = document.createElement('span');

    cursor.className = 'brand-typcursor';
    cursor.setAttribute('aria-hidden', 'true');

    const eersteRegel = split.lines[0];

    if (eersteRegel !== undefined) {
        eersteRegel.prepend(cursor);
    }

    gsap.set(split.chars, { opacity: 0 });

    const tijdlijn = gsap.timeline({ delay: wachten });

    let moment = 0;

    split.chars.forEach((teken) => {
        tijdlijn.set(teken, { opacity: 1 }, moment);
        tijdlijn.add(() => teken.after(cursor), moment);

        // Een kleine afwijking per aanslag, en een adempauze na het eind
        // van een woord of een zin.
        const tekst = teken.textContent ?? '';
        const pauze = /[.,!?]/.test(tekst) ? 4 : /\s/.test(tekst) ? 1.6 : 1;

        moment += perTeken * pauze * (0.75 + Math.random() * 0.5);
    });

    // De cursor knippert nog even door en verdwijnt dan. Meteen weghalen
    // op de laatste letter voelt afgekapt; eeuwig laten staan trekt de
    // aandacht van de tekst weg.
    tijdlijn.to(cursor, { opacity: 0, duration: 0.3 }, moment + 1.2);

    return () => {
        tijdlijn.kill();
        cursor.remove();
        split.revert();
    };
}

/**
 * De ring om het ronde portret: hij tekent zichzelf, en daarna blijft er
 * een lichtboog omheen draaien.
 *
 * Twee lagen met elk hun eigen taak. De **vaste ring** tekent zich één
 * keer bij binnenkomst en blijft dan staan: dat is het kader. De
 * **lichtboog** draait daarna eindeloos rond, en dat is wat het medaillon
 * levend houdt in plaats van er alleen maar te staan.
 *
 * De boog draait bewust traag -- veertien seconden voor een hele ronde.
 * Hij zit naast tekst die gelezen moet worden, en alles wat sneller
 * beweegt trekt de ogen weg van die tekst. Dit valt op als je ernaar
 * kijkt en niet als je ernaast leest.
 *
 * **De rotatie zit op een groep om de boog heen en niet op de boog zelf.**
 * Een SVG-element draait om de oorsprong van zijn eigen coördinaten, en
 * dat is bij een cirkel de linkerbovenhoek van het tekenvlak en niet zijn
 * middelpunt. Met `transformOrigin` op de groep staat dat vast op het
 * midden, ongeacht hoe groot het medaillon op dat moment is.
 *
 * Bij reduced motion staat de ring meteen vol en draait er niets.
 *
 * Geeft een opruimfunctie terug; roep die aan in onBeforeUnmount.
 */
export function tekenRing(
    ring: SVGCircleElement,
    baan: SVGGElement | null,
    { wachten = 0 } = {},
): () => void {
    if (prefersReducedMotion()) {
        gsap.set(ring, { drawSVG: '100%' });
        gsap.set(baan, { opacity: 1 });

        return () => {};
    }

    const tekenen = gsap.fromTo(
        ring,
        { drawSVG: '0%' },
        {
            drawSVG: '100%',
            duration: 1.1,
            delay: wachten,
            ease: 'power2.inOut',
        },
    );

    if (baan === null) {
        return () => tekenen.kill();
    }

    const verschijnen = gsap.fromTo(
        baan,
        { opacity: 0 },
        { opacity: 1, duration: 0.6, delay: wachten + 0.9 },
    );

    /*
     * **De boog draait alleen op een breed scherm.**
     *
     * Een animatie die nooit ophoudt houdt zijn laag eeuwig in beweging,
     * en een browser rastert een bewegende laag in lagere resolutie --
     * die scherpte komt pas terug als hij stilstaat. Hier zit de foto in
     * diezelfde laag, dus op een telefoon oogt het portret permanent
     * wazig. Op een desktop-GPU valt dat weg; op een telefoon niet.
     *
     * `gsap.matchMedia` en niet één meting bij het laden: draai je je
     * telefoon, dan hoort het mee te veranderen, en hij draait bovendien
     * netjes terug wat hij had gezet.
     */
    const mm = gsap.matchMedia();

    mm.add('(min-width: 50rem)', () => {
        const draaien = gsap.to(baan, {
            rotation: 360,
            transformOrigin: 'center center',
            svgOrigin: '50 50',
            duration: 14,
            repeat: -1,
            ease: 'none',
        });

        return () => draaien.kill();
    });

    return () => {
        tekenen.kill();
        verschijnen.kill();
        mm.revert();
    };
}

/**
 * Scrolt soepel naar een onderdeel op de pagina.
 *
 * **Via Lenis en niet via `window.scrollTo` of ScrollToPlugin.** Lenis
 * houdt zijn eigen scrollpositie bij en duwt die elk beeldje naar de
 * pagina; zou iets anders daar tegelijk aan trekken, dan vechten de twee
 * en trilt de pagina. Zijn eigen `scrollTo` weet ervan en neemt het over.
 *
 * `verschuiving` is negatief: de plakkende kop staat boven het onderdeel,
 * dus we moeten een stukje eerder stoppen. Zonder dat verdwijnt de
 * sectietitel achter de balk -- precies het gebrek dat een anker zonder
 * `scroll-margin` ook heeft.
 *
 * Zeven tienden van een seconde: snel genoeg om niet te wachten, traag
 * genoeg om te zien waar je heen gaat. Bij reduced motion is het een
 * directe sprong; een lange glijbeweging over de hele pagina is precies
 * waar iemand met bewegingsklachten last van heeft.
 *
 * Geeft terug of het gelukt is. Bestaat het doel niet -- de klant heeft
 * dat onderdeel net uitgezet -- dan `false`, en dan hoort de aanroeper de
 * link gewoon zijn werk te laten doen.
 */
export function scrollNaar(doel: string, { verschuiving = -72 } = {}): boolean {
    if (typeof document === 'undefined') {
        return false;
    }

    const element =
        doel === 'top' ? document.body : document.getElementById(doel);

    if (element === null) {
        return false;
    }

    const naarBoven = doel === 'top';

    if (prefersReducedMotion() || lenis === undefined) {
        element.scrollIntoView({ block: 'start' });

        if (!naarBoven) {
            window.scrollBy(0, verschuiving);
        }

        return true;
    }

    lenis.scrollTo(naarBoven ? 0 : element, {
        offset: naarBoven ? 0 : verschuiving,
        duration: 0.7,
        easing: (t: number) => 1 - Math.pow(1 - t, 3),
    });

    return true;
}

/**
 * Houdt bij welk onderdeel je aan het bekijken bent.
 *
 * `opActief` krijgt de sleutel van het onderdeel waar de denkbeeldige lijn
 * op een derde van het scherm doorheen loopt, of `null` als je erboven
 * zit -- in de hero dus, en dan hoort er in het menu niets op te lichten.
 *
 * **Een derde van boven en niet het midden.** Je leest van boven naar
 * beneden, dus je bent met de bovenkant van een sectie bezig terwijl het
 * midden van het scherm nog bij de vorige hoort.
 *
 * Roep dit opnieuw aan als de lijst met onderdelen verandert; de klant kan
 * ze verslepen en uitzetten, en dan klopt een oude reeks triggers niet
 * meer. De opruimfunctie ruimt alles op wat deze aanroep heeft gemaakt.
 *
 * Geeft een opruimfunctie terug; roep die aan in onBeforeUnmount.
 */
export function volgSecties(
    sleutels: string[],
    opActief: (sleutel: string | null) => void,
): () => void {
    if (typeof document === 'undefined' || sleutels.length === 0) {
        return () => {};
    }

    /*
     * Een verzameling en niet één waarde.
     *
     * Op het moment dat je van het ene onderdeel in het andere scrolt,
     * vuren twee triggers vlak na elkaar: de ene gaat uit, de andere
     * aan. In welke volgorde dat gebeurt ligt niet vast, en zou de
     * uit-melding als laatste komen, dan blijft het menu leeg staan
     * terwijl je midden in een onderdeel zit. Door bij te houden wát er
     * actief is en daarna pas te kiezen, kan die volgorde niets meer
     * kapotmaken.
     */
    const actief = new Set<string>();

    const meld = (): void => {
        // De eerste in de volgorde van de pagina, zodat er bij twee
        // overlappende onderdelen altijd hetzelfde uitkomt.
        opActief(sleutels.find((sleutel) => actief.has(sleutel)) ?? null);
    };

    const triggers = sleutels.flatMap((sleutel) => {
        const element = document.getElementById(sleutel);

        if (element === null) {
            return [];
        }

        return ScrollTrigger.create({
            trigger: element,
            start: 'top 33%',
            end: 'bottom 33%',
            onToggle: (self) => {
                if (self.isActive) {
                    actief.add(sleutel);
                } else {
                    actief.delete(sleutel);
                }

                meld();
            },
        });
    });

    return () => triggers.forEach((trigger) => trigger.kill());
}

export { gsap, ScrollTrigger };
