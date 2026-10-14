<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatteerGetal } from '@/lib/getallen';
import type { StatistiekOpDeSite } from '@/types/statistieken';

/**
 * Eén statistiek als lijn die over een schaalverdeling volloopt.
 *
 * Het werkpaard van deze module: tien vaardigheden onder elkaar blijven
 * leesbaar, en je ziet in één blik welke eruit springen.
 *
 * **Dit component beweegt zelf niets.** De vulling komt binnen als de
 * CSS-variabele `--vulling` (0 tot 1) die `vulStatistieken` op het vak
 * zet, en de balk schaalt daarop. Zo hangt alles in het blok aan
 * dezelfde voortgang en kan er niets uit de pas lopen.
 *
 * `data-vul` markeert het vak voor die helper, `data-getal` het element
 * waar het oplopende getal in komt.
 *
 * Zie docs/architecture/modules/statistieken.md.
 */
const props = defineProps<{ item: StatistiekOpDeSite }>();

/**
 * Het getal zoals het er staat vóórdat de animatie begint.
 *
 * De eindwaarde en niet nul: zonder JavaScript staat er dan het goede
 * getal. `vulStatistieken` zet hem bij het opstarten op nul en telt dan
 * op.
 */
const scheiding = computed(() =>
    (usePage().props.locale as string | undefined) === 'en' ? ',' : '.',
);

const getal = computed(() =>
    formatteerGetal(props.item.waarde, scheiding.value),
);

/** De balk loopt tot het percentage, dus nooit verder dan vol. */
const doel = computed(() => Math.min(100, Math.max(0, props.item.waarde)));
</script>

<template>
    <!--
        `data-vul` is de aanhaking voor `vulStatistieken`, en `--doel` het
        percentage waar de lijn naartoe loopt.
    -->
    <div
        data-vul
        class="brand-site-statistiek-balk"
        :style="{ '--doel': doel }"
    >
        <div class="brand-site-statistiek-balk-kop">
            <span class="brand-site-statistiek-naam">
                {{ props.item.naam }}
            </span>

            <span class="brand-site-statistiek-balk-waarde font-data">
                <span v-if="props.item.voor">{{ props.item.voor }}</span>
                <span data-getal>{{ getal }}</span>
                <span>{{ props.item.na ?? '%' }}</span>
            </span>
        </div>

        <!--
            De schaal en de lijn. Geen gevulde baan eronder maar een
            schaalverdeling, zoals op een meetinstrument: de streepjes
            zeggen hoe ver het had gekund, zonder een blok dat de helft
            van de ruimte grijs maakt. `aria-hidden` omdat het getal er
            als tekst naast staat.
        -->
        <div class="brand-site-statistiek-schaal" aria-hidden="true">
            <span class="brand-site-statistiek-lijn" />
        </div>

        <p v-if="props.item.notitie" class="brand-site-statistiek-notitie">
            {{ props.item.notitie }}
        </p>
    </div>
</template>
