<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useTemplateRef,
    watch,
} from 'vue';
import ProjectUitgelicht from '@/components/site/ProjectUitgelicht.vue';
import { gsap, prefersReducedMotion } from '@/lib/motion';
import type { ProjectHerkomst, ProjectOpDeSite } from '@/types/projecten';

/**
 * De uitgelichte projecten, als slideshow over de volle breedte.
 *
 * Staat bovenaan `/projecten` en in het blok op de voorpagina zodra er meer
 * dan één ster staat. Elke dia is hetzelfde blok als daar
 * (`ProjectUitgelicht`), zodat de twee plekken niet uiteen kunnen lopen --
 * alleen zonder de opkomanimatie, want die hoort bij scrollen en niet bij
 * schuiven.
 *
 * **Eén uitgelicht project is geen slideshow.** Dan verdwijnen de pijlen en
 * de bolletjes vanzelf en blijft er één blok staan. Pijlen bij één dia zijn
 * een belofte die niet wordt waargemaakt.
 *
 * ## Hoe hij wisselt
 *
 * **De dia's liggen op elkaar en de nieuwe veegt open.** Niet meer een
 * spoor dat opzij schuift, maar een diafragma: de nieuwe dia wordt met een
 * `clip-path` van de ene rand naar de andere onthuld, over de vorige heen,
 * en het beeld erin zakt van iets te groot naar zijn eigen maat. Vooruit
 * veegt hij van rechts, terug van links, zodat de richting klopt met de
 * pijl waar je op drukte. Dat is dezelfde taal als de rest van de site:
 * dingen komen in beeld, ze schuiven niet langs.
 *
 * **De dia volgt nog steeds je vinger.** Tijdens het slepen schuift de dia
 * in beeld mee en dimt hij een beetje. Bij het loslaten veegt de volgende
 * open. Net als de pijlen loopt een veeg rond: voorbij de laatste dia komt
 * de eerste. Eerst was de veeg aan de uiteinden stroever en liep hij niet
 * rond; met twee projecten deed een veeg dan de helft van de keren niets,
 * terwijl de pijl ernaast gewoon doorging.
 *
 * **Een sleep is geen klik.** De dia's zijn links, dus zonder dit opent een
 * veeg het project in plaats van door te schuiven. Na een beweging van meer
 * dan een paar beeldpunten vangen we de eerstvolgende klik weg.
 *
 * **Alle dia's zijn even hoog, en er wordt niets aan hoogte geanimeerd.**
 * Ze liggen in één rastercel op elkaar, dus het venster is vanzelf zo hoog
 * als de hoogste dia; een kortere dia centreert zijn inhoud. Een venster
 * dat meegroeit met de dia in beeld is hier al eens geprobeerd: `height`
 * animeren laat de browser elke frame de pagina eronder herberekenen, en
 * dat hakkelde.
 *
 * **Het doorschuiven stopt zodra iemand kijkt**: bij de muis erop, bij
 * toetsenbordfocus in het blok, tijdens het slepen, zodra het tabblad naar
 * de achtergrond gaat, en zodra het blok zelf uit beeld is gescrold. Dat
 * laatste volgde uit het vorige: een tabblad op de achtergrond laten we
 * met rust, maar een slideshow die onderaan de pagina staat te draaien
 * terwijl de bezoeker bovenaan leest, is net zo goed werk voor niemand --
 * en hij zou bij terugkomst op een willekeurige dia staan. Tekst die wegschuift terwijl je hem leest is
 * het ergste wat een slideshow kan doen. De lijn van de huidige dia loopt
 * vol met de tijd die er nog staat, zodat het doorschuiven niet uit het
 * niets komt.
 *
 * **Bij `prefers-reduced-motion` veegt er niets en draait er niets.** Dan
 * is het een stapel waar je met de pijlen doorheen klikt.
 *
 * De dia's die niet in beeld staan krijgen `inert`: anders loopt de
 * tabvolgorde door knoppen die niemand ziet staan.
 *
 * Zie docs/architecture/modules/projecten.md.
 */
const props = withDefaults(
    defineProps<{
        items: ProjectOpDeSite[];
        /**
         * Zie `ProjectKaart`: belandt als `?van=` in de adressen van de
         * dia's. Op de voorpagina `'start'`, op `/projecten` niets.
         */
        herkomst?: ProjectHerkomst;
        /**
         * Of het venster zich opbouwt terwijl je ernaar scrolt. Op de
         * voorpagina wel: daar is dit de casus van de sectie, en die gaat
         * open als een lens (zie `ProjectUitgelicht`). Op `/projecten`
         * niet: daar staat hij bovenaan en ben je er al.
         */
        opbouw?: boolean;
    }>(),
    { herkomst: null, opbouw: false },
);

