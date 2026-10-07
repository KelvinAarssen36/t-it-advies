<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import ScrollProgress from '@/components/site/ScrollProgress.vue';
import SiteFooter from '@/components/site/SiteFooter.vue';
import SiteHeader from '@/components/site/SiteHeader.vue';
import SiteIntro from '@/components/site/SiteIntro.vue';
import {
    alInBeeld,
    hermeetScroll,
    revealOnScroll,
    scrollNaar,
    splitReveal,
    startSmoothScroll,
} from '@/lib/motion';

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

/**
 * De sleutel van `<main>`: de taal én de pagina waar je bent.
 *
 * De taal stond er al, om de reden hierboven. De pagina kwam erbij voor de
 * overgang: Vue wisselt alleen iets in als de sleutel verandert, en zonder
 * de paginanaam erin blijft het bij navigeren hetzelfde element.
 *
 * Een klik op een anker ín de voorpagina verandert deze sleutel niet, en
 * dat is precies goed -- daar scrol je, daar wissel je niet van pagina.
 */
const paginaSleutel = computed(() => `${taal.value}|${usePage().component}`);

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

    /*
     * En dan pas opmeten. De pagina is net een paar schermen langer of
     * korter geworden, en Lenis en ScrollTrigger rekenen tot hier met de
     * hoogte van de vorige. Ná het aanmaken, zodat de verversing met de
     * triggers rekent die er nu zijn; zie `hermeetScroll`.
     */
    hermeetScroll();
};

/**
 * De nieuwe pagina komt binnen: scrollen en opnieuw scannen.
 *
 * **Dit is het moment waarop de nieuwe `<main>` in de DOM staat en nog
 * onzichtbaar is**, en dat is voor allebei de dingen hieronder precies het
 * goede moment.
 *
 * **Het scannen moet hier en niet bij `navigate`, en dat was een fout.**
 * De markup van een reveal draagt `opacity-0` als klasse; `revealOnScroll`
 * haalt die er met een inline stijl weer af. Wordt een element niet
 * gescand, dan blijft het dus onzichtbaar -- geen vertraagde animatie maar
 * verdwenen tekst.
 *
 * Zolang `<main>` geen overgang had, viel dat samen: Inertia wisselde de
 * inhoud om en een `nextTick` later stonden de nieuwe elementen er. Met
 * `mode="out-in"` is dat niet meer waar. `navigate` vuurt bij het
 * omwisselen, maar de nieuwe `main` wordt pas ingevoegd nadat de oude is
 * weggegaan -- een paar honderd milliseconden later. Die `nextTick` scande
 * dus de vertrekkende pagina, en de aankomende nooit. Op de voorpagina bleef
 * daardoor vrijwel alle tekst weg.
 *
 * **Eerst scrollen, dan twee beeldjes wachten, dan pas scannen.** `alInBeeld`
 * meet met `getBoundingClientRect`, en Lenis zet zijn scrollpositie pas door
 * in zijn eigen beeldje -- dat hangt aan de ticker van GSAP en niet aan een
 * losse `requestAnimationFrame`. Zou je meteen meten, dan rekent hij met de
 * plek waar je vandaan kwam en krijgt een blok dat allang in beeld staat een
 * scroll-trigger die nooit meer langskomt: dezelfde onzichtbare tekst, via
 * een andere weg. Twee beeldjes is hier dezelfde afspraak als in
 * `SiteHeader` en `Welcome.vue`, en valt ruim binnen het infaden.
 *
 * **Staat er een anker in het adres, dan scrollen we hier niet.** Dan komt
 * de bezoeker van een subpagina terug naar een onderdeel van de voorpagina,
 * en die pagina weet zelf waar dat staat; zie `Welcome.vue`. Zou het hier
 * óók gebeuren, dan springt hij eerst naar boven en daarna pas naar het
 * onderdeel.
 */
const voorBinnenkomst = (): void => {
    if (window.location.hash === '') {
        scrollNaar('top', { direct: true });
    }

    requestAnimationFrame(() => requestAnimationFrame(() => scanReveals()));
};

