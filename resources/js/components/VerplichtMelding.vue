<script setup lang="ts">
import { AlertCircle } from '@lucide/vue';
import { computed } from 'vue';

/**
 * Wat er nog moet gebeuren voordat er opgeslagen kan worden, boven de
 * knoppen van een venster.
 *
 * Hoort bij `useVerplicht()` uit lib/verplicht.ts. Zolang de klant niets
 * heeft aangeraakt is `ontbreekt` leeg en staat hier dus niets; pas als
 * hij op de grijze knop drukt verschijnt de regel.
 *
 * **Twee plekken, geen keuze tussen de twee.** Deze regel noemt de velden
 * bij naam zodat je ze ook ziet als ze buiten beeld staan -- een lang
 * venster scrollt, en dan zie je een markering onder een veld helemaal
 * niet. Onder het veld zelf staat de tweede helft: daar wijst `melding()`
 * precies aan wélk vakje leeg is. Samen is er geen zoekwerk meer.
 *
 * **`boodschap` is voor het venster waar "nog in te vullen" niet klopt.**
 * Bij de indeling van de cijfers gaat het niet om een leeg veld maar om
 * twee groepen met dezelfde naam; daar staat de uitleg al bij de groep
 * zelf en hoeft deze regel alleen de weg te wijzen.
 *
 * `role="alert"` zorgt dat een schermlezer hem voorleest op het moment dat
 * hij verschijnt; zonder dat gebeurt er voor die gebruiker helemaal niets
 * als hij op de knop drukt.
 */
const props = withDefaults(
    defineProps<{
        /** De namen van de velden die nog leeg zijn. */
        ontbreekt?: string[];

        /** Een eigen zin in plaats van de opsomming. */
        boodschap?: string;

        /** Of die eigen zin te zien moet zijn. */
        toon?: boolean;
    }>(),
    { ontbreekt: () => [], boodschap: undefined, toon: false },
);

const zichtbaar = computed(() =>
    props.boodschap === undefined ? props.ontbreekt.length > 0 : props.toon,
);
</script>

<template>
    <p v-if="zichtbaar" class="brand-verplicht-melding" role="alert">
        <AlertCircle class="size-4 shrink-0" aria-hidden="true" />
        <span v-if="boodschap !== undefined">{{ boodschap }}</span>
        <span v-else>
            {{ $t('Vul eerst nog in:') }}
            <strong class="font-medium">{{ ontbreekt.join(', ') }}</strong>
        </span>
    </p>
</template>