/** Hoe lang een dia blijft staan voordat hij doorschuift. */
const WACHTTIJD = 7000;

/** Vanaf hoeveel beeldpunten een sleep als een veeg telt. */
const VEEG_DREMPEL = 56;

/** En vanaf hoeveel het geen klik meer is. */
const KLIK_SPELING = 6;

const huidig = ref(0);
/**
 * Waarom het doorschuiven stilstaat: muis, focus, slepen of tabblad.
 *
 * Een reden per bron en geen enkel aan/uit. Met één schakelaar zette de
 * muis die het blok verliet het doorschuiven weer aan terwijl de
 * toetsenbordfocus er nog in stond, en dan schoof de dia onder de knop
 * vandaan waar je net op stond.
 */
type Reden = 'muis' | 'focus' | 'slepen' | 'tabblad';

const redenen = ref(new Set<Reden>());
const stilgezet = computed(() => redenen.value.size > 0);

/**
 * De dia die net weg is. Hij blijft onder de nieuwe liggen terwijl die
 * openveegt; zonder hem zag je tijdens het vegen de lege plaat erachter.
 */
const vorig = ref<number | null>(null);

/** Van welke kant de nieuwe dia binnenveegt. */
const richting = ref<'vooruit' | 'terug'>('vooruit');

/** Staat het blok in beeld? Tot de eerste meting nemen we aan van wel. */
const inBeeld = ref(true);
const blok = useTemplateRef<HTMLElement>('blok');
const venster = useTemplateRef<HTMLElement>('venster');

/**
 * De beelden in de dia's gaan open terwijl je scrolt: van een kleiner,
 * ronder venster naar hun volle kader, op de positie van de bezoeker.
 * Dezelfde opening als de losse casus in `ProjectUitgelicht`.
 *
 * **Op de beelden en niet op het hele venster.** Eerst ging het venster als
 * geheel open, en dan knipte de opening halverwege ook de titel af: je las
 * "migratie v" en dacht dat er iets stuk was. Nu staat de tekst er meteen
 * en gaat alleen het beeld open. De `clip-path` zit op het kader; het beeld
 * erin heeft zijn eigen overgang (`transform`) bij het wisselen van dia, en
 * die twee raken elkaar niet.
 *
 * Alleen `clip-path`; onder reduced motion staan de beelden gewoon open.
 */
let stopOpbouw: (() => void) | undefined;

function bouwOp(): () => void {
    if (
        prefersReducedMotion() ||
        blok.value === null ||
        venster.value === null
    ) {
        return () => {};
    }

    const vlak = blok.value;
    const kaders = [
        ...venster.value.querySelectorAll<HTMLElement>(
            '.brand-site-casus-beeld',
        ),
    ];

    if (kaders.length === 0) {
        return () => {};
    }

    const mm = gsap.matchMedia();

    mm.add(
        { breed: '(min-width: 50rem)', smal: '(max-width: 49.999rem)' },
        (context) => {
            const { breed } = context.conditions as { breed: boolean };

            const opening = gsap.timeline({
                defaults: { ease: 'none' },
                scrollTrigger: {
                    trigger: vlak,
                    start: 'top 92%',
                    end: breed ? 'top 35%' : 'top 50%',
                    scrub: true,
                    invalidateOnRefresh: true,
                },
            });

            opening.fromTo(
                kaders,
                {
                    clipPath: breed
                        ? 'inset(10% 16% 10% 16% round 2.5rem)'
                        : 'inset(7% 9% 7% 9% round 1.5rem)',
                },
                { clipPath: 'inset(0% 0% 0% 0% round 0.75rem)' },
                0,
            );

            /*
             * Beeld en tekst komen uit twee richtingen samen, net als bij
             * een losse casus (zie ProjectUitgelicht.vue): de beeldkaders
             * van links, de tekstblokken van rechts, op dezelfde scroll als
             * de opening. Op het kader en het tekstblok zelf -- de dia
             * eromheen heeft zijn eigen transform voor het slepen, het beeld
             * erin zijn eigen schaal. Alleen op een breed scherm.
             */
            const teksten = [
                ...(venster.value?.querySelectorAll<HTMLElement>(
                    '.brand-site-casus-tekst',
                ) ?? []),
            ];

            if (breed) {
                const afstand = (): number =>
                    Math.min(vlak.clientWidth * 0.12, 170);

                opening.fromTo(
                    kaders,
                    { x: () => -afstand() },
                    { x: 0, ease: 'power2.out' },
                    0,
                );
                opening.fromTo(
                    teksten,
                    { x: () => afstand() * 0.8 },
                    { x: 0, ease: 'power2.out' },
                    0,
                );
            }

            return () => {
                opening.scrollTrigger?.kill();
                opening.kill();
                gsap.set(kaders, { clearProps: 'clipPath,transform' });
                gsap.set(teksten, { clearProps: 'transform' });
            };
        },
    );

    return () => mm.revert();
}

