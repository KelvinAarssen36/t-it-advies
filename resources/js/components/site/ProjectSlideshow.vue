<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    ref,
    useTemplateRef,
    watch,
} from 'vue';
import ProjectUitgelicht from '@/components/site/ProjectUitgelicht.vue';
import { prefersReducedMotion } from '@/lib/motion';
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
 * ## Wat het soepel maakt
 *
 * **Het spoor volgt je vinger.** Niet pas springen bij het loslaten maar
 * meebewegen terwijl je sleept, en aan de uiteinden stroever worden zodat
 * je voelt dat er niets meer komt. Dat is het verschil tussen "er gebeurt
 * iets als ik klaar ben" en "ik schuif het zelf".
 *
 * **Een sleep is geen klik.** De dia's zijn links, dus zonder dit opent een
 * veeg het project in plaats van door te schuiven. Na een beweging van meer
 * dan een paar beeldpunten vangen we de eerstvolgende klik weg.
 *
 * **Wat wegschuift dimt en krimpt een beetje.** Zonder dat verschil ziet
 * een dia die halverwege het beeld uit loopt eruit als dezelfde dia die
 * scheef staat. Met diepte lees je het als iets dat vertrekt.
 *
 * **Alle dia's zijn even hoog, en er wordt niets aan hoogte geanimeerd.**
 * Daar zijn twee pogingen aan voorafgegaan en allebei waren ze fout.
 *
 * De eerste maakte de dia's gelijk zónder iets aan het hoogteverschil te
 * doen: een dia zonder afbeelding werd uitgerekt tot de hoogte van een dia
 * mét afbeelding, met een half blok leegte onder de knop.
 *
 * De tweede liet het venster meebewegen naar de hoogte van de dia in
 * beeld. Dat zag er op papier goed uit, maar `height` is een eigenschap
 * die de browser dwingt om elke frame de hele pagina eronder opnieuw door
 * te rekenen -- en dat is precies waar het hakkelen vandaan kwam.
 *
 * De oorzaak zat ergens anders: op een telefoon was de beeldkolom
 * volledige breedte met een vierkante verhouding, dus op een scherm van
 * 370 beeldpunten werd dat een afbeelding van 370 hoog, tegenover zo'n 300
 * voor een dia zonder beeld. Dat verschil is nu bij de bron weggehaald
 * (zie `.brand-projectuitgelicht-beeld img` in app.css), en wat er
 * overblijft vullen we op door de inhoud verticaal te centreren. Geen
 * animatie op een eigenschap die de pagina herberekent, dus ook geen
 * hapering.
 *
 * **Het doorschuiven stopt zodra iemand kijkt**: bij de muis erop, bij
 * toetsenbordfocus in het blok, tijdens het slepen, zodra het tabblad naar
 * de achtergrond gaat, en zodra het blok zelf uit beeld is gescrold. Dat
 * laatste volgde uit het vorige: een tabblad op de achtergrond laten we
 * met rust, maar een slideshow die onderaan de pagina staat te draaien
 * terwijl de bezoeker bovenaan leest, is net zo goed werk voor niemand --
 * en hij zou bij terugkomst op een willekeurige dia staan. Tekst die wegschuift terwijl je hem leest is
 * het ergste wat een slideshow kan doen. Het bolletje laat met een ring
 * zien hoeveel tijd er nog staat, zodat het doorschuiven niet uit het niets
 * komt.
 *
 * **Bij `prefers-reduced-motion` schuift er niets en draait er niets.** Dan
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
    }>(),
    { herkomst: null },
);

/** Hoe lang een dia blijft staan voordat hij doorschuift. */
const WACHTTIJD = 7000;

/** Vanaf hoeveel beeldpunten een sleep als een veeg telt. */
const VEEG_DREMPEL = 56;

/** En vanaf hoeveel het geen klik meer is. */
const KLIK_SPELING = 6;

const huidig = ref(0);
const stilgezet = ref(false);

/** Staat het blok in beeld? Tot de eerste meting nemen we aan van wel. */
const inBeeld = ref(true);
const blok = useTemplateRef<HTMLElement>('blok');

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

function naar(index: number): void {
    const aantal = props.items.length;

    if (aantal === 0) {
        return;
    }

    huidig.value = ((index % aantal) + aantal) % aantal;
    beurt.value += 1;
}

function vorige(): void {
    naar(huidig.value - 1);
}

function volgende(): void {
    naar(huidig.value + 1);
}

function startDoorschuiven(): void {
    stopDoorschuiven();

    if (!meerdere.value || rustig.value || stilgezet.value || !inBeeld.value) {
        return;
    }

    tikker = setInterval(volgende, WACHTTIJD);
}

function stopDoorschuiven(): void {
    if (tikker !== null) {
        clearInterval(tikker);
        tikker = null;
    }
}

function pauzeer(): void {
    stilgezet.value = true;
}

function hervat(): void {
    stilgezet.value = false;
}

/** Een tabblad op de achtergrond hoeft niet door te schuiven. */
function bijZichtbaarheid(): void {
    if (document.visibilityState === 'hidden') {
        pauzeer();
    } else {
        hervat();
    }
}

/*
 * Opnieuw beginnen met aftellen zodra je zelf doorklikt. Anders schuift
 * hij een halve seconde later alsnog door omdat de klok van de vorige dia
 * nog liep.
 */
