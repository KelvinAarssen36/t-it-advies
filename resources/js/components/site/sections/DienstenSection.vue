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
import DienstVenster from '@/components/site/DienstVenster.vue';
import FeatureCard from '@/components/site/FeatureCard.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import { Button } from '@/components/ui/button';
import {
    gsap,
    irisOpen,
    kaartenBinnen,
    prefersReducedMotion,
    ScrollTrigger,
    scrollNaar,
    scrollVoortgang,
    spotlicht,
    stapelBinnen,
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
const wijsAan = (dienst: DienstOpDeSite): void => {
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

/* --- Binnenkomen: de index bouwt zich op ------------------------------ */

/*
 * **Breed bouwt de index zich op terwijl je binnenkomt.** De namen schuiven
 * per regel uit een masker omhoog, gestaffeld, en hun pictogram zet zich
 * neer; de rail ernaast loopt met je scroll mee door de index
 * (`scrollVoortgang`), en het paneel gaat de eerste keer open als een
 * diafragma (`irisOpen`). Drie bewegingen die samen één moment zijn: de
 * lijst verschijnt, en het licht valt op de eerste dienst.
 *
 * **Smal komen de kaarten één voor één binnen** (`kaartenBinnen`), zoals
 * voorheen; een masker per regel op een kaart van een telefoonscherm
 * breed is te veel beweging voor te weinig ruimte.
 *
 * De twee soorten opruimers staan los van elkaar: bij bladeren bouwt
 * alleen de index zich opnieuw op (het paneel staat al open), en bij een
 * wissel tussen breed en smal begint alles opnieuw, want dan staan er
 * andere elementen.
 */
const sectie = useTemplateRef<HTMLElement>('sectie');
const paneel = useTemplateRef<HTMLElement>('paneel');
const spoor = useTemplateRef<HTMLElement>('spoor');
const indexOpruimers: Array<() => void> = [];
const paneelOpruimers: Array<() => void> = [];

const kaarten = (): HTMLElement[] =>
    Array.from(
        sectie.value?.querySelectorAll<HTMLElement>('[data-kaart]') ?? [],
    );

const binnen = (selector: string): HTMLElement[] =>
    Array.from(index.value?.querySelectorAll<HTMLElement>(selector) ?? []);

/**
 * De namen schuiven per regel uit hun masker omhoog, gebundeld: wat samen
 * in beeld komt, komt samen op, met een kleine stagger.
 *
 * **Eigen code en niet `stapelBinnen`**, en dat is een bewuste uitzondering.
 * Die hulp zet aan het eind `x` en `y` terug maar niet `yPercent`, dus een
 * regel die met een procent onder zijn masker begint, bleef daar de hele
 * duur staan en sprong pas bij het opruimen ineens in beeld -- in de
 * browser gemeten ruim een seconde te laat. Een procent is hier nodig: een
 * naam is één of drie regels hoog, en een vaste pixelafstand verstopt de
 * ene niet en de andere half.
 */
const regelsOmhoog = (regels: HTMLElement[]): (() => void) => {
    if (regels.length === 0 || prefersReducedMotion()) {
        return () => {};
    }

    gsap.set(regels, { yPercent: 110 });

    const triggers = ScrollTrigger.batch(regels, {
        start: 'top 88%',
        once: true,
        onEnter: (groep) =>
            gsap.to(groep, {
                yPercent: 0,
                duration: 0.9,
                stagger: 0.06,
                ease: 'expo.out',
                overwrite: true,
                clearProps: 'transform',
            }),
    });

    return () => {
        triggers.forEach((trigger) => trigger.kill());
        gsap.killTweensOf(regels);
        gsap.set(regels, { clearProps: 'transform' });
    };
};

/** De index (breed) of de kaarten (smal) laten binnenkomen. */
const bouwIndex = (): void => {
    indexOpruimers.splice(0).forEach((opruimen) => opruimen());

    if (!breed.value) {
        indexOpruimers.push(kaartenBinnen(kaarten()));

        return;
    }

    indexOpruimers.push(
        regelsOmhoog(binnen('.brand-site-diensten-naam-regel')),
        stapelBinnen(binnen('.brand-site-diensten-naam-icoon'), {
            van: { opacity: 0, scale: 0.6 },
            stagger: 0.06,
        }),
    );

    if (spoor.value !== null && index.value !== null) {
        indexOpruimers.push(
            scrollVoortgang(spoor.value, {
                trigger: index.value,
                start: 'top 80%',
                eind: 'bottom 45%',
            }),
        );
    }
};

/** Het paneel: één keer open als een diafragma, met een spotlicht. */
const bouwPaneel = (): void => {
    paneelOpruimers.splice(0).forEach((opruimen) => opruimen());

    if (!breed.value || paneel.value === null) {
        return;
    }

    paneelOpruimers.push(
        irisOpen(paneel.value, { midden: '50% 20%', duur: 1.1 }),
        spotlicht([paneel.value]),
    );
};

const herstel = (): void => {
    bouwIndex();
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

    herstel();
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
                            @mouseenter="wijsAan(dienst)"
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
                    <div class="brand-site-diensten-stapel">
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
                            <span
                                class="brand-site-diensten-iris"
                                aria-hidden="true"
                            >
                                <DienstIcoon
                                    :icoon="dienst.icon"
                                    class="size-5"
                                />
                            </span>

                            <h3 class="brand-site-diensten-titel">
                                {{ dienst.titel }}
                            </h3>

                            <p class="brand-site-diensten-zin">
                                {{ dienst.samenvatting }}
                            </p>

                            <ul
                                v-if="dienst.punten.length > 0"
                                class="brand-site-diensten-punten"
                            >
                                <li
                                    v-for="(punt, plek) in dienst.punten"
                                    :key="punt"
                                    :style="{ '--punt': plek }"
                                >
                                    {{ punt }}
                                </li>
                            </ul>

                            <!--
                                Alleen als er echt meer te lezen valt: een
                                knop die niets opent is erger dan geen knop.
                            -->
                            <div
                                v-if="dienst.verhaal !== null"
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
