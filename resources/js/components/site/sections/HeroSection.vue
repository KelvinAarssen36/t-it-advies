<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, useTemplateRef } from 'vue';
import LinkedinMerk from '@/components/site/LinkedinMerk.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import { Button } from '@/components/ui/button';
import { INTRO_DUUR, introSpeeltAf } from '@/lib/intro';
import {
    gsap,
    parallax,
    prefersReducedMotion,
    tekenRing,
    typMachine,
    volgDeMuis,
} from '@/lib/motion';
import type { SiteKop } from '@/types/ervaring';

/**
 * De kop van de landingspagina.
 *
 * Staat altijd bovenaan en is niet te verslepen; dat is geen instelling
 * maar wat dit onderdeel is. Zie App\Enums\PageSectionKey.
 *
 * De binnenkomstanimatie staat hier en niet in de layout, omdat hij bij
 * déze sectie hoort: de rest van de pagina komt binnen door te scrollen,
 * deze is er al als je aankomt.
 *
 * **De scène bestaat uit drie lagen die los van elkaar bewegen:** de
 * achtergrond, het portret, en de tekst. Bij het scrollen loopt de tekst
 * het snelst weg en de achtergrond het langzaamst, en daardoor lijkt er
 * diepte te zitten tussen dingen die allemaal even plat zijn. Meer dan
 * een paar tientallen pixels verschil moet het niet worden -- parallax
 * die opvalt is parallax die misselijk maakt.
 */
const props = defineProps<{
    /**
     * Welke onderdelen er verder op de pagina staan.
     *
     * Alleen om te bepalen of de knop naar de diensten ergens heen gaat.
     * Zet de klant dat onderdeel uit, dan zou die knop naar een anker
     * springen dat niet bestaat -- en dan gebeurt er bij het klikken niets,
     * wat er van buiten uitziet als een kapotte website.
     */
    sections: string[];

    /**
     * De drie teksten, uit de database en niet meer uit dit bestand.
     *
     * Ze stonden hier hardgecodeerd, en daarmee was dit het enige stuk
     * van de voorpagina dat de klant níet kon aanpassen -- terwijl het
     * het eerste is wat iedereen leest. De keuze tussen Nederlands en
     * Engels is op de server al gemaakt; zie App\Models\HeroHeading.
     */
    heading: SiteKop;
}>();

// Vier maten van dezelfde achtergrond; de browser kiest op schermbreedte en
// pixeldichtheid. Zie docs/architecture/frontend-en-animatie.md.
const heroSrcset = [
    '/images/hero-achtergrond-960.webp 960w',
    '/images/hero-achtergrond-1600.webp 1600w',
    '/images/hero-achtergrond-2560.webp 2560w',
    '/images/hero-achtergrond-3840.webp 3840w',
].join(', ');

/*
 * Hetzelfde verhaal voor het portret. Het origineel is een PNG van bijna
 * twee megabyte met doorzichtige achtergrond; dit zijn de WebP-varianten
 * daarvan, met die doorzichtigheid intact. De PNG blijft in de map staan
 * als bron, maar komt niet op de website.
 */
const portretSrcset = [
    '/images/persoon-medaillon-320.webp 320w',
    '/images/persoon-medaillon-480.webp 480w',
    '/images/persoon-medaillon-640.webp 640w',
    '/images/persoon-medaillon-960.webp 960w',
].join(', ');

/**
 * De naam onder de titel, op een telefoon.
 *
 * Hij komt uit `config/security.php`, waar hij als het account van de
 * eigenaar al vastligt. Laat je hem leeg, dan toont het visitekaartje
 * alleen de foto en de functie -- ook dat oogt af.
 *
 * Een naam wordt niet vertaald; de functie eronder wel, en die staat
 * daarom in het sjabloon in een `$t()`. Zodra de kop een module wordt
 * komen ze allebei uit de database, samen met de titel en de twee
 * knoppen; zie docs/openstaand.md.
 */
const NAAM = 'Erik Aarssen';

/**
 * Het LinkedIn-adres, uit dezelfde bron als het blok onderaan.
 *
 * Gedeeld via Inertia en niet hier neergezet: twee knoppen die naar
 * hetzelfde profiel wijzen horen niet uit elkaar te kunnen lopen. Zie
 * config/site.php.
 */
