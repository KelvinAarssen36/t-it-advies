<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';
import { introDraagtOver, introSpeeltAf } from '@/lib/intro';
import { gsap, opEenBreedScherm } from '@/lib/motion';

/**
 * De welkomstanimatie: "scherpstellen".
 *
 * Het merk is een oog, en de site gebruikt dat als lens: dingen komen in
 * focus. Deze entree is daar het eerste en grootste voorbeeld van. In het
 * donker tekenen de ringen van een lens zich om het merkteken, het teken
 * stelt scherp, de schaalring draait als een focusring en de naam komt op.
 * Dan vliegt de hele lens naar de plek waar in de hero de iris staat, en
 * gaat het diafragma van daaruit open. Waar de ringen landen, tekent de
 * ring van de hero zich en gaat het portret open: je kijkt door de lens de
 * site in. Ruim twee seconden; op een telefoon een lichtere versie (alleen
 * de ring en de naam, en sneller), die landt in de kleine iris bovenin de
 * hero.
 *
 * **Geen laadscherm.** De hero staat er volledig onder; deze laag ligt er
 * alleen overheen. Er wordt niet aan de scrollpositie gezeten en er wordt
 * niets tegengehouden: elke toets, klik, tik, scrollbeweging of veeg spoelt
 * de entree door naar het einde, en er staat een knop om over te slaan.
 *
 * **Wanneer hij speelt** -- één keer per browser, alleen op de voorpagina,
 * niet met een anker in het adres -- staat in lib/intro.ts, en daar staat
 * ook het signaal waarop de hero begint. De hero wacht daar hoogstens 2,6
 * seconde op. En de laag ruimt zichzelf in CSS op na vier seconden
 * (`brand-site-intro-vangnet`), ook als deze code halverwege zou stuklopen:
 * een laag die blijft liggen is een pagina die niet werkt.
 *
 * Zie docs/architecture/frontend-en-animatie.md, "De intro bij binnenkomst".
 */

const page = usePage();

const speelt = ref(introSpeeltAf(page.component === 'Welcome'));

/** De naam per letter, voor de opkomst. Een merknaam, dus niet vertaald. */
const NAAM = '@T IT Advies';
const letters = Array.from(NAAM);

const laag = useTemplateRef<HTMLElement>('laag');
const veld = useTemplateRef<HTMLElement>('veld');
const gloed = useTemplateRef<HTMLElement>('gloed');
const lens = useTemplateRef<HTMLElement>('lens');
const merk = useTemplateRef<HTMLElement>('merk');
const schaal = useTemplateRef<SVGCircleElement>('schaal');
const ring = useTemplateRef<SVGCircleElement>('ring');
const binnen = useTemplateRef<SVGCircleElement>('binnen');
const boog = useTemplateRef<SVGGElement>('boog');
const naam = useTemplateRef<HTMLElement>('naam');
const streep = useTemplateRef<HTMLElement>('streep');
const knop = useTemplateRef<HTMLElement>('knop');

let opbouw: gsap.core.Timeline | undefined;
let landing: gsap.core.Timeline | undefined;

/** Haalt de laag weg; daarna is er niets meer van de entree over. */
const klaar = (): void => {
    introDraagtOver();
    stopLuisteren();
    speelt.value = false;
};

/**
 * Waar de lens heen vliegt: de ringen van de iris in de hero.
 *
 * Gemeten op het moment van landen en niet bij het begin: de hero staat
 * dan zeker in de DOM, en de lettertypen zijn binnen. De intro en de hero
 * tekenen dezelfde cirkels in hetzelfde `viewBox`, dus als de doos van de
 * lens op de doos van de ringen valt, vallen de cirkels op elkaar.
 *
 * Geen iris (een telefoon, of hij staat niet in beeld): dan blijft de lens
 * in het midden en gaat het diafragma daar open.
 */
