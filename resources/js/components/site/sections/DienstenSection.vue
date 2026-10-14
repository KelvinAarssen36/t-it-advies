<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ArrowRight, ChevronLeft, ChevronRight } from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useTemplateRef,
    watch,
} from 'vue';
import DienstIcoon from '@/components/site/DienstIcoon.vue';
import DienstOnderdelen from '@/components/site/DienstOnderdelen.vue';
import DienstVenster from '@/components/site/DienstVenster.vue';
import FeatureCard from '@/components/site/FeatureCard.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import { Button } from '@/components/ui/button';
import {
    alInBeeld,
    gsap,
    kaartenBinnen,
    prefersReducedMotion,
    ScrollTrigger,
    scrollNaar,
    spotlicht,
} from '@/lib/motion';
import type { DienstOpDeSite, DienstenKop } from '@/types/diensten';
import type { SectieProps } from '@/types/secties';

/**
 * De diensten: een index met een lens erop.
 *
 * **Op een breed scherm is het een index en geen raster kaarten.** Links
 * staan de namen van de diensten groot onder elkaar; rechts ligt één glazen
 * paneel met het verhaal van de dienst die je aanwijst. Zo leest het als de
 * inhoudsopgave van een praktijk in plaats van als zes gelijke tegels -- en
 * je ziet in één oogopslag wát er is, en daarna pas hoe.
 *
 * **Het paneel volgt de dienst die je aanwijst, aantikt of met de tabtoets
 * bereikt**; standaard de eerste. Aanwijzen heeft een korte drempel, zodat
 * een muis die schuin over de lijst trekt het paneel niet laat flakkeren.
 *
 * **Alle panelen van de pagina staan op elkaar gestapeld**, en alleen het
 * actieve is zichtbaar. Daardoor is het blok altijd zo hoog als het langste
 * verhaal en springt de pagina eronder niet als je van dienst wisselt. De
 * andere panelen zijn `inert`: niet te bereiken en niet voor te lezen.
 *
 * **Op een telefoon zijn het gewoon kaarten**, onder elkaar, met de
 * samenvatting zichtbaar. Aanwijzen bestaat daar niet, en een paneel naast
 * een lijst past er niet. Een kaart is een knop als er een heel verhaal
 * achter zit (dat opent DienstVenster), anders een `<article>`; zie
 * FeatureCard.
 *
 * **Er wordt gebladerd**, zes per pagina op een breed scherm en drie op een
 * telefoon, zodat het blok niet met elke dienst langer wordt.
 *
 * `tone` en `divided` komen van buiten; zie Welcome.vue.
 *
 * Zie docs/architecture/modules/diensten.md.
 */
defineProps<SectieProps>();

const page = usePage();

const diensten = computed<DienstOpDeSite[]>(
    () => (page.props.services as DienstOpDeSite[] | undefined) ?? [],
);

const kop = computed<DienstenKop | null>(
    () => (page.props.serviceHeading as DienstenKop | undefined) ?? null,
);

/* --- Breed of smal ----------------------------------------------------- */

/**
 * De grens tussen index en kaarten: `tablet:` in de CSS.
 *
 * Een `matchMedia`-luisteraar en geen `resize`: die vuurt bij elke pixel,
 * deze alleen als je de grens overgaat.
 */
const GRENS = '(min-width: 50rem)';

const breed = ref(
    typeof window === 'undefined' ? true : window.matchMedia(GRENS).matches,
);

/* --- Bladeren ---------------------------------------------------------- */

/**
 * Hoeveel diensten er per pagina staan.
 *
 * Zes op een breed scherm: in de index is een dienst één regel en geen
 * kaart, dus er past meer in één blik dan vroeger -- zes namen onder
 * elkaar is nog een overzicht, acht wordt een lijst die je moet lezen.
 * Op een telefoon drie, want daar is elke kaart een halve schermhoogte en
 * horen de knopjes om verder te bladeren nog in beeld te komen.
 */
const BREED = 6;
const SMAL = 3;

const perPagina = computed(() => (breed.value ? BREED : SMAL));
const pagina = ref(1);

const paginas = computed(() =>
    Math.max(1, Math.ceil(diensten.value.length / perPagina.value)),
);

const zichtbaar = computed(() =>
    diensten.value.slice(
        (pagina.value - 1) * perPagina.value,
        pagina.value * perPagina.value,
    ),
);

