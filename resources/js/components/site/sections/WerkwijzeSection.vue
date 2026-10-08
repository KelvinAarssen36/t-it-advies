<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    useTemplateRef,
    watch,
} from 'vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import { Button } from '@/components/ui/button';
import { tekenPad } from '@/lib/motion';
import { werkwijze } from '@/routes';
import type { SectieKop, SectieProps } from '@/types/secties';
import type { StapOpDeSite } from '@/types/werkwijze';

/**
 * De werkwijze: van kennismaken tot overdragen.
 *
 * **De stappen stonden hier in het bestand** -- vier stuks, met de kop
 * erboven net zo. Nu komen ze uit de database en beheert de klant ze zelf.
 * De vormgeving is met opzet niet meeverhuisd: die was al goed.
 *
 * **De lijn erboven tekent zichzelf terwijl je scrolt**, met een
 * lichtpuntje dat erlangs reist, en elke stap licht op zodra het punt hem
 * passeert. Dat maakt van losse blokjes één doorlopende beweging -- en dat
 * is precies wat "werkwijze" hoort over te brengen: het is een volgorde,
 * geen opsomming.
 *
 * **De lijn staat nu ook op een telefoon.** Dat kon eerst niet: het pad
 * lag vast in de code, horizontaal, op vier stappen afgestemd. Nu wordt
 * het getekend door de nummers heen, waar die ook staan -- naast elkaar,
 * over twee rijen, of onder elkaar. Zie `tekenPad` in lib/motion.ts.
 *
 * **Tot vier stappen een rij, daarboven een kolom.** Geen raster van
 * meerdere rijen, en dat is een keuze die uit de lijn volgt: een lijn die
 * van het einde van de ene rij naar het begin van de volgende moet, snijdt
 * onderweg dwars door de tekst van de kaarten ertussen. Er is geen route
 * die dat niet doet.
 *
 * De eerste poging liet de lijn dan maar weg. Dat was erger: bij vijf
 * stappen stond er ineens een leeg gat waar iets hoorde. Nu schakelt de
 * opmaak om naar één kolom -- precies wat een telefoon al deed -- en loopt
 * de lijn er verticaal langs. Die vorm werkt bij elk aantal.
 *
 * **De kolomvorm is compacter dan de rij, en dat moet ook.** Een rij van
 * vier kaarten is zo hoog als de langste kaart; een kolom van acht is de
 * som van alle acht. Met dezelfde maten wordt dat een sectie waar je
 * minutenlang langs scrolt -- en omdat elke telefoon de kolomvorm krijgt,
 * geldt dat daar altijd. Kleinere titel, kleinere tekst en minder ruimte
 * ertussen scheelt ongeveer de helft. Zie `.brand-werkwijze-raster` in
 * app.css; de maten staan daar en niet in dit sjabloon, want ze
 * verschillen per vorm.
 */
defineProps<SectieProps>();

const page = usePage();

const stappen = computed<StapOpDeSite[]>(
    () => (page.props.workSteps as StapOpDeSite[] | undefined) ?? [],
);

const kop = computed<SectieKop | null>(
    () => (page.props.workHeading as SectieKop | undefined) ?? null,
);

/**
 * Of de pagina met het hele verhaal bestaat.
 *
 * De server beslist dat, want daar staat ook de regel die bepaalt of die
 * pagina bestaat. Zou dit scherm zelf gaan kijken of er ergens een verhaal
 * is, dan wijst de knop vroeg of laat naar een 404.
 */
const eigenPagina = computed<boolean>(
    () => (page.props.workPage as boolean | undefined) ?? false,
);

/**
 * Of de stappen op één rij passen.
 *
 * Twee tot vier naast elkaar; daarboven onder elkaar. Zie het blok
 * bovenaan voor waarom er geen tussenvorm is. Dit attribuut stuurt de hele
 * omschakeling in de CSS aan, zodat de opmaak op één plek staat.
 *
 * **Eén stap telt niet als rij**, en dat is geen muggenzifterij: de
 * rijvorm houdt bovenaan vijf rem vrij voor de lijn, en bij één stap is er
 * geen lijn -- één punt verbindt niets. Zonder deze ondergrens staat er
 * tachtig pixels leegte boven een enkele stap, voor niets.
 */
const opEenRij = computed(
    () => stappen.value.length >= 2 && stappen.value.length <= 4,
);