const doel = (): { x: number; y: number; schaal: number } | null => {
    const ringen = document.querySelector<HTMLElement>(
        '.brand-site-hero-ringen',
    );
    const vlak = ringen?.getBoundingClientRect();
    const eigen = lens.value?.getBoundingClientRect();

    if (
        vlak === undefined ||
        eigen === undefined ||
        vlak.width === 0 ||
        vlak.bottom < 0 ||
        vlak.top > window.innerHeight
    ) {
        return null;
    }

    return {
        x: vlak.left + vlak.width / 2 - (eigen.left + eigen.width / 2),
        y: vlak.top + vlak.height / 2 - (eigen.top + eigen.height / 2),
        schaal: vlak.width / eigen.width,
    };
};

/**
 * Het tweede deel: de lens landt en het diafragma gaat open.
 *
 * Een eigen tijdlijn, pas gebouwd als hij begint, omdat de bestemming dan
 * pas gemeten kan worden. `snel` als de bezoeker overslaat: dan dezelfde
 * beweging in een derde van de tijd, zodat ook overslaan nog aanvoelt als
 * landen en niet als een harde knip.
 */
const landen = (snel = false): void => {
    if (landing !== undefined || laag.value === null || veld.value === null) {
        return;
    }

    const bestemming = doel();
    const lensVlak = lens.value?.getBoundingClientRect();
    const midden = bestemming
        ? {
              x:
                  (lensVlak?.left ?? 0) +
                  (lensVlak?.width ?? 0) / 2 +
                  bestemming.x,
              y:
                  (lensVlak?.top ?? 0) +
                  (lensVlak?.height ?? 0) / 2 +
                  bestemming.y,
          }
        : { x: window.innerWidth / 2, y: window.innerHeight / 2 };

    /*
     * Hoe groot het gat moet worden: van dat midden tot de verste hoek van
     * het scherm, plus een rand voor de zachte overgang.
     */
    const straal =
        Math.hypot(
            Math.max(midden.x, window.innerWidth - midden.x),
            Math.max(midden.y, window.innerHeight - midden.y),
        ) + 60;

    // Vanaf hier kijk je al door de laag heen: een klik hoort dan bij de
    // pagina eronder, niet bij de entree.
    laag.value.style.pointerEvents = 'none';
    veld.value.style.setProperty('--gat-x', `${midden.x}px`);
    veld.value.style.setProperty('--gat-y', `${midden.y}px`);

    landing = gsap.timeline({
        defaults: { ease: 'expo.inOut' },
        onComplete: klaar,
    });

    /*
     * Het gat gaat pas open als de lens er bijna is, en eerst alleen tot de
     * rand van de ring: je kijkt even door de lens, en dan gaat het
     * diafragma verder open tot voorbij de hoeken. Ging het meteen open,
     * dan stond er een losse ronde vlek op de plek waar de lens nog heen
     * moest.
     */
    const ringStraal =
        (lensVlak?.width ?? 0) * (bestemming?.schaal ?? 1) * 0.47;
    const aankomst = bestemming ? 0.42 : 0.1;

    landing
        .to(
            naam.value,
            { opacity: 0, y: -12, duration: 0.3, ease: 'power2.in' },
            0,
        )
        .to(knop.value, { opacity: 0, duration: 0.25, ease: 'power1.out' }, 0)
        .to(gloed.value, { opacity: 0, duration: 0.5, ease: 'power2.in' }, 0)
        // De hero mag beginnen: zijn ring en portret komen op terwijl de
        // lens bij ze aankomt.
        .call(introDraagtOver, undefined, Math.max(0, aankomst - 0.05));

    if (bestemming) {
        landing
            .fromTo(
                veld.value,
                { '--gat': '0px' },
                {
                    '--gat': `${ringStraal}px`,
                    duration: 0.25,
                    ease: 'power1.out',
                },
                aankomst,
            )
            .to(
                veld.value,
                { '--gat': `${straal}px`, duration: 0.6, ease: 'power3.inOut' },
                aankomst + 0.25,
            );
    } else {
        /*
         * Zonder iris (een telefoon) in één keer vanuit het midden: een
         * tussenstop op de maat van de ring liet daar een rondje met
         * halve regels tekst zien, want er staat niets onder de lens.
         */
        landing.fromTo(
            veld.value,
            { '--gat': '0px' },
            { '--gat': `${straal}px`, duration: 0.7, ease: 'power3.inOut' },
            aankomst,
        );
    }

    if (bestemming) {
        landing
            .to(
                lens.value,
                {
                    x: bestemming.x,
                    y: bestemming.y,
                    scale: bestemming.schaal,
                    duration: 0.75,
                },
                0,
            )
            // Het teken gaat de pupil in: daar gaat zo het portret open.
            .to(
                merk.value,
                { scale: 0.15, opacity: 0, duration: 0.6, ease: 'power2.in' },
                0.05,
            )
            // En de ringen lossen op in die van de hero.
            .to(lens.value, { opacity: 0, duration: 0.35, ease: 'none' }, 0.65);
    } else {
        landing.to(
            lens.value,
            { scale: 0.6, opacity: 0, duration: 0.45, ease: 'power3.in' },
            0,
        );
    }

    if (snel) {
        landing.timeScale(2.6);
    } else if (!opEenBreedScherm()) {
        /*
         * Op een telefoon landt de lens ook in de iris (die staat daar
         * sinds de hero een regel met portret heeft), maar sneller: het
         * hele stuk blijft daar onder de twee seconden.
         */
        landing.timeScale(1.3);
    }
};

