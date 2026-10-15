<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useTemplateRef,
} from 'vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import StatistiekBanden from '@/components/site/StatistiekBanden.vue';
import { vulStatistieken, type TeVullenStatistiek } from '@/lib/motion';
import type { SectieKop, SectieProps } from '@/types/secties';
import type { StatistiekGroep } from '@/types/statistieken';

/**
 * De statistieken: vaardigheden en kengetallen, als instrumenten.
 *
 * **Het beeld is een meetpaneel.** Ringen zijn wijzers met een schaal van
 * maatstreepjes, tellers zijn grote mono-cijfers, balken zijn een dunne
 * lijn over een schaalverdeling. Geen gevulde grijze banen: op een
 * instrument zie je de schaal en de naald, niet een balk die half leeg is.
 *
 * **Dit blok is gebouwd rond één animatie.** De ringen tekenen zich, de
 * balken lopen vol en de getallen tellen op, als een golf van boven naar
 * beneden -- één keer, zodra het in beeld komt. Daarna blijft er een
 * lichtpunt rondgaan: over de boog van een ring, over het gevulde stuk
 * van een balk, langs de randlijn van een teller. Hoe dat werkt en
 * waarom het niet meer aan de scrollpositie hangt, staat bij
 * `vulStatistieken` in motion.ts.
 *
 * **De indeling komt van de server.** Welke groepen er zijn en hoe ze in
 * banden zijn verdeeld is al uitgezocht in
 * `HomeController::statistiekGroepen()`. Dit component tekent alleen nog.
 * Dat is met opzet: groeperen op de vertaalde groepsnaam zou de pagina
 * in twee talen anders indelen, en een ring naast een balk naast een
 * teller ziet er kapot uit -- allebei beslissingen die niet in de
 * opmaaklaag horen.
 *
 * Binnen een groep volgt de pagina de sleepvolgorde van de klant, met
 * een nieuwe band zodra de vorm verandert. Zo doet slepen altijd iets
 * zichtbaars; eerder stonden alle ringen vooraan en leek de sleepgreep
 * niets te doen.
 *
 * **De samenvatting staat altijd open; de groepen staan achter tabs.** Het
 * blok was de langste sectie van de pagina (1293 pixels breed, 1511 op een
 * telefoon), met alle instrumenten van alle groepen onder elkaar. Nu:
 *
 * - **De naamloze groep is de samenvatting** en staat altijd zichtbaar. Dat
 *   is geen keuze van dit component maar van het model: een statistiek
 *   zonder groep staat "los bovenaan" -- de kengetallen die nergens bij
 *   horen en die je als eerste wilt zien (jaren, tickets, bereikbaarheid,
 *   klanten). Zie docs/architecture/modules/statistieken.md.
 * - **De groepen met een naam krijgen elk een tab**, maar alleen als het er
 *   twee of meer zijn. Eén groep staat er gewoon, met zijn kopje: een
 *   tabrij met één tab is een knop die niets kiest.
 * - **Er wordt niets verzonnen.** De tabs zijn precies de groepen die de
 *   klant in het beheer heeft gemaakt, in zijn volgorde, met zijn namen;
 *   het getal erachter is hoeveel cijfers erin staan.
 *
 * `tone` en `divided` komen van buiten: welke achtergrond deze sectie
 * krijgt hangt af van waar hij in de rij staat. Zie Welcome.vue.
 *
 * Zie docs/architecture/modules/statistieken.md.
 */
defineProps<SectieProps>();

const page = usePage();

const groepen = computed<StatistiekGroep[]>(
    () => (page.props.statistics as StatistiekGroep[] | undefined) ?? [],
);

const kop = computed<SectieKop | null>(
    () => (page.props.statisticHeading as SectieKop | undefined) ?? null,
);

/**
 * De duizendtalscheiding hoort bij de taal van de bezoeker.
 *
 * In het Nederlands een punt, in het Engels een komma. De helper die de
 * getallen schrijft weet niets van talen; die krijgt het teken mee.
 */
const scheiding = computed(() =>
    (page.props.locale as string | undefined) === 'en' ? ',' : '.',
);

/* --- Samenvatting en tabs ---------------------------------------------- */

/** De naamloze groep: de samenvatting, altijd zichtbaar. */
const samenvatting = computed(() =>
    groepen.value.filter((groep) => groep.naam === null),
);

/** De groepen met een naam: de tabs, als het er twee of meer zijn. */
const benoemd = computed(() =>
    groepen.value.filter((groep) => groep.naam !== null),
);

const metTabs = computed(() => benoemd.value.length >= 2);

const aantal = (groep: StatistiekGroep): number =>
    groep.bundels.reduce((som, bundel) => som + bundel.items.length, 0);

const actief = ref(0);

/** Unieke id's per tab en paneel; de sleutel is de Nederlandse groepsnaam. */
const id = (soort: 'tab' | 'paneel', index: number): string =>
    `statistiek-${soort}-${index}`;

