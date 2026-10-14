<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    useTemplateRef,
    watch,
} from 'vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import { Button } from '@/components/ui/button';
import { gsap, ScrollTrigger, tekenPad } from '@/lib/motion';
import { werkwijze } from '@/routes';
import type { SectieKop, SectieProps } from '@/types/secties';
import type { StapOpDeSite } from '@/types/werkwijze';

/**
 * De werkwijze: het lichtpad.
 *
 * **Op een breed scherm is dit een korte scène.** De kop en de knop staan
 * stil (CSS `position: sticky`, geen ScrollTrigger-pin) en de stappen
 * schuiven er als één spoor onderdoor van rechts naar links, precies zo
 * ver als je scrolt. Over de stappen loopt een lichtstraal die de andere
 * kant op groeit: het spoor gaat naar links, het licht naar rechts, en
 * elke stap licht op op het moment dat het licht hem bereikt. Terug-
 * scrollen draait alles terug. Zo is "werkwijze" een volgorde die je
 * doorloopt, en geen rij blokjes.
 *
 * **De scène kost precies de afstand die het spoor moet afleggen.** Die
 * wordt gemeten (de breedte van het spoor min de breedte van het venster)
 * en als lege ruimte onder het podium gezet; meer scroll dan dat is lege
 * scroll. Is dat meer dan een schermhoogte, dan gaat het spoor iets sneller
 * dan je scrolt in plaats van dat de scène langer wordt.
 *
 * **Geen scène als die niets toevoegt** -- `data-baan` op de scène, gezet
 * door `meet()`:
 * - `scene`: het spoor past niet in de breedte; het schuift.
 * - `rij`: het past wel (twee of drie stappen, of een breed scherm). Dan
 *   staat de rij stil en loopt alleen het licht met je mee.
 * - geen waarde: een raster dat afbreekt. Bij reduced motion, en als het
 *   podium niet in het scherm past (een laag venster met lange teksten):
 *   een scène waarvan je de onderkant niet ziet, is erger dan geen scène.
 *
 * **Onder 64rem een kolom** met de straal verticaal langs de knopen;
 * die tekent `tekenPad` in lib/motion.ts. Geen sticky op een telefoon: daar
 * scrol je met je duim en hoort de pagina gewoon te lopen.
 *
 * Zie docs/architecture/modules/werkwijze.md.
 */
defineProps<SectieProps>();

const page = usePage();

const stappen = computed<StapOpDeSite[]>(
    () => (page.props.workSteps as StapOpDeSite[] | undefined) ?? [],
);

const kop = computed<SectieKop | null>(
    () => (page.props.workHeading as SectieKop | undefined) ?? null,
);

/**
 * Of de pagina met het hele verhaal bestaat.
 *
 * De server beslist dat, want daar staat ook de regel die bepaalt of die
 * pagina bestaat. Zou dit scherm zelf gaan kijken of er ergens een verhaal
 * is, dan wijst de knop vroeg of laat naar een 404.
 */
const eigenPagina = computed<boolean>(
    () => (page.props.workPage as boolean | undefined) ?? false,
);

/** Eén stap verbindt niets: dan geen straal en geen knopen. */
const vorm = computed(() => (stappen.value.length === 1 ? 'enkel' : 'reeks'));

/**
 * De golfrichting van `tekenPad` voor elke stap in de kolom: de eerste en
 * de laatste liggen op de rechte lijn, de middelste wisselen af. `tekenPad`
 * laat de straal tien pixels om de middelste stappen heen golven; de CSS
 * schuift de zichtbare knoop mee. Verander je die tien pixels in
 * motion.ts, verander ze dan ook in resources/css/site/werkwijze.css.
 */
const golf = (index: number): number => {
    if (index === 0 || index === stappen.value.length - 1) {
        return 0;
    }

    return index % 2 === 0 ? 1 : -1;
};