const meerdere = computed(() => props.items.length > 1);
const rustig = computed(() => prefersReducedMotion());

/* --- Doorschuiven ------------------------------------------------------ */

let tikker: ReturnType<typeof setInterval> | null = null;
let beeldKijker: IntersectionObserver | undefined;

/**
 * Loopt op bij elke wissel en zit in de sleutel van de voortgangsring.
 *
 * Daardoor begint die animatie opnieuw zodra je zelf doorklikt -- Vue
 * vervangt het element en de browser start de animatie van voren af aan.
 * Zonder deze teller loopt de ring door waar hij was, en dan staat er een
 * ring van tachtig procent onder een dia die net is begonnen.
 */
const beurt = ref(0);

/**
 * De veeg die nu loopt, als die er is.
 *
 * **De veeg is een tijdlijn en geen CSS-overgang, en dat is de reparatie
 * van een echte fout.** Eerst deed een `transition` op `clip-path` het
 * werk: een dia die niet aan de beurt was stond weggeknipt, en werd hij de
 * huidige, dan liep de knip naar nul. Maar de dia die net weg was bleef
 * open onder de nieuwe liggen (`data-vorig`), en met twee projecten is dat
 * precies de dia die je bij de volgende klik weer nodig hebt. Die ging van
 * "open" naar "open": geen verschil, dus geen overgang. Alleen de eerste
 * wissel veegde; elke wissel daarna stond er ineens. Bij drie of meer
 * gebeurde hetzelfde zodra je terugklikte. Gemeten: de eerste wissel
 * doorliep 58 tussenstanden van de knip, elke volgende één.
 *
 * Nu zet elke wissel zijn eigen beginstand (`fromTo`), dus wat er daarvoor
 * met die dia gebeurde doet er niet meer toe.
 *
 * **Een klik tijdens een veeg gaat niet verloren.** De lopende veeg springt
 * naar zijn eind en de nieuwe begint meteen; zo kun je snel doorklikken
 * zonder dat er iets vastloopt of een klik wordt ingeslikt.
 */
let veeg: gsap.core.Timeline | null = null;

function rondVeegAf(): void {
    veeg?.progress(1);
    veeg = null;
}

function blad(index: number): HTMLElement | undefined {
    return (
        venster.value?.querySelectorAll<HTMLElement>('.brand-site-dia-blad')[
            index
        ] ?? undefined
    );
}

/**
 * Hoe een wissel eruitziet: de nieuwe dia veegt open van de kant waar je
 * heen bladert, het beeld erin zakt van iets te dichtbij naar zijn maat en
 * de tekst schuift het laatste stukje mee. De vorige wijkt een fractie
 * naar de andere kant uit en dimt, zodat je ziet dat hij onder de nieuwe
 * verdwijnt en niet gewoon wordt overgeschilderd.
 *
 * Alleen `clip-path`, `transform` en `opacity`, op de dia's zelf, hun
 * beeld en de regels van hun tekst -- niet op het beeldkader en het
 * tekstblok, want die stuurt de opbouw bij het scrollen (`bouwOp()`).
 *
 * `vanaf`: waar de vorige dia stond als je hem met je vinger wegsleepte,
 * zodat hij van daaruit verder gaat in plaats van eerst terug te springen.
 */