/**
 * Op een smal scherm geen nummers maar "2 / 4": een rij knopjes die op
 * twee regels valt is erger dan niet rechtstreeks naar pagina drie kunnen.
 */
const compact = computed(() => !breed.value);

const nummers = computed<(number | '…')[]>(() => {
    const totaal = paginas.value;

    if (totaal <= 7) {
        return Array.from({ length: totaal }, (_, index) => index + 1);
    }

    const rond = [1, totaal, pagina.value - 1, pagina.value, pagina.value + 1]
        .filter((nummer) => nummer >= 1 && nummer <= totaal)
        .sort((a, b) => a - b);

    const uitkomst: (number | '…')[] = [];

    [...new Set(rond)].forEach((nummer, index, lijst) => {
        const vorige = lijst[index - 1];

        if (vorige !== undefined && nummer - vorige > 1) {
            uitkomst.push('…');
        }

        uitkomst.push(nummer);
    });

    return uitkomst;
});

/**
 * Eén dienst in totaal: dan geen index naast het paneel.
 *
 * Een inhoudsopgave van één regel is geen inhoudsopgave, en de naam zou
 * dan twee keer naast elkaar staan. Het paneel krijgt de volle breedte.
 */
const enkel = computed(() => diensten.value.length === 1);

/* --- De dienst onder de lens ------------------------------------------- */

const actiefId = ref<number | null>(null);

const actief = computed<DienstOpDeSite | undefined>(
    () =>
        zichtbaar.value.find((dienst) => dienst.id === actiefId.value) ??
        zichtbaar.value[0],
);

let drempel: number | undefined;

const kies = (dienst: DienstOpDeSite): void => {
    window.clearTimeout(drempel);
    actiefId.value = dienst.id;
};

/**
 * Aanwijzen met een korte drempel.
 *
 * Wie met de muis van de eerste naar de vijfde dienst trekt, komt onderweg
 * langs drie andere. Zonder drempel wisselt het paneel dan vier keer in een
 * halve seconde; met negentig milliseconden blijft het staan tot je stilhoudt.
 */
/*
 * **Alleen als de muis echt beweegt.** Scrol je met de muis stil boven de
 * index, dan schuiven de namen onder de wijzer door, en de browser meldt
 * dat als "de muis komt binnen" -- ook al heeft niemand hem aangeraakt.
 * Zonder deze grens wisselde het paneel tijdens het scrollen vier keer van
 * dienst (gemeten). Een echte beweging verandert de plek van de wijzer;
 * doorschuivende inhoud niet.
 */
let laatsteWijzer = { x: -1, y: -1 };

const wijzerBewoog = (gebeurtenis: MouseEvent): boolean => {
    const bewogen =
        gebeurtenis.clientX !== laatsteWijzer.x ||
        gebeurtenis.clientY !== laatsteWijzer.y;

    laatsteWijzer = { x: gebeurtenis.clientX, y: gebeurtenis.clientY };

    return bewogen;
};

const wijsAan = (dienst: DienstOpDeSite, gebeurtenis: MouseEvent): void => {
    if (!wijzerBewoog(gebeurtenis)) {
        return;
    }

    window.clearTimeout(drempel);
    drempel = window.setTimeout(() => {
        actiefId.value = dienst.id;
    }, 90);
};

const laatLos = (): void => window.clearTimeout(drempel);

/* --- De lens: het lichte stuk rail naast de actieve naam --------------- */

const index = useTemplateRef<HTMLElement>('index');

const lens = ref({ y: 0, schaal: 0 });

/**
 * De lens meten: waar staat de actieve naam, en hoe hoog is hij.
 *
 * In pixels van de lijst zelf, met `offsetTop` en niet met
 * `getBoundingClientRect`: die laatste verschuift tijdens het scrollen en
 * tijdens de binnenkomstanimatie, `offsetTop` niet. De hoogte gaat als
 * schaal op een rail van honderd pixels, zodat er alleen een transform
 * verandert en nooit een hoogte.
 */
const meetLens = (): void => {
    const knop = index.value?.querySelector<HTMLElement>('[aria-current]');
    const rij = knop?.closest('li');

    if (!knop || !rij) {
        lens.value = { y: 0, schaal: 0 };

        return;
    }

    /*
     * Via de rij en niet via de knop zelf: de binnenkomstanimatie zet een
     * transform op de rij, en dan wordt die rij de `offsetParent` van de
     * knop. `knop.offsetTop` is dan altijd nul.
     */
    lens.value = { y: rij.offsetTop, schaal: rij.offsetHeight / 100 };
};

