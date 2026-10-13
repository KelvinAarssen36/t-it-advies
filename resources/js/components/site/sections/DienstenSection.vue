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
import DienstVenster from '@/components/site/DienstVenster.vue';
import FeatureCard from '@/components/site/FeatureCard.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import { kaartenBinnen, kantelKaarten, scrollNaar } from '@/lib/motion';
import type { DienstOpDeSite, DienstenKop } from '@/types/diensten';
import type { SectieProps } from '@/types/secties';

/**
 * De diensten.
 *
 * **De teksten komen uit de database**, sinds de module er is. Ze stonden
 * hier in een lijst in dit bestand, en daarmee was dit het op een na
 * grootste blok op de voorpagina dat de klant niet zelf kon beheren.
 *
 * `tone` en `divided` komen van buiten: welke achtergrond deze sectie
 * krijgt hangt af van waar hij in de rij staat, en dat weet hij zelf
 * niet. Zie Welcome.vue.
 *
 * **Er wordt gebladerd en niet uitgeklapt**, net als bij de tijdlijn. De
 * klant mag zoveel diensten neerzetten als hij wil, maar een blok dat
 * met elke dienst langer wordt duwt de rest van de pagina onder de
 * vouw. Elke pagina is even hoog, dus de pagina eronder blijft staan --
 * en je ziet aan de knopjes hoeveel er nog komt.
 *
 * **De kaarten komen één voor één binnen** zodra ze in beeld scrollen.
 * Zie `kaartenBinnen` in motion.ts.
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

/* --- Bladeren ---------------------------------------------------------- */

/**
 * Hoeveel diensten er per pagina staan.
 *
 * Vier op een breed scherm: dat is één rij, en een rij is precies wat je
 * in één oogopslag overziet. Op een telefoon drie, want daar staan ze
 * onder elkaar en is elke kaart een halve schermhoogte -- vier zou
 * betekenen dat je moet scrollen om de knopjes te vinden waarmee je
 * verder bladert.
 */
const BREED = 4;
const SMAL = 3;

const perPagina = ref(BREED);
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
 * Op een smal scherm geen nummers maar "2 / 4".
 *
 * Zelfde afweging als bij de tijdlijn: een rij knopjes die op twee
 * regels valt is erger dan niet rechtstreeks naar pagina drie kunnen
 * springen. Op een telefoon zijn die knopjes toch te klein om gericht
 * te raken.
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

/**
 * Hoeveel kolommen het raster krijgt.
 *
 * **Zolang alles op één pagina past volgt het aantal kolommen het aantal
 * diensten**, zodat de rij vol staat. Vier worden er twee bij twee en
 * niet vier naast elkaar: vier kaarten op één rij zijn zo smal dat de
 * tekst erin op zes regels valt.
 *
 * Zodra er gebladerd wordt ligt het raster vast op twee kolommen -- dus
 * een volle pagina is twee bij twee. Anders zou de laatste pagina met
 * één overgebleven dienst een kaart van de volle breedte tonen, en
 * springt de maat van de kaarten bij elke klik.
 *
 * De klassen staan voluit en worden niet in elkaar gezet. Tailwind leest
 * de broncode om te weten welke klassen bestaan; een naam die pas tijdens
 * het draaien ontstaat vindt hij niet, en dan mist die klasse in de
 * gebouwde stylesheet.
 */
const kolommen = computed(() => {
    if (paginas.value > 1) {
        return 'sm:grid-cols-2';
    }

    switch (diensten.value.length) {
        case 1:
            return 'max-w-md';
        case 2:
        case 4:
            return 'sm:grid-cols-2';
        default:
            return 'sm:grid-cols-2 lg:grid-cols-3';
    }
});

const sectie = useTemplateRef<HTMLElement>('sectie');

const opruimers: Array<() => void> = [];

/**
 * De kanteling staat apart van de rest van de opruimers.
 *
 * Hij hangt aan de kaarten die er nú staan, en bij het bladeren zijn dat
 * andere elementen. Vandaar dat hij los bij de hand moet zijn: eerst
 * losmaken van de oude, dan opnieuw aansluiten op de nieuwe.
 */
let stopKantelen: (() => void) | undefined;

const kaarten = (): HTMLElement[] =>
    Array.from(
        sectie.value?.querySelectorAll<HTMLElement>('[data-kaart]') ?? [],
    );

