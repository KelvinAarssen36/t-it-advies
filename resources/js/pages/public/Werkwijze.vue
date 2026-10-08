<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, useTemplateRef } from 'vue';
import SiteKruimels from '@/components/site/SiteKruimels.vue';
import SiteTerug from '@/components/site/SiteTerug.vue';
import { tekenPad } from '@/lib/motion';
import type { SectieKop } from '@/types/secties';
import type { StapOpDeSite } from '@/types/werkwijze';

/**
 * De werkwijze, uitgeschreven.
 *
 * Op de voorpagina staan de stappen als korte kaarten naast elkaar; hier
 * krijgt elke stap de ruimte die daar niet is. **De pagina bestaat alleen
 * als er ook echt meer te lezen valt** -- de server weigert hem zolang
 * geen enkele stap een verhaal heeft, en de knop ernaartoe verschijnt om
 * dezelfde reden niet. Een pagina die dezelfde vier zinnen herhaalt is een
 * omweg.
 *
 * **Dezelfde lijn als op de voorpagina, maar verticaal.** Dat hoefde niet
 * apart gebouwd te worden: `tekenPad` tekent zijn pad door de nummers heen
 * waar die ook staan. Hier staan ze onder elkaar, dus loopt de lijn naar
 * beneden.
 *
 * Een stap zonder verhaal blijft gewoon staan, met zijn korte tekst. Hem
 * weglaten zou de nummering onderbreken, en dan klopt de werkwijze niet
 * meer met die op de voorpagina.
 *
 * De layout komt automatisch: alles in `pages/public/` krijgt
 * `PublicLayout`; zie resources/js/app.ts.
 */
const props = defineProps<{
    kop: SectieKop;
    stappen: StapOpDeSite[];
}>();

const svg = useTemplateRef<SVGSVGElement>('svg');
const pad = useTemplateRef<SVGPathElement>('pad');
const punt = useTemplateRef<SVGCircleElement>('punt');
const lijst = useTemplateRef<HTMLElement>('lijst');

/** De alinea's van een verhaal; een witregel maakt een nieuwe alinea. */
const alineas = (tekst: string): string[] =>
    tekst
        .split(/\n\s*\n/)
        .map((stuk) => stuk.trim())
        .filter((stuk) => stuk !== '');

const aantal = computed(() => props.stappen.length);

let opruimen: (() => void) | undefined;

onMounted(() => {
    if (svg.value === null || pad.value === null || lijst.value === null) {
        return;
    }

    const kaarten = Array.from(
        lijst.value.querySelectorAll<HTMLElement>('[data-stap]'),
    );

    opruimen = tekenPad(svg.value, pad.value, punt.value, kaarten);
});

onBeforeUnmount(() => {
    opruimen?.();
});
</script>

<template>
    <Head :title="props.kop.titel" />

    <div class="mx-auto max-w-3xl px-6 py-16 sm:py-24">
        <SiteKruimels :titel="'Werkwijze'" />

        <header class="max-w-2xl">
            <p v-if="props.kop.opschrift" class="brand-projectpagina-opschrift">
                {{ props.kop.opschrift }}
            </p>

            <h1 class="brand-projectpagina-titel">{{ props.kop.titel }}</h1>

            <p v-if="props.kop.inleiding" class="brand-projectpagina-inleiding">
                {{ props.kop.inleiding }}
            </p>
        </header>

        <div class="relative mt-14">
            <!--
                Dezelfde lijn als op de voorpagina. `aria-hidden`: de
                volgorde staat al in de genummerde lijst; een schermlezer
                heeft aan "een golvende lijn" niets.
            -->
            <svg ref="svg" class="brand-werkwijze-lijn" aria-hidden="true">
                <path
                    ref="pad"
                    fill="none"
                    stroke="url(#werkwijze-pagina-verloop)"
                    stroke-width="2"
                    stroke-linecap="round"
                />

                <circle ref="punt" class="brand-werkwijze-punt" r="4" />

                <defs>
                    <linearGradient
                        id="werkwijze-pagina-verloop"
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="1"
                    >
                        <stop offset="0%" stop-color="#0787e8" />
                        <stop offset="100%" stop-color="#13c7f3" />
                    </linearGradient>
                </defs>
            </svg>

            <ol ref="lijst" class="brand-werkwijzepagina">
                <li
                    v-for="(stap, index) in props.stappen"
                    :key="stap.id"
                    data-stap
                    data-reveal
                    class="brand-werkwijzepagina-stap opacity-0"
                >
                    <div class="brand-werkwijzepagina-kop">
                        <span
                            data-stap-punt
                            class="brand-werkwijze-nummer font-mono text-sm"
                        >
                            {{ String(index + 1).padStart(2, '0') }}
                        </span>

                        <span v-if="stap.duur" class="brand-werkwijze-duur">
                            {{ stap.duur }}
                        </span>

                        <!--
                            Hoeveelste van hoeveel. Op de voorpagina zie je
                            dat aan de rij kaarten naast elkaar; hier scrol
                            je en is dat weg.
                        -->
                        <span class="brand-werkwijzepagina-teller">
                            {{
                                $t('Stap :nummer van :totaal', {
                                    nummer: index + 1,
                                    totaal: aantal,
                                })
                            }}
                        </span>
                    </div>

                    <h2 class="brand-werkwijzepagina-titel">
                        {{ stap.titel }}
                    </h2>

                    <p
                        v-if="stap.samenvatting"
                        class="brand-werkwijzepagina-kort"
                    >
                        {{ stap.samenvatting }}
                    </p>

                    <div
                        v-if="stap.verhaal"
                        class="brand-werkwijzepagina-tekst"
                    >
                        <p
                            v-for="(alinea, nr) in alineas(stap.verhaal)"
                            :key="nr"
                        >
                            {{ alinea }}
                        </p>
                    </div>

                    <p
                        v-if="stap.resultaat"
                        class="brand-werkwijzepagina-resultaat"
                    >
                        <span class="brand-werkwijzepagina-resultaat-kop">
                            {{ $t('Wat het oplevert') }}
                        </span>
                        {{ stap.resultaat }}
                    </p>
                </li>
            </ol>
        </div>

        <!-- Het einde van de pagina: je bent klaar met lezen. -->
        <SiteTerug />
    </div>
</template>