let kijker: ResizeObserver | undefined;

watch(actief, () => void nextTick(meetLens));

/* --- Binnenkomen: één beweging, van boven naar beneden ---------------- */

/*
 * **Breed komt de sectie binnen als één beweging**, en die loopt in één
 * richting: het licht valt van boven naar beneden. De rail van de index
 * tekent zich omlaag, de namen komen er gestaffeld achteraan omhoog, het
 * paneel rolt tegelijk van boven open, en daarna komt zijn inhoud op en
 * lichten de onderdelen een voor een aan. Ruim een seconde in totaal, en
 * eenmalig: de sectie komt één keer binnen, niet bij elke scroll.
 *
 * **Hier stonden drie bewegingen die elk hun eigen gang gingen**, en dat
 * was het slordige: de namen schoven uit een masker, de pictogrammen
 * ploften er met een schaal achteraan, de rail liep met de scroll mee en
 * het paneel ging pas daarna open als een cirkel vanuit het midden -- met
 * halverwege een losse ronde vlek en een scheef afgesneden hoek. Drie
 * tempo's, twee richtingen en een halve sectie leeg.
 *
 * **Smal komen de kaarten één voor één binnen** (`kaartenBinnen`), zoals
 * voorheen.
 *
 * Bij bladeren komen alleen de nieuwe namen op; het paneel staat al open.
 * Bij een wissel tussen breed en smal begint alles opnieuw, want dan staan
 * er andere elementen.
 */
const sectie = useTemplateRef<HTMLElement>('sectie');
const paneel = useTemplateRef<HTMLElement>('paneel');
const spoor = useTemplateRef<HTMLElement>('spoor');
const stapel = useTemplateRef<HTMLElement>('stapel');
const indexOpruimers: Array<() => void> = [];
const paneelOpruimers: Array<() => void> = [];

/** Of de sectie al een keer is binnengekomen (dan alleen nog bladeren). */
let binnengekomen = false;

const kaarten = (): HTMLElement[] =>
    Array.from(
        sectie.value?.querySelectorAll<HTMLElement>('[data-kaart]') ?? [],
    );

/**
 * Het licht op de onderdelen aanzetten. Pas als de sectie in beeld komt:
 * stond het er vanaf het laden, dan speelde de eerste dienst zijn opkomst
 * af ver buiten beeld. `--licht-start` laat het licht op de onderdelen
 * wachten tot het paneel bij ze is opengerold; bij een latere wissel staat
 * hij op nul.
 */
const zetLicht = (wacht: number): void => {
    if (stapel.value === null) {
        return;
    }

    stapel.value.style.setProperty('--licht-start', `${wacht}s`);
    stapel.value.setAttribute('data-licht', '');
};

/** De namen, na bladeren: alleen de rijen, kort en gestaffeld. */
const rijenOp = (): (() => void) => {
    const rijen = Array.from(
        index.value?.querySelectorAll<HTMLElement>(':scope > li') ?? [],
    );

    if (rijen.length === 0 || prefersReducedMotion()) {
        return () => {};
    }

    const tween = gsap.fromTo(
        rijen,
        { opacity: 0, y: 12 },
        {
            opacity: 1,
            y: 0,
            duration: 0.5,
            stagger: 0.05,
            ease: 'expo.out',
            clearProps: 'opacity,transform',
        },
    );

    return () => {
        tween.kill();
        gsap.set(rijen, { clearProps: 'opacity,transform' });
    };
};

/**
 * De eerste binnenkomst, breed. Eén tijdlijn, zodat alles op elkaar wacht
 * in plaats van op zijn eigen trigger: wie snel scrolt ziet dezelfde
 * volgorde als wie langzaam scrolt, alleen eerder.
 */