const tabRij = useTemplateRef<HTMLElement>('tabRij');

const tabKnoppen = (): HTMLButtonElement[] =>
    Array.from(
        tabRij.value?.querySelectorAll<HTMLButtonElement>('[role="tab"]') ?? [],
    );

/**
 * Een tab kiezen.
 *
 * **Automatische activatie** (het WAI-ARIA-patroon kent twee smaken): met
 * de pijltjes kies je meteen. Dat mag omdat alle panelen al in de pagina
 * staan; er wordt niets geladen, dus een paneel tonen kost niets en je
 * hoeft niet eerst te verplaatsen en dan nog op Enter te drukken.
 *
 * Een paneel vult zich de eerste keer dat het in beeld komt (de golf van
 * `vulStatistieken`). Daarna is het een korte kruisovergang: wie heen en
 * weer klikt, ziet zijn cijfers wisselen en niet elke keer de hele golf.
 */
const kies = (index: number, focus = false): void => {
    const knoppen = tabKnoppen();
    const nieuw = (index + knoppen.length) % Math.max(1, knoppen.length);

    actief.value = nieuw;

    if (focus) {
        knoppen[nieuw]?.focus();
    }

    houdInBeeld(knoppen[nieuw]);
    void nextTick(() => vulPaneel(nieuw));
};

/**
 * Op een telefoon schuift de tabrij; de gekozen tab hoort daarin zichtbaar
 * te staan. Zelf rekenen en niet `scrollIntoView`: die mag ook de pagina
 * verschuiven, en dat vecht met Lenis.
 */
const houdInBeeld = (knop: HTMLElement | undefined): void => {
    const rij = tabRij.value;

    if (!rij || !knop || rij.scrollWidth <= rij.clientWidth) {
        return;
    }

    const links = knop.offsetLeft - 16;
    const rechts = knop.offsetLeft + knop.offsetWidth + 16 - rij.clientWidth;

    if (rij.scrollLeft > links) {
        rij.scrollTo({ left: links, behavior: 'smooth' });
    } else if (rij.scrollLeft < rechts) {
        rij.scrollTo({ left: rechts, behavior: 'smooth' });
    }
};

/**
 * Aan welke kant van de tabrij nog iets verborgen staat. De rand vervaagt
 * alleen daar (zie `data-meer` in statistieken.css): een vaste vervaging
 * links knipte de eerste tab af terwijl er niets meer te zien was.
 */
const meer = ref<'' | 'links' | 'rechts' | 'beide'>('');

const meetRij = (): void => {
    const rij = tabRij.value;

    if (!rij || rij.scrollWidth <= rij.clientWidth + 1) {
        meer.value = '';

        return;
    }

    const links = rij.scrollLeft > 2;
    const rechts = rij.scrollLeft + rij.clientWidth < rij.scrollWidth - 2;

    meer.value =
        links && rechts ? 'beide' : links ? 'links' : rechts ? 'rechts' : '';
};

let kijker: ResizeObserver | undefined;

const opToets = (gebeurtenis: KeyboardEvent): void => {
    const sprongen: Record<string, number> = {
        ArrowRight: actief.value + 1,
        ArrowLeft: actief.value - 1,
        Home: 0,
        End: benoemd.value.length - 1,
    };

    const doel = sprongen[gebeurtenis.key];

    if (doel === undefined) {
        return;
    }

    gebeurtenis.preventDefault();
    kies(doel, true);
};

/* --- Vullen ------------------------------------------------------------- */

const blok = useTemplateRef<HTMLElement>('blok');

const stoppers: Array<() => void> = [];

/** Welke tabpanelen al gevuld zijn. */
const gevuld = new Set<number>();

/**
 * **Per band een eigen golf, en niet één voor het hele blok.** Eerst hing
 * alles aan de bovenkant van de sectie: die komt binnen, en het hele blok
 * begon tegelijk te vullen. De rijen onderaan waren dan al klaar voordat
 * je erbij was. Nu vult een band pas als hij zelf in beeld komt.
 *
 * Binnen een band de volgorde waarin ze op het scherm staan, want die
 * bepaalt het golfje; `querySelectorAll` geeft documentvolgorde.
 */
const vulBanden = (binnen: HTMLElement): void => {
    for (const band of binnen.querySelectorAll<HTMLElement>('[data-bundel]')) {
        const onderdelen: TeVullenStatistiek[] = Array.from(
            band.querySelectorAll<HTMLElement>('[data-vul]'),
        ).map((vak) => ({
            vak,
            getal: vak.querySelector<HTMLElement>('[data-getal]'),
            waarde: Number(vak.dataset.waarde ?? 0),
        }));

        /*
         * Het anker en niet de band zelf als trigger. `vulStatistieken`
         * begint als zijn blok op 80% van het scherm staat; een rij balken
         * die daaronder in beeld kwam, stond dan zichtbaar leeg. Het anker
         * begint een stukje boven de band (zie
         * `.brand-site-statistiek-wacht`).
         */
        const anker = band.querySelector<HTMLElement>('[data-wacht]') ?? band;

        stoppers.push(
            vulStatistieken(anker, onderdelen, { scheiding: scheiding.value }),
        );
    }
};

