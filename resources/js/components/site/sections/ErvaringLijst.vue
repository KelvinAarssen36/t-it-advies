<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import ErvaringIcoon from '@/components/site/ErvaringIcoon.vue';
import {
    drawTimeline,
    followYears,
    revealCards,
    ScrollTrigger,
} from '@/lib/motion';
import type { ErvaringOpDeSite } from '@/types/ervaring';

/**
 * De tijdlijn als lijst: alles onder elkaar, met een rail ernaast.
 *
 * Dit is de tweede weergave van de ervaringen, naast de carrousel. Hij is
 * er voor wie de hele loopbaan in één keer wil zien of hem wil doorzoeken
 * met Ctrl+F -- en op een telefoon is hij de standaard, want een vak dat
 * je scroll overneemt hoort daar niet.
 *
 * **Eén regel per ervaring, en klikken opent het venster.** De
 * beschrijving stond hier eerst uitklapbaar onder de regel. Dat werkte op
 * een breed scherm, maar op een telefoon duwde een uitgeklapte tekst de
 * rest van de lijst weg en was je het overzicht kwijt. Nu is elke regel
 * even hoog en tikt hij open in een venster dat zich aan het apparaat
 * aanpast. Zie ErvaringVenster.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
const props = defineProps<{ items: ErvaringOpDeSite[] }>();

const emit = defineEmits<{ open: [item: ErvaringOpDeSite] }>();

/**
 * Hoeveel ervaringen er per pagina staan.
 *
 * Een tijdlijn die de hele pagina vult duwt de rest onder de vouw, en dan
 * leest niemand meer waar het contactformulier staat. Op een telefoon
 * telt dat dubbel, want een regel is daar hoger: de periode zakt naar een
 * eigen regel. **Vier in plaats van zes**, en dus wat meer pagina's --
 * een keer extra doorklikken is daar prettiger dan langer scrollen.
 */
const BREED = 6;
const SMAL = 4;

const perPagina = ref(BREED);

const pagina = ref(1);

const paginas = computed(() =>
    Math.max(1, Math.ceil(props.items.length / perPagina.value)),
);

const zichtbaar = computed(() =>
    props.items.slice(
        (pagina.value - 1) * perPagina.value,
        pagina.value * perPagina.value,
    ),
);

/**
 * Welke paginanummers er onder de lijst komen te staan.
 *
 * Bij een handvol pagina's staan ze er allemaal. Daarboven alleen de
 * eerste, de laatste en die om je heen, met een beletselteken in de
 * gaten -- anders staat er op een telefoon een rij knopjes die zelf twee
 * regels in beslag neemt.
 */
const nummers = computed<(number | '…')[]>(() => {
    const totaal = paginas.value;

    if (totaal <= 7) {
        return Array.from({ length: totaal }, (_, index) => index + 1);
    }

    const rond = [1, totaal, pagina.value - 1, pagina.value, pagina.value + 1]
        .filter((nummer) => nummer >= 1 && nummer <= totaal)
        .sort((a, b) => a - b);

    const uniek = [...new Set(rond)];
    const uitkomst: (number | '…')[] = [];

    uniek.forEach((nummer, index) => {
        const vorige = uniek[index - 1];

        if (vorige !== undefined && nummer - vorige > 1) {
            uitkomst.push('…');
        }

        uitkomst.push(nummer);
    });

    return uitkomst;
});

/**
 * De zichtbare ervaringen, gegroepeerd per beginjaar.
 *
 * Het jaartal links loopt daardoor mee terwijl je scrolt en wisselt zodra
 * je een nieuw jaar in gaat -- met `position: sticky` en zonder een regel
 * JavaScript, want elke groep is een eigen rij in het raster en een sticky
 * element blijft binnen zijn eigen rij hangen. Dat het jaartal ook oplicht
 * zodra zijn groep aan de beurt is, doet `followYears`.
 *
 * **Let op één gevolg van "wat nu loopt staat bovenaan".** Is de lopende
 * ervaring eerder begonnen dan een afgeronde daaronder, dan telt het
 * jaartal niet netjes af. Dat is zeldzaam en het klopt nog steeds -- het
 * jaar hoort altijd bij de items eronder -- maar het is geen fout in de
 * groepering.
 */