const scene = useTemplateRef<HTMLElement>('scene');
const podium = useTemplateRef<HTMLElement>('podium');
const venster = useTemplateRef<HTMLElement>('venster');
const baan = useTemplateRef<HTMLElement>('baan');
const vol = useTemplateRef<HTMLElement>('vol');
const licht = useTemplateRef<HTMLElement>('licht');
const svg = useTemplateRef<SVGSVGElement>('svg');
const pad = useTemplateRef<SVGPathElement>('pad');
const punt = useTemplateRef<SVGCircleElement>('punt');
const lijst = useTemplateRef<HTMLElement>('lijst');

/** Breed genoeg voor de scène: zie het blok bovenaan. */
const BREED = '(min-width: 64rem)';

let media: gsap.MatchMedia | undefined;

/**
 * Het brede scherm: het spoor, het licht en de stappen die oplichten.
 *
 * Geeft een opruimfunctie terug; `gsap.matchMedia` roept die aan zodra het
 * scherm smaller wordt of de bezoeker reduced motion aanzet.
 */
const spoor = (): (() => void) | undefined => {
    if (
        scene.value === null ||
        podium.value === null ||
        venster.value === null ||
        baan.value === null ||
        vol.value === null ||
        licht.value === null ||
        lijst.value === null
    ) {
        return undefined;
    }

    const sceneEl = scene.value;
    const podiumEl = podium.value;
    const vensterEl = venster.value;
    const baanEl = baan.value;
    const lijstEl = lijst.value;

    const kaarten = Array.from(
        lijstEl.querySelectorAll<HTMLElement>('[data-stap]'),
    );

    if (kaarten.length < 2) {
        return undefined;
    }

    /** Hoe ver het spoor naar links gaat, in beeldpunten. */
    let reis = 0;
    /** Hoeveel scroll dat kost: de reis, hooguit een schermhoogte. */
    let afstand = 0;
    /** Van de eerste tot de laatste knoop: zo ver loopt het licht. */
    let lengte = 0;
    /** Waar elke stap op die lengte zit, als deel van 0 tot 1. */
    let drempels: number[] = [];
    /** Hoe ver het podium onder de bovenrand plakt. */
    let boven = 0;

    /**
     * Meet en kiest de vorm. Draait bij het begin en bij elke verversing
     * van ScrollTrigger (`refreshInit`), dus vóórdat die zijn triggers
     * opnieuw uitrekent: de ruimte onder het podium staat dan al goed.
     *
     * **Gemeten in de vorm van de scène.** Het raster breekt af, dus pas
     * als de stappen op één regel staan zie je hoe breed het spoor is en
     * hoe hoog het podium wordt. Daarom eerst `scene` zetten, meten, en
     * dan pas kiezen. Dat is één keer extra rekenen per verversing.
     */
    const meet = (): void => {
        sceneEl.setAttribute('data-baan', 'scene');
        sceneEl.style.setProperty('--reis', '0px');
        gsap.set(baanEl, { x: 0 });

        /*
         * Hoe ver het venster buiten het raam mag reiken: tot vlak voor
         * de rand van de plaat (of van de pagina), hooguit 8rem. Gemeten
         * en niet in CSS uitgerekend, want `100vw` telt de schuifbalk mee
         * en dan steekt het venster net buiten de pagina.
         */
        const raam = vensterEl.parentElement?.getBoundingClientRect();
        const plaat = (
            sceneEl.closest('.brand-site-sectie-plaat') ??
            document.documentElement
        ).getBoundingClientRect();
        const rand =
            raam === undefined
                ? 0
                : Math.max(
                      0,
                      Math.min(
                          128,
                          raam.left - plaat.left - 4,
                          plaat.right - raam.right - 4,
                      ),
                  );

        vensterEl.style.setProperty('--rand', `${Math.floor(rand)}px`);

        const spoorBreedte = baanEl.offsetWidth;
        const vensterBreedte = vensterEl.clientWidth - 2 * Math.floor(rand);
        const hoogte = podiumEl.offsetHeight;

        reis = Math.max(0, spoorBreedte - vensterBreedte);

        const eerste = kaarten[0]?.offsetLeft ?? 0;
        const laatste = kaarten[kaarten.length - 1]?.offsetLeft ?? 0;

        lengte = Math.max(1, laatste - eerste);
        drempels = kaarten.map((kaart) => (kaart.offsetLeft - eerste) / lengte);

        baanEl.style.setProperty('--spoor-begin', `${eerste}px`);
        baanEl.style.setProperty('--spoor-lengte', `${lengte}px`);

        /*
         * In het midden van het scherm als het podium klein genoeg is,
         * anders net onder de zwevende kopbalk (88 pixels: de balk plus
         * lucht). Zo staat de scène nooit half onder die balk.
         */
        boven = Math.max(88, Math.round((window.innerHeight - hoogte) / 2));

        const pastInHetScherm = hoogte + boven <= window.innerHeight - 16;

        if (!pastInHetScherm) {
            sceneEl.removeAttribute('data-baan');
            lijstEl.removeAttribute('data-licht');
            kaarten.forEach((kaart) => kaart.setAttribute('data-bereikt', ''));
            reis = 0;
            afstand = 0;

            return;
        }

        lijstEl.setAttribute('data-licht', '');

        if (reis <= 4) {
            sceneEl.setAttribute('data-baan', 'rij');
            reis = 0;
            afstand = 0;

            return;
        }

        afstand = Math.min(reis, window.innerHeight);
        sceneEl.style.setProperty('--reis', `${afstand}px`);
        sceneEl.style.setProperty('--podium-boven', `${boven}px`);
    };

    /**
     * Welke stappen het licht al bereikt heeft, en welke het laatst.
     * Een positie en geen waarde: terugscrollen dooft ze weer.
     */
    const markeer = (voortgang: number): void => {
        if (!sceneEl.hasAttribute('data-baan')) {
            return;
        }

        let actief = -1;

        kaarten.forEach((kaart, index) => {
            if (voortgang >= (drempels[index] ?? 1) - 0.001) {
                kaart.setAttribute('data-bereikt', '');
                actief = index;
            } else {
                kaart.removeAttribute('data-bereikt');
            }
        });

        kaarten.forEach((kaart, index) =>
            kaart.toggleAttribute('data-actief', index === actief),
        );
    };

    meet();
    ScrollTrigger.addEventListener('refreshInit', meet);

    const inScene = (): boolean =>
        sceneEl.getAttribute('data-baan') === 'scene';

    /*
     * Eén trigger voor beide vormen. In de scène loopt hij precies zo lang
     * als het podium plakt: van "bovenkant scène op de plakhoogte" tot
     * "onderkant scène op de onderkant van het podium". In een stilstaande
     * rij loopt alleen het licht, terwijl de rij door het beeld schuift.
     * De functies worden bij elke verversing opnieuw uitgerekend.
     */
    const tijdlijn = gsap.timeline({
        scrollTrigger: {
            trigger: sceneEl,
            start: () => (inScene() ? `top ${boven}px` : 'top 75%'),
            end: () =>
                inScene()
                    ? `bottom ${boven + podiumEl.offsetHeight}px`
                    : 'bottom 55%',
            scrub: true,
            invalidateOnRefresh: true,
            onUpdate: (self) => markeer(self.progress),
            onRefresh: (self) => markeer(self.progress),
        },
    });

    tijdlijn
        .fromTo(baanEl, { x: 0 }, { x: () => -reis, ease: 'none' }, 0)
        .fromTo(vol.value, { scaleX: 0 }, { scaleX: 1, ease: 'none' }, 0)
        .fromTo(licht.value, { x: 0 }, { x: () => lengte, ease: 'none' }, 0);

    markeer(tijdlijn.scrollTrigger?.progress ?? 0);

    return () => {
        ScrollTrigger.removeEventListener('refreshInit', meet);
        tijdlijn.scrollTrigger?.kill();
        tijdlijn.kill();
        gsap.set([baanEl, vol.value, licht.value], { clearProps: 'transform' });
        sceneEl.removeAttribute('data-baan');
        sceneEl.style.removeProperty('--reis');
        sceneEl.style.removeProperty('--podium-boven');
        vensterEl.style.removeProperty('--rand');
        lijstEl.removeAttribute('data-licht');
        kaarten.forEach((kaart) => {
            kaart.removeAttribute('data-bereikt');
            kaart.removeAttribute('data-actief');
        });
    };
};

