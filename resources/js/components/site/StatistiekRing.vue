<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatteerGetal } from '@/lib/getallen';
import type { StatistiekOpDeSite } from '@/types/statistieken';

/**
 * Eén statistiek als wijzer die zichzelf tekent.
 *
 * De blikvanger van deze module. Drie of vier naast elkaar zijn
 * indrukwekkend; tien worden een dashboard, en daarom zegt het
 * beheerscherm erbij dat je hem voor je beste cijfers gebruikt.
 *
 * **De boog is CSS en geen JavaScript.** `pathLength="100"` rekent de
 * omtrek van de cirkel om naar honderd eenheden, wat hij ook meet.
 * Daarmee is de boog een `stroke-dashoffset` die uit twee variabelen
 * komt: `--doel` (het percentage van deze statistiek) en `--vulling`
 * (hoe ver de animatie is).
 *
 * Dat had ook met DrawSVG gekund, en dat was de eerste opzet. Het nadeel
 * bleek meteen in het beheerscherm: daar scrollt niets, dus daar stond
 * de ring altijd helemaal vol. Nu klopt hij overal -- in het
 * voorbeeldvenster, op de site, en zelfs als er helemaal geen
 * JavaScript draait.
 *
 * De cirkel begint bovenaan en gaat met de klok mee. Dat is de `rotate`
 * op de groep: een SVG-cirkel begint standaard rechts, en een ring die
 * op drie uur begint leest als scheef.
 *
 * Zie docs/architecture/modules/statistieken.md.
 */
const props = defineProps<{ item: StatistiekOpDeSite }>();

/**
 * Het getal zoals het er staat vóórdat de animatie begint.
 *
 * Dit is de eindwaarde en niet nul, en dat is met opzet: zonder
 * JavaScript staat er dan het goede getal. `vulStatistieken` zet hem
 * bij het opstarten op nul en telt dan op. Zie motion.ts.
 */
const scheiding = computed(() =>
    (usePage().props.locale as string | undefined) === 'en' ? ',' : '.',
);

const getal = computed(() =>
    formatteerGetal(props.item.waarde, scheiding.value),
);

/** De boog loopt tot het percentage, dus nooit verder dan rond. */
const doel = computed(() => Math.min(100, Math.max(0, props.item.waarde)));
</script>

<template>
    <div
        data-vul
        class="brand-site-statistiek-ring"
        :style="{ '--doel': doel }"
    >
        <div class="brand-site-statistiek-ring-vak">
            <!--
                De wijzerplaat: zestig maatstreepjes rondom, als op een
                meter. Een eigen laag, zodat hij zich met een masker rond
                kan tekenen terwijl de naald uitslaat; zie
                `.brand-site-statistiek-ring-schaallaag`. Een streepjeslijn
                op een cirkel met `pathLength="100"`, dus geen zestig losse
                lijntjes.
            -->
            <svg
                class="brand-site-statistiek-ring-schaallaag"
                viewBox="0 0 120 120"
                aria-hidden="true"
            >
                <circle
                    class="brand-site-statistiek-ring-schaal"
                    cx="60"
                    cy="60"
                    r="57"
                    pathLength="100"
                />
            </svg>
            <svg viewBox="0 0 120 120" aria-hidden="true">
                <!-- De baan: haarfijn, zodat je ziet hoe ver het had gekund. -->
                <circle
                    class="brand-site-statistiek-ring-baan"
                    cx="60"
                    cy="60"
                    r="50"
                />

                <g transform="rotate(-90 60 60)">
                    <circle
                        class="brand-site-statistiek-ring-boog"
                        cx="60"
                        cy="60"
                        r="50"
                        pathLength="100"
                    />

                    <!--
                        Het lichtpunt dat na het tekenen af en toe een
                        rondje over de boog maakt: de rustanimatie. In
                        dezelfde gedraaide groep als de boog, anders begint
                        het op drie uur en de boog bovenaan.
                    -->
                    <circle
                        class="brand-site-statistiek-ring-vonk"
                        cx="60"
                        cy="60"
                        r="50"
                        pathLength="100"
                    />
                </g>
            </svg>

            <span class="brand-site-statistiek-ring-getal font-data">
                <span v-if="props.item.voor">{{ props.item.voor }}</span>
                <span data-getal>{{ getal }}</span>
                <span class="brand-site-statistiek-ring-teken">
                    {{ props.item.na ?? '%' }}
                </span>
            </span>
        </div>

        <span class="brand-site-statistiek-naam">{{ props.item.naam }}</span>

        <p v-if="props.item.notitie" class="brand-site-statistiek-notitie">
            {{ props.item.notitie }}
        </p>
    </div>
</template>