const groepen = computed(() => {
    const uitkomst: {
        sleutel: number;
        jaar: string;
        items: ErvaringOpDeSite[];
    }[] = [];

    for (const item of zichtbaar.value) {
        const laatste = uitkomst[uitkomst.length - 1];

        if (laatste?.jaar === item.jaar) {
            laatste.items.push(item);
        } else {
            /*
             * De sleutel is het id van de eerste ervaring en niet het
             * jaartal. Door "wat nu loopt staat bovenaan" kan hetzelfde
             * jaar twee keer als aparte groep voorkomen -- een lopende
             * functie uit 2019 boven een afgeronde uit 2019 -- en dan
             * krijgt `v-for` twee keer dezelfde sleutel. Vue hergebruikt
             * dan mogelijk de verkeerde knoop, en dan hangen de punten
             * van de tijdlijn aan het verkeerde element.
             */
            uitkomst.push({ sleutel: item.id, jaar: item.jaar, items: [item] });
        }
    }

    return uitkomst;
});

/* --- De animaties ---------------------------------------------------- */

const rail = ref<HTMLElement | null>(null);
const lijst = ref<HTMLElement | null>(null);

let stopTekenen: (() => void) | undefined;
let stopKaarten: (() => void) | undefined;
let stopJaren: (() => void) | undefined;

const zoek = (kiezer: string): HTMLElement[] =>
    Array.from(lijst.value?.querySelectorAll<HTMLElement>(kiezer) ?? []);

const tekenOpnieuw = (): void => {
    stopTekenen?.();

    if (rail.value === null) {
        return;
    }

    stopTekenen = drawTimeline(rail.value, zoek('[data-punt]'));
};

const volgJaren = (): void => {
    stopJaren?.();
    stopJaren = followYears(zoek('.brand-tijdlijn-groep'));
};

/**
 * De paginagrootte volgt de schermbreedte.
 *
 * Een `matchMedia`-luisteraar en geen `resize`: die laatste vuurt bij elke
 * pixel, deze alleen als je de grens overgaat -- bij het draaien van een
 * telefoon of het verslepen van een venster.
 *
 * Wordt de pagina daardoor kleiner, dan kan het paginanummer buiten de
 * lijst vallen. Vandaar de begrenzing: anders sta je op pagina 7 van 5 en
 * is de lijst leeg.
 */
let breedte: MediaQueryList | undefined;

const meetPagina = (): void => {
    perPagina.value = breedte?.matches ? BREED : SMAL;
    pagina.value = Math.min(pagina.value, paginas.value);
};

onMounted(() => {
    breedte = window.matchMedia('(min-width: 48rem)');
    breedte.addEventListener('change', meetPagina);
    meetPagina();

    stopKaarten = revealCards(zoek('.brand-tijdlijn-kaart'));
    tekenOpnieuw();
    volgJaren();
});

onBeforeUnmount(() => {
    breedte?.removeEventListener('change', meetPagina);

    stopTekenen?.();
    stopKaarten?.();
    stopJaren?.();
});

/**
 * Naar een andere pagina.
 *
 * Drie dingen moeten hier achter elkaar gebeuren, en de volgorde is niet
 * vrijblijvend: eerst laat Vue de nieuwe kaarten staan, dan animeren we ze
 * binnen -- ze zijn al in beeld, dus met een scroll-trigger zouden ze
 * onzichtbaar blijven hangen -- en pas daarna meet ScrollTrigger de lijn
 * opnieuw op, want de punten staan ergens anders.
 *
 * Er wordt bewust niet naar boven gescrold. De lijst is zes regels hoog en
 * de knopjes staan er vlak onder; springt de pagina, dan ben je die
 * knopjes kwijt en moet je ze terugzoeken voor de volgende pagina.
 */
const naarPagina = async (nieuw: number): Promise<void> => {
    if (nieuw === pagina.value || nieuw < 1 || nieuw > paginas.value) {
        return;
    }

    pagina.value = nieuw;

    await nextTick();

    revealCards(zoek('.brand-tijdlijn-kaart'), { direct: true });

    ScrollTrigger.refresh();
    tekenOpnieuw();
    volgJaren();
};
</script>