/**
 * Een tabpaneel vullen, één keer. Een paneel dat niet gekozen is wordt niet
 * gevuld: het staat onzichtbaar in de pagina, en een golf die niemand ziet
 * is werk voor niets -- en zou bij het kiezen alleen nog stilstaan.
 */
const vulPaneel = (index: number): void => {
    if (gevuld.has(index)) {
        return;
    }

    const paneel = blok.value?.querySelector<HTMLElement>(
        `#${id('paneel', index)}`,
    );

    if (!paneel) {
        return;
    }

    gevuld.add(index);
    vulBanden(paneel);
};

onMounted(() => {
    if (blok.value === null) {
        return;
    }

    for (const groep of blok.value.querySelectorAll<HTMLElement>(
        '[data-vaste-groep]',
    )) {
        vulBanden(groep);
    }

    if (metTabs.value) {
        vulPaneel(actief.value);
        meetRij();

        if (tabRij.value && typeof ResizeObserver !== 'undefined') {
            kijker = new ResizeObserver(meetRij);
            kijker.observe(tabRij.value);
        }
    }
});

onBeforeUnmount(() => {
    kijker?.disconnect();
    stoppers.forEach((stop) => stop());
});
</script>

<template>
    <SiteSection id="statistieken" :tone="tone" :divided="divided">
        <div ref="blok" class="brand-site-statistieken">
            <SectionHeading
                rol="feit"
                v-if="kop"
                :eyebrow="kop.opschrift ?? undefined"
                :title="kop.titel"
                :intro="kop.inleiding ?? undefined"
            />

            <!--
                De samenvatting: de cijfers zonder groep, altijd zichtbaar.
                Geen kopje; die staan vooraan en horen nergens bij.
            -->
            <div
                v-for="groep in samenvatting"
                :key="groep.sleutel"
                data-vaste-groep
                class="brand-site-statistiek-groep"
            >
                <StatistiekBanden :groep="groep" />
            </div>

            <!-- Eén groep met een naam: gewoon tonen, met zijn kopje. -->
            <template v-if="!metTabs">
                <div
                    v-for="groep in benoemd"
                    :key="groep.sleutel"
                    data-vaste-groep
                    class="brand-site-statistiek-groep"
                >
                    <h3 class="brand-site-statistiek-groepkop">
                        {{ groep.naam }}
                    </h3>
                    <StatistiekBanden :groep="groep" />
                </div>
            </template>

            <!--
                Twee of meer groepen: tabs. Het WAI-ARIA-patroon: een
                `tablist` met `tab`-knoppen, één `tabpanel` per groep, en
                alleen de gekozen tab in de tabvolgorde (pijltjes, Home en
                End voor de rest).

                **Alle panelen staan in de pagina en op elkaar** (dezelfde
                rastercel). Het blok is daardoor zo hoog als het hoogste
                paneel en de pagina eronder verspringt niet bij het
                wisselen; een paneel dat niet gekozen is, is onzichtbaar en
                `inert`, maar zijn inhoud staat er -- voor een zoekmachine,
                en als er iets misgaat met het kiezen.
            -->
            <div v-else class="brand-site-statistiek-groep">
                <div
                    ref="tabRij"
                    role="tablist"
                    :aria-label="$t('Cijfers per groep')"
                    class="brand-site-statistiek-tabrij"
                    :data-meer="meer || undefined"
                    @keydown="opToets"
                    @scroll.passive="meetRij"
                >
                    <button
                        v-for="(groep, index) in benoemd"
                        :id="id('tab', index)"
                        :key="groep.sleutel"
                        type="button"
                        role="tab"
                        class="brand-site-statistiek-tab"
                        :aria-selected="actief === index ? 'true' : 'false'"
                        :aria-controls="id('paneel', index)"
                        :tabindex="actief === index ? 0 : -1"
                        @click="kies(index)"
                    >
                        <span>{{ groep.naam }}</span>
                        <span
                            class="brand-site-statistiek-tab-aantal brand-site-data"
                            aria-hidden="true"
                            >{{ aantal(groep) }}</span
                        >
                    </button>
                </div>

                <div class="brand-site-statistiek-panelen">
                    <div
                        v-for="(groep, index) in benoemd"
                        :id="id('paneel', index)"
                        :key="groep.sleutel"
                        role="tabpanel"
                        class="brand-site-statistiek-paneel"
                        :aria-labelledby="id('tab', index)"
                        :data-actief="actief === index ? '' : undefined"
                        :inert="actief !== index"
                        :tabindex="actief === index ? 0 : -1"
                    >
                        <StatistiekBanden :groep="groep" />
                    </div>
                </div>
            </div>
        </div>
    </SiteSection>
</template>