const linkedin = computed(
    () => (usePage().props.linkedin as string | undefined) ?? '',
);

const gloed = useTemplateRef<HTMLElement>('gloed');
const portret = useTemplateRef<HTMLElement>('portret');
const tekst = useTemplateRef<HTMLElement>('tekst');
const kop = useTemplateRef<HTMLElement>('kop');
const ring = useTemplateRef<SVGCircleElement>('ring');
const baan = useTemplateRef<SVGGElement>('baan');

let intro: gsap.core.Timeline | undefined;
const opruimers: Array<() => void> = [];

/**
 * De typmachine staat apart van de rest van de opruimers.
 *
 * Hij knipt de kop in losse letters en zet die bij het opruimen terug.
 * Dat moet gebeuren vóórdat iemand anders in dat element schrijft, dus
 * het is de moeite waard om hem los bij de hand te hebben. Wisselt de
 * bezoeker van taal, dan gooit PublicLayout deze hele sectie weg en
 * bouwt hem opnieuw op; zie de toelichting bij `taal` daar.
 */
let stopTypen: (() => void) | undefined;

onMounted(() => {
    // Bij reduced motion zetten we de elementen meteen op hun eindtoestand.
    // De animatie overslaan zou ze onzichtbaar laten; zie motion.ts.
    if (prefersReducedMotion()) {
        gsap.set('[data-intro]', { opacity: 1, y: 0 });
        gsap.set([kop.value, portret.value], { opacity: 1, x: 0 });

        return;
    }

    /*
     * Het portret komt van opzij binnen en de tekst van onderen. Twee
     * richtingen, en dat is met opzet: kwamen ze allebei van onderen, dan
     * is het één blok dat omhoogschuift in plaats van een scène die zich
     * opbouwt.
     *
     * Het portret begint een fractie eerder dan de tekst. Je oog gaat
     * eerst naar het gezicht en leest daarna; die volgorde omdraaien
     * levert een kop op die je al gelezen hebt voordat het beeld er is.
     */
    /*
     * Speelt de merkintro, dan begint de hero pas als die weg is. De
     * vraag wordt hier opnieuw gesteld en niet doorgegeven: lib/intro.ts
     * legt het antwoord bij de eerste keer vast, dus de introlaag en deze
     * sectie krijgen gegarandeerd hetzelfde te horen -- ook al mounten ze
     * op een ander moment. Zou de introlaag helemaal niet verschijnen,
     * dan is dit hoogstens een korte vertraging en geen tekst die nooit
     * komt.
     */
    const wachten = introSpeeltAf() ? INTRO_DUUR - 0.25 : 0;

    intro = gsap
        .timeline({ delay: wachten, defaults: { ease: 'power3.out' } })
        .fromTo(
            portret.value,
            { opacity: 0, x: 40, scale: 1.04 },
            { opacity: 1, x: 0, scale: 1, duration: 1.2 },
            0,
        )
        .fromTo(
            '[data-intro]',
            { opacity: 0, y: 28 },
            { opacity: 1, y: 0, duration: 0.9, stagger: 0.12 },
            0.15,
        );

    /*
     * De kop wordt letter voor letter ingetikt, en de ring om het
     * portret tekent zich ondertussen. Allebei los van de tijdlijn
     * hierboven: ze hebben hun eigen opruimfunctie en hun eigen
     * begintoestand.
     *
     * De kop draagt daarom geen `data-intro`. Twee systemen die om
     * beurten in dezelfde doorzichtigheid schrijven, is precies hoe je
     * een knipperende kop krijgt.
     */
    if (kop.value !== null) {
        stopTypen = typMachine(kop.value, { wachten: wachten + 0.12 });
    }

    if (ring.value !== null) {
        opruimers.push(
            tekenRing(ring.value, baan.value, { wachten: wachten + 0.2 }),
        );
    }

    if (gloed.value !== null) {
        opruimers.push(volgDeMuis(gloed.value));
    }

    /*
     * De drie lagen lopen uiteen zodra je gaat scrollen. De getallen zijn
     * klein en het verschil ertussen is wat het werk doet: de achtergrond
     * blijft het meest achter, de tekst loopt het hardst weg.
     *
     * **Alleen op een breed scherm**, en dat is geen smaakkwestie. Een
     * element dat permanent met de scroll meebeweegt staat permanent in
     * een bewegende compositielaag, en die rastert de browser in lagere
     * resolutie; de scherpte komt pas terug als hij stilstaat. Bij het
     * portret zag je dat als een foto die de hele tijd wazig is. Op een
     * telefoon kost de parallax dus scherpte en levert hij, op dat
     * formaat, nauwelijks diepte op.
     *
     * `gsap.matchMedia` zorgt dat het meegaat als je je telefoon draait,
     * en draait bij het passeren van de grens netjes terug wat het had
     * gezet.
     */
    const mm = gsap.matchMedia();

    mm.add('(min-width: 50rem)', () => {
        const stoppers = [
            portret.value === null
                ? () => {}
                : parallax(portret.value, { afstand: 90 }),
            tekst.value === null
                ? () => {}
                : parallax(tekst.value, { afstand: -60 }),
        ];

        return () => stoppers.forEach((stop) => stop());
    });

    opruimers.push(() => mm.revert());
});