const svg = useTemplateRef<SVGSVGElement>('svg');
const pad = useTemplateRef<SVGPathElement>('pad');
const punt = useTemplateRef<SVGCircleElement>('punt');
const lijst = useTemplateRef<HTMLElement>('lijst');

let opruimen: (() => void) | undefined;

const begin = (): void => {
    opruimen?.();
    opruimen = undefined;

    if (svg.value === null || pad.value === null || lijst.value === null) {
        return;
    }

    const kaarten = Array.from(
        lijst.value.querySelectorAll<HTMLElement>('[data-stap]'),
    );

    opruimen = tekenPad(svg.value, pad.value, punt.value, kaarten);
};

/*
 * Opnieuw beginnen als de stappen veranderen -- bij een taalwissel komt
 * er een nieuwe lijst binnen. De oude tekening wijst dan naar elementen
 * die er niet meer zijn.
 */
watch(stappen, () => void begin());

onMounted(begin);

onBeforeUnmount(() => {
    opruimen?.();
});
</script>

<template>
    <SiteSection id="werkwijze" :tone="tone" :divided="divided">
        <SectionHeading
            v-if="kop"
            :eyebrow="kop.opschrift ?? undefined"
            :title="kop.titel"
            :intro="kop.inleiding ?? undefined"
        />

        <div class="relative mt-12">
            <!--
                De lijn ligt achter de nummers. Hij vult de hele omlijsting
                in plaats van een strook bovenin, want op een telefoon loopt
                hij van boven naar beneden.

                `aria-hidden`: de volgorde staat al in de genummerde lijst
                eronder. Een schermlezer heeft aan "een golvende lijn"
                niets.
            -->
            <svg ref="svg" class="brand-werkwijze-lijn" aria-hidden="true">
                <path
                    ref="pad"
                    fill="none"
                    stroke="url(#werkwijze-verloop)"
                    stroke-width="2"
                    stroke-linecap="round"
                />

                <circle ref="punt" class="brand-werkwijze-punt" r="4" />

                <defs>
                    <linearGradient id="werkwijze-verloop" x1="0" x2="1">
                        <stop offset="0%" stop-color="#0787e8" />
                        <stop offset="100%" stop-color="#13c7f3" />
                    </linearGradient>
                </defs>
            </svg>

            <ol
                ref="lijst"
                class="brand-werkwijze-raster"
                :data-rij="opEenRij ? '' : undefined"
                :style="{ '--stappen': stappen.length }"
            >
                <li
                    v-for="(stap, index) in stappen"
                    :key="stap.id"
                    data-stap
                    data-reveal
                    class="brand-werkwijze-stap opacity-0"
                >
                    <!--
                        `data-stap-punt`: hier hangt de lijn aan vast. Zie
                        `tekenPad`; door het midden van de kaart zou hij op
                        een telefoon dwars door de tekst lopen.
                    -->
                    <span
                        data-stap-punt
                        class="brand-werkwijze-nummer font-mono text-sm"
                    >
                        {{ String(index + 1).padStart(2, '0') }}
                    </span>

                    <!--
                        De duur staat naast het nummer en niet onder de
                        titel: samen zeggen ze "stap twee, twee weken", en
                        dat is precies de vraag die een bezoeker hier heeft.
                    -->
                    <span v-if="stap.duur" class="brand-werkwijze-duur">
                        {{ stap.duur }}
                    </span>

                    <h3 class="brand-werkwijze-titel">{{ stap.titel }}</h3>

                    <p v-if="stap.samenvatting" class="brand-werkwijze-tekst">
                        {{ stap.samenvatting }}
                    </p>

                    <p v-if="stap.resultaat" class="brand-werkwijze-resultaat">
                        {{ stap.resultaat }}
                    </p>
                </li>
            </ol>
        </div>

        <!--
            Alleen als er echt meer te lezen valt. Een knop naar een pagina
            die dezelfde vier zinnen herhaalt is een omweg; de server houdt
            die regel bij, zodat de knop en de pagina niet uit elkaar kunnen
            lopen.
        -->
        <div v-if="eigenPagina" data-reveal class="mt-10 opacity-0">
            <Button variant="outline" as-child>
                <Link :href="werkwijze()">
                    {{ $t('Lees hoe ik werk') }}
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </Button>
        </div>
    </SiteSection>
</template>