const binnenkomst = (): (() => void) => {
    const indexEl = index.value;
    const paneelEl = paneel.value;
    const blokEl = sectie.value?.querySelector<HTMLElement>(
        '.brand-site-diensten',
    );

    if (blokEl == null || paneelEl === null) {
        return () => {};
    }

    if (prefersReducedMotion()) {
        binnengekomen = true;
        zetLicht(0);

        return () => {};
    }

    const rijen = Array.from(
        indexEl?.querySelectorAll<HTMLElement>(':scope > li') ?? [],
    );
    const lensEl = indexEl?.querySelector<HTMLElement>(
        '.brand-site-diensten-lens',
    );
    const blad = paneelEl.querySelector<HTMLElement>(
        '.brand-site-diensten-blad[data-actief]',
    );
    const delen = Array.from(
        blad?.querySelectorAll<HTMLElement>(':scope > [data-deel]') ?? [],
    );
    const elementen = [
        spoor.value,
        lensEl,
        paneelEl,
        ...rijen,
        ...delen,
    ].filter((el): el is HTMLElement => el != null);

    const tijdlijn = gsap.timeline({
        paused: true,
        defaults: { ease: 'expo.out' },
        onStart: () => zetLicht(0.5),
        onComplete: () => {
            gsap.set(elementen, { clearProps: 'opacity,transform,clipPath' });
            stapel.value?.style.removeProperty('--licht-start');
        },
    });

    if (spoor.value !== null) {
        tijdlijn.fromTo(
            spoor.value,
            { scaleY: 0 },
            { scaleY: 1, duration: 0.95, ease: 'power2.inOut' },
            0,
        );
    }

    tijdlijn
        .fromTo(
            rijen,
            { opacity: 0, y: 14 },
            { opacity: 1, y: 0, duration: 0.6, stagger: 0.06 },
            0.05,
        )
        .fromTo(
            paneelEl,
            { clipPath: 'inset(0% 0% 100% 0% round 1rem)', y: 18 },
            {
                clipPath: 'inset(0% 0% 0% 0% round 1rem)',
                y: 0,
                duration: 0.9,
                ease: 'expo.inOut',
            },
            0.08,
        )
        .fromTo(
            delen,
            { opacity: 0, y: 10 },
            { opacity: 1, y: 0, duration: 0.55, stagger: 0.06 },
            0.42,
        );

    if (lensEl != null) {
        tijdlijn.fromTo(
            lensEl,
            { opacity: 0 },
            { opacity: 1, duration: 0.3, ease: 'power1.out' },
            0.4,
        );
    }

    /*
     * De beginstand meteen zetten, anders staat alles een beeldje lang in
     * zijn eindstand voordat de trigger hem terugzet: een flits.
     */
    tijdlijn.progress(0);

    const speel = (): void => {
        binnengekomen = true;
        tijdlijn.play();
    };

    let trigger: ScrollTrigger | undefined;

    if (alInBeeld(blokEl, 0.82)) {
        speel();
    } else {
        trigger = ScrollTrigger.create({
            trigger: blokEl,
            start: 'top 82%',
            once: true,
            onEnter: speel,
        });
    }

    return () => {
        trigger?.kill();
        tijdlijn.kill();
        gsap.set(elementen, { clearProps: 'opacity,transform,clipPath' });
    };
};

/**
 * De index (breed) of de kaarten (smal) laten binnenkomen.
 *
 * `gebladerd` alleen na bladeren: dan komen de nieuwe namen op. Bij het
 * laden doet `binnenkomst()` dat al, en twee bewegingen op dezelfde rij
 * laten hem knipperen.
 */
const bouwIndex = (gebladerd: boolean): void => {
    indexOpruimers.splice(0).forEach((opruimen) => opruimen());

    if (!breed.value) {
        indexOpruimers.push(kaartenBinnen(kaarten()));

        return;
    }

    if (gebladerd && binnengekomen) {
        indexOpruimers.push(rijenOp());
    }
};

/** Het paneel: de eerste binnenkomst, en het spotlicht onder de muis. */
const bouwPaneel = (): void => {
    paneelOpruimers.splice(0).forEach((opruimen) => opruimen());

    if (!breed.value || paneel.value === null) {
        return;
    }

    if (!binnengekomen) {
        paneelOpruimers.push(binnenkomst());
    } else {
        zetLicht(0);
    }

    paneelOpruimers.push(spotlicht([paneel.value]));
};

const herstel = (gebladerd = false): void => {
    bouwIndex(gebladerd);
    meetLens();
    kijkNaarIndex();
};

const kijkNaarIndex = (): void => {
    kijker?.disconnect();

    if (index.value && typeof ResizeObserver !== 'undefined') {
        kijker = new ResizeObserver(meetLens);
        kijker.observe(index.value);
    }
};

