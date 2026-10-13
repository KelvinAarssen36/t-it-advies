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
const pagina = computed(() => usePage().component);
const paginaSleutel = computed(() => `${taal.value}|${pagina.value}`);

let stopScroll: (() => void) | undefined;
let stopReveal: (() => void) | undefined;
let stopSplit: (() => void) | undefined;
let stopListening: (() => void) | undefined;

/**
 * Is er alleen van taal gewisseld, en waar stond de bezoeker toen?
 *
 * Een taalwissel verandert de sleutel hierboven en is voor Vue dus
 * hetzelfde als naar een andere pagina gaan. Voor de bezoeker is het dat
 * niet: hij was iets aan het lezen en wisselt van taal om datzelfde stuk
 * in het Nederlands of Engels te lezen. Naar boven springen gooit hem dan
 * weg van wat hij aan het doen was.
 *
 * **Dit hangt aan de reactiviteit van Vue en níet aan een gebeurtenis van
 * Inertia, en dat is de kern.** Inertia vuurt `navigate` alleen af als het
 * adres verandert (`if (!replace)` in zijn router). Een taalwissel is een
 * POST die terugkomt op dezelfde pagina, dus daar hoor je niets van. Een
 * `watch` op de sleutel vuurt wél, want die sleutel verandert gewoon.
 *
 * **En hier staat de scrollpositie nog goed.** Een watcher met de normale
 * `pre`-volgorde draait vóórdat Vue de pagina bijwerkt, dus de oude `main`
 * staat er nog in zijn volle hoogte. Een regel later is dat niet meer waar.
 */
let alleenDeTaal = false;
let positieBijVertrek = 0;
let vorigePagina = pagina.value;

/**
 * Waar de bezoeker stond, het liefst uitgedrukt in een onderdeel.
 *
 * **Dezelfde pixel is niet dezelfde plek.** De Engelse tekst is korter: op
 * de voorpagina scheelt de hele pagina ruim duizend pixels. Zet je de
 * bezoeker op dezelfde pixel terug, dan is alles bóven hem korter of langer
 * geworden en kijkt hij dus naar iets anders. In de browser gemeten, bij
 * gelijke `scrollY`: de werkwijze schoof 379 pixels en het contactblok
 * 1007.
 *
 * Daarom onthouden we welk onderdeel bovenaan het scherm stond en hoeveel
 * ervan al voorbij was. Dat onderdeel staat na de wissel op een andere
 * hoogte, maar het is hetzelfde onderdeel -- en dat is wat hij las.
 *
 * `section[id]` en niet elk element met een id: een formulierveld heeft er
 * ook een, en die van een uitklapper krijgt bij elke opbouw een nieuw
 * nummer.
 */
type Houvast = { id: string; afstand: number };

let houvast: Houvast | null = null;

const pakHouvast = (): Houvast | null => {
    let gevonden: Houvast | null = null;

    for (const sectie of document.querySelectorAll<HTMLElement>(
        'main section[id]',
    )) {
        const afstand = sectie.getBoundingClientRect().top;

        // Het onderdeel waar je ín zit: het laatste dat boven de bovenrand
        // van het scherm is begonnen. Is er nog niets begonnen, dan sta je
        // in de hero en valt er niets aan vast te houden.
        if (afstand <= 1 && (gevonden === null || afstand > gevonden.afstand)) {
            gevonden = { id: sectie.id, afstand };
        }
    }

    return gevonden;
};

watch(paginaSleutel, () => {
    alleenDeTaal = pagina.value === vorigePagina;
    vorigePagina = pagina.value;
    positieBijVertrek = window.scrollY;
    houvast = pakHouvast();
});

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
 *
 * **En een taalwissel is géén paginawissel.** Die komt hier wel langs --
 * de taal zit in de sleutel van `main` -- maar hoort de bezoeker te laten
 * staan waar hij stond. Zie het blok hieronder.
 */
const voorBinnenkomst = (): void => {
    /*
     * Alleen de taal is gewisseld: terugzetten waar de bezoeker stond.
     *
     * **Terugzetten en niet alleen de sprong overslaan.** De nieuwe pagina
     * heeft een andere hoogte -- de Engelse tekst is korter -- dus niets
     * doen laat de bezoeker achter op een pixel die ergens anders ligt dan
     * waar hij keek. `zetTerug` mikt daarom op het onderdeel waar hij in
     * zat en valt alleen terug op de pixel als dat er niet meer is.
     *
     * **Twee beeldjes wachten, en pas daarna springen.** `Welcome.vue`
     * springt bij het opkomen naar het anker in het adres als dat er
     * staat, en doet dat ook met twee beeldjes. Zou dit meteen gebeuren,
     * dan won dat ankersprongetje en stond de bezoeker alsnog ergens
     * anders dan waar hij was. Het valt allemaal binnen het infaden.
     */
    if (alleenDeTaal) {
        requestAnimationFrame(() =>
            requestAnimationFrame(() => {
                zetTerug();

                naHetScrollen();
            }),
        );

        return;
    }

    if (window.location.hash === '') {
        scrollNaar('top', { direct: true });
    }

    naHetScrollen();
};