function speelVeeg(
    nieuw: HTMLElement,
    oud: HTMLElement | undefined,
    kant: 'vooruit' | 'terug',
    vanaf: number,
): void {
    const teken = kant === 'vooruit' ? 1 : -1;
    const foto = nieuw.querySelector<HTMLElement>(
        '.brand-site-casus-beeld img',
    );
    const regels = [
        ...nieuw.querySelectorAll<HTMLElement>('.brand-site-casus-tekst > *'),
    ];

    veeg = gsap.timeline({
        defaults: { overwrite: 'auto' },
        onComplete: () => {
            gsap.set(nieuw, { clearProps: 'clipPath' });
            gsap.set(regels, { clearProps: 'transform,opacity' });

            if (foto !== null) {
                gsap.set(foto, { clearProps: 'transform' });
            }

            if (oud !== undefined) {
                gsap.set(oud, { clearProps: 'transform,opacity' });
            }

            veeg = null;
            vorig.value = null;
        },
    });

    veeg.fromTo(
        nieuw,
        {
            clipPath:
                teken > 0 ? 'inset(0% 0% 0% 100%)' : 'inset(0% 100% 0% 0%)',
        },
        {
            clipPath: 'inset(0% 0% 0% 0%)',
            duration: 0.9,
            ease: 'power3.inOut',
        },
        0,
    );

    if (foto !== null) {
        veeg.fromTo(
            foto,
            { scale: 1.12 },
            { scale: 1, duration: 1.3, ease: 'power2.out' },
            0,
        );
    }

    if (regels.length > 0) {
        veeg.fromTo(
            regels,
            { x: 48 * teken, opacity: 0 },
            {
                x: 0,
                opacity: 1,
                duration: 0.7,
                ease: 'power3.out',
                stagger: 0.05,
            },
            0.3,
        );
    }

    if (oud !== undefined) {
        veeg.fromTo(
            oud,
            { x: vanaf, opacity: 1 },
            {
                x: vanaf - 64 * teken,
                opacity: 0.45,
                duration: 0.9,
                ease: 'power3.inOut',
            },
            0,
        );
    }
}

/**
 * Naar een dia, vanaf een bepaalde kant.
 *
 * Eerst de stand (welke dia aan is, welke net weg), dan laat Vue de
 * attributen schrijven, en pas dan begint de veeg -- nog vóór de browser
 * tekent, dus de nieuwe dia staat nooit een beeldje open voordat hij
 * openveegt.
 */
async function naar(
    index: number,
    kant?: 'vooruit' | 'terug',
    vanaf = 0,
): Promise<void> {
    const aantal = props.items.length;

    if (aantal === 0) {
        return;
    }

    const doel = ((index % aantal) + aantal) % aantal;

    if (doel === huidig.value) {
        return;
    }

    rondVeegAf();

    const van = huidig.value;

    richting.value = kant ?? (doel > van ? 'vooruit' : 'terug');
    vorig.value = rustig.value ? null : van;
    huidig.value = doel;
    beurt.value += 1;

    if (rustig.value) {
        return;
    }

    await nextTick();

    // Is er intussen alweer verder geklikt, dan is die wissel aan zet.
    if (huidig.value !== doel) {
        return;
    }

    const nieuw = blad(doel);

    if (nieuw !== undefined) {
        speelVeeg(nieuw, blad(van), richting.value, vanaf);
    }
}

function vorige(vanaf = 0): void {
    void naar(huidig.value - 1, 'terug', vanaf);
}

function volgende(vanaf = 0): void {
    void naar(huidig.value + 1, 'vooruit', vanaf);
}

function startDoorschuiven(): void {
    stopDoorschuiven();

    if (!meerdere.value || rustig.value || stilgezet.value || !inBeeld.value) {
        return;
    }

    tikker = setInterval(() => volgende(), WACHTTIJD);
}

function stopDoorschuiven(): void {
    if (tikker !== null) {
        clearInterval(tikker);
        tikker = null;
    }
}

function pauzeer(reden: Reden): void {
    redenen.value.add(reden);
}

function hervat(reden: Reden): void {
    redenen.value.delete(reden);
}

/** Focus die naar een andere knop in het blok gaat, blijft binnen. */
function bijFocusWeg(gebeurtenis: FocusEvent): void {
    const naar = gebeurtenis.relatedTarget;

    if (naar instanceof Node && blok.value?.contains(naar)) {
        return;
    }

    hervat('focus');
}

/** Een tabblad op de achtergrond hoeft niet door te schuiven. */
function bijZichtbaarheid(): void {
    if (document.visibilityState === 'hidden') {
        pauzeer('tabblad');
    } else {
        hervat('tabblad');
    }
}

/*
 * Opnieuw beginnen met aftellen zodra je zelf doorklikt. Anders schuift
 * hij een halve seconde later alsnog door omdat de klok van de vorige dia
 * nog liep.
 */