/**
 * De kaarten die er nu staan zichtbaar maken en laten kantelen.
 *
 * **Dit moet na élke wisseling van de inhoud gebeuren, en dat was de
 * fout.** Een kaart begint op doorzichtigheid nul -- die klasse zit in
 * FeatureCard -- en wordt pas zichtbaar doordat een animatie hem
 * ophaalt. Verscheen er een kaart zonder dat dit draaide, dan bleef hij
 * onzichtbaar. Precies dat gebeurde bij het overgaan van een
 * breedtegrens: op een telefoon staan er drie, op een breed scherm
 * vier, en die vierde was er wel maar zag je niet. Pas na verversen
 * stond hij er.
 *
 * `direct` omdat de kaarten al in beeld staan; een scroll-trigger vuurt
 * dan nooit af. De kanteling wordt opnieuw aangesloten, want het zijn
 * andere elementen dan daarnet.
 */
const herstelKaarten = (): void => {
    stopKantelen?.();

    kaartenBinnen(kaarten(), { direct: true });
    stopKantelen = kantelKaarten(kaarten());
};

/**
 * De paginagrootte volgt de schermbreedte.
 *
 * Een `matchMedia`-luisteraar en geen `resize`: die laatste vuurt bij
 * elke pixel, deze alleen als je de grens overgaat -- bij het draaien
 * van een telefoon of het verslepen van een venster.
 *
 * Wordt de pagina daardoor kleiner, dan kan het paginanummer buiten de
 * lijst vallen. Vandaar de begrenzing: anders sta je op pagina 3 van 2
 * en is het blok leeg.
 */
const GRENS = '(min-width: 50rem)';

let breedte: MediaQueryList | undefined;

const meetPagina = (veranderKaarten = true): void => {
    const breed = window.matchMedia(GRENS).matches;
    const nieuw = breed ? BREED : SMAL;
    const anders = perPagina.value !== nieuw;

    perPagina.value = nieuw;
    compact.value = !breed;
    pagina.value = Math.min(pagina.value, paginas.value);

    if (anders && veranderKaarten) {
        void nextTick(herstelKaarten);
    }
};

/**
 * Naar een andere pagina.
 *
 * **Er wordt naar de kop van dit onderdeel gesprongen.** Dat stond hier
 * eerst niet, en dat was verkeerd: een nieuwe pagina is korter of langer
 * dan de vorige, dus alles eronder verschuift. Je drukte op "volgende"
 * en keek ineens naar de tijdlijn -- en op een telefoon, waar de kaarten
 * onder elkaar staan, begon je zelfs midden in de tweede kaart.
 *
 * Nu sta je na elke klik bovenaan de eerste dienst van de nieuwe pagina,
 * en is het precies wat je van bladeren verwacht.
 */
const naarPagina = async (nieuw: number): Promise<void> => {
    if (nieuw === pagina.value || nieuw < 1 || nieuw > paginas.value) {
        return;
    }

    pagina.value = nieuw;

    await nextTick();

    herstelKaarten();
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
 * Een benoemde functie en geen pijltje in de aanroep: hij moet bij het
 * opruimen weer losgemaakt kunnen worden, en dat lukt alleen met
 * dezelfde verwijzing.
 */
const opBreedte = (): void => meetPagina();

onMounted(async () => {
    breedte = window.matchMedia(GRENS);
    breedte.addEventListener('change', opBreedte);

    // Bij het opstarten geen `herstelKaarten`: die hoort bij een
    // wisseling, en hieronder wordt de binnenkomst juist wél aan de
    // scroll gehangen -- dan komen de kaarten op als je erbij bent.
    meetPagina(false);

    /*
     * Wachten tot Vue de juiste kaarten heeft neergezet. `meetPagina`
     * hierboven kan de paginagrootte net hebben verkleind, en dan staan
     * er op dit moment nog vier kaarten in de boom terwijl er drie
     * horen. De animatie zou dan aan een kaart hangen die zo verdwijnt.
     */
    await nextTick();

    opruimers.push(kaartenBinnen(kaarten()));
    stopKantelen = kantelKaarten(kaarten());
});

onBeforeUnmount(() => {
    breedte?.removeEventListener('change', opBreedte);

    stopKantelen?.();
    opruimers.forEach((opruimen) => opruimen());
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

            <!--
                Hier stond een vaste hoogte, om te voorkomen dat de
                pagina verschuift bij een kortere laatste pagina. Die is
                eruit: op een telefoon staan de kaarten onder elkaar, dus
                die gereserveerde hoogte was een gat van bijna twee
                schermen onder de laatste kaart. Het verschuiven wordt nu
                opgelost door naar de kop van dit onderdeel te springen;
                zie `naarPagina`.
            -->
            <div class="mt-12 grid gap-6" :class="kolommen">
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
                even hoog, dus de rest van de landingspagina blijft op
                zijn plek staan -- en je weet hoeveel er nog komt.
            -->
            <nav
                v-if="paginas > 1"
                class="brand-bladeren mt-10"
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
        </div>

        <DienstVenster v-model:open="venster" :item="gekozen" />
    </SiteSection>
</template>
