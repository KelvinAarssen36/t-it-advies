<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useTemplateRef,
} from 'vue';
import CertificaatTegel from '@/components/site/CertificaatTegel.vue';
import CertificaatVenster from '@/components/site/CertificaatVenster.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import { kaartenBinnen, kantelKaarten, scrollNaar } from '@/lib/motion';
import type {
    CertificaatOpDeSite,
    CertificatenKop,
    OpleidingOpDeSite,
} from '@/types/certificaten';
import type { SectieProps } from '@/types/secties';

/**
 * De certificaten, met de opleidingen eronder.
 *
 * De tijdlijn vertelt wát de eigenaar heeft gedaan; dit is het bewijs
 * erbij. Een raster van tegels waarin het logo van de uitgever de
 * blikvanger is, want een bezoeker scant logo's die hij herkent en leest
 * pas daarna de naam.
 *
 * **Er wordt gebladerd**, net als bij de diensten en de tijdlijn, en om
 * dezelfde reden: een blok dat met elk certificaat langer wordt duwt de
 * rest van de pagina onder de vouw. Acht op een breed scherm -- twee
 * rijen van vier, precies één blik -- en vier op een telefoon, waar ze
 * onder elkaar staan.
 *
 * **De opleidingen zijn geen tweede raster.** Ze staan als een kort
 * lijstje onder de streep: de opleiding, de instelling, en rechts het
 * niveau met de periode. Zijn er geen, dan staat de streep er ook niet.
 *
 * `tone` en `divided` komen van buiten: welke achtergrond deze sectie
 * krijgt hangt af van waar hij in de rij staat, en dat weet hij zelf
 * niet. Zie Welcome.vue.
 *
 * Zie docs/architecture/modules/certificaten.md.
 */
defineProps<SectieProps>();

const page = usePage();

const certificaten = computed<CertificaatOpDeSite[]>(
    () => (page.props.certificates as CertificaatOpDeSite[] | undefined) ?? [],
);

const opleidingen = computed<OpleidingOpDeSite[]>(
    () => (page.props.educations as OpleidingOpDeSite[] | undefined) ?? [],
);

const kop = computed<CertificatenKop | null>(
    () =>
        (page.props.certificateHeading as CertificatenKop | undefined) ?? null,
);

/* --- Bladeren ---------------------------------------------------------- */

/**
 * Hoeveel certificaten er per pagina staan.
 *
 * Acht op een breed scherm, meer dan de vier bij de diensten: een tegel
 * is hier een logo met twee regels eronder en geen kaart met een alinea,
 * dus er passen er twee rijen van vier in dezelfde ruimte. Op een
 * telefoon vier, want daar staan ze twee naast elkaar -- ook dat is twee
 * rijen.
 */
const BREED = 8;
const SMAL = 4;

const perPagina = ref(BREED);
const pagina = ref(1);

const paginas = computed(() =>
    Math.max(1, Math.ceil(certificaten.value.length / perPagina.value)),
);

const zichtbaar = computed(() =>
    certificaten.value.slice(
        (pagina.value - 1) * perPagina.value,
        pagina.value * perPagina.value,
    ),
);

/**
 * Op een smal scherm geen nummers maar "2 / 4".
 *
 * Zelfde afweging als bij de tijdlijn en de diensten: een rij knopjes
 * die op twee regels valt is erger dan niet rechtstreeks naar pagina
 * drie kunnen springen.
 */
const compact = ref(false);

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

const sectie = useTemplateRef<HTMLElement>('sectie');

const opruimers: Array<() => void> = [];

/**
 * De kanteling staat apart van de rest van de opruimers.
 *
 * Hij hangt aan de tegels die er nú staan, en bij het bladeren zijn dat
 * andere elementen. Vandaar dat hij los bij de hand moet zijn: eerst
 * losmaken van de oude, dan opnieuw aansluiten op de nieuwe.
 */
let stopKantelen: (() => void) | undefined;

const tegels = (): HTMLElement[] =>
    Array.from(
        sectie.value?.querySelectorAll<HTMLElement>('[data-kaart]') ?? [],
    );

/**
 * De tegels die er nu staan zichtbaar maken en laten kantelen.
 *
 * **Dit moet na élke wisseling van de inhoud gebeuren.** Een tegel
 * begint op doorzichtigheid nul en wordt pas zichtbaar doordat een
 * animatie hem ophaalt; verschijnt er een zonder dat dit draait, dan
 * blijft hij onzichtbaar. Precies dat ging bij de diensten mis bij het
 * overgaan van een breedtegrens.
 *
 * `direct` omdat de tegels al in beeld staan; een scroll-trigger vuurt
 * dan nooit af.
 */
const herstelTegels = (): void => {
    stopKantelen?.();

    kaartenBinnen(tegels(), { direct: true });
    stopKantelen = kantelKaarten(tegels(), { graden: 5 });
};

