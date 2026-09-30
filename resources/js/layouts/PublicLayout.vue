<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import ScrollProgress from '@/components/site/ScrollProgress.vue';
import SiteFooter from '@/components/site/SiteFooter.vue';
import SiteHeader from '@/components/site/SiteHeader.vue';
import SiteIntro from '@/components/site/SiteIntro.vue';
import { revealOnScroll, splitReveal, startSmoothScroll } from '@/lib/motion';

/**
 * De publieke site.
 *
 * Staat altijd in het donkere thema, ook als de bezoeker licht heeft
 * ingesteld. Midnight Navy is het fundament van de huisstijl; een lichte
 * variant van dezelfde pagina zou een tweede ontwerp zijn en geen
 * instelling. Het beheergedeelte volgt de voorkeur van de gebruiker wél.
 *
 * De animatielaag zit hier en niet in de pagina's zelf: smooth scrolling en
 * de scroll-reveals horen bij de hele site, en zo hoeft elke nieuwe pagina
 * de opruimfuncties niet opnieuw te regelen.
 *
 * Zie docs/architecture/frontend-en-animatie.md.
 */

const root = ref<HTMLElement | null>(null);

/**
 * De taal is de sleutel van `<main>`, en dat is geen detail.
 *
 * SplitText knipt een kop in losse regels en onthoudt de oorspronkelijke
 * opmaak om later te kunnen terugdraaien. Wisselt de bezoeker van taal,
 * dan vervangt Vue de tekst in diezelfde elementen -- en zet `revert()`
 * daarna de tekst van vóór de wissel terug. Je klikt dan op EN en leest
 * nog steeds Nederlands.
 *
 * Met een sleutel op de taal gooit Vue de hele pagina weg en bouwt hem
 * opnieuw op. De koppen zijn dan nieuwe elementen, de animaties beginnen
 * schoon, en de typmachine in de hero tikt de nieuwe zin gewoon opnieuw.
 */
const taal = computed(() => usePage().props.locale);

let stopScroll: (() => void) | undefined;
let stopReveal: (() => void) | undefined;
let stopSplit: (() => void) | undefined;
let stopListening: (() => void) | undefined;

/**
 * Twee soorten reveals, en dat is een bewust onderscheid.
 *
 * `[data-reveal]` schuift een heel blok omhoog: goed voor een kaart, een
 * knoppenrij of een alinea. `[data-split]` tilt tekst regel voor regel
 * achter een masker op, en dat is zwaarder werk -- SplitText moet meten,
 * en bij elke maatverandering opnieuw. Dat doe je op een kop, niet op
 * elke zin op de pagina.
 */
const scanReveals = () => {
    stopReveal?.();
    stopSplit?.();

    stopReveal = revealOnScroll('[data-reveal]', root.value);
    stopSplit = splitReveal('[data-split]', root.value);
};

/**
 * Opnieuw scannen zodra de taal verandert.
 *
 * De sleutel op `<main>` hierboven gooit bij een taalwissel de hele
 * pagina weg en bouwt hem opnieuw op. Dat zijn dus nieuwe elementen, met
 * hun `opacity-0` er weer op, en die moeten opnieuw worden opgehaald.
 *
 * Dit staat er náást de luisteraar op `navigate` hieronder, en niet in
 * plaats daarvan. Die luisteraar dekt het waarschijnlijk al af -- een
 * taalwissel is ook een navigatie -- maar deze herscan hangt aan precies
 * het ding dat de pagina heeft herbouwd, en dat is één schakel minder om
 * je zorgen over te maken.
 */
watch(taal, () => void nextTick(scanReveals));

onMounted(() => {
    stopScroll = startSmoothScroll();
    scanReveals();

    // Deze layout blijft bij een Inertia-navigatie staan, dus onMounted
    // draait maar één keer. Zonder deze herscan blijven de reveals van een
    // volgende pagina op opacity 0 hangen -- onzichtbare inhoud.
    stopListening = router.on('navigate', () => {
        void nextTick(scanReveals);
    });
});

onBeforeUnmount(() => {
    stopListening?.();
    stopSplit?.();
    stopReveal?.();
    stopScroll?.();
});
</script>

<template>
    <div
        id="top"
        ref="root"
        class="brand-dark-page dark min-h-screen bg-background text-foreground"
    >
        <!--
            De intro ligt over alles heen en haalt zichzelf weg. Hij staat
            als eerste in de boom, zodat hij er ook echt bovenop valt
            zonder dat er een z-index-wedstrijd voor nodig is.
        -->
        <SiteIntro />

        <ScrollProgress />

        <SiteHeader />

        <main :key="taal">
            <slot />
        </main>

        <SiteFooter />
    </div>
</template>