/**
 * De bezoeker terugzetten waar hij stond.
 *
 * Eerst op het onderdeel dat hij aan het lezen was; bestaat dat niet meer
 * -- een subpagina heeft er geen, en een onderdeel kan in de andere taal
 * wegvallen -- dan alsnog op de pixel. Dat laatste is minder nauwkeurig,
 * maar het is beter dan bovenaan beginnen.
 */
const zetTerug = (): void => {
    const sectie =
        houvast === null ? null : document.getElementById(houvast.id);

    if (sectie !== null && houvast !== null) {
        /*
         * `scrollY + top` is de plek van het onderdeel in de hele pagina,
         * los van waar we nu toevallig staan. Daar gaat af wat er van dat
         * onderdeel al voorbij was, en dan staat het weer precies zo.
         */
        scrollNaar(
            window.scrollY +
                sectie.getBoundingClientRect().top -
                houvast.afstand,
            { direct: true },
        );

        return;
    }

    scrollNaar(positieBijVertrek, { direct: true });
};

/**
 * Opnieuw scannen zodra er gescrold is.
 *
 * Twee beeldjes ná het scrollen en niet ervoor, om de reden die bij
 * `voorBinnenkomst` staat: `alInBeeld` meet met `getBoundingClientRect`
 * en Lenis zet zijn positie pas in zijn eigen beeldje door.
 */
const naHetScrollen = (): void => {
    requestAnimationFrame(() =>
        requestAnimationFrame(() => {
            scanReveals();

            /*
             * En dan nog één keer bijtrekken, twee beeldjes later.
             *
             * `splitReveal` knipt een kop in losse regels en wikkelt die in
             * maskers. Dat maakt die koppen een paar pixels hoger, en dus
             * schuift alles eronder mee -- zonder dat er gescrold wordt.
             * Gemeten: het onderdeel waar de bezoeker in zat stond na het
             * terugzetten drie pixels van zijn plek, en daarna alsnog acht.
             *
             * De twee beeldjes zijn nodig omdat die nieuwe hoogte pas in
             * het volgende beeldje vaststaat; meteen opnieuw meten gaf
             * dezelfde fout terug. Het valt binnen het infaden, dus de
             * bezoeker ziet die correctie niet.
             *
             * Alleen bij een taalwissel: bij een echte paginawissel sta je
             * bovenaan en valt er niets bij te trekken.
             */
            if (alleenDeTaal) {
                requestAnimationFrame(() => requestAnimationFrame(zetTerug));
            }

            /*
             * En zeggen dat de pagina is gewisseld.
             *
             * **De kop blijft staan bij een navigatie** -- alleen `main`
             * wordt vervangen -- dus zijn scroll-triggers wijzen daarna
             * naar onderdelen die uit de DOM zijn gehaald. Ze vuren dan
             * nooit meer, en de markering onder het menu blijft staan waar
             * hij stond toen je wegging. Zie `SiteHeader`.
             *
             * Een gebeurtenis op `window` en geen gedeelde toestand: de
             * kop en deze layout weten verder niets van elkaar, en dat
             * hoort zo te blijven.
             */
            window.dispatchEvent(new CustomEvent('brand:pagina-gewisseld'));
        }),
    );
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
 * **Bij een taalwissel ligt die grens op de onderrand van het scherm en
 * niet op 85%.** `alInBeeld` rekent standaard tot 85% van de hoogte: een
 * blok dat daaronder begint is zichtbaar voor de bezoeker maar telt niet
 * mee, en wacht netjes op zijn scroll-trigger. Dat klopt als je bovenaan
 * begint. Bij een taalwissel blijf je staan waar je stond, en dan valt er
 * geregeld iets in die onderste strook -- in de browser gemeten bleef stap
 * vier van de werkwijze zo leeg achter. De bezoeker keek er een tel
 * geleden nog naar, dus hij hoort het te blijven zien.
 *
 * Vindt dit toch iets, dan is er iets mis met het moment waarop
 * `scanReveals` draait -- en niet met deze regel. Zie `voorBinnenkomst`.
 */
const naBinnenkomst = (): void => {
    const blokken = root.value?.querySelectorAll<HTMLElement>(
        '[data-reveal], [data-split]',
    );

    const grens = alleenDeTaal ? 1 : 0.85;

    blokken?.forEach((blok) => {
        if (!alInBeeld(blok, grens)) {
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