/**
 * Het smalle scherm: een kolom met de straal verticaal langs de knopen.
 *
 * De stappen lichten op, ze vervagen niet in: zolang het licht een stap
 * nog niet bereikt heeft ligt hij zacht terug (`data-licht`), komt het
 * langs (`data-bereikt`, gezet door `tekenPad`), dan komt hij naar voren.
 * Alleen als er echt een pad loopt; anders zou een stap eeuwig wachten.
 */
const kolom = (): (() => void) | undefined => {
    if (svg.value === null || pad.value === null || lijst.value === null) {
        return undefined;
    }

    const lijstEl = lijst.value;
    const kaarten = Array.from(
        lijstEl.querySelectorAll<HTMLElement>('[data-stap]'),
    );

    const stoppen = tekenPad(svg.value, pad.value, punt.value, kaarten);

    lijstEl.toggleAttribute('data-licht', !svg.value.hasAttribute('data-leeg'));

    return () => {
        stoppen();
        lijstEl.removeAttribute('data-licht');
        kaarten.forEach((kaart) => kaart.removeAttribute('data-bereikt'));
    };
};

const begin = (): void => {
    media?.revert();
    media = gsap.matchMedia();

    media.add(
        {
            breed: `${BREED} and (prefers-reduced-motion: no-preference)`,
            smal: `not all and ${BREED}`,
            rustig: `${BREED} and (prefers-reduced-motion: reduce)`,
        },
        (context) => {
            const { breed, smal } = context.conditions ?? {};

            if (breed) {
                return spoor();
            }

            if (smal) {
                return kolom();
            }

            /*
             * Breed scherm met reduced motion: het raster, alles helder.
             * Geen beweging, en ook niets dat op beweging wacht.
             */
            const kaarten = Array.from(
                lijst.value?.querySelectorAll<HTMLElement>('[data-stap]') ?? [],
            );

            kaarten.forEach((kaart) => kaart.setAttribute('data-bereikt', ''));

            return () =>
                kaarten.forEach((kaart) =>
                    kaart.removeAttribute('data-bereikt'),
                );
        },
    );
};