const naarPagina = async (nieuw: number): Promise<void> => {
    if (nieuw === pagina.value || nieuw < 1 || nieuw > paginas.value) {
        return;
    }

    pagina.value = nieuw;
    actiefId.value = null;

    await nextTick();

    herstel(true);
    scrollNaar('diensten');
};

/* --- Het venster met het hele verhaal --------------------------------- */

const venster = ref(false);
const gekozen = ref<DienstOpDeSite | null>(null);

const openen = (dienst: DienstOpDeSite): void => {
    gekozen.value = dienst;
    venster.value = true;
};

/*
 * Over de grens: andere opbouw, andere paginagrootte. Het paginanummer
 * wordt begrensd -- anders sta je op pagina 3 van 2 en is het blok leeg.
 * Een benoemde functie, zodat hij bij het opruimen los kan.
 */
let breedte: MediaQueryList | undefined;

const opBreedte = (): void => {
    breed.value = window.matchMedia(GRENS).matches;
    pagina.value = Math.min(pagina.value, paginas.value);

    void nextTick(() => {
        bouwPaneel();
        herstel();
    });
};

onMounted(async () => {
    breedte = window.matchMedia(GRENS);
    breedte.addEventListener('change', opBreedte);
    breed.value = breedte.matches;

    await nextTick();

    bouwPaneel();
    herstel();
});

onBeforeUnmount(() => {
    breedte?.removeEventListener('change', opBreedte);
    window.clearTimeout(drempel);
    kijker?.disconnect();
    indexOpruimers.forEach((opruimen) => opruimen());
    paneelOpruimers.forEach((opruimen) => opruimen());
});
</script>