/* --- Overslaan ---------------------------------------------------------- */

/**
 * Wie iets doet, mag verder. Een toets (ook Tab en Escape), een klik, een
 * tik, het scrollwiel of een veeg: dan loopt de entree in een paar tienden
 * van een seconde naar het einde. Het scrollen zelf gaat gewoon door; de
 * pagina eronder schuift al mee.
 */
const slaOver = (): void => {
    if (landing !== undefined) {
        landing.timeScale(2.6);

        return;
    }

    // Naar het eind van de opbouw zonder zijn `onComplete`: die zou het
    // landen op gewone snelheid starten.
    opbouw?.progress(1, true).kill();
    landen(true);
};

const opToets = (gebeurtenis: KeyboardEvent): void => {
    if (['Shift', 'Control', 'Alt', 'Meta'].includes(gebeurtenis.key)) {
        return;
    }

    slaOver();
};

const GEBEURTENISSEN = ['pointerdown', 'wheel', 'touchstart'] as const;

const luisteren = (): void => {
    window.addEventListener('keydown', opToets);
    GEBEURTENISSEN.forEach((naamGebeurtenis) =>
        window.addEventListener(naamGebeurtenis, slaOver, { passive: true }),
    );
};

const stopLuisteren = (): void => {
    window.removeEventListener('keydown', opToets);
    GEBEURTENISSEN.forEach((naamGebeurtenis) =>
        window.removeEventListener(naamGebeurtenis, slaOver),
    );
};

/* --- Het eerste deel: de lens tekent zich en stelt scherp ------------- */