watch([stilgezet, meerdere, huidig, inBeeld], startDoorschuiven);

onMounted(() => {
    if (props.opbouw) {
        stopOpbouw = bouwOp();
    }

    startDoorschuiven();
    document.addEventListener('visibilitychange', bijZichtbaarheid);

    /*
     * Het vangnet voor een sleep die buiten het venster eindigt.
     *
     * Zolang de beweging onder de klikspeling blijft is er geen
     * `setPointerCapture`, dus een `pointerup` buiten het venster komt
     * daar nooit aan en zou het slepen aan laten staan. Deze luisteraar
     * doet niets als er niet gesleept wordt.
     */
    window.addEventListener('pointerup', bijLoslaten);
    window.addEventListener('pointercancel', bijLoslaten);

    if (blok.value !== null && typeof IntersectionObserver !== 'undefined') {
        beeldKijker = new IntersectionObserver(
            ([regel]) => (inBeeld.value = regel?.isIntersecting ?? true),
            // Een strookje van het blok is genoeg om te blijven draaien.
            { threshold: 0.15 },
        );

        beeldKijker.observe(blok.value);
    }
});

onBeforeUnmount(() => {
    stopOpbouw?.();
    veeg?.kill();
    stopDoorschuiven();
    document.removeEventListener('visibilitychange', bijZichtbaarheid);
    window.removeEventListener('pointerup', bijLoslaten);
    window.removeEventListener('pointercancel', bijLoslaten);
    beeldKijker?.disconnect();
});

/* --- Slepen ------------------------------------------------------------ */

/** Hoeveel beeldpunten de dia nu mee is met de vinger. */
const sleep = ref(0);
const sleept = ref(false);

let startX = 0;
let verplaatst = false;

function bijNeerzetten(gebeurtenis: PointerEvent): void {
    if (!meerdere.value || gebeurtenis.button !== 0) {
        return;
    }

    // Wie vastpakt tijdens een veeg, pakt de dia zoals hij straks staat.
    rondVeegAf();
    startX = gebeurtenis.clientX;
    verplaatst = false;
    sleept.value = true;
    pauzeer('slepen');
}

/**
 * Pas vastpakken zodra het écht een sleep is.
 *
 * **Niet meteen bij het neerzetten, en dat is geen detail.** Met
 * `setPointerCapture` gaan ook de muisgebeurtenissen naar het venster, en
 * dan valt `click` op het venster in plaats van op de link van de dia --
 * waardoor een gewone klik op "Bekijk project" niets meer doet. Door pas
 * te grijpen na een beweging van een paar beeldpunten blijft een klik een
 * klik, en krijgt een sleep alsnog de muis mee als hij buiten het venster
 * belandt.
 */
function bijBewegen(gebeurtenis: PointerEvent): void {
    if (!sleept.value) {
        return;
    }

    const afstand = gebeurtenis.clientX - startX;

    if (!verplaatst && Math.abs(afstand) > KLIK_SPELING) {
        verplaatst = true;

        const vak = gebeurtenis.currentTarget as HTMLElement;

        if (vak.setPointerCapture !== undefined) {
            vak.setPointerCapture(gebeurtenis.pointerId);
        }
    }

    sleep.value = afstand;
}

function bijLoslaten(): void {
    if (!sleept.value) {
        return;
    }

    const afstand = sleep.value;

    sleept.value = false;
    sleep.value = 0;
    hervat('slepen');

    if (Math.abs(afstand) < VEEG_DREMPEL) {
        return;
    }

    if (afstand < 0) {
        volgende(afstand);
    } else {
        vorige(afstand);
    }
}

/**
 * Een sleep is geen klik.
 *
 * De dia's zijn links; zonder deze vanger opent een veeg het project.
 * `capture` omdat de klik anders eerst bij de link aankomt.
 */
function bijKlikken(gebeurtenis: MouseEvent): void {
    if (!verplaatst) {
        return;
    }

    gebeurtenis.preventDefault();
    gebeurtenis.stopPropagation();
    verplaatst = false;
}

/**
 * Hoe de dia in beeld met de vinger meegaat: hij schuift mee en dimt een
 * beetje, zodat je ziet dat er iets gaat gebeuren als je loslaat.
 */