/*
 * Opnieuw beginnen als de stappen veranderen -- bij een taalwissel komt
 * er een nieuwe lijst binnen. `flush: 'post'`: pas als de nieuwe stappen
 * in de pagina staan; zonder dat mat de oude code de stappen die net
 * weggingen.
 */
watch(stappen, begin, { flush: 'post' });

onMounted(begin);

onBeforeUnmount(() => {
    media?.revert();
    media = undefined;
});
</script>

<template>
    <SiteSection id="werkwijze" :tone="tone" :divided="divided">
        <!--
            De scène: het podium plakt, de lege ruimte erna is de afstand
            die het spoor aflegt. Zonder scène is die ruimte nul.
        -->
        <div ref="scene" class="brand-site-werkwijze-scene" :data-vorm="vorm">
            <div ref="podium" class="brand-site-werkwijze-podium">
                <div class="brand-site-werkwijze-zijkant">
                    <SectionHeading
                        v-if="kop"
                        class="brand-site-werkwijze-kop"
                        :eyebrow="kop.opschrift ?? undefined"
                        :title="kop.titel"
                        :intro="kop.inleiding ?? undefined"
                    />

                    <!--
                        Alleen als er echt meer te lezen valt; de server
                        houdt die regel bij. Op een breed scherm staat hij
                        naast de kop en blijft hij met de kop staan; op
                        een smal scherm zet de CSS hem ná de stappen.
                    -->
                    <div
                        v-if="eigenPagina"
                        data-reveal
                        class="brand-site-werkwijze-knop opacity-0"
                    >
                        <Button variant="brand-outline" as-child>
                            <Link :href="werkwijze()">
                                {{ $t('Lees hoe ik werk') }}
                                <ArrowRight class="size-4" aria-hidden="true" />
                            </Link>
                        </Button>
                    </div>
                </div>

                <div ref="venster" class="brand-site-werkwijze">
                    <!--
                        De straal van de kolom, voor het smalle scherm.
                        `aria-hidden`: de volgorde staat al in de genummerde
                        lijst eronder.
                    -->
                    <svg
                        ref="svg"
                        class="brand-site-werkwijze-lijn"
                        aria-hidden="true"
                    >
                        <path
                            ref="pad"
                            fill="none"
                            stroke="url(#brand-site-werkwijze-verloop)"
                            stroke-width="2"
                            stroke-linecap="round"
                        />

                        <circle
                            ref="punt"
                            class="brand-site-werkwijze-licht"
                            r="4"
                        />

                        <defs>
                            <linearGradient
                                id="brand-site-werkwijze-verloop"
                                x1="0"
                                y1="0"
                                x2="1"
                                y2="1"
                            >
                                <stop
                                    offset="0%"
                                    class="brand-site-werkwijze-verloop-begin"
                                />
                                <stop
                                    offset="100%"
                                    class="brand-site-werkwijze-verloop-eind"
                                />
                            </linearGradient>
                        </defs>
                    </svg>

                    <!-- Het spoor dat op een breed scherm schuift. -->
                    <div ref="baan" class="brand-site-werkwijze-baan">
                        <!--
                            De straal van het spoor: een vaste lijn, het
                            deel dat al verlicht is, en het licht zelf.
                        -->
                        <div
                            class="brand-site-werkwijze-straal"
                            aria-hidden="true"
                        >
                            <span
                                ref="vol"
                                class="brand-site-werkwijze-straal-vol"
                            />
                            <span
                                ref="licht"
                                class="brand-site-werkwijze-straal-licht"
                            />
                        </div>

                        <ol
                            ref="lijst"
                            class="brand-site-werkwijze-stappen"
                            :style="{ '--stappen': stappen.length }"
                        >
                            <li
                                v-for="(stap, index) in stappen"
                                :key="stap.id"
                                data-stap
                                class="brand-site-werkwijze-stap"
                                :style="{ '--golf': golf(index) }"
                            >
                                <!--
                                    De knoop op de straal, en eronder het
                                    anker waar `tekenPad` de kolomstraal
                                    aan ophangt.
                                -->
                                <span
                                    class="brand-site-werkwijze-knoop"
                                    aria-hidden="true"
                                >
                                    <span
                                        data-stap-punt
                                        class="brand-site-werkwijze-anker"
                                    />
                                </span>

                                <div class="brand-site-werkwijze-inhoud">
                                    <!--
                                        Het nummer is voor het oog; de
                                        volgorde staat al in de `<ol>`.
                                    -->
                                    <span
                                        class="brand-site-werkwijze-nummer"
                                        aria-hidden="true"
                                    >
                                        {{ String(index + 1).padStart(2, '0') }}
                                    </span>

                                    <h3 class="brand-site-werkwijze-titel">
                                        {{ stap.titel }}
                                    </h3>

                                    <p
                                        v-if="stap.samenvatting"
                                        class="brand-site-werkwijze-tekst"
                                    >
                                        {{ stap.samenvatting }}
                                    </p>

                                    <!--
                                        Duur en resultaat: hoe lang, en wat
                                        heb ik daarna.
                                    -->
                                    <dl
                                        v-if="stap.duur || stap.resultaat"
                                        class="brand-site-werkwijze-feiten"
                                    >
                                        <div v-if="stap.duur">
                                            <dt>{{ $t('Duur') }}</dt>
                                            <dd class="brand-site-data">
                                                {{ stap.duur }}
                                            </dd>
                                        </div>
                                        <div v-if="stap.resultaat">
                                            <dt>{{ $t('Resultaat') }}</dt>
                                            <dd>{{ stap.resultaat }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            </li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- De afstand die het spoor aflegt; zie het blok bovenaan. -->
            <div class="brand-site-werkwijze-reis" aria-hidden="true" />
        </div>
    </SiteSection>
</template>