const bouwOp = (): void => {
    const breed = opEenBreedScherm();
    const regels = naam.value?.querySelectorAll('[data-letter]') ?? [];

    opbouw = gsap.timeline({
        defaults: { ease: 'expo.out' },
        onComplete: () => landen(),
    });

    opbouw
        .fromTo(
            gloed.value,
            { opacity: 0 },
            { opacity: 1, duration: 0.6, ease: 'power2.out' },
            0,
        )
        .fromTo(
            ring.value,
            { drawSVG: '0%' },
            {
                drawSVG: '100%',
                duration: breed ? 0.8 : 0.55,
                ease: 'power2.inOut',
            },
            0,
        )
        /*
         * Het teken stelt scherp. De vervaging alleen op een breed scherm
         * en op dit ene kleine beeld; een filter op een telefoon kost meer
         * dan hij daar oplevert.
         */
        .fromTo(
            merk.value,
            breed
                ? { opacity: 0, scale: 0.82, filter: 'blur(8px)' }
                : { opacity: 0, scale: 0.82 },
            breed
                ? {
                      opacity: 1,
                      scale: 1,
                      filter: 'blur(0px)',
                      duration: 0.75,
                      clearProps: 'filter',
                  }
                : { opacity: 1, scale: 1, duration: 0.5 },
            0.1,
        )
        .fromTo(
            regels,
            { yPercent: 105, opacity: 0 },
            {
                yPercent: 0,
                opacity: 1,
                duration: 0.55,
                stagger: breed ? 0.025 : 0.015,
            },
            breed ? 0.4 : 0.2,
        )
        .fromTo(
            streep.value,
            { scaleX: 0 },
            { scaleX: 1, duration: 0.5, ease: 'power3.inOut' },
            breed ? 0.5 : 0.25,
        );

    /*
     * Op een breed scherm de rest van de lens: de schaal komt erbij en
     * draait als een focusring, een binnenring tekent zich de andere kant
     * op en een lichtboog loopt een halve slag rond. Op een telefoon niet:
     * daar is de lens klein, en drie ringen op negen rem is druk.
     */
    if (breed) {
        opbouw
            .fromTo(
                schaal.value,
                { opacity: 0, rotation: -70, svgOrigin: '50 50' },
                {
                    opacity: 1,
                    rotation: 25,
                    svgOrigin: '50 50',
                    duration: 1.05,
                    ease: 'power2.inOut',
                },
                0,
            )
            .fromTo(
                binnen.value,
                { drawSVG: '100% 100%' },
                { drawSVG: '0% 100%', duration: 0.75, ease: 'power2.inOut' },
                0.12,
            )
            .fromTo(
                boog.value,
                { opacity: 0, rotation: -40, svgOrigin: '50 50' },
                {
                    opacity: 1,
                    rotation: 190,
                    svgOrigin: '50 50',
                    duration: 0.85,
                    ease: 'power2.inOut',
                },
                0.25,
            );
    }

    // Een korte rust op het eind, zodat het beeld even staat voor het landt.
    opbouw.to({}, { duration: 0.05 });
};

onMounted(async () => {
    if (!speelt.value || laag.value === null) {
        introDraagtOver();

        return;
    }

    luisteren();

    /*
     * Het merkteken is een afbeelding. Eerst laten decoderen, anders
     * komt de lens op zonder het oog erin. Hoogstens 200 ms: een trage
     * afbeelding mag de entree niet tegenhouden.
     */
    const beeld = merk.value as HTMLImageElement | null;

    await Promise.race([
        beeld?.decode?.().catch(() => undefined),
        new Promise((klaarMet) => window.setTimeout(klaarMet, 200)),
    ]);

    /*
     * En dan het eerste rustige moment. Bij het laden zet de pagina al haar
     * onderdelen klaar, en dat zijn een of twee lange taken; begon de ring
     * daar middenin te tekenen, dan haperde precies het begin. Gemeten op
     * de gebouwde site met een vier keer tragere processor: zonder deze
     * stap viel er een taak van 150 ms in de eerste streep van de ring.
     * Hoogstens 350 ms; op een snel toestel is dat moment er meteen. Tot
     * dan staat er alleen het donkere veld, en dat is ook de eerste stand
     * van de entree.
     */
    await new Promise<void>((verder) => {
        // Safari kent `requestIdleCallback` pas sinds kort; daar meteen.
        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(() => verder(), { timeout: 350 });
        } else {
            globalThis.setTimeout(verder, 0);
        }
    });

    if (!speelt.value) {
        return;
    }

    try {
        bouwOp();

        /*
         * Alleen in de ontwikkelmodus: de tijdlijnen aan het venster, zodat
         * een test of een ontwikkelaar de entree kan stilzetten en beeld
         * voor beeld kan bekijken. In de gebouwde site bestaat dit niet.
         */
        if (import.meta.env.DEV) {
            (window as unknown as Record<string, unknown>).__brandIntro = {
                opbouw: () => opbouw,
                landing: () => landing,
            };
        }
    } catch {
        // Liever geen entree dan een laag die blijft liggen.
        klaar();
    }
});