const sleepStijl = computed(() =>
    sleep.value === 0
        ? undefined
        : {
              transform: `translate3d(${sleep.value}px, 0, 0)`,
              opacity: String(1 - Math.min(Math.abs(sleep.value) / 900, 0.35)),
          },
);
</script>

<template>
    <section
        v-if="items.length > 0"
        ref="blok"
        class="brand-site-dia"
        :aria-roledescription="meerdere ? $t('Slideshow') : undefined"
        :aria-label="meerdere ? $t('Uitgelichte projecten') : undefined"
        @pointerenter="pauzeer('muis')"
        @pointerleave="hervat('muis')"
        @focusin="pauzeer('focus')"
        @focusout="bijFocusWeg"
        @keydown.left="vorige()"
        @keydown.right="volgende()"
    >
        <div
            ref="venster"
            class="brand-site-dia-venster"
            :data-sleept="sleept ? '' : undefined"
            :aria-live="meerdere && stilgezet ? 'polite' : 'off'"
            @pointerdown="bijNeerzetten"
            @pointermove="bijBewegen"
            @pointerup="bijLoslaten"
            @pointercancel="bijLoslaten"
            @click.capture="bijKlikken"
        >
            <div
                v-for="(item, index) in items"
                :key="item.slug"
                class="brand-site-dia-blad"
                :data-aan="index === huidig ? '' : undefined"
                :data-vorig="index === vorig ? '' : undefined"
                :inert="index !== huidig ? true : undefined"
                :aria-hidden="index !== huidig ? 'true' : undefined"
                :role="meerdere ? 'group' : undefined"
                :aria-roledescription="meerdere ? $t('Dia') : undefined"
                :aria-label="
                    meerdere
                        ? $t(':nummer van :aantal', {
                              nummer: String(index + 1),
                              aantal: String(items.length),
                          })
                        : undefined
                "
                :style="index === huidig ? sleepStijl : undefined"
            >
                <ProjectUitgelicht
                    :item="item"
                    :animeren="false"
                    :meteen="index === 0"
                    :herkomst="props.herkomst"
                />
            </div>
        </div>

        <!--
            De bediening staat ónder de dia en niet eroverheen. Pijlen op
            het beeld dekken juist het beeld af, en op een telefoon staan
            ze dan ook nog eens boven de tekst.
        -->
        <div v-if="meerdere" class="brand-site-dia-bediening">
            <button
                type="button"
                class="brand-site-dia-pijl"
                data-kant="terug"
                :aria-label="$t('Vorige project')"
                @click="vorige()"
            >
                <ChevronLeft class="size-5" aria-hidden="true" />
            </button>

            <!--
                Eén lijn per dia. Het aanraakvlak is de volle hoogte van de
                knop (44 pixels); de lijn zelf is maar twee pixels dik.
            -->
            <div class="brand-site-dia-lijnen">
                <button
                    v-for="(item, index) in items"
                    :key="item.slug"
                    type="button"
                    class="brand-site-dia-lijn"
                    :data-aan="index === huidig ? '' : undefined"
                    :aria-current="index === huidig ? 'true' : undefined"
                    :aria-label="$t('Ga naar :titel', { titel: item.titel })"
                    @click="void naar(index)"
                >
                    <!--
                        De voortgang van het doorschuiven, als vulling in
                        de lijn. `:key` loopt mee met de beurt zodat de
                        animatie opnieuw begint bij elke wissel, en de
                        CSS-variabele houdt hem gelijk aan de wachttijd
                        hierboven -- zo kunnen die twee niet uit elkaar
                        lopen.
                    -->
                    <span
                        v-if="index === huidig && !rustig"
                        :key="beurt"
                        class="brand-site-dia-voortgang"
                        :data-stil="stilgezet || !inBeeld ? '' : undefined"
                        :style="{ '--dia-wachttijd': `${WACHTTIJD}ms` }"
                        aria-hidden="true"
                    />
                </button>
            </div>

            <button
                type="button"
                class="brand-site-dia-pijl"
                data-kant="vooruit"
                :aria-label="$t('Volgende project')"
                @click="volgende()"
            >
                <ChevronRight class="size-5" aria-hidden="true" />
            </button>

            <!--
                Waar je bent, in cijfers. Verborgen voor een schermlezer:
                die hoort het al bij de dia zelf ("1 van 3").
            -->
            <span
                class="brand-site-dia-teller brand-site-data"
                aria-hidden="true"
            >
                {{ huidig + 1 }} / {{ items.length }}
            </span>
        </div>
    </section>
</template>
