import gsap from 'gsap';
import { DrawSVGPlugin } from 'gsap/DrawSVGPlugin';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { SplitText } from 'gsap/SplitText';
import Lenis from 'lenis';
import { formatteerGetal } from '@/lib/getallen';

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

/**
 * De tabletgrens uit de huisstijl; alles eronder noemen we een telefoon.
 *
 * Hetzelfde getal als `@media (min-width: 48rem)` in app.css. Het staat
 * hier zodat de animaties en de opmaak niet uit elkaar kunnen lopen.
 */
const SMAL = '(max-width: 47.9375rem)';

/**
 * Staan we op een smal scherm?
 *
 * Gebruikt om animaties die op een groot scherm prettig zijn, op een
 * telefoon korter te maken. Daar telt elke tiende seconde zwaarder: het
 * scherm is kleiner, dus een blok dat nog moet opkomen is meteen de hele
 * pagina, en de verbinding is doorgaans trager -- de animatie begint dus
 * al later dan op een laptop.
 *
 * Bij twijfel (geen venster, zoals bij server-side rendering) nemen we
 * aan van niet: de lange variant is de bestaande.
 */
export function opEenTelefoon(): boolean {
    return typeof window !== 'undefined' && window.matchMedia(SMAL).matches;
}

export function prefersReducedMotion(): boolean {
    if (typeof window === 'undefined' || !window.matchMedia) {
        return false;
    }

    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Staat dit element al in beeld, of is het al voorbij?
 *
 * **Dit is het antwoord op de stilste fout van dit project.** Bijna elke
 * binnenkomst hieronder werkt zo: het element begint onzichtbaar, en een
 * scroll-trigger haalt het op zodra je erlangs komt. Dat klopt zolang
 * het element onder de vouw wordt aangemaakt.
 *
 * Wordt het aangemaakt terwijl je er al voorbij bent -- bij het wisselen
 * van taal wordt de hele pagina opnieuw opgebouwd, en bij het bladeren
 * een deel ervan -- dan komt die trigger nooit meer langs. Het element
 * staat er dan wel, maar op doorzichtigheid nul. Je ziet een lege plek
 * en pas na verversen de tekst.
 *
 * Vandaar dat elke functie hieronder deze vraag stelt in plaats van dat
 * de aanroeper het moet weten. Wie een nieuwe animatie toevoegt hoeft er
 * niets voor te doen; wie het vergeet, loopt niet in de val.
 *
 * `deel` is dezelfde grens als de bijbehorende scroll-trigger, zodat
 * "al in beeld" hier hetzelfde betekent als daar.
 */
export function alInBeeld(element: Element, deel = 0.85): boolean {
    if (typeof window === 'undefined') {
        return true;
    }

    return element.getBoundingClientRect().top < window.innerHeight * deel;
}

/**
 * `ScrollTrigger.batch`, maar met een opruimfunctie die écht alles opruimt.
 *
 * **`batch` lekte de hele vorige pagina.** Per callback (`onEnter` enz.)
 * maakt hij meteen een gepauzeerde `gsap.delayedCall`. Gaat de batch af,
 * dan loopt die af en verdwijnt hij; gaat hij nóóit af -- je klikt naar een
 * project voordat je bij de certificaten bent -- dan blijft hij voor altijd
 * in de tijdlijn staan. Hij houdt de callback vast, de callback houdt de
 * elementen vast, en via `__vueParentComponent` hield dat de hele oude
 * pagina in het geheugen: gemeten ±3100 knopen en 3 MB per bezoek aan de
 * voorpagina, en het werd met elke terugkeer meer. De triggers killen is
 * niet genoeg; de delayedCalls staan los van de triggers.
 *
 * Daarom: kijken welke delayedCalls `batch` net aanmaakte (de nieuwe,
 * gepauzeerde tweens met een functie als doel) en die bij het opruimen ook
 * killen. Zie docs/architecture/prestaties.md.
 */
export function bundel(
    doelen: Element[],
    vars: ScrollTrigger.BatchVars,
): () => void {
    const ervoor = new Set(gsap.globalTimeline.getChildren(false, true, false));
    const triggers = ScrollTrigger.batch(doelen, vars);
    const vertragingen = gsap.globalTimeline
        .getChildren(false, true, false)
        .filter(
            (tween) =>
                !ervoor.has(tween) &&
                tween.paused() &&
                typeof (tween as gsap.core.Tween).targets()[0] === 'function',
        );

    /*
     * Meteen leeg, en dat is geen netheid. De opruimfunctie hieronder deelt
     * zijn scope met `ervoor`, dus zolang die bestaat, bestaat deze set --
     * met daarin álle tweens van dat moment, ook die op elementen van een
     * pagina die allang weg is. Gemeten: zonder deze regel hield een
     * taalwissel alsnog de hele vorige pagina vast.
     */
    ervoor.clear();

    return () => {
        triggers.forEach((trigger) => trigger.kill());
        vertragingen.forEach((vertraging) => vertraging.kill());
    };
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

    /*
     * **Vooraan in de ticker** (het derde argument), vóór GSAP zijn tweens
     * tekent. Achteraan schreef GSAP eerst alle transforms van dit beeldje,
     * waarna Lenis met `scrollTo` scrolde -- en scrollen dwingt de browser
     * die verse stijlen meteen door te rekenen. Daarna las ScrollTrigger de
     * positie en schreef GSAP weer. Gemeten: zo'n twee extra doorrekeningen
     * per beeldje tijdens het scrollen. Nu scrolt Lenis eerst op een schone
     * pagina, rekent ScrollTrigger zijn voortgang uit, en tekent GSAP
     * daarna alles in één keer. Zie docs/architecture/prestaties.md.
     */
    gsap.ticker.add(ticker, false, true);
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
 * Wanneer een beweging begint en hoe lang hij duurt.
 *
 * **Op een telefoon eerder en korter, en dat is rekenwerk en geen smaak.**
 * Op een groot scherm scrol je met een muiswiel: een paar honderd pixels
 * per keer, met pauzes ertussen. Op een telefoon veeg je, en dan schuift
 * er al gauw tweeduizend pixels per seconde langs. In de acht tienden van
 * een seconde die de beweging duurde was het blok dus meer dan een
 * schermhoogte verder -- je las het al terwijl het nog aan het opkomen was.
 *
 * In de browser gemeten, tijdens een gewone veeg: van de elf blokken die
 * langskwamen stonden er acht nog op minder dan negentig procent
 * doorzichtigheid op het moment dat ze al halverwege het scherm stonden.
 * Twee ervan op nul. Op een grotere telefoon werd het erger, want daar is
 * de afstand tussen "komt net in beeld" en "staat er echt" groter.
 *
 * Daarom begint hij nu iets vóór de onderrand -- `105%` is net onder het
 * scherm -- en duurt hij ruim de helft korter. De bezoeker ziet de
 * beweging nog steeds; hij is alleen klaar tegen de tijd dat het blok op
 * zijn plek staat.
 *
 * Dezelfde grens als de opmaak gebruikt (48rem), zodat "telefoon" hier en
 * in de CSS hetzelfde betekent.
 */
function beweging(): { vanaf: number; duur: number } {
    const telefoon =
        typeof window !== 'undefined' &&
        window.matchMedia('(max-width: 48rem)').matches;

    return telefoon ? { vanaf: 1.15, duur: 0.4 } : { vanaf: 0.85, duur: 0.8 };
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
    /*
     * **Wat al eens is opgekomen, blijft staan.** Dit mag meer dan eens
     * over dezelfde pagina lopen: bij een navigatie die het component laat
     * staan wordt er opnieuw gescand, en zonder deze zeef zet die scan
     * alles wat je op dat moment ziet terug op nul om het weer op te laten
     * komen. Dan knippert de halve pagina omdat je een formulier hebt
     * verstuurd.
     *
     * Het merkje komt er pas als de beweging klaar is. Een blok dat nog op
     * zijn scroll-trigger wacht is dus niet gemerkt, en wordt bij een
     * herscan gewoon opnieuw opgepakt -- precies wat je wil, want dat is
     * nog onzichtbaar.
     */
    const targets = gsap.utils
        .toArray<HTMLElement>(selector, scope ?? undefined)
        .filter((target) => target.dataset.gezien === undefined);

    if (targets.length === 0) {
        return () => {};
    }

    if (prefersReducedMotion()) {
        gsap.set(targets, { opacity: 1, y: 0 });

        targets.forEach((target) => {
            target.dataset.gezien = '';
        });

        return () => {};
    }

    const { vanaf, duur } = beweging();

    const tweens = targets.map((target) =>
        gsap.fromTo(
            target,
            { opacity: 0, y: 24 },
            {
                opacity: 1,
                y: 0,
                duration: duur,
                ease: 'power2.out',

                // Klaar, dus bij een herscan overslaan. Zie de zeef boven.
                onComplete: () => {
                    target.dataset.gezien = '';
                },

                /*
                 * Staat het al in beeld, dan geen scroll-trigger maar
                 * meteen spelen: die trigger komt nooit meer langs en
                 * dan blijft de tekst onzichtbaar. Zie `alInBeeld`.
                 */
                ...(alInBeeld(target, vanaf)
                    ? {}
                    : {
                          scrollTrigger: {
                              trigger: target,
                              start: `top ${vanaf * 100}%`,
                              once: true,
                          },
                      }),
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

    /*
     * Wat al in beeld staat komt meteen op; `direct` mag dat ook
     * afdwingen maar hoeft niet meer. Zie `alInBeeld`.
     */
    const meteen = kaarten.filter((kaart) => direct || alInBeeld(kaart, 0.88));

    if (meteen.length > 0) {
        binnen(meteen);
    }

    const rest = kaarten.filter((kaart) => !meteen.includes(kaart));

    if (rest.length === 0) {
        return () => {};
    }

    return bundel(rest, {
        start: 'top 88%',
        once: true,
        onEnter: binnen,
    });
}

/**
 * Een getal dat oploopt in beeld zetten, zonder de hele pagina te laten
 * herberekenen.
 *
 * **`textContent =` was hier de duurste regel van de site.** Dat vervangt
 * bij elk beeldje de tekstknoop door een nieuwe -- voor de browser een
 * wijziging in de boom (`childList`), en die liet bij elke tik de stijl van
 * een groot deel van de pagina opnieuw berekenen. In de browser gemeten,
 * scrollend door de ervaring terwijl de cijfers optelden: 768 ms aan
 * stijlwerk en 43% beeldjes te laat. Met de waarde van de bestaande
 * tekstknoop aanpassen, en alleen als het getal echt verandert: 87 ms en
 * 17%. Hetzelfde gold voor de statistieken.
 */
function schrijfGetal(element: HTMLElement, tekst: string): void {
    const knoop = element.firstChild;

    if (
        knoop !== null &&
        knoop === element.lastChild &&
        knoop.nodeType === Node.TEXT_NODE
    ) {
        if (knoop.nodeValue !== tekst) {
            knoop.nodeValue = tekst;
        }

        return;
    }

    element.textContent = tekst;
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
        schrijfGetal(element, String(Math.round(waarde)));
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

            // Al in beeld? Dan meteen tellen. Zonder dit blijft het
            // getal op nul staan; zie `alInBeeld`.
            ...(alInBeeld(teller, 0.9)
                ? {}
                : {
                      scrollTrigger: {
                          trigger: teller,
                          start: 'top 90%',
                          once: true,
                      },
                  }),
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
    let positie = window.scrollY;

    const write = (): void => {
        frame = 0;

        const progress = scrollable > 0 ? positie / scrollable : 0;

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

    /*
     * **De positie lezen in het scroll-event, niet in het animation frame.**
     * Het scroll-event komt aan het begin van een beeldje, op een pagina
     * die nog schoon is. In het animation frame heeft GSAP al geschreven,
     * en dan dwingt het lezen van `scrollY` de browser alles eerst door te
     * rekenen: gemeten ruim duizend keer tijdens één keer doorscrollen.
     */
    const opScroll = (): void => {
        positie = window.scrollY;
        schedule();
    };

    const measure = (): void => {
        scrollable = document.documentElement.scrollHeight - window.innerHeight;
        positie = window.scrollY;
        schedule();
    };

    window.addEventListener('scroll', opScroll, { passive: true });
    window.addEventListener('resize', measure);

    const observer = new ResizeObserver(measure);
    observer.observe(document.body);

    measure();

    return () => {
        window.removeEventListener('scroll', opScroll);
        window.removeEventListener('resize', measure);
        observer.disconnect();

        if (frame !== 0) {
            window.cancelAnimationFrame(frame);
        }
    };
}

/* -------------------------------------------------------------------------
 * Regels splitsen zonder dat elk lettertype alle koppen opnieuw splitst.
 *
 * **Dit was de grootste hapering van de site**, gemeten en niet vermoed:
 * zie docs/architecture/prestaties.md. SplitText met `autoSplit` en
 * `type: 'lines'` hangt bij elke splitsing een luisteraar aan
 * `document.fonts` ('loadingdone'), en die splitst bij élk lettertype dat
 * klaar is opnieuw -- ook als dat lettertype in die kop niet voorkomt. Op de
 * voorpagina staan zo'n tien gesplitste koppen; elk binnenkomend lettertype
 * (Geist, Geist Mono, een extra gewicht) liet ze alle tien opnieuw opmeten,
 * opsplitsen en hun animatie opnieuw opbouwen. Gemeten: twee taken van
 * ±800 ms, ruim een seconde na het laden -- precies als een bezoeker begint
 * te scrollen -- en bij een viermaal tragere processor ruim drie seconden.
 *
 * Nu haalt `splitsRegels()` die luisteraar na elke splitsing weer weg, en
 * luistert één gedeelde luisteraar hier in de plaats. Die splitst alleen de
 * koppen opnieuw waarvan het **eigen** lettertype (familie en gewicht) net
 * binnen is: alleen dan verandert de regelval. De luisteraar voor een
 * andere breedte (ResizeObserver, met een drempel) blijft van SplitText.
 *
 * `_split` is intern in SplitText (3.13+). Bestaat hij niet meer na een
 * update, dan valt dit stil terug op het oude gedrag: dan splitst SplitText
 * weer bij elk lettertype, wat trager is maar niet fout.
 * ---------------------------------------------------------------------- */
type RegelSplitsing = {
    split: SplitText;
    vars: SplitText.Vars;
};

const regelSplitsingen = new Set<RegelSplitsing>();
let luistertNaarLettertypen = false;

const zonderAanhalingstekens = (familie: string): string =>
    familie.replace(/["']/g, '').trim().toLowerCase();

/** Valt `gewicht` binnen wat een FontFace dekt ("600" of "100 900")? */
const gewichtPast = (dekking: string, gewicht: number): boolean => {
    const [van, tot = van] = dekking
        .split(/\s+/)
        .map((deel) => Number.parseInt(deel, 10) || 400);

    return gewicht >= van && gewicht <= tot;
};

const haalLettertypeLuisteraarWeg = (split: SplitText): void => {
    const intern = (split as unknown as { _split?: EventListener })._split;

    if (typeof intern === 'function') {
        document.fonts?.removeEventListener('loadingdone', intern);
    }
};

const opLettertype = (gebeurtenis: Event): void => {
    const binnen = ((gebeurtenis as FontFaceSetLoadEvent).fontfaces ?? []).map(
        (face) => ({
            familie: zonderAanhalingstekens(face.family),
            gewicht: face.weight,
        }),
    );

    if (binnen.length === 0) {
        return;
    }

    /*
     * Eerst alles lezen, dan pas splitsen. Splitsen schrijft in de pagina;
     * door lezen en schrijven af te wisselen rekent de browser na elke kop
     * de hele opmaak opnieuw door.
     */
    const opnieuw: RegelSplitsing[] = [];

    regelSplitsingen.forEach((splitsing) => {
        const element = splitsing.split.elements[0] as HTMLElement | undefined;

        if (
            !splitsing.split.isSplit ||
            element === undefined ||
            !element.isConnected
        ) {
            return;
        }

        const stijl = getComputedStyle(element);
        const familie = zonderAanhalingstekens(
            stijl.fontFamily.split(',')[0] ?? '',
        );
        const gewicht = Number.parseInt(stijl.fontWeight, 10) || 400;

        if (
            binnen.some(
                (face) =>
                    face.familie === familie &&
                    gewichtPast(face.gewicht, gewicht),
            )
        ) {
            opnieuw.push(splitsing);
        }
    });

    opnieuw.forEach(({ split, vars }) => split.split(vars));
};

/**
 * `SplitText.create` voor regels, met `autoSplit`, maar zonder de dure
 * hersplitsing bij elk lettertype. Zie het blok hierboven.
 *
 * Geeft de splitsing terug; ruim op met `stopRegels()`, dat ook `revert()`
 * doet.
 */
export function splitsRegels(
    target: HTMLElement,
    vars: SplitText.Vars,
): SplitText {
    const eigen = vars.onSplit;

    const volledig: SplitText.Vars = {
        ...vars,
        autoSplit: true,
        /*
         * In `onSplit`, want SplitText hangt zijn luisteraar bij élke
         * splitsing opnieuw op -- ook bij een hersplitsing door een andere
         * breedte -- en roept `onSplit` pas daarna aan.
         */
        onSplit: (self: SplitText) => {
            haalLettertypeLuisteraarWeg(self);

            return eigen?.(self);
        },
    };

    const split = SplitText.create(target, volledig);

    regelSplitsingen.add({ split, vars: volledig });

    if (!luistertNaarLettertypen && document.fonts !== undefined) {
        document.fonts.addEventListener('loadingdone', opLettertype);
        luistertNaarLettertypen = true;
    }

    return split;
}

/** Draait een splitsing van `splitsRegels()` terug en vergeet hem. */
export function stopRegels(split: SplitText): void {
    regelSplitsingen.forEach((splitsing) => {
        if (splitsing.split === split) {
            regelSplitsingen.delete(splitsing);
        }
    });

    split.revert();
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
    /* Dezelfde zeef als bij `revealOnScroll`, en om dezelfde reden. */
    const targets = gsap.utils
        .toArray<HTMLElement>(selector, scope ?? undefined)
        .filter((target) => target.dataset.gezien === undefined);

    if (targets.length === 0) {
        return () => {};
    }

    if (prefersReducedMotion()) {
        gsap.set(targets, { opacity: 1, y: 0 });

        targets.forEach((target) => {
            target.dataset.gezien = '';
        });

        return () => {};
    }

    const splits: SplitText[] = [];

    const splitsKop = (target: HTMLElement): void => {
        gsap.set(target, { opacity: 1 });

        /*
         * Hier meteen en niet na de beweging. Vanaf deze regel staat de kop
         * op doorzichtigheid 1 en is hij dus zichtbaar; de regels schuiven
         * alleen nog achter hun masker omhoog. Zou het merkje pas aan het
         * eind komen, dan hersplitst een herscan een kop die je al leest.
         */
        target.dataset.gezien = '';

        /*
         * Dezelfde afspraak als bij `revealOnScroll`, en om dezelfde reden:
         * op een telefoon eerder beginnen en korter doen. Een kop die regel
         * voor regel opkomt is hier zelfs gevoeliger voor, want de laatste
         * regel wacht ook nog op zijn spreiding -- die gaat dus mee omlaag.
         */
        const { vanaf, duur } = beweging();
        const koprand = vanaf === 0.85 ? 0.88 : vanaf;
        const scherpstellen = opEenBreedScherm();

        splits.push(
            splitsRegels(target, {
                type: 'lines',
                mask: 'lines',
                onSplit: (self) =>
                    gsap.from(self.lines, {
                        yPercent: 115,
                        /*
                         * Scherpstellen: de regel komt op van een lichte waas
                         * naar scherp, als een lens die zijn focus vindt.
                         * Alleen op een breed scherm, en alleen op koppen --
                         * `filter` is duurder dan een transform, en op een
                         * paar regels tekst valt dat weg; op een telefoon
                         * niet. `clearProps` haalt hem er na afloop af, zodat
                         * de tekst daarna niet in een eigen laag blijft staan.
                         */
                        ...(scherpstellen
                            ? { filter: 'blur(10px)', clearProps: 'filter' }
                            : {}),
                        duration: duur * 1.1,
                        stagger: duur * 0.11,
                        ease: 'power3.out',

                        // Al in beeld? Dan meteen, zonder trigger. Zie
                        // `alInBeeld`.
                        ...(alInBeeld(target, koprand)
                            ? {}
                            : {
                                  scrollTrigger: {
                                      trigger: target,
                                      start: `top ${koprand * 100}%`,
                                      once: true,
                                  },
                              }),
                    }),
            }),
        );
    };

    /*
     * **Pas splitsen vlak voordat de kop in beeld komt.** Splitsen meet de
     * regels op, en dat dwingt de browser de opmaak door te rekenen -- per
     * kop, terwijl de pagina nog wordt opgebouwd. Tien koppen tegelijk bij
     * het laden was gemeten de duurste stap van de eerste seconde
     * (docs/architecture/prestaties.md). Wat binnen twee schermen staat
     * splitst meteen; de rest pas als hij één scherm onder de onderrand is,
     * ruim voordat zijn eigen trigger (`koprand`) afgaat.
     *
     * Het merkje `gezien` komt er wél meteen op, anders pakt een herscan
     * dezelfde kop nog een keer. Tot hij gesplitst is blijft hij op
     * doorzichtigheid 0 -- maar hij staat dan nog een scherm onder je.
     */
    const later: HTMLElement[] = [];

    targets.forEach((target) => {
        target.dataset.gezien = '';

        if (
            alInBeeld(target, 2) ||
            typeof IntersectionObserver === 'undefined'
        ) {
            splitsKop(target);
        } else {
            later.push(target);
        }
    });

    const kijker =
        later.length > 0
            ? new IntersectionObserver(
                  (items) =>
                      items.forEach((item) => {
                          if (!item.isIntersecting) {
                              return;
                          }

                          kijker?.unobserve(item.target);
                          splitsKop(item.target as HTMLElement);
                      }),
                  { rootMargin: '0px 0px 100% 0px' },
              )
            : undefined;

    later.forEach((target) => kijker?.observe(target));

    return () => {
        kijker?.disconnect();
        splits.forEach((split) => stopRegels(split));
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
        /*
         * **`rotationX` en niet `rotateX`.** Die tweede is in GSAP een
         * alias; intern wordt de waarde onder de naam `rotationX`
         * bewaard. `quickTo` zoekt bij elke aanroep de bewaarde waarde
         * op onder precies de naam die je hier opgeeft, vindt hem niet,
         * en waarschuwt dan in de console: "rotateX not eligible for
         * reset". Hij herstelt zichzelf, maar de melding blijft komen.
         */
        const naarX = gsap.quickTo(kaart, 'rotationX', {
            duration: 0.5,
            ease: 'power3.out',
        });
        const naarY = gsap.quickTo(kaart, 'rotationY', {
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
 * Een vloeiende lijn door een rij punten, met een lichtpuntje dat erlangs
 * reist terwijl je scrolt.
 *
 * Gebruikt door de werkwijze: de lijn loopt langs de nummers van de
 * stappen en elke stap licht op zodra het punt hem passeert. Dat maakt van
 * losse blokjes één doorlopende beweging -- en dat is precies wat
 * "werkwijze" hoort over te brengen: het is een volgorde, geen opsomming.
 *
 * **Het pad wordt getekend, niet meegegeven.** Dat was eerst andersom: een
 * vaste `d` met een handgetekende golf, in een viewBox van 1000 bij 60. Dat
 * werkt zolang er precies vier stappen zijn die op één rij staan. Bij drie
 * loopt de lijn langs een leeg vak, en op een telefoon -- waar ze onder
 * elkaar staan -- kon hij helemaal niet bestaan.
 *
 * Nu volgt de lijn de punten waar ze ook staan: één rij naast elkaar, of
 * één kolom onder elkaar. Dat laatste is nieuw; daar was voorheen geen
 * lijn.
 *
 * **Staan de punten in een raster van meerdere rijen, dan is er geen
 * lijn.** Dat is een grens en geen tekortkoming: een lijn die van het einde
 * van de ene rij naar het begin van de volgende moet, snijdt onderweg dwars
 * door de tekst van de kaarten ertussen. Er is geen route die dat niet doet.
 * Dan is geen lijn eerlijker dan een lijn die door een alinea loopt.
 *
 * Vier dingen die bewust zo zijn:
 *
 * - **Gemeten met `offsetLeft` en niet met `getBoundingClientRect`.** De
 *   stappen komen op met een verschuiving, en een rect geeft de verschóven
 *   plek terug. Dan tekent de lijn langs waar de kaarten even stonden.
 *   `offsetLeft` is layout en kent die verschuiving niet.
 * - **De lijn hangt aan de nummers, maar loopt er niet doorheen.** Bij een
 *   rij naast elkaar gaat hij een stukje omhoog, zodat hij boven de
 *   nummers langs loopt in plaats van er dwars doorheen -- met het cijfer
 *   en de duur doorgestreept als gevolg. Bij een kolom onder elkaar blijft
 *   hij op de nummers staan: die liggen dan in een eigen kantlijn met een
 *   rondje eromheen, en dan is de lijn er juist de draad doorheen.
 * - **De tussenpunten worden afwisselend opzij geduwd.** Een lijn langs
 *   punten die op één hoogte staan is kaarsrecht, en dat was juist de
 *   charme niet. Deze golf ontstaat vanzelf in de goede richting: op een
 *   rij golft hij op en neer, in een kolom naar links en rechts.
 * - **JavaScript zet alleen een attribuut op de stap.** De kleur en de
 *   gloed staan in CSS, zodat de huisstijl op één plek blijft.
 *
 * Bij reduced motion staat het pad meteen vol getekend, is het lichtpuntje
 * weg en zijn alle stappen gemarkeerd.
 *
 * Geeft een opruimfunctie terug; roep die aan in onBeforeUnmount.
 */
export function tekenPad(
    svg: SVGSVGElement,
    pad: SVGPathElement,
    punt: SVGElement | null,
    stappen: HTMLElement[],
): () => void {
    if (stappen.length < 2) {
        // Eén punt is geen lijn. De SVG blijft leeg in plaats van dat er
        // een streepje van nul lengte staat te wachten op een animatie.
        svg.setAttribute('data-leeg', '');

        return () => {};
    }

    svg.removeAttribute('data-leeg');

    /**
     * Het vak waar de SVG en de stappen samen in liggen.
     *
     * **Niet `svg.offsetParent`**, en dat is geen smaak: een SVG-element
     * heeft die eigenschap helemaal niet -- `offsetParent` zit op
     * `HTMLElement`. De ouder is hier het vak met `position: relative`,
     * en dat is precies het punt waar alle metingen vanaf tellen.
     */
    const omlijsting = svg.parentElement;

    if (omlijsting === null) {
        return () => {};
    }

    /** Hoe ver de tussenpunten opzij gaan, in beeldpunten. */
    const GOLF = 10;

    /**
     * Hoe ver de hele lijn boven een rij nummers blijft.
     *
     * Alleen bij een rij naast elkaar; zie het blok bovenaan. Ruim genoeg
     * om het cijfer én de duur ernaast vrij te houden, en met lucht
     * eromheen -- strak boven de tekst leest hij als een doorhaling die
     * net mist. Het raster houdt er bovenaan ruimte voor vrij; zie
     * `.brand-werkwijze-raster` in app.css.
     */
    const BOVEN = 42;

    /** Een uitloop korter dan dit is geen uitloop maar een hobbel. */
    const MINIMALE_UITLOOP = 12;

    /** Waar elke stap op het pad zit, als deel van de lengte. */
    let posities: number[] = [];

    /**
     * Het aanhechtpunt van een stap: het nummer, of anders de stap zelf.
     *
     * Beide gemeten ten opzichte van de omlijsting waar ook de SVG in
     * ligt. Dat werkt omdat die omlijsting `position: relative` heeft en
     * er niets tussen zit dat zelf gepositioneerd is.
     */
    const puntVan = (stap: HTMLElement): { x: number; y: number } => {
        const doel =
            stap.querySelector<HTMLElement>('[data-stap-punt]') ?? stap;

        let x = doel.offsetWidth / 2;
        let y = doel.offsetHeight / 2;

        let loper: HTMLElement | null = doel;

        while (loper !== null && loper !== omlijsting) {
            x += loper.offsetLeft;
            y += loper.offsetTop;
            loper = loper.offsetParent as HTMLElement | null;
        }

        return { x, y };
    };

    /**
     * Een vloeiend pad door de punten heen.
     *
     * Catmull-Rom omgezet naar kubieke bézierbochten: dat loopt gegarandeerd
     * dóór elk punt in plaats van er met een boog omheen, en dat is precies
     * wat je hier wil -- de lijn hoort de nummers te raken.
     */
    const padUit = (punten: Array<{ x: number; y: number }>): string => {
        const eerste = punten[0];

        if (eerste === undefined) {
            return '';
        }

        let d = `M ${eerste.x.toFixed(1)} ${eerste.y.toFixed(1)}`;

        for (let i = 0; i < punten.length - 1; i++) {
            const p0 = punten[i - 1] ?? punten[i];
            const p1 = punten[i];
            const p2 = punten[i + 1];
            const p3 = punten[i + 2] ?? punten[i + 1];

            if (
                p0 === undefined ||
                p1 === undefined ||
                p2 === undefined ||
                p3 === undefined
            ) {
                continue;
            }

            // Een zesde van de afstand tot de buren: ruim genoeg voor een
            // zachte bocht, krap genoeg om niet door te schieten.
            const c1x = p1.x + (p2.x - p0.x) / 6;
            const c1y = p1.y + (p2.y - p0.y) / 6;
            const c2x = p2.x - (p3.x - p1.x) / 6;
            const c2y = p2.y - (p3.y - p1.y) / 6;

            d +=
                ` C ${c1x.toFixed(1)} ${c1y.toFixed(1)},` +
                ` ${c2x.toFixed(1)} ${c2y.toFixed(1)},` +
                ` ${p2.x.toFixed(1)} ${p2.y.toFixed(1)}`;
        }

        return d;
    };

    const meet = (): void => {
        const breedte = omlijsting.clientWidth;
        const hoogte = omlijsting.clientHeight;

        if (breedte === 0 || hoogte === 0) {
            return;
        }

        svg.setAttribute('viewBox', `0 0 ${breedte} ${hoogte}`);

        const rauw = stappen.map(puntVan);

        /*
         * De tussenpunten afwisselend opzij, loodrecht op de richting van
         * de hele lijn. Het eerste en het laatste punt blijven staan: de
         * lijn hoort bij het eerste nummer te beginnen en bij het laatste
         * te eindigen, niet er een stukje naast.
         */
        const start = rauw[0];
        const slot = rauw[rauw.length - 1];

        const dx = (slot?.x ?? 0) - (start?.x ?? 0);
        const dy = (slot?.y ?? 0) - (start?.y ?? 0);
        const lengte = Math.hypot(dx, dy) || 1;

        /*
         * Staan de punten op één rij, in één kolom, of geen van beide?
         *
         * Gemeten met een marge, want een kaart met een langere titel
         * duwt zijn nummer niet op de pixel gelijk met de buren.
         */
        const xen = rauw.map((p) => p.x);
        const yen = rauw.map((p) => p.y);
        const MARGE = 8;

        const eenRij = Math.max(...yen) - Math.min(...yen) <= MARGE;
        const eenKolom = Math.max(...xen) - Math.min(...xen) <= MARGE;

        /*
         * Geen van beide: een raster van meerdere rijen. Dan is er geen
         * route die niet dwars door de tekst van een kaart loopt, dus
         * tekenen we niets -- en zetten we de stappen op "bereikt", want
         * anders blijven hun nummers gedempt wachten op een lijn die
         * nooit komt.
         */
        if (!eenRij && !eenKolom) {
            svg.setAttribute('data-leeg', '');
            pad.setAttribute('d', '');
            stappen.forEach((stap) => stap.setAttribute('data-bereikt', ''));
            posities = [];

            return;
        }

        svg.removeAttribute('data-leeg');

        /*
         * Op een rij gaat de hele lijn een stukje omhoog, zodat hij niet
         * door de cijfers heen loopt. In een kolom blijft hij er precies
         * op staan: daar liggen de nummers in een eigen kantlijn met een
         * rondje eromheen, en dan is de lijn de draad erdoorheen.
         */
        const omhoog = eenKolom ? 0 : BOVEN;

        const punten = rauw.map((p, index) => {
            const basis = { x: p.x, y: p.y - omhoog };

            if (index === 0 || index === rauw.length - 1) {
                return basis;
            }

            // De loodrechte van de richting, op lengte 1 gebracht.
            const nx = -dy / lengte;
            const ny = dx / lengte;
            const kant = index % 2 === 0 ? 1 : -1;

            return {
                x: basis.x + nx * GOLF * kant,
                y: basis.y + ny * GOLF * kant,
            };
        });

        /*
         * De punten waar het pad langs loopt: de stappen, plus bij een rij
         * een aanloop links en een uitloop rechts tot aan de rand.
         *
         * **Dat is geen opsmuk.** De nummers staan links in hun kolom, dus
         * het laatste nummer zit op driekwart van de breedte. Een lijn die
         * daar ophoudt leest als een streepje dat toevallig tussen vier
         * punten past, met een kwart leegte ernaast. Van rand tot rand
         * leest hij als een verloop dat doorgaat -- precies wat het oude,
         * vaste pad deed.
         *
         * De uitlopers staan bewust níet in `punten`: daar wordt opgezocht
         * waar elke stap op het pad zit, en een punt dat geen stap is hoort
         * daar niet bij. Ze liggen op dezelfde hoogte als hun buur, zodat
         * de lijn vlak het beeld in en uit gaat; zou de uitloop meekantelen
         * met de laatste bocht, dan schiet hij omhoog het beeld uit.
         */
        const eerste = punten[0];
        const laatste = punten[punten.length - 1];

        const padPunten = [...punten];

        if (!eenKolom && eerste !== undefined && laatste !== undefined) {
            if (eerste.x > MINIMALE_UITLOOP) {
                padPunten.unshift({ x: 0, y: eerste.y });
            }

            if (breedte - laatste.x > MINIMALE_UITLOOP) {
                padPunten.push({ x: breedte, y: laatste.y });
            }
        }

        pad.setAttribute('d', padUit(padPunten));

        /*
         * Waar elke stap op het pad zit. Niet uitgerekend maar opgezocht:
         * het pad golft, dus de helft van de lengte ligt niet bij de
         * helft van de stappen. Tweehonderd monsters is ruim genoeg voor
         * een lijn van een paar honderd beeldpunten.
         */
        const padLengte = pad.getTotalLength();

        if (padLengte === 0) {
            return;
        }

        const MONSTERS = 200;
        const monsters: Array<{ x: number; y: number; deel: number }> = [];

        for (let i = 0; i <= MONSTERS; i++) {
            const deel = i / MONSTERS;
            const plek = pad.getPointAtLength(padLengte * deel);

            monsters.push({ x: plek.x, y: plek.y, deel });
        }

        /*
         * Zoeken op de verschóven punten en niet op de rauwe.
         *
         * Het pad loopt langs de verschoven punten; het rauwe punt ligt er
         * een stukje onder. Zou je dáárop zoeken, dan is het dichtstbije
         * monster bij elke stap ongeveer even ver weg en klopt de volgorde
         * niet meer -- en dan licht stap drie op terwijl het puntje nog bij
         * stap twee is.
         */
        posities = punten.map((doel) => {
            let beste = 0;
            let kortste = Infinity;

            monsters.forEach((monster) => {
                const afstand = Math.hypot(
                    monster.x - doel.x,
                    monster.y - doel.y,
                );

                if (afstand < kortste) {
                    kortste = afstand;
                    beste = monster.deel;
                }
            });

            return beste;
        });
    };

    if (prefersReducedMotion()) {
        meet();
        gsap.set(pad, { drawSVG: '100%' });
        gsap.set(punt, { opacity: 0 });
        stappen.forEach((stap) => stap.setAttribute('data-bereikt', ''));

        return () => {};
    }

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
     * Het lichtpuntje wordt met `getPointAtLength` neergezet en niet met
     * MotionPath. Die plugin meet in schermpixels en zet daarna een
     * verschuiving in de coördinaten van de SVG; `getPointAtLength` geeft
     * meteen een punt in de coördinaten van het pad zelf, en die zijn hier
     * gelijk aan beeldpunten.
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
            trigger: svg,
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

    /*
     * Opnieuw meten zodra het blok van maat verandert. Een luisteraar op
     * het venster is niet genoeg: de stappen verspringen ook als het
     * lettertype binnenkomt of als een samenvatting over een regel meer
     * gaat lopen.
     */
    let kijker: ResizeObserver | undefined;

    if (typeof ResizeObserver !== 'undefined') {
        kijker = new ResizeObserver(() => {
            meet();
            zetPunt(tijdlijn.scrollTrigger?.progress ?? 0);
        });

        kijker.observe(omlijsting);
    }

    return () => {
        kijker?.disconnect();
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
export function kaartenBinnen(
    kaarten: HTMLElement[],
    { direct = false }: { direct?: boolean } = {},
): () => void {
    if (kaarten.length === 0) {
        return () => {};
    }

    if (prefersReducedMotion()) {
        gsap.set(kaarten, { opacity: 1, y: 0, rotateX: 0, scale: 1 });

        return () => {};
    }

    gsap.set(kaarten, { transformPerspective: 900 });

    const van = { opacity: 0, y: 40, rotateX: -12, scale: 0.96 };

    const naar = {
        opacity: 1,
        y: 0,
        rotateX: 0,
        scale: 1,
        duration: 0.85,
        stagger: 0.12,
        ease: 'power3.out',
    };

    /*
     * Meteen spelen als de kaarten al in beeld staan -- de volgende
     * pagina van een lijst bijvoorbeeld. Een scroll-trigger vuurt daar
     * nooit af, en dan blijft alles op doorzichtigheid nul hangen.
     *
     * `direct` mag dat ook afdwingen, maar het hoeft niet meer: de
     * eerste kaart wordt gewoon gevraagd of hij al in beeld staat. Zo
     * kan een aanroeper het niet meer vergeten. Zie `alInBeeld`.
     *
     * Wel iets korter dan bij binnenkomst: je hebt zelf op een knopje
     * gedrukt en wacht op het antwoord.
     */
    if (direct || alInBeeld(kaarten[0], 0.85)) {
        const tween = gsap.fromTo(kaarten, van, {
            ...naar,
            duration: 0.55,
            stagger: 0.07,
        });

        return () => tween.kill();
    }

    const tween = gsap.fromTo(kaarten, van, {
        ...naar,
        scrollTrigger: {
            trigger: kaarten[0],
            start: 'top 85%',
            once: true,
        },
    });

    return () => {
        tween.scrollTrigger?.kill();
        tween.kill();
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
 * **De rotatie zit op een eigen `<svg>` om de boog heen**, en niet op een
 * element binnen de tekening: zie de uitleg bij `draaien` hieronder. Het
 * midden van dat vierkante element is het midden van de ring, dus
 * `transformOrigin: 50% 50%` staat vast, hoe groot het medaillon ook is.
 *
 * Bij reduced motion staat de ring meteen vol en draait er niets.
 *
 * Geeft een opruimfunctie terug; roep die aan in onBeforeUnmount.
 */
export function tekenRing(
    ring: SVGCircleElement,
    baan: Element | null,
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
        /*
         * **De boog staat in een eigen `<svg>`, en die draait als geheel.**
         * Draaide GSAP een `<g>` binnen de tekening, dan schreef hij elk
         * beeldje het `transform`-attribuut van dat element, en dan
         * schildert de browser de hele tekening opnieuw: bovenaan de pagina,
         * ook stilstaand, ruim drie seconden schilderwerk per drie seconden
         * (gemeten in een profiel). Een los `<svg>`-element draait met een
         * gewone CSS-transform, en die schuift de compositor zonder iets
         * opnieuw te tekenen.
         */
        const draaien = gsap.to(baan, {
            rotation: 360,
            transformOrigin: '50% 50%',
            duration: 14,
            repeat: -1,
            ease: 'none',
        });

        /*
         * Stil zolang de ring niet in beeld is. Een eindeloze tijdlijn op
         * iets dat drie schermen hoger staat kost accu en levert niets op;
         * dezelfde afspraak als de rustanimatie van de statistieken.
         */
        const zicht = ScrollTrigger.create({
            trigger: ring,
            start: 'top bottom',
            end: 'bottom top',
            onToggle: (zelf) =>
                zelf.isActive ? draaien.resume() : draaien.pause(),
        });

        return () => {
            zicht.kill();
            draaien.kill();
        };
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
 * **`direct` springt er zonder glijbeweging heen.** Dat is voor het moment
 * waarop de pagina nog onzichtbaar is, tijdens de overgang tussen twee
 * pagina's: dan hoort de bezoeker niet te zien dát er gescrold wordt, hij
 * hoort er gewoon te staan zodra het beeld komt. Een glijbeweging achter
 * een doorzichtige laag is tijd die je kwijt bent zonder dat iemand het
 * ziet. Zie PublicLayout.
 *
 * Geeft terug of het gelukt is. Bestaat het doel niet -- de klant heeft
 * dat onderdeel net uitgezet -- dan `false`, en dan hoort de aanroeper de
 * link gewoon zijn werk te laten doen.
 *
 * **Een getal is een plek in pixels en geen onderdeel.** Dat is voor het
 * ene geval waarin we de bezoeker precies terug moeten zetten waar hij
 * stond in plaats van bij iets: een taalwissel. Zie PublicLayout.
 */
export function scrollNaar(
    doel: string | number,
    { verschuiving = -72, direct = false } = {},
): boolean {
    if (typeof document === 'undefined') {
        return false;
    }

    /*
     * Een plek in pixels. Hermeten moet ook hier eerst -- om dezelfde
     * reden als hieronder -- en daarna knipt Lenis zelf af op de hoogte
     * van de pagina. Staat de bezoeker onderaan en is de nieuwe pagina
     * korter, dan landt hij dus onderaan en niet buiten de pagina.
     */
    if (typeof doel === 'number') {
        if (lenis === undefined) {
            window.scrollTo(0, doel);

            return true;
        }

        lenis.resize();
        lenis.scrollTo(doel, { immediate: true });

        return true;
    }

    const element =
        doel === 'top' ? document.body : document.getElementById(doel);

    if (element === null) {
        return false;
    }

    const naarBoven = doel === 'top';
    const zonderBeweging = direct || prefersReducedMotion();

    /*
     * **Eerst hermeten, dan springen.** Lenis houdt de hoogte van de pagina
     * in een cache en knipt elk doel af op die hoogte:
     * `clamp(0, target, this.limit)`. Komt de bezoeker net van een korte
     * subpagina naar de lange voorpagina, dan staat die limiet nog op de
     * oude hoogte -- en dan land je ergens halverwege in plaats van bij het
     * onderdeel waar je op klikte. Pas bij een tweede klik, als Lenis
     * zichzelf via zijn eigen waarnemer heeft bijgewerkt, klopt het wel.
     *
     * Dat was precies de klacht: "je moet nog een keer drukken".
     *
     * Alleen bij een directe sprong, want dat is het geval waarin de pagina
     * net gewisseld kan zijn. Een gewone klik op een anker binnen dezelfde
     * pagina hoeft dit niet, en een hermeting kost een herberekening van de
     * opmaak.
     */
    if (lenis !== undefined && zonderBeweging) {
        lenis.resize();
    }

    if (lenis === undefined) {
        element.scrollIntoView({ block: 'start' });

        if (!naarBoven) {
            window.scrollBy(0, verschuiving);
        }

        return true;
    }

    /*
     * Ook een directe sprong gaat via Lenis. `window.scrollTo` zou de
     * pagina ergens neerzetten waar Lenis niets van weet, en die duwt zijn
     * eigen positie het beeldje daarna gewoon terug.
     */
    lenis.scrollTo(naarBoven ? 0 : element, {
        offset: naarBoven ? 0 : verschuiving,
        ...(zonderBeweging
            ? { immediate: true }
            : {
                  duration: 0.7,
                  easing: (t: number) => 1 - Math.pow(1 - t, 3),
              }),
    });

    return true;
}

/**
 * Laat Lenis en ScrollTrigger de pagina opnieuw opmeten.
 *
 * **Nodig na een paginawissel, en om twee redenen tegelijk.** Allebei
 * houden ze de hoogte van het document in een cache en werken die bij op
 * een `resize` van het venster. Een Inertia-navigatie is geen resize: de
 * inhoud wordt vervangen en de pagina wordt een paar schermen langer of
 * korter, terwijl zij met het oude getal blijven rekenen.
 *
 * Voor Lenis betekent dat een doel dat wordt afgeknipt -- zie `scrollNaar`.
 * Voor ScrollTrigger betekent het dat een nieuw aangemaakte trigger zijn
 * begin- en eindpunt berekent met een verkeerde maximale scrollpositie, en
 * dus op de verkeerde hoogte afgaat of helemaal niet.
 *
 * Roep dit aan nadat de nieuwe animaties zijn aangemaakt; dan rekent de
 * verversing met de triggers die er echt zijn en niet met die van de
 * pagina die net is vertrokken.
 */
export function hermeetScroll(): void {
    lenis?.resize();
    ScrollTrigger.refresh();
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

/**
 * Eén statistiek zoals `vulStatistieken` hem nodig heeft.
 *
 * Alleen wat er moet bewegen, en dat is verrassend weinig: **deze helper
 * zet één variabele en schrijft één getal.** De balk die volloopt en de
 * ring die rond gaat zijn allebei CSS die op `--vulling` rekent.
 *
 * Dat is met opzet zo. De eerste opzet tekende de ring met DrawSVG, en
 * dat werkt -- maar dan hangt de ring aan JavaScript: in het
 * voorbeeldvenster van het beheerscherm, waar niets scrollt, stond hij
 * altijd helemaal vol. Met `pathLength="100"` op de cirkel is de boog
 * gewoon een `stroke-dashoffset` die uit een variabele komt, en klopt
 * hij overal -- ook als er helemaal geen JavaScript draait.
 */
export type TeVullenStatistiek = {
    /**
     * Het element dat de CSS-variabelen krijgt.
     *
     * `--vulling` tijdens het vullen, en daarna `--glans` en `--kracht`
     * voor het lichtpunt dat blijft rondgaan.
     */
    vak: HTMLElement;
    /** Het element waar het getal in komt te staan. */
    getal: HTMLElement | null;
    /** Waar hij naartoe telt. */
    waarde: number;
};

/**
 * De statistieken vullen zich als een golf, één keer.
 *
 * **Dit liep eerst mee met de scrollpositie, en dat moest eruit.** Het
 * zag er goed uit bij naar beneden scrollen, maar het leverde een
 * misverstand op dat erger was dan het effect goed was: scroll je weer
 * naar boven, dan lopen alle percentages terug. Je ziet dan getallen
 * veranderen die niets met je bezoek te maken hebben, en het blok voelt
 * langer dan het is -- je bent er al voorbij en het beweegt nog.
 *
 * Nu speelt het **één keer af** zodra het in beeld komt, en daarna staat
 * het stil. Dat is rustiger, het is wat de tijdlijn en de dienstkaarten
 * ook doen, en het laat geen twijfel over wat een getal betekent.
 *
 * Er zit ook geen `pin` meer op. Dat was de helft van het probleem: een
 * blok dat zich vastzet en daarna nog een schermhoogte scroll opeet is
 * precies waarom de pagina langer leek.
 *
 * **Drie dingen maken er nog steeds een golf van en geen schakelaar.**
 *
 * 1. **Elk item begint iets later dan het vorige.** Daardoor rolt het
 *    van boven naar beneden door het blok heen. Hoe meer items, hoe
 *    korter de tussenpoos -- anders duurt het bij twintig stuks een
 *    halve minuut.
 * 2. **De vulling loopt een fractie door en komt terug.** Een balk die
 *    op 85 procent hard stopt oogt als een laadbalk; eentje die net
 *    voorbij zijn doel schiet en terugveert oogt als iets dat op zijn
 *    plek valt. Vandaar `back.out` met een kleine uitslag.
 * 3. **Het getal telt mee en blijft achter.** Het loopt op met de
 *    vulling, dus je leest het terwijl het vol wordt.
 *
 * **En daarna gaat het lichtpunt rondjes lopen.** Een blok dat na zijn
 * golf helemaal stilstaat voelt dood; daar vroeg de eigenaar een kleine,
 * subtiele animatie voor. Die zit in `rustOp()` hieronder en begint
 * precies wanneer het vullen klaar is.
 *
 * **De getallen worden hier opgemaakt en niet in CSS**, want er hoort een
 * duizendtalscheiding in en die verschilt per taal. Vandaar `scheiding`:
 * de aanroeper weet welke taal het portaal heeft, deze helper niet.
 *
 * Bij `prefers-reduced-motion` staat alles meteen op zijn eindwaarde:
 * de componenten hebben die al, dus er valt niets te doen.
 *
 * Geeft een opruimfunctie terug; roep die aan in onBeforeUnmount.
 */
export function vulStatistieken(
    blok: HTMLElement,
    onderdelen: TeVullenStatistiek[],
    { scheiding = '.' }: { scheiding?: string } = {},
): () => void {
    if (onderdelen.length === 0) {
        return () => {};
    }

    const schrijf = (element: HTMLElement | null, waarde: number): void => {
        if (element !== null) {
            schrijfGetal(element, formatteerGetal(waarde, scheiding));
        }
    };

    if (prefersReducedMotion()) {
        // Alles meteen af. De componenten staan al op hun eindwaarde --
        // `--vulling` valt terug op 1 en het getal staat er als tekst --
        // dus er valt hier niets te doen.
        return () => {};
    }

    /*
     * Op nul beginnen.
     *
     * Dit is het enige moment waarop JavaScript iets wégneemt: zonder
     * deze regel staat alles al goed (zie de terugval in app.css), en
     * dat is precies de bedoeling voor wie geen JavaScript heeft. Wie
     * het wél heeft, ziet het opbouwen.
     */
    onderdelen.forEach((deel) => {
        deel.vak.style.setProperty('--vulling', '0');
        schrijf(deel.getal, 0);
    });

    /*
     * De tussenpoos tussen twee items, in seconden.
     *
     * Bij weinig items mag het rustig golven; bij veel items moet het
     * korter, anders duurt de hele beweging te lang en kijkt niemand
     * meer. Hoogstens anderhalve seconde voor het hele blok, hoe veel
     * items er ook staan.
     */
    const tussen = Math.min(0.09, 1.5 / Math.max(1, onderdelen.length));

    /*
     * Wat er gebeurt als het vullen klaar is.
     *
     * **Dit is de rustanimatie, en die was een wens van de eigenaar.**
     * Nadat de golf één keer door het blok is gerold staat er een
     * stilstaand plaatje, en dat miste hij: hij vroeg om "een kleine
     * subtiele animatie voor als alles al gebeurd is", voor elk van de
     * drie vormen.
     *
     * Het is één idee in drie vormen: **het lichtpunt dat met het vullen
     * meeliep, blijft daarna rondgaan.** Bij een balk schuift het als
     * glans over het gevulde stuk, bij een ring loopt het een rondje
     * over de boog, en bij een teller zakt het langs zijn randlijn naar
     * beneden. Allemaal met dezelfde twee variabelen, zodat de CSS per
     * vorm alleen hoeft te bepalen wát er beweegt.
     *
     * | Variabele  | Wat deze functie erin zet            |
     * | ---------- | ------------------------------------ |
     * | `--glans`  | Waar het punt staat, 0 tot 1.        |
     * | `--kracht` | Hoe sterk het is, 0 tot 1.           |
     *
     * Drie keuzes die het rustig houden in plaats van druk:
     *
     * 1. **Eén lichtpunt tegelijk, niet allemaal samen.** De items zijn
     *    gelijkmatig over de cyclus verdeeld, dus het loopt als een
     *    vuurtoren het blok rond. Bij veel items wordt de cyclus langer
     *    in plaats van de tussenpozen korter -- anders knippert het.
     * 2. **`--kracht` vaart in en uit** met een sinus over de reis. Een
     *    lichtpunt dat op volle sterkte begint en eindigt, duikt op en
     *    klapt weg; nu komt het op en gaat het weer.
     * 3. **Het staat stil zolang je het niet ziet.** Een tijdlijn die
     *    eeuwig doorloopt op een blok dat drie schermen hoger staat,
     *    kost accu en levert niets op.
     */
    const rustOp = (): (() => void) => {
        /*
         * De lengte van één ronde door het blok, in seconden.
         *
         * Bij drie cijfers mag het vlot rondgaan; bij vijftien moet het
         * langer duren, anders is er altijd ergens iets aan het
         * oplichten en wordt het een kerstboom.
         *
         * **Dit is twee keer korter gemaakt, allebei op zijn verzoek.**
         * Eerst 1,2 seconde per cijfer: bij zestien cijfers kwam het licht
         * dan eens per veertien seconden langs een bepaalde balk, en dan
         * is de kans groot dat je er net niet naar keek. Daarna 0,8, en
         * nog steeds vroeg hij of het vaker kon. Nu een halve seconde per
         * cijfer met zeven en een half als plafond: elk cijfer licht dus
         * minstens eens per zeven seconden op, en er lopen er een paar
         * tegelijk.
         *
         * Korter dan dit zou ik niet gaan. De ondergrens van vier
         * seconden is er voor een blok met twee of drie cijfers: die
         * zouden anders om de twee seconden knipperen, en dan is het geen
         * rustanimatie meer.
         */
        const ronde = Math.min(7.5, Math.max(4, onderdelen.length * 0.5));

        const tijdlijn = gsap.timeline({ repeat: -1 });

        onderdelen.forEach((deel, index) => {
            const stand = { glans: 0 };

            tijdlijn.to(
                stand,
                {
                    glans: 1,
                    duration: 1.6,
                    ease: 'none',
                    onUpdate: () => {
                        deel.vak.style.setProperty(
                            '--glans',
                            stand.glans.toFixed(4),
                        );

                        deel.vak.style.setProperty(
                            '--kracht',
                            Math.sin(Math.PI * stand.glans).toFixed(4),
                        );
                    },
                    onComplete: () => {
                        // Helemaal uit, en niet "bijna uit": een restje
                        // doorschijnendheid blijft als vlek staan.
                        deel.vak.style.setProperty('--kracht', '0');
                    },
                },
                (index * ronde) / onderdelen.length,
            );
        });

        const kijker = ScrollTrigger.create({
            trigger: blok,
            start: 'top bottom',
            end: 'bottom top',
            onToggle: (zelf) => {
                if (zelf.isActive) {
                    tijdlijn.play();

                    return;
                }

                tijdlijn.pause();
            },
        });

        // Staat het blok niet in beeld, dan hoeft er nu niets te lopen.
        if (!kijker.isActive) {
            tijdlijn.pause();
        }

        return () => {
            kijker.kill();
            tijdlijn.kill();
        };
    };

    /** De opruimer van de rustanimatie, zodra die loopt. */
    let ruimRustOp: (() => void) | undefined;

    const tijdlijn = gsap.timeline({
        paused: true,
        onComplete: () => {
            ruimRustOp = rustOp();
        },
    });

    onderdelen.forEach((deel, index) => {
        const stand = { vulling: 0 };

        tijdlijn.to(
            stand,
            {
                vulling: 1,
                duration: 1.1,

                /*
                 * `back.out` met een kleine uitslag: de vulling schiet
                 * een fractie voorbij zijn doel en veert terug. Dat is
                 * het verschil tussen een laadbalk en iets dat op zijn
                 * plek valt.
                 *
                 * Klein houden. Bij een hogere uitslag gaat een ring van
                 * 95 procent even over de honderd heen, en dan tekent
                 * de boog zichzelf dubbel over het begin.
                 */
                ease: 'back.out(1.4)',

                onUpdate: () => {
                    /*
                     * Afkappen op 1. De overshoot van `back.out` mag de
                     * beweging doen, maar niet in de waarde terechtkomen
                     * -- anders staat er even 97 waar 95 hoort.
                     */
                    const vulling = Math.min(1, stand.vulling);

                    deel.vak.style.setProperty('--vulling', vulling.toFixed(4));
                    schrijf(deel.getal, deel.waarde * vulling);
                },
            },
            index * tussen,
        );
    });

    /*
     * Eén keer afspelen zodra het blok in beeld komt.
     *
     * `once: true` en geen `scrub`: terugscrollen hoort niets terug te
     * draaien. Staat het blok al in beeld bij het laden -- een
     * navigatie binnen de site, of een korte pagina -- dan vuurt een
     * scroll-trigger nooit af en moet het meteen spelen. Zie
     * `alInBeeld`; dezelfde valkuil als bij alle andere reveals hier.
     */
    if (alInBeeld(blok, 0.9)) {
        tijdlijn.play();

        return () => {
            ruimRustOp?.();
            tijdlijn.kill();
        };
    }

    const trigger = ScrollTrigger.create({
        trigger: blok,
        start: 'top 80%',
        once: true,
        onEnter: () => tijdlijn.play(),
    });

    return () => {
        trigger.kill();
        ruimRustOp?.();
        tijdlijn.kill();
    };
}

/**
 * Laat een waarde zich vastzetten als een rolbord.
 *
 * Het beeld is een vertrekbord op een station: de letters vallen niet van
 * links naar rechts op hun plek maar door elkaar, met een kleine
 * terugstuit. Dat laatste is wat het "klik" geeft -- zonder die overshoot
 * is het gewoon tekst die omhoogschuift.
 *
 * **Waarom dit naast `splitReveal` staat en er niet in zit.** Die werkt op
 * regels en is bedoeld voor koppen: een zin die zich zet. Dit werkt op
 * letters en is bedoeld voor losse waarden van een paar woorden. Eén
 * functie met een schakelaar ertussen zou twee verschillende effecten in
 * elkaar schuiven die niets delen behalve SplitText.
 *
 * Zes dingen die hier bewust zo zijn:
 *
 * - **`mask: 'chars'` doet het maskeren, niet wij.** SplitText zet zelf
 *   een omhulsel met `overflow: hidden` om elke letter. Zelf iets
 *   eromheen bouwen werkt ook, tot de tekst anders afbreekt.
 * - **De tekst wordt nooit vervangen.** Er rolt geen willekeurige reeks
 *   tekens doorheen zoals bij een echt rolbord; alleen de échte letters
 *   bewegen. Een animatie die de inhoud tijdelijk vervalst is een
 *   animatie die een schermlezer voorliegt.
 * - **SplitText zet zelf een `aria-label`** met de hele tekst erin. Een
 *   schermlezer leest daardoor "Vanaf januari" en niet "V, a, n, a, f".
 *   Haal dat er niet af.
 * - **Alleen `translate` en `opacity`.** Compositor-only; alles wat
 *   `height` of `padding` aanraakt kost een layout per frame, en dat is
 *   precies waar de slideshow ooit op vastliep.
 * - **Boven `MAX_LETTERS` gaat het per woord in plaats van per letter.**
 *   Een waarde van tachtig tekens zou tachtig losse letters opleveren:
 *   geen bord meer maar ruis, en tachtig elementen om te animeren. De
 *   grens ligt op `MAX_LETTERS` hieronder, niet op die tachtig. Het blijft wel hetzelfde bord met
 *   dezelfde terugstuit, alleen grover: een lange waarde valt zo niet uit
 *   de toon naast een korte, en dat zou een platte schuif eronder wel
 *   doen.
 * - **De beweging begint iets ná de strook.** Het vak eronder komt op met
 *   de gewone `revealOnScroll`; zonder die vertraging zetten de letters
 *   zich vast in een vak dat nog doorzichtig is.
 *
 * Geeft een opruimfunctie terug; roep die aan in onBeforeUnmount.
 */
export function rolbord(selector: string, scope?: Element | null): () => void {
    /** Boven dit aantal letters gaat het per woord in plaats van per letter. */
    const MAX_LETTERS = 24;

    /**
     * Wachten tot het vak eronder er is. `revealOnScroll` laat de strook
     * vanaf dezelfde regel in 0,8 seconde opkomen; na 0,3 seconde staat
     * hij ver genoeg om de letters erin te laten landen.
     */
    const VERTRAGING = 0.3;

    /* Dezelfde zeef als bij `revealOnScroll`, en om dezelfde reden. */
    const targets = gsap.utils
        .toArray<HTMLElement>(selector, scope ?? undefined)
        .filter((target) => target.dataset.gezien === undefined);

    if (targets.length === 0) {
        return () => {};
    }

    if (prefersReducedMotion()) {
        gsap.set(targets, { opacity: 1, y: 0 });

        targets.forEach((target) => {
            target.dataset.gezien = '';
        });

        return () => {};
    }

    const splits: SplitText[] = [];

    targets.forEach((target) => {
        gsap.set(target, { opacity: 1 });

        /*
         * Hier meteen en niet na de beweging, net als bij `splitReveal`:
         * vanaf deze regel staat de waarde op doorzichtigheid 1 en is hij
         * dus leesbaar. Zou het merkje pas aan het eind komen, dan
         * hersplitst een herscan iets wat je al leest.
         */
        target.dataset.gezien = '';

        const tekst = (target.textContent ?? '').trim();

        /*
         * Dezelfde regel als `revealOnScroll`, en niet eerder: eerder ging de
         * waarde rollen vóórdat de strook zelf was opgekomen.
         *
         * **En ook niet later, en dat was een fout.** Hier stond vast 85%,
         * terwijl de strook op een telefoon al op 115% opkomt (zie
         * `beweging()`). Een waarde in de onderste strook van het scherm
         * stond dan zichtbaar leeg -- het vak was er, de tekst wachtte nog
         * op een trigger die pas bij verder scrollen kwam. Spring je naar
         * `#kerngegevens`, dan kwam hij zelfs helemaal niet.
         */
        const { vanaf } = beweging();

        /*
         * **De strook is de trigger, niet de waarde.** Elke waarde wachtte
         * eerst op zijn eigen plek, en de strook kwam als geheel op: een
         * tweede rij die net onder de grens begon, stond dan zichtbaar in
         * een opgekomen strook -- maar leeg. Nu rollen alle waarden zodra
         * hun strook binnenkomt: één mededeling, één beweging.
         */
        const strook =
            target.closest<HTMLElement>(
                '[data-rolbord-strook], [data-reveal]',
            ) ?? target;
        const start = alInBeeld(strook, vanaf)
            ? {}
            : {
                  scrollTrigger: {
                      trigger: strook,
                      start: `top ${vanaf * 100}%`,
                      once: true,
                  },
              };

        /*
         * Te lang voor losse letters: dan hetzelfde bord, maar per woord.
         * "Op locatie, op afstand of allebei" hoort naast "Vanaf januari"
         * te staan alsof ze samen op één bord zitten, en dat doen ze
         * alleen als ze dezelfde beweging maken.
         */
        if (tekst.length > MAX_LETTERS) {
            splits.push(
                SplitText.create(target, {
                    type: 'words',
                    mask: 'words',
                    autoSplit: true,
                    onSplit: (self) =>
                        gsap.from(self.words, {
                            yPercent: 110,
                            duration: 0.55,

                            /*
                             * Korter gespreid dan bij de letters: het zijn
                             * er een stuk of zes, en met dezelfde spreiding
                             * hangt het laatste woord onnodig lang.
                             */
                            stagger: { amount: 0.3, from: 'random' },

                            ease: 'back.out(2)',
                            delay: VERTRAGING,
                            ...start,
                        }),
                }),
            );

            return;
        }

        splits.push(
            SplitText.create(target, {
                type: 'chars',
                mask: 'chars',
                autoSplit: true,
                onSplit: (self) =>
                    gsap.from(self.chars, {
                        yPercent: 110,
                        duration: 0.55,

                        /*
                         * De willekeur ís het effect. `amount` en niet
                         * `each`: zo duurt het geheel even lang of er nu
                         * zeven of twintig letters staan, en blijft de
                         * strook als één beweging lezen.
                         */
                        stagger: { amount: 0.5, from: 'random' },

                        // De terugstuit die het "klik" geeft.
                        ease: 'back.out(2)',

                        delay: VERTRAGING,
                        ...start,
                    }),
            }),
        );
    });

    return () => splits.forEach((split) => split.revert());
}

/**
 * Laat een lijn vanuit het midden naar buiten groeien.
 *
 * Bedoeld voor de accentlijn boven de voet: hij ligt er al, en zodra je de
 * voet bereikt trekt hij zich open naar beide kanten. Dat leest als een
 * afsluiting -- een streep die onder het verhaal wordt gezet -- en niet als
 * nog een blok dat komt opzetten.
 *
 * Drie dingen die hier bewust zo zijn:
 *
 * - **`scaleX` en niets anders.** Compositor-only; `width` animeren kost
 *   een layout per frame, en dit is een element dat over de volle breedte
 *   van de pagina loopt.
 * - **`transform-origin` staat in de CSS en niet hier.** Waar een lijn
 *   vandaan groeit is opmaak, en dan hoort het bij de klasse die hem
 *   tekent. Zie `.brand-footer-lijn` in app.css.
 * - **Dezelfde zeef en dezelfde terugval als de andere helpers hier.** Wat
 *   al is gezien blijft staan, bij `prefers-reduced-motion` staat de lijn
 *   er meteen, en staat hij al in beeld dan speelt het zonder
 *   scroll-trigger -- die komt anders nooit meer langs.
 *
 * Geeft een opruimfunctie terug; roep die aan in onBeforeUnmount.
 */
export function groeiLijn(
    selector: string,
    scope?: Element | null,
): () => void {
    const targets = gsap.utils
        .toArray<HTMLElement>(selector, scope ?? undefined)
        .filter((target) => target.dataset.gezien === undefined);

    if (targets.length === 0) {
        return () => {};
    }

    if (prefersReducedMotion()) {
        gsap.set(targets, { scaleX: 1 });

        targets.forEach((target) => {
            target.dataset.gezien = '';
        });

        return () => {};
    }

    const tweens = targets.map((target) =>
        gsap.fromTo(
            target,
            { scaleX: 0 },
            {
                scaleX: 1,
                duration: 1.1,
                ease: 'power3.out',

                onComplete: () => {
                    target.dataset.gezien = '';
                },

                /*
                 * Dezelfde grens als de trigger eronder, zoals het contract
                 * bovenaan dit bestand eist. Hier stond de standaard van
                 * 0.85 tegenover een trigger op 95%, en dat was de enige
                 * plek waar die twee uit elkaar liepen.
                 */
                ...(alInBeeld(target, 0.95)
                    ? {}
                    : {
                          scrollTrigger: {
                              trigger: target,
                              start: 'top 95%',
                              once: true,
                          },
                      }),
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

/* -------------------------------------------------------------------------
 * "Helder zicht": de taal van de publieke site.
 *
 * Het merk is een oog, en alles hieronder speelt daarop: een diafragma dat
 * opengaat, een beeld dat scherp wordt, licht dat met je meeloopt. Elk
 * effect betekent "dit komt in focus" en is geen versiering.
 *
 * Dezelfde drie afspraken als de rest van dit bestand: JavaScript zet alleen
 * eigenschappen en variabelen (de opmaak staat in resources/css/site/), elk
 * effect geeft een opruimfunctie terug, en onder reduced motion staat alles
 * meteen in zijn eindtoestand.
 *
 * Zie docs/architecture/frontend-en-animatie.md, "De landing als scène".
 * ---------------------------------------------------------------------- */

/**
 * Een breed scherm, waar de zwaardere effecten mogen.
 *
 * Dezelfde grens als `tablet:` in de CSS (50rem). Blur en parallax kosten
 * op een telefoon scherpte en accu en leveren op dat formaat weinig op.
 */
export function opEenBreedScherm(): boolean {
    return (
        typeof window !== 'undefined' &&
        window.matchMedia('(min-width: 50rem)').matches
    );
}

/** Begin met een scroll-trigger, of meteen als het al in beeld staat. */
function wanneerInBeeld(
    element: Element,
    vanaf: number,
): gsap.TweenVars['scrollTrigger'] | undefined {
    return alInBeeld(element, vanaf)
        ? undefined
        : { trigger: element, start: `top ${vanaf * 100}%`, once: true };
}

/**
 * Een diafragma dat opengaat: van een stip in het midden naar het hele vlak.
 *
 * Een `clip-path` en geen schaal, want een schaal vervormt wat erin staat --
 * een gezicht dat groeit oogt als een zoom, een gezicht dat onthuld wordt
 * als een oog dat opengaat. Na afloop gaat de `clip-path` er weer af, zodat
 * er niets blijft knippen als het vlak later van maat verandert.
 *
 * `direct` speelt hem meteen, voor wat bij binnenkomst al in beeld staat
 * (de hero). Anders wacht hij tot het element in beeld scrolt.
 */
export function irisOpen(
    element: HTMLElement,
    {
        wachten = 0,
        duur = 1.4,
        direct = false,
        midden = '50% 50%',
    }: {
        wachten?: number;
        duur?: number;
        direct?: boolean;
        midden?: string;
    } = {},
): () => void {
    if (prefersReducedMotion()) {
        gsap.set(element, { clearProps: 'clipPath', opacity: 1 });

        return () => {};
    }

    const { vanaf } = beweging();
    const tween = gsap.fromTo(
        element,
        { clipPath: `circle(0% at ${midden})`, opacity: 1 },
        {
            clipPath: `circle(75% at ${midden})`,
            duration: opEenTelefoon() ? duur * 0.7 : duur,
            delay: wachten,
            ease: 'expo.inOut',
            clearProps: 'clipPath',
            scrollTrigger: direct ? undefined : wanneerInBeeld(element, vanaf),
        },
    );

    return () => {
        tween.scrollTrigger?.kill();
        tween.kill();
    };
}

/**
 * Een spotlicht dat de cursor volgt, voor de rand van een plaat.
 *
 * Alleen de plek, als `--glans-x` en `--glans-y`; wat er met die plek
 * gebeurt (een lichtere rand, een zachte gloed) staat in de CSS. Dezelfde
 * variabelen als `kantelKaarten`, zodat een kaart die allebei krijgt niet
 * twee keer gemeten wordt -- gebruik dan alleen `kantelKaarten`.
 *
 * Alleen met een muis. Op een aanraakscherm is er geen cursor om te volgen,
 * en een lichtvlek die blijft staan waar je tikte oogt als een vlek.
 */
export function spotlicht(elementen: HTMLElement[]): () => void {
    if (elementen.length === 0 || prefersReducedMotion() || !heeftMuis()) {
        return () => {};
    }

    const opruimers = elementen.map((element) => {
        const opBeweging = (gebeurtenis: PointerEvent): void => {
            const vlak = element.getBoundingClientRect();

            element.style.setProperty(
                '--glans-x',
                `${(gebeurtenis.clientX - vlak.left).toFixed(0)}px`,
            );
            element.style.setProperty(
                '--glans-y',
                `${(gebeurtenis.clientY - vlak.top).toFixed(0)}px`,
            );
        };

        element.addEventListener('pointermove', opBeweging, { passive: true });

        return () => element.removeEventListener('pointermove', opBeweging);
    });

    return () => opruimers.forEach((opruimen) => opruimen());
}

/**
 * Het omgevingslicht dat met de bezoeker meeloopt.
 *
 * Eén vast element achter de hele pagina (zie PublicLayout), met twee
 * zachte lichtvlekken erin. Per onderdeel schuift het licht naar links of
 * naar rechts, en dat volgt de **plek in de rij** en niet de naam van het
 * onderdeel: na een verplaatsing door de klant wisselt het licht dus nog
 * steeds netjes af, net als de achtergrond van de secties.
 *
 * JavaScript zet alleen `--veld-kant` (-1, 0 of 1); de verschuiving zelf is
 * een CSS-overgang op `transform`. In de hero staat het licht in het
 * midden.
 *
 * Onder reduced motion blijft het licht stil in het midden staan: het is
 * sfeer, geen informatie.
 */
export function lichtVeld(
    veld: HTMLElement,
    scope?: Element | null,
): () => void {
    veld.style.setProperty('--veld-kant', '0');

    if (prefersReducedMotion()) {
        return () => {};
    }

    const secties = gsap.utils.toArray<HTMLElement>(
        'main section[id]',
        scope ?? undefined,
    );

    const triggers = secties.map((sectie, plek) =>
        ScrollTrigger.create({
            trigger: sectie,
            start: 'top 55%',
            end: 'bottom 55%',
            onToggle: (zelf) => {
                if (zelf.isActive) {
                    veld.style.setProperty(
                        '--veld-kant',
                        plek % 2 === 0 ? '1' : '-1',
                    );
                } else if (plek === 0 && zelf.direction < 0) {
                    // Terug omhoog de hero in: licht weer in het midden.
                    veld.style.setProperty('--veld-kant', '0');
                }
            },
        }),
    );

    return () => triggers.forEach((trigger) => trigger.kill());
}

/**
 * De kop van de hero komt op: regel voor regel achter een masker, en scherp.
 *
 * Hetzelfde als `splitReveal`, maar zonder scroll-trigger en met een
 * wachttijd: de hero staat er al als je aankomt, en wacht alleen op de
 * merkintro. Hij verving de typmachine, die halve titels liet zien -- bij
 * een kop van tachtig tekens las je drie seconden lang "Advies over inr".
 *
 * Geeft een opruimfunctie terug die de splitsing terugdraait; doe dat
 * vóórdat iemand anders in de kop schrijft.
 */
export function kopOpkomst(
    kop: HTMLElement,
    { wachten = 0 }: { wachten?: number } = {},
): () => void {
    if (prefersReducedMotion()) {
        gsap.set(kop, { opacity: 1 });

        return () => {};
    }

    gsap.set(kop, { opacity: 1 });

    const scherp = opEenBreedScherm();
    /*
     * Via `splitsRegels` en niet rechtstreeks `SplitText.create` met
     * `autoSplit`: dan hersplitst SplitText de kop bij élk lettertype dat
     * binnenkomt, ook als het een ander is. Zie `splitsRegels`.
     */
    const split = splitsRegels(kop, {
        type: 'lines',
        mask: 'lines',
        onSplit: (self) =>
            gsap.from(self.lines, {
                yPercent: 110,
                ...(scherp
                    ? { filter: 'blur(12px)', clearProps: 'filter' }
                    : {}),
                duration: opEenTelefoon() ? 0.8 : 1.15,
                stagger: 0.09,
                delay: wachten,
                ease: 'expo.out',
            }),
    });

    return () => stopRegels(split);
}

/* -------------------------------------------------------------------------
 * Ronde twee: beweging tijdens het scrollen, niet alleen bij binnenkomst.
 *
 * De eerste versie had één entree bovenaan en daarna vooral fade-ins. Deze
 * hulpen maken de beweging onderdeel van het lezen: een plaat die uit de
 * diepte omhoogkomt, een groep die zich samen opbouwt, en een voortgang die
 * de CSS zelf kan gebruiken. Alles hier hangt aan de **positie** van de
 * bezoeker (`scrub`) of speelt één keer (`once`) -- nooit een waarde die
 * terugloopt als je omhoog scrolt (zie de les over `scrub` in
 * docs/architecture/frontend-en-animatie.md).
 * ---------------------------------------------------------------------- */

/**
 * Een plaat komt omhoog uit de diepte terwijl je hem binnenscrolt.
 *
 * Van iets lager naar zijn plek, gekoppeld aan de scroll:
 * je duwt hem zelf in beeld. Alleen `transform`, op één groot element, dus
 * de browser schuift een laag en tekent niets opnieuw. Op een telefoon en
 * onder reduced motion niets: daar staat de plaat er gewoon.
 *
 * **De transform gaat er weer af zodra de plaat op zijn plek staat.** Een
 * blijvende `transform` maakt van de plaat het kader voor alles wat erin
 * `position: fixed` staat, en een plaat hoort nergens het kader van te zijn.
 */
export function plaatOpkomst(plaat: HTMLElement): () => void {
    if (prefersReducedMotion()) {
        return () => {};
    }

    const mm = gsap.matchMedia();

    mm.add('(min-width: 50rem)', () => {
        /*
         * **Alleen verschuiven, niet schalen.** Hier stond eerst ook een
         * schaal van 0,965 naar 1, en die kostte meer dan hij opleverde: een
         * schaal die elk beeldje verandert laat de browser een plaat vol
         * tekst steeds opnieuw rasteren, terwijl een verschuiving de laag
         * alleen verplaatst. Gemeten: het blok onder zo'n plaat ging van
         * een vijfde trage beeldjes naar vrijwel nul.
         */
        const tween = gsap.fromTo(
            plaat,
            { y: 72 },
            {
                y: 0,
                ease: 'none',
                scrollTrigger: {
                    trigger: plaat,
                    start: 'top bottom',
                    end: 'top 62%',
                    scrub: true,
                    invalidateOnRefresh: true,
                    onUpdate: (zelf) => {
                        if (zelf.progress >= 1) {
                            plaat.style.transform = '';
                        }
                    },
                },
            },
        );

        return () => {
            tween.scrollTrigger?.kill();
            tween.kill();
            gsap.set(plaat, { clearProps: 'transform' });
        };
    });

    return () => mm.revert();
}

/**
 * Een groep komt samen binnen, gebundeld.
 *
 * `ScrollTrigger.batch` en niet een trigger per element: wat tegelijk in
 * beeld komt, komt ook tegelijk op, met een kleine stagger ertussen. Een
 * raster van acht tegels dat per tegel op zijn eigen trigger wacht, komt
 * in brokken binnen en leest als laden.
 *
 * `van` is de begintoestand (alleen transform en opacity). Wat al in beeld
 * staat, speelt meteen. Onder reduced motion staat alles meteen op zijn
 * plek, en elk element krijgt `data-gezien` zodat een herscan het laat staan.
 */
export function stapelBinnen(
    elementen: HTMLElement[],
    {
        van = { opacity: 0, y: 28 },
        stagger = 0.06,
        duur,
    }: { van?: gsap.TweenVars; stagger?: number; duur?: number } = {},
): () => void {
    const nieuw = elementen.filter((e) => e.dataset.gezien === undefined);

    if (nieuw.length === 0) {
        return () => {};
    }

    const eind: gsap.TweenVars = {
        opacity: 1,
        x: 0,
        y: 0,
        // Ook de procentversies: een begin met `yPercent` bleef anders tot
        // de `clearProps` staan en sprong dan pas in beeld.
        xPercent: 0,
        yPercent: 0,
        scale: 1,
        rotation: 0,
        rotationX: 0,
        rotationY: 0,
        clearProps: 'transform',
    };

    if (prefersReducedMotion()) {
        gsap.set(nieuw, eind);
        nieuw.forEach((e) => (e.dataset.gezien = ''));

        return () => {};
    }

    const { vanaf, duur: standaard } = beweging();
    gsap.set(nieuw, van);

    const stopBundel = bundel(nieuw, {
        start: `top ${vanaf * 100}%`,
        once: true,
        onEnter: (groep) =>
            gsap.to(groep, {
                ...eind,
                duration: (duur ?? standaard) * 1.2,
                stagger,
                ease: 'expo.out',
                overwrite: true,
                onComplete: () =>
                    (groep as HTMLElement[]).forEach(
                        (e) => (e.dataset.gezien = ''),
                    ),
            }),
    });

    return () => {
        stopBundel();
        gsap.killTweensOf(nieuw);
    };
}

/**
 * De voortgang door een element, als CSS-variabele `--voortgang` (0 tot 1).
 *
 * Voor effecten die in de CSS horen: een lijn die meeloopt, een beeld dat
 * openvouwt. JavaScript zet alleen het getal. **Zet hem op een klein
 * element**, niet op een sectie vol tekst: een CSS-variabele erft, en elke
 * wijziging laat de browser de stijl van alle kinderen opnieuw berekenen.
 *
 * Onder reduced motion staat hij vast op 1: de eindtoestand.
 */
export function scrollVoortgang(
    element: HTMLElement,
    {
        trigger = element,
        start = 'top 85%',
        eind = 'bottom 40%',
    }: { trigger?: HTMLElement; start?: string; eind?: string } = {},
): () => void {
    if (prefersReducedMotion()) {
        element.style.setProperty('--voortgang', '1');

        return () => {};
    }

    const st = ScrollTrigger.create({
        trigger,
        start,
        end: eind,
        invalidateOnRefresh: true,
        onUpdate: (zelf) =>
            element.style.setProperty('--voortgang', zelf.progress.toFixed(4)),
        onRefresh: (zelf) =>
            element.style.setProperty('--voortgang', zelf.progress.toFixed(4)),
    });

    return () => st.kill();
}

export { gsap, ScrollTrigger };