<template>
    <div>
        <div ref="lijst" class="brand-tijdlijn">
            <div ref="rail" class="brand-tijdlijn-rail" aria-hidden="true" />

            <div
                v-for="groep in groepen"
                :key="groep.sleutel"
                class="brand-tijdlijn-groep"
            >
                <!--
                    Het jaartal blijft hangen zolang je in zijn groep zit en
                    wordt weggeduwd door het volgende. Dat is pure CSS: een
                    sticky element blijft binnen zijn eigen rij in het
                    raster. Op een telefoon is er geen kolom voor en staat
                    de periode toch al op elke regel.
                -->
                <span data-jaar class="brand-tijdlijn-jaar" aria-hidden="true">
                    {{ groep.jaar }}
                </span>

                <div class="brand-tijdlijn-groep-items">
                    <article
                        v-for="item in groep.items"
                        :key="item.id"
                        class="brand-tijdlijn-item"
                    >
                        <span
                            data-punt
                            :data-loopt="item.loopt ? '' : undefined"
                            class="brand-tijdlijn-punt"
                            aria-hidden="true"
                        >
                            <img v-if="item.logo" :src="item.logo" alt="" />
                            <ErvaringIcoon
                                v-else
                                :icoon="item.icon"
                                class="size-4"
                            />
                        </span>

                        <!--
                            De hele regel is een knop. Dat is bewust geen
                            `div` met een klikafhandeling: zo kom je er met
                            het toetsenbord bij, leest een schermlezer hem
                            als iets waar je op kunt drukken, en werkt
                            Enter vanzelf.

                            Alleen de kaart komt binnen, niet het hele item.
                            Zou het punt meebewegen, dan meet drawTimeline
                            zijn plek op de lijn terwijl hij nog verschoven
                            staat -- en dan lichten de punten net te vroeg
                            of te laat op.
                        -->
                        <button
                            type="button"
                            class="brand-tijdlijn-kaart"
                            @click="emit('open', item)"
                        >
                            <div class="brand-tijdlijn-kop">
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex flex-wrap items-center gap-x-2 gap-y-1"
                                    >
                                        <h3 class="font-medium text-white">
                                            {{ item.functie }}
                                        </h3>
                                        <span
                                            v-if="item.loopt"
                                            class="brand-tijdlijn-nu"
                                        >
                                            {{ $t('Nu') }}
                                        </span>
                                    </div>

                                    <!--
                                        Organisatie en metagegevens op één
                                        regel. De puntjes ertussen komen uit
                                        de CSS, dus er blijft er nooit eentje
                                        over bij een ontbrekend deel.

                                        De organisatie is hier geen link:
                                        een link in een knop mag niet, en
                                        in het venster staat hij wél.
                                    -->
                                    <p class="brand-tijdlijn-meta mt-1">
                                        <span class="brand-tijdlijn-org">
                                            {{ item.organisatie }}
                                        </span>

                                        <span
                                            v-for="deel in item.meta"
                                            :key="deel"
                                        >
                                            {{ deel }}
                                        </span>
                                    </p>
                                </div>

                                <p class="brand-tijdlijn-tijd">
                                    <span>{{ item.periode }}</span>
                                    <span>{{ item.duur }}</span>
                                </p>

                                <ChevronRight
                                    class="brand-tijdlijn-pijl"
                                    aria-hidden="true"
                                />
                            </div>
                        </button>
                    </article>
                </div>
            </div>
        </div>

        <!--
            Bladeren in plaats van uitklappen. Elke pagina is even hoog,
            dus de rest van de landingspagina blijft op zijn plek staan --
            en je weet hoeveel er nog komt.
        -->
        <nav
            v-if="paginas > 1"
            class="brand-bladeren mt-8"
            :aria-label="$t('Pagina van de tijdlijn')"
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
                    :aria-current="nummer === pagina ? 'page' : undefined"
                    @click="naarPagina(nummer)"
                >
                    {{ nummer }}
                </button>
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
</template>
