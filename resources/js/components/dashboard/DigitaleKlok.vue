<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * De digitale klok op het dashboard.
 *
 * **Alles wat je ziet wordt door de browser opgemaakt en niet door ons.**
 * `Intl.DateTimeFormat` krijgt de taal van het portaal en de gekozen
 * tijdzone, en levert de tijd en de datum zoals ze in die regio horen te
 * staan -- "donderdag 1 oktober 2026" in het Nederlands, met de dag
 * vooraan. Zelf datums in elkaar zetten met een lijst maandnamen is precies
 * hoe je een klok krijgt die in het Engels "1 October" zegt waar "October
 * 1" hoort.
 *
 * **De tijdzone is een IANA-naam en geen offset**, dus zomer- en wintertijd
 * gaan automatisch mee. Zie `DashboardTimezone`.
 *
 * **Waarom hier geen GSAP.** De rest van het project animeert met GSAP,
 * maar dat zit alleen in motion.ts en daarmee in de bundel van de publieke
 * site. Het voor een klok naar het portaal halen kost 150 kB voor iets wat
 * CSS prima doet. Alles hieronder beweegt met CSS-animaties die vanzelf
 * opnieuw beginnen: elk cijfer heeft zijn karakter in zijn `key`, dus zodra
 * het verandert vervangt Vue dat ene element en loopt de animatie opnieuw.
 * Een cijfer dat gelijk blijft, beweegt niet.
 *
 * Zie docs/architecture/dashboard.md.
 */
const props = defineProps<{
    /** Een IANA-naam, zoals `Europe/Amsterdam`. */
    tijdzone: string;
}>();

const nu = ref(new Date());

let tikker: ReturnType<typeof setTimeout> | undefined;

/**
 * Elke seconde bijwerken, en wel op de seconde.
 *
 * Een vaste `setInterval(1000)` loopt langzaam uit de pas met de echte
 * klok: hij start op een willekeurig moment binnen de seconde en de
 * browser mag hem laten wachten. Dan ziet een bezoeker een seconde twee
 * keer of helemaal niet. Daarom wordt elke keer uitgerekend hoe lang het
 * nog duurt tot de volgende hele seconde.
 */
const plan = (): void => {
    const wacht = 1000 - (Date.now() % 1000);

    tikker = setTimeout(() => {
        nu.value = new Date();
        plan();
    }, wacht);
};

onMounted(plan);

onBeforeUnmount(() => {
    if (tikker !== undefined) {
        clearTimeout(tikker);
    }
});

/**
 * De taalcode voor de opmaak.
 *
 * Het portaal kent `nl` en `en`; `Intl` wil een volledige tag. Voor Engels
 * `en-GB` en niet `en-US`, want daar staat de maand vooraan in een datum en
 * de klok op twaalf uur -- en dit blijft een Nederlands bedrijf, ook als de
 * eigenaar het portaal in het Engels zet.
 */
const taal = computed(() =>
    (usePage().props.locale as string | undefined) === 'en' ? 'en-GB' : 'nl-NL',
);

/**
 * Een opmaker, of een die terugvalt op de zone van de browser.
 *
 * Een onbekende tijdzone laat `Intl` met een `RangeError` omvallen, en dan
 * is het hele dashboard leeg. De server laat alleen zones uit de enum door,
 * dus dit hoort nooit te gebeuren -- maar een lege pagina is een te hoge
 * prijs voor een tikfout in een instelling.
 */
const opmaker = (opties: Intl.DateTimeFormatOptions): Intl.DateTimeFormat => {
    try {
        return new Intl.DateTimeFormat(taal.value, {
            ...opties,
            timeZone: props.tijdzone,
        });
    } catch {
        return new Intl.DateTimeFormat(taal.value, opties);
    }
};