/**
 * Het vangnet: wat in beeld staat en tóch onzichtbaar is, zetten we aan.
 *
 * **Dit hoort niets te vinden.** Het staat er omdat de fout die het afvangt
 * een pagina zonder tekst oplevert, en dat is het ergste wat er met deze
 * site kan gebeuren. De markup van een reveal draagt `opacity-0` als klasse
 * en rekent erop dat JavaScript die er met een inline stijl weer af haalt;
 * mist dat om welke reden dan ook, dan blijft de tekst weg. Draait dit
 * vangnet, dan ploft er iets in beeld -- lelijk, maar leesbaar.
 *
 * **Alleen wat in beeld staat.** Een blok verderop op de pagina hóórt op
 * nul te staan: dat wacht op zijn scroll-trigger. En alleen ná de overgang,
 * want een blok dat net is gescand staat dan allang aan het opkomen en dus
 * boven nul.
 *
 * Vindt dit toch iets, dan is er iets mis met het moment waarop
 * `scanReveals` draait -- en niet met deze regel. Zie `voorBinnenkomst`.
 */
const naBinnenkomst = (): void => {
    const blokken = root.value?.querySelectorAll<HTMLElement>(
        '[data-reveal], [data-split]',
    );

    blokken?.forEach((blok) => {
        if (!alInBeeld(blok)) {
            return;
        }

        if (window.getComputedStyle(blok).opacity !== '0') {
            return;
        }

        blok.style.opacity = '1';

        /*
         * En merken, net als een gewone beweging doet. Anders pakt een
         * volgende herscan dit blok weer op en zet het terug op nul -- en
         * dan knippert precies wat dit vangnet net heeft gered.
         */
        blok.dataset.gezien = '';
    });
};

onMounted(() => {
    stopScroll = startSmoothScroll();
    scanReveals();

    /*
     * Deze layout blijft bij een Inertia-navigatie staan, dus `onMounted`
     * draait maar één keer.
     *
     * **Alleen voor een navigatie die de pagina niet wisselt.** Dat is
     * bijvoorbeeld het contactformulier dat zichzelf opnieuw laadt en zijn
     * bevestiging toont: dezelfde component, nieuwe inhoud, geen overgang
     * -- en dus ook geen `before-enter` om het werk te doen. Wisselt de
     * pagina wél, dan staat de nieuwe `main` hier nog niet en doet
     * `voorBinnenkomst` het straks.
     */
    let vorigeSleutel = paginaSleutel.value;

    stopListening = router.on('navigate', () => {
        const nu = paginaSleutel.value;
        const gewisseld = nu !== vorigeSleutel;

        vorigeSleutel = nu;

        if (gewisseld) {
            return;
        }

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

        <!--
            De overgang tussen twee pagina's.

            **Alleen `<main>` wisselt; de kop en de voettekst blijven
            staan.** Dat is wat continuïteit geeft: de navigatieknop die je
            net aanklikte blijft op zijn plek, en wat eromheen staat
            beweegt niet mee.

            **En het lost het knipperen op dat er al was.** Bij een
            navigatie wisselde Inertia de inhoud meteen om, waarna
            `scanReveals` een tel later elk blok op `opacity: 0` zette om
            het weer op te laten komen. De nieuwe pagina werd dus eerst
            volledig getekend, knipperde naar onzichtbaar en kwam daarna
            op. Nu begint deze `main` al op nul, dus dat omzetten valt
            binnen de onzichtbare fase. Eén beweging in plaats van een
            flits gevolgd door een beweging.

            `out-in` en niet tegelijk: twee pagina's die over elkaar heen
            liggen hebben allebei hun eigen hoogte, en dan springt de
            voettekst tijdens de overgang op en neer.
        -->
        <Transition
            name="brand-pagina"
            mode="out-in"
            @before-enter="voorBinnenkomst"
            @after-enter="naBinnenkomst"
        >
            <main :key="paginaSleutel">
                <slot />
            </main>
        </Transition>

        <SiteFooter />
    </div>
</template>