watch([stilgezet, meerdere, huidig, inBeeld], startDoorschuiven);

onMounted(() => {
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
    stopDoorschuiven();
    document.removeEventListener('visibilitychange', bijZichtbaarheid);
    window.removeEventListener('pointerup', bijLoslaten);
    window.removeEventListener('pointercancel', bijLoslaten);
    beeldKijker?.disconnect();
});

/* --- Slepen ------------------------------------------------------------ */

/** Hoeveel beeldpunten het spoor nu mee is met de vinger. */
const sleep = ref(0);
const sleept = ref(false);

let startX = 0;
let verplaatst = false;

/**
 * Aan de uiteinden stroever.
 *
 * Voorbij de eerste of de laatste dia is er niets meer, en dan hoort het
 * spoor niet gewoon door te lopen: je zou het beeld leeg trekken. Een
 * derde van de afstand is genoeg om te voelen dat het meegeeft en dat het
 * ophoudt.
 */
function metWeerstand(afstand: number): number {
    const aanBegin = huidig.value === 0 && afstand > 0;
    const aanEind = huidig.value === props.items.length - 1 && afstand < 0;

    return aanBegin || aanEind ? afstand / 3 : afstand;
}

function bijNeerzetten(gebeurtenis: PointerEvent): void {
    if (!meerdere.value || gebeurtenis.button !== 0) {
        return;
    }

    startX = gebeurtenis.clientX;
    verplaatst = false;
    sleept.value = true;
    pauzeer();
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

    sleep.value = metWeerstand(afstand);
}

function bijLoslaten(): void {
    if (!sleept.value) {
        return;
    }

    const afstand = sleep.value;

    sleept.value = false;
    sleep.value = 0;
    hervat();

    if (Math.abs(afstand) < VEEG_DREMPEL) {
        return;
    }

    if (afstand < 0) {
        volgende();
    } else {
        vorige();
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

/** Waar het spoor staat: hele dia's plus wat de vinger eraan trekt. */
const spoorStijl = computed(() => ({
    translate: `calc(${huidig.value * -100}% + ${sleep.value}px) 0`,
}));
</script>

<template>
    <section
        v-if="items.length > 0"
        ref="blok"
        class="brand-projectdia"
        :aria-roledescription="meerdere ? $t('Slideshow') : undefined"
        :aria-label="meerdere ? $t('Uitgelichte projecten') : undefined"
        @pointerenter="pauzeer"
        @pointerleave="hervat"
        @focusin="pauzeer"
        @focusout="hervat"
        @keydown.left="vorige"
        @keydown.right="volgende"
    >
        <div
            class="brand-projectdia-venster"
            :data-sleept="sleept ? '' : undefined"
            @pointerdown="bijNeerzetten"
            @pointermove="bijBewegen"
            @pointerup="bijLoslaten"
            @pointercancel="bijLoslaten"
            @click.capture="bijKlikken"
        >
            <div
                class="brand-projectdia-spoor"
                :data-stil="rustig || sleept ? '' : undefined"
                :style="spoorStijl"
            >
                <div
                    v-for="(item, index) in items"
                    :key="item.slug"
                    class="brand-projectdia-blad"
                    :data-aan="index === huidig ? '' : undefined"
                    :inert="index !== huidig ? true : undefined"
                    :aria-hidden="index !== huidig ? 'true' : undefined"
                >
                    <ProjectUitgelicht
                        :item="item"
                        :animeren="false"
                        :meteen="index === 0"
                        :herkomst="props.herkomst"
                    />
                </div>
            </div>
        </div>

        <!--
            De bediening staat ónder de dia en niet eroverheen. Pijlen op
            het beeld dekken juist het beeld af, en op een telefoon staan
            ze dan ook nog eens boven de tekst.
        -->
        <div v-if="meerdere" class="brand-projectdia-bediening">
            <button
                type="button"
                class="brand-projectdia-pijl"
                :aria-label="$t('Vorige project')"
                @click="vorige"
            >
                <ChevronLeft class="size-5" aria-hidden="true" />
            </button>

            <div class="brand-projectdia-bolletjes">
                <button
                    v-for="(item, index) in items"
                    :key="item.slug"
                    type="button"
                    class="brand-projectdia-bolletje"
                    :data-aan="index === huidig ? '' : undefined"
                    :aria-current="index === huidig ? 'true' : undefined"
                    :aria-label="$t('Ga naar :titel', { titel: item.titel })"
                    @click="naar(index)"
                >
                    <!--
                        De voortgang van het doorschuiven, als vulling in
                        het streepje. `:key` loopt mee met de beurt zodat
                        de animatie opnieuw begint bij elke wissel, en de
                        CSS-variabele houdt hem gelijk aan de wachttijd
                        hierboven -- zo kunnen die twee niet uit elkaar
                        lopen.
                    -->
                    <span
                        v-if="index === huidig && !rustig"
                        :key="beurt"
                        class="brand-projectdia-voortgang"
                        :data-stil="stilgezet || !inBeeld ? '' : undefined"
                        :style="{ '--dia-wachttijd': `${WACHTTIJD}ms` }"
                        aria-hidden="true"
                    />
                </button>
            </div>

            <button
                type="button"
                class="brand-projectdia-pijl"
                :aria-label="$t('Volgende project')"
                @click="volgende"
            >
                <ChevronRight class="size-5" aria-hidden="true" />
            </button>
        </div>
    </section>
</template>