/**
 * De paginagrootte volgt de schermbreedte.
 *
 * Een `matchMedia`-luisteraar en geen `resize`: die laatste vuurt bij
 * elke pixel, deze alleen als je de grens overgaat.
 *
 * Wordt de pagina daardoor kleiner, dan kan het paginanummer buiten de
 * lijst vallen. Vandaar de begrenzing: anders sta je op pagina 3 van 2
 * en is het blok leeg.
 */
const GRENS = '(min-width: 50rem)';

let breedte: MediaQueryList | undefined;

const meetPagina = (veranderTegels = true): void => {
    const breed = window.matchMedia(GRENS).matches;
    const nieuw = breed ? BREED : SMAL;
    const anders = perPagina.value !== nieuw;

    perPagina.value = nieuw;
    compact.value = !breed;
    pagina.value = Math.min(pagina.value, paginas.value);

    if (anders && veranderTegels) {
        void nextTick(herstelTegels);
    }
};

/**
 * Naar een andere pagina, en terug naar de kop van dit onderdeel.
 *
 * Een nieuwe pagina is korter of langer dan de vorige, dus alles
 * eronder verschuift. Zonder die sprong druk je op "volgende" en kijk je
 * ineens naar het contactformulier.
 */
const naarPagina = async (nieuw: number): Promise<void> => {
    if (nieuw === pagina.value || nieuw < 1 || nieuw > paginas.value) {
        return;
    }

    pagina.value = nieuw;

    await nextTick();

    herstelTegels();
    scrollNaar('certificaten');
};

/* --- Het venster met de details --------------------------------------- */

const venster = ref(false);
const gekozen = ref<CertificaatOpDeSite | null>(null);

const openen = (certificaat: CertificaatOpDeSite): void => {
    gekozen.value = certificaat;
    venster.value = true;
};

/*
 * Een benoemde functie en geen pijltje in de aanroep: hij moet bij het
 * opruimen weer losgemaakt kunnen worden, en dat lukt alleen met
 * dezelfde verwijzing.
 */
const opBreedte = (): void => meetPagina();

onMounted(async () => {
    breedte = window.matchMedia(GRENS);
    breedte.addEventListener('change', opBreedte);

    // Bij het opstarten geen `herstelTegels`: die hoort bij een
    // wisseling, en hieronder wordt de binnenkomst juist wél aan de
    // scroll gehangen -- dan komen de tegels op als je erbij bent.
    meetPagina(false);

    /*
     * Wachten tot Vue de juiste tegels heeft neergezet. `meetPagina`
     * hierboven kan de paginagrootte net hebben verkleind, en dan staan
     * er op dit moment nog acht tegels in de boom terwijl er vier horen.
     */
    await nextTick();

    opruimers.push(kaartenBinnen(tegels()));
    stopKantelen = kantelKaarten(tegels(), { graden: 5 });
});

onBeforeUnmount(() => {
    breedte?.removeEventListener('change', opBreedte);

    stopKantelen?.();
    opruimers.forEach((opruimen) => opruimen());
});
</script>

<template>
    <SiteSection id="certificaten" :tone="tone" :divided="divided">
        <div ref="sectie">
            <SectionHeading
                v-if="kop"
                :eyebrow="kop.opschrift ?? undefined"
                :title="kop.titel"
                :intro="kop.inleiding ?? undefined"
            />

            <div v-if="zichtbaar.length > 0" class="brand-certificaten mt-12">
                <CertificaatTegel
                    v-for="certificaat in zichtbaar"
                    :key="certificaat.id"
                    :item="certificaat"
                    @openen="openen(certificaat)"
                />
            </div>

            <!--
                Bladeren in plaats van alles onder elkaar. Elke pagina is
                even hoog, dus de rest van de landingspagina blijft op
                zijn plek staan -- en je weet hoeveel er nog komt.
            -->
            <nav
                v-if="paginas > 1"
                class="brand-bladeren mt-10"
                :aria-label="$t('Pagina van de certificaten')"
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
                    <template v-for="(nummer, index) in nummers">
                        <span
                            v-if="nummer === '…'"
                            :key="`gat-${index}`"
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

            <!--
                De opleidingen. Geen tweede raster maar een kort lijstje
                onder een streep: dit is de aanvulling en niet het
                onderwerp. Zijn er geen, dan staat de streep er ook niet.
            -->
            <div v-if="opleidingen.length > 0" class="brand-opleidingen">
                <p class="brand-opleidingen-kopje">{{ $t('Opleiding') }}</p>

                <ul class="brand-opleidingen-lijst">
                    <li
                        v-for="opleiding in opleidingen"
                        :key="opleiding.id"
                        class="brand-opleiding"
                    >
                        <span class="brand-opleiding-naam">
                            {{ opleiding.naam }}
                        </span>
                        <span class="brand-opleiding-instelling">
                            {{ opleiding.instelling }}
                        </span>
                        <span class="brand-opleiding-periode">
                            <template v-if="opleiding.niveau">
                                {{ opleiding.niveau }}
                                <span aria-hidden="true">·</span>
                            </template>
                            {{ opleiding.periode }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <CertificaatVenster v-model:open="venster" :item="gekozen" />
    </SiteSection>
</template>