onBeforeUnmount(() => {
    opbouw?.kill();
    landing?.kill();
    stopLuisteren();
    introDraagtOver();
});
</script>

<template>
    <!--
        Bovenaan de boom, vóór de kop en de inhoud, zodat hij er zonder
        z-index-wedstrijd bovenop ligt en de knop om over te slaan het eerste
        is wat de tabtoets vindt.
    -->
    <div v-if="speelt" ref="laag" class="brand-site-intro">
        <div ref="veld" class="brand-site-intro-veld" aria-hidden="true" />
        <div ref="gloed" class="brand-site-intro-gloed" aria-hidden="true" />

        <div class="brand-site-intro-midden" aria-hidden="true">
            <!--
                De lens: dezelfde cirkels in hetzelfde tekenvlak als de iris
                in de hero (zie HeroSection), zodat ze bij het landen precies
                op elkaar vallen.
            -->
            <div ref="lens" class="brand-site-intro-lens">
                <svg class="brand-site-intro-ringen" viewBox="0 0 100 100">
                    <defs>
                        <linearGradient
                            id="intro-lens-verloop"
                            x1="0"
                            y1="0"
                            x2="1"
                            y2="1"
                        >
                            <stop
                                offset="0%"
                                style="stop-color: var(--brand-cyan)"
                            />
                            <stop
                                offset="55%"
                                style="stop-color: var(--brand-blue)"
                            />
                            <stop
                                offset="100%"
                                style="
                                    stop-color: var(--brand-cyan);
                                    stop-opacity: 0.2;
                                "
                            />
                        </linearGradient>
                    </defs>

                    <circle
                        ref="schaal"
                        class="brand-site-intro-schaal"
                        cx="50"
                        cy="50"
                        r="49.4"
                        pathLength="360"
                        vector-effect="non-scaling-stroke"
                    />
                    <circle
                        ref="ring"
                        cx="50"
                        cy="50"
                        r="47"
                        fill="none"
                        stroke="url(#intro-lens-verloop)"
                        stroke-width="1"
                        vector-effect="non-scaling-stroke"
                    />
                    <circle
                        ref="binnen"
                        class="brand-site-intro-binnen"
                        cx="50"
                        cy="50"
                        r="39"
                        vector-effect="non-scaling-stroke"
                    />
                    <g ref="boog" class="brand-site-intro-boog">
                        <circle
                            cx="50"
                            cy="50"
                            r="47"
                            fill="none"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-dasharray="22 274"
                            vector-effect="non-scaling-stroke"
                        />
                        <circle cx="50" cy="3" r="1.4" />
                    </g>
                </svg>

                <img
                    ref="merk"
                    class="brand-site-intro-merk"
                    src="/images/logo-merk.webp"
                    alt=""
                    decoding="async"
                />
            </div>

            <p ref="naam" class="brand-site-intro-naam">
                <span
                    v-for="(letter, plek) in letters"
                    :key="plek"
                    class="brand-site-intro-letter"
                    :data-merk="plek < 2 ? '' : undefined"
                    ><span data-letter>{{
                        letter === ' ' ? ' ' : letter
                    }}</span></span
                >
            </p>
            <span ref="streep" class="brand-site-intro-streep" />
        </div>

        <button
            ref="knop"
            type="button"
            class="brand-site-intro-sla"
            @click="slaOver"
        >
            {{ $t('Intro overslaan') }}
        </button>
    </div>
</template>