onBeforeUnmount(() => {
    intro?.kill();
    stopTypen?.();
    opruimers.forEach((opruimen) => opruimen());
});
</script>

<template>
    <SiteSection
        tone="gradient"
        image="/images/hero-achtergrond-1600.webp"
        :image-srcset="heroSrcset"
        priority
    >
        <!--
            De gloed achter de scène. Hij ligt achter alles en vangt niets
            aan muisgebeurtenissen af, vandaar `pointer-events-none` en
            `aria-hidden`. De verschuiving met de cursor staat in CSS en
            leest `--muis-x` en `--muis-y`; JavaScript bepaalt alleen waar
            de muis is.
        -->
        <div
            ref="gloed"
            class="brand-hero-gloed pointer-events-none"
            aria-hidden="true"
        />

        <div class="brand-hero-raster py-8 sm:py-16">
            <div ref="tekst" class="min-w-0">
                <!--
                    Op een telefoon staat de functie in het visitekaartje
                    onder de titel, dus daar zou dit opschrift hem
                    herhalen.
                -->
                <p
                    data-intro
                    class="mb-4 hidden text-sm tracking-[0.2em] text-brand-cyan uppercase opacity-0 tablet:block"
                >
                    {{ props.heading.opschrift }}
                </p>

                <h1
                    ref="kop"
                    class="max-w-3xl text-4xl leading-[1.05] font-semibold tracking-tight text-pretty text-white opacity-0 sm:text-6xl"
                >
                    {{ props.heading.titel }}
                </h1>

                <!--
                    Het visitekaartje: alleen op een telefoon.

                    Daar stond de grote cirkel onder de knoppen, en die
                    kostte een kwart schermhoogte voor iets wat je pas
                    zag als je er al voorbij was. Zo krijgt de foto een
                    reden om er te staan -- het is niet een plaatje maar
                    wie het doet -- en kost hij een strook in plaats van
                    een blok.

                    Vanaf een tablet is hij weg: daar staat het
                    medaillon ernaast, en dan zou dit hetzelfde gezicht
                    twee keer tonen.
                -->
                <div data-intro class="brand-visitekaartje opacity-0">
                    <img
                        src="/images/persoon-medaillon-320.webp"
                        alt=""
                        width="320"
                        height="320"
                        decoding="async"
                        fetchpriority="high"
                    />

                    <span class="min-w-0">
                        <span v-if="NAAM" class="brand-visitekaartje-naam">
                            {{ NAAM }}
                        </span>
                        <!--
                            Hetzelfde opschrift als hierboven, en dat is
                            precies de bedoeling: op een telefoon staat
                            het hier en op een breder scherm daar. De
                            klant past het op één plek aan.
                        -->
                        <span class="brand-visitekaartje-functie">
                            {{ props.heading.opschrift }}
                        </span>
                    </span>
                </div>

                <!--
                    De zin eronder is optioneel. Laat de klant hem leeg,
                    dan staat er gewoon niets in plaats van een lege regel
                    die de knoppen omlaag duwt.
                -->
                <p
                    v-if="props.heading.inleiding"
                    data-intro
                    class="mt-6 max-w-xl text-lg text-pretty text-muted-foreground opacity-0"
                >
                    {{ props.heading.inleiding }}
                </p>

                <div data-intro class="mt-10 flex flex-wrap gap-3 opacity-0">
                    <Button
                        v-if="props.sections.includes('contact')"
                        as="a"
                        href="#contact"
                        size="lg"
                        variant="brand"
                    >
                        {{ $t('Neem contact op') }}
                    </Button>
                    <Button
                        v-if="props.sections.includes('diensten')"
                        as="a"
                        href="#diensten"
                        size="lg"
                        variant="brand-outline"
                    >
                        {{ $t('Bekijk de diensten') }}
                    </Button>

                    <!--
                        Het LinkedIn-knopje. Bewust klein en zonder tekst:
                        dit is de derde keuze op deze regel, en twee
                        knoppen met woorden plus een derde met woorden is
                        geen keuze meer maar een menu.

                        Het merkteken alleen is genoeg -- dat herkent
                        iedereen -- en de naam staat in het `aria-label`
                        voor wie het niet ziet. Bij hover draait het rond
                        en kleurt het mee; zie `brand-merkknop` in
                        app.css.

                        Nieuw tabblad, net als in het blok onderaan: dit
                        is een ander domein en een bezoeker hoort zijn
                        plek op deze pagina niet kwijt te raken.
                    -->
                    <a
                        v-if="linkedin"
                        :href="linkedin"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="brand-merkknop"
                        :aria-label="$t('Bekijk het LinkedIn-profiel')"
                    >
                        <LinkedinMerk />
                    </a>
                </div>
            </div>

            <!--
                Het portret als rond medaillon.

                Het is één samengesteld beeld: het oog-embleem uit de
                huisstijl met de uitgeknipte persoon erop, in dezelfde
                uitsnede gebakken. Twee losse lagen in CSS zou ook kunnen,
                maar dan verschuift de uitlijning per schermbreedte en
                staat zijn hoofd de ene keer wel en de andere keer niet
                voor het oog.

                De ring eromheen is een SVG en geen `border`, want een
                rand kun je niet laten tekenen. Hij begint op nul en
                trekt zich in ruim een seconde rond; zie tekenRing() in
                motion.ts.

                Leeg `alt`: de naam en het bedrijf staan er al in tekst
                naast, en een schermlezer heeft aan "een foto van een man
                met een bril" niets.
            -->
            <div class="brand-hero-portret hidden tablet:block">
                <div ref="portret" class="brand-medaillon opacity-0">
                    <img
                        src="/images/persoon-medaillon-480.webp"
                        :srcset="portretSrcset"
                        sizes="(min-width: 64rem) 28rem, (min-width: 50rem) 18rem, min(62vw, 16rem)"
                        alt=""
                        width="960"
                        height="960"
                        decoding="async"
                        fetchpriority="high"
                    />

                    <svg
                        class="brand-medaillon-ring"
                        viewBox="0 0 100 100"
                        aria-hidden="true"
                    >
                        <!-- Het vaste kader: tekent zich één keer. -->
                        <circle
                            ref="ring"
                            cx="50"
                            cy="50"
                            r="48.5"
                            fill="none"
                            stroke="url(#medaillon-verloop)"
                            stroke-width="1"
                            vector-effect="non-scaling-stroke"
                        />

                        <!--
                            De lichtboog die blijft ronddraaien. De groep
                            eromheen is er voor de rotatie: een cirkel
                            draait anders om de hoek van het tekenvlak en
                            niet om zijn eigen midden.
                        -->
                        <g ref="baan" class="brand-medaillon-baan">
                            <circle
                                cx="50"
                                cy="50"
                                r="48.5"
                                fill="none"
                                stroke="#13c7f3"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-dasharray="26 279"
                                vector-effect="non-scaling-stroke"
                            />
                            <circle cx="50" cy="1.5" r="1.8" fill="#a9e8fa" />
                        </g>

                        <defs>
                            <linearGradient
                                id="medaillon-verloop"
                                x1="0"
                                y1="0"
                                x2="1"
                                y2="1"
                            >
                                <stop offset="0%" stop-color="#13c7f3" />
                                <stop offset="55%" stop-color="#0787e8" />
                                <stop
                                    offset="100%"
                                    stop-color="#13c7f3"
                                    stop-opacity="0.25"
                                />
                            </linearGradient>
                        </defs>
                    </svg>
                </div>
            </div>
        </div>
    </SiteSection>
</template>
