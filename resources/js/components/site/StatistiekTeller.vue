<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatteerGetal } from '@/lib/motion';
import type { StatistiekOpDeSite } from '@/types/statistieken';

/**
 * Eén statistiek als groot getal dat oploopt.
 *
 * Voor alles wat géén percentage is: aantallen, jaren, bereikbaarheid.
 * Het teken ervoor en erachter bepaalt de klant zelf -- "€ 1.200",
 * "500+", "24/7".
 *
 * **Er staat geen procentteken standaard achter**, anders dan bij de
 * balk en de ring. Daar is het percentage de hele betekenis; hier zou
 * het een getal vervalsen.
 *
 * Het getal zelf wordt door `vulStatistieken` geschreven, inclusief de
 * duizendtalscheiding -- die verschilt per taal en hoort dus niet in
 * CSS. `data-vul` markeert het vak, `data-getal` het getal.
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
</script>

<template>
    <div data-vul class="brand-statistiek-teller">
        <span class="brand-statistiek-teller-getal">
            <span v-if="props.item.voor" class="brand-statistiek-teken">
                {{ props.item.voor }}
            </span>
            <span data-getal>{{ getal }}</span>
            <span v-if="props.item.na" class="brand-statistiek-teken">
                {{ props.item.na }}
            </span>
        </span>

        <span class="brand-statistiek-naam">{{ props.item.naam }}</span>

        <p v-if="props.item.notitie" class="brand-statistiek-notitie">
            {{ props.item.notitie }}
        </p>
    </div>
</template>
