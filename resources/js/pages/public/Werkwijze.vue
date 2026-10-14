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
 * **Dezelfde straal als op de voorpagina, maar verticaal.** `tekenPad`
 * tekent hem door de knopen met de nummers en laat elke knoop oplichten
 * als het licht hem bereikt (`data-bereikt`), met de scroll mee. De
 * stappen zelf komen één keer binnen (`data-reveal`); het verhaal leest
 * dan stil. De stijl staat in resources/css/site/werkwijze.css.
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

/**
 * De golfrichting van `tekenPad` per stap: de eerste en de laatste op de
 * rechte lijn, de middelste om en om tien pixels opzij. De knoop schuift
 * met een transform mee, zodat hij op de straal ligt; `tekenPad` meet met
 * `offsetLeft` en ziet die verschuiving niet. Zie werkwijze.css.
 */
const golf = (index: number): number => {
    if (index === 0 || index === aantal.value - 1) {
        return 0;
    }

    return index % 2 === 0 ? 1 : -1;
};

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

        <header class="brand-site-werkwijzepagina-kop">
            <p v-if="props.kop.opschrift" class="brand-site-opschrift">
                {{ props.kop.opschrift }}
            </p>

            <h1 class="brand-site-subpagina-titel">{{ props.kop.titel }}</h1>

            <p
                v-if="props.kop.inleiding"
                class="brand-site-werkwijzepagina-inleiding"
            >
                {{ props.kop.inleiding }}
            </p>
        </header>

        <div class="brand-site-werkwijzepagina">
            <!--
                Dezelfde straal als op de voorpagina, hier verticaal door
                de knopen. `aria-hidden`: de volgorde staat al in de
                genummerde lijst; een schermlezer heeft aan "een golvende
                lijn" niets.
            -->
            <svg ref="svg" class="brand-site-werkwijze-lijn" aria-hidden="true">
                <path
                    ref="pad"
                    fill="none"
                    stroke="url(#brand-site-werkwijzepagina-verloop)"
                    stroke-width="2"
                    stroke-linecap="round"
                />

                <circle ref="punt" class="brand-site-werkwijze-licht" r="4" />

                <defs>
                    <linearGradient
                        id="brand-site-werkwijzepagina-verloop"
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="1"
                    >
                        <stop
                            offset="0%"
                            class="brand-site-werkwijze-verloop-begin"
                        />
                        <stop
                            offset="100%"
                            class="brand-site-werkwijze-verloop-eind"
                        />
                    </linearGradient>
                </defs>
            </svg>

            <ol ref="lijst" class="brand-site-werkwijzepagina-stappen">
                <li
                    v-for="(stap, index) in props.stappen"
                    :key="stap.id"
                    data-stap
                    data-reveal
                    class="brand-site-werkwijzepagina-stap opacity-0"
                >
                    <!--
                        De knoop met het nummer: daar hangt de straal aan.
                        Het nummer is voor het oog; de volgorde staat al
                        in de `<ol>`.
                    -->
                    <span
                        data-stap-punt
                        class="brand-site-werkwijzepagina-knoop"
                        :style="{ '--golf': golf(index) }"
                        aria-hidden="true"
                    >
                        {{ String(index + 1).padStart(2, '0') }}
                    </span>

                    <div class="brand-site-werkwijzepagina-inhoud">
                        <!--
                            Hoeveelste van hoeveel, en hoe lang. Op de
                            voorpagina zie je dat aan de rij; hier scrol je
                            en is dat weg.
                        -->
                        <p class="brand-site-werkwijzepagina-meta">
                            <span>
                                {{
                                    $t('Stap :nummer van :totaal', {
                                        nummer: index + 1,
                                        totaal: aantal,
                                    })
                                }}
                            </span>
                            <span
                                v-if="stap.duur"
                                class="brand-site-werkwijzepagina-duur"
                            >
                                {{ stap.duur }}
                            </span>
                        </p>

                        <h2 class="brand-site-werkwijzepagina-titel">
                            {{ stap.titel }}
                        </h2>

                        <p
                            v-if="stap.samenvatting"
                            class="brand-site-werkwijzepagina-kort"
                        >
                            {{ stap.samenvatting }}
                        </p>

                        <div
                            v-if="stap.verhaal"
                            class="brand-site-werkwijzepagina-tekst"
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
                            class="brand-site-werkwijzepagina-resultaat"
                        >
                            <span
                                class="brand-site-werkwijzepagina-resultaat-kop"
                            >
                                {{ $t('Wat het oplevert') }}
                            </span>
                            {{ stap.resultaat }}
                        </p>
                    </div>
                </li>
            </ol>
        </div>

        <!-- Het einde van de pagina: je bent klaar met lezen. -->
        <SiteTerug sleutel="werkwijze" />
    </div>
</template>