<template>
    <SiteSection id="diensten" :tone="tone" :divided="divided">
        <div ref="sectie">
            <SectionHeading
                v-if="kop"
                :eyebrow="kop.opschrift"
                :title="kop.titel"
                :intro="kop.inleiding ?? undefined"
            />

            <!-- Breed: de index met de lens, en het paneel ernaast. -->
            <div
                v-if="breed"
                class="brand-site-diensten"
                :data-enkel="enkel ? '' : undefined"
            >
                <ol
                    v-if="!enkel"
                    ref="index"
                    class="brand-site-diensten-index"
                    :style="{
                        '--lens-y': `${lens.y}px`,
                        '--lens-schaal': lens.schaal,
                    }"
                    @mouseleave="laatLos"
                >
                    <!--
                        Het spoor loopt met de scroll mee door de index
                        (`--voortgang`); de lens markeert de actieve naam.
                    -->
                    <span
                        ref="spoor"
                        class="brand-site-diensten-spoor"
                        aria-hidden="true"
                    />
                    <span class="brand-site-diensten-lens" aria-hidden="true" />
                    <li v-for="dienst in zichtbaar" :key="dienst.id">
                        <!--
                            Een knop die het paneel ernaast vult; het hele
                            verhaal opent pas met de knop in dat paneel.
                            `aria-current` zegt welke dienst er nu in het
                            paneel staat.
                        -->
                        <button
                            type="button"
                            class="brand-site-diensten-naam"
                            aria-controls="diensten-paneel"
                            :aria-current="
                                actief?.id === dienst.id ? 'true' : undefined
                            "
                            @mousemove="wijsAan(dienst, $event)"
                            @focus="kies(dienst)"
                            @click="kies(dienst)"
                        >
                            <span
                                class="brand-site-diensten-naam-icoon"
                                aria-hidden="true"
                            >
                                <DienstIcoon
                                    :icoon="dienst.icon"
                                    class="size-4"
                                />
                            </span>
                            <!--
                                Het masker en de regel erin: de naam schuift
                                bij binnenkomst van onder het masker omhoog.
                            -->
                            <span class="brand-site-diensten-naam-tekst">
                                <span class="brand-site-diensten-naam-regel">
                                    {{ dienst.titel }}
                                </span>
                            </span>
                            <ArrowRight
                                class="brand-site-diensten-naam-pijl size-5"
                                aria-hidden="true"
                            />
                        </button>
                    </li>
                </ol>

                <!--
                    Alle panelen van deze pagina op elkaar; alleen het
                    actieve is zichtbaar. Zo is het blok zo hoog als het
                    langste verhaal en verspringt er niets bij het wisselen.
                -->
                <div
                    id="diensten-paneel"
                    ref="paneel"
                    class="brand-site-diensten-paneel brand-site-spot brand-site-glas"
                >
                    <div ref="stapel" class="brand-site-diensten-stapel">
                        <article
                            v-for="dienst in zichtbaar"
                            :key="dienst.id"
                            class="brand-site-diensten-blad"
                            :data-actief="
                                actief?.id === dienst.id ? '' : undefined
                            "
                            :inert="actief?.id !== dienst.id"
                            :aria-hidden="
                                actief?.id !== dienst.id ? 'true' : undefined
                            "
                        >
                            <!--
                                `data-deel` zijn de delen die bij de eerste
                                binnenkomst na elkaar opkomen.
                            -->
                            <span
                                data-deel
                                class="brand-site-diensten-iris"
                                aria-hidden="true"
                            >
                                <DienstIcoon
                                    :icoon="dienst.icon"
                                    class="size-5"
                                />
                            </span>

                            <h3 data-deel class="brand-site-diensten-titel">
                                {{ dienst.titel }}
                            </h3>

                            <p data-deel class="brand-site-diensten-zin">
                                {{ dienst.samenvatting }}
                            </p>

                            <div
                                v-if="dienst.punten.length > 0"
                                data-deel
                                class="brand-site-diensten-onderdelen"
                            >
                                <p class="brand-site-diensten-onderdelen-kop">
                                    {{ $t('Waar dit over gaat') }}
                                </p>
                                <DienstOnderdelen :punten="dienst.punten" />
                            </div>

                            <!--
                                Alleen als er echt meer te lezen valt: een
                                knop die niets opent is erger dan geen knop.
                            -->
                            <div
                                v-if="dienst.verhaal !== null"
                                data-deel
                                class="brand-site-diensten-meer"
                            >
                                <Button
                                    variant="brand-outline"
                                    size="sm"
                                    @click="openen(dienst)"
                                >
                                    {{ $t('Lees meer') }}
                                    <ArrowRight
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </div>
                        </article>
                    </div>
                </div>
            </div>

            <!-- Smal: kaarten onder elkaar, de samenvatting zichtbaar. -->
            <div v-else class="brand-site-diensten-kaarten">
                <FeatureCard
                    v-for="dienst in zichtbaar"
                    :key="dienst.id"
                    data-kaart
                    :icoon="dienst.icon"
                    :title="dienst.titel"
                    :punten="dienst.punten"
                    :open="dienst.verhaal !== null"
                    @openen="openen(dienst)"
                >
                    {{ dienst.samenvatting }}
                </FeatureCard>
            </div>

            <!--
                Bladeren in plaats van alles onder elkaar. Elke pagina is
                even hoog, dus de rest van de landingspagina blijft op zijn
                plek staan -- en je weet hoeveel er nog komt.
            -->
            <nav
                v-if="paginas > 1"
                class="brand-bladeren mt-8"
                :aria-label="$t('Pagina van de diensten')"
            >
                <button
                    type="button"
                    class="brand-bladeren-knop"
                    :disabled="pagina === 1"
                    :aria-label="$t('Vorige pagina')"
                    @click="naarPagina(pagina - 1)"
                >
                    <ChevronLeft class="size-4" />
                </button>

                <span v-if="compact" class="brand-bladeren-stand">
                    {{ pagina }} / {{ paginas }}
                </span>

                <template v-else>
                    <template v-for="(nummer, plek) in nummers">
                        <span
                            v-if="nummer === '…'"
                            :key="`gat-${plek}`"
                            class="brand-bladeren-gat"
                            aria-hidden="true"
                        >
                            …
                        </span>
                        <button
                            v-else
                            :key="nummer"
                            type="button"
                            class="brand-bladeren-knop"
                            :data-actief="nummer === pagina ? '' : undefined"
                            :aria-current="
                                nummer === pagina ? 'page' : undefined
                            "
                            @click="naarPagina(nummer)"
                        >
                            {{ nummer }}
                        </button>
                    </template>
                </template>

                <button
                    type="button"
                    class="brand-bladeren-knop"
                    :disabled="pagina === paginas"
                    :aria-label="$t('Volgende pagina')"
                    @click="naarPagina(pagina + 1)"
                >
                    <ChevronRight class="size-4" />
                </button>
            </nav>
        </div>

        <DienstVenster v-model:open="venster" :item="gekozen" />
    </SiteSection>
</template>