/**
 * "14:05" -- het uur en de minuut, groot.
 *
 * **`hourCycle: 'h23'` en niet `hour12: false`.** Die twee lijken hetzelfde
 * maar zijn het niet: `hour12: false` laat de browser kiezen tussen h23 (0
 * tot 23) en h24 (1 tot 24), en bij die tweede staat er om half één 's
 * nachts `24:30` in plaats van `00:30`. Precies het soort fout dat je
 * alleen midden in de nacht ziet.
 *
 * Ze kunnen niet samen: staat `hour12` erbij, dan wint die en wordt
 * `hourCycle` genegeerd.
 */
const uurMinuut = computed(() =>
    opmaker({ hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
        .format(nu.value)
        .split(''),
);

/** "09" -- de seconden, klein ernaast. */
const seconden = computed(() =>
    opmaker({ second: '2-digit' }).format(nu.value).padStart(2, '0').split(''),
);

const datum = computed(() =>
    opmaker({
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(nu.value),
);

/** Dezelfde datum maar kort, voor de dunne strook op een telefoon. */
const datumKort = computed(() =>
    opmaker({ weekday: 'short', day: 'numeric', month: 'short' }).format(
        nu.value,
    ),
);

/**
 * Hoe ver de minuut is, als 0 tot 1.
 *
 * Voor het streepje onder de klok. Het is de enige beweging die langzaam
 * gaat, en daarmee het verschil tussen een plaatje met cijfers en iets dat
 * loopt.
 */
const voortgang = computed(() => {
    const deel = Number(opmaker({ second: '2-digit' }).format(nu.value));

    return (Number.isNaN(deel) ? 0 : deel) / 60;
});

/**
 * De hele tijd als één zin, voor wie dit voorgelezen krijgt.
 *
 * De losse cijfers zijn `aria-hidden`: letter voor letter "een, vier,
 * dubbele punt, nul, vijf" is geen tijd. `aria-live` staat er bewust
 * **niet** op -- een klok die elke seconde iets roept maakt een schermlezer
 * onbruikbaar.
 */
const voorgelezen = computed(
    () =>
        `${opmaker({ hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(nu.value)} — ${datum.value}`,
);
</script>

<template>
    <div class="brand-klok">
        <!--
            De gloed erachter. Puur versiering, dus `aria-hidden`, en op een
            telefoon staat hij uit: daar is het blok een dunne strook en
            dan is een wolk licht eromheen onrust.
        -->
        <span class="brand-klok-gloed" aria-hidden="true" />

        <p class="brand-klok-tijd">
            <span class="sr-only">{{ voorgelezen }}</span>

            <span aria-hidden="true" class="brand-klok-cijfers">
                <!--
                    Het karakter zit in de `key`. Verandert het, dan
                    vervangt Vue dit ene element en begint de animatie
                    opnieuw; blijft het gelijk, dan beweegt er niets. Dat
                    is wat ervoor zorgt dat alleen het cijfer dat echt
                    verspringt ook verspringt.
                -->
                <span
                    v-for="(teken, index) in uurMinuut"
                    :key="`${index}-${teken}`"
                    class="brand-klok-cijfer"
                    :data-scheiding="teken === ':' ? '' : undefined"
                >
                    {{ teken }}
                </span>

                <span class="brand-klok-seconden">
                    <span
                        v-for="(teken, index) in seconden"
                        :key="`s${index}-${teken}`"
                        class="brand-klok-cijfer"
                    >
                        {{ teken }}
                    </span>
                </span>
            </span>
        </p>

        <!--
            Het streepje dat met de minuut volloopt. Het staat tussen de
            tijd en de datum omdat het bij de tijd hoort en niet bij de
            datum.
        -->
        <span
            class="brand-klok-baan"
            aria-hidden="true"
            :style="{ '--minuut': voortgang }"
        />

        <p class="brand-klok-datum" aria-hidden="true">
            <span class="brand-klok-datum-lang">{{ datum }}</span>
            <span class="brand-klok-datum-kort">{{ datumKort }}</span>
        </p>
    </div>
</template>
