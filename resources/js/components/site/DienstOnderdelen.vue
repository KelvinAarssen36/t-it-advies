<script setup lang="ts">
import { computed } from 'vue';
import { onderdeelIcoon } from '@/lib/onderdeelIcoon';

/**
 * De onderdelen van een dienst: "monitoring", "back-ups", "updates".
 *
 * **Eén geheel en geen losse pillen.** Hier stonden kleine labels met een
 * stip ervoor, en dat las als een verzameling tags: los, karig, en elk
 * label even zwaar als een knop. Nu is het een raster van gelijke vakjes
 * met een haarlijn ertussen, zoals de kerngegevens: elk vakje een klein
 * teken en het steekwoord, samen één blok dat zegt "dit valt eronder".
 *
 * **Niet klikbaar, en dat zie je.** Geen rand per vakje, geen handje, geen
 * indrukken. Onder de muis licht het teken op -- het wijst aan, het belooft
 * niets. Zie "Eén taal voor alles wat je aanraakt" in
 * docs/architecture/frontend-en-animatie.md.
 *
 * Het teken kiest de site zelf op grond van het woord, zie
 * `onderdeelIcoon`. `--plek` is de plaats in de rij; de CSS gebruikt hem
 * om de vakjes na elkaar te laten oplichten als een dienst in beeld komt.
 *
 * Gebruikt in het paneel (breed), op de kaart (smal) en in DienstVenster,
 * zodat een onderdeel er overal hetzelfde uitziet.
 */
const props = defineProps<{ punten: string[] }>();

const onderdelen = computed(() =>
    props.punten.map((tekst) => ({ tekst, icoon: onderdeelIcoon(tekst) })),
);
</script>

<template>
    <ul
        v-if="onderdelen.length > 0"
        class="brand-site-onderdelen"
        :data-oneven="onderdelen.length % 2 === 1 ? '' : undefined"
    >
        <li
            v-for="(onderdeel, plek) in onderdelen"
            :key="`${plek}-${onderdeel.tekst}`"
            class="brand-site-onderdeel"
            :style="{ '--plek': plek }"
        >
            <span class="brand-site-onderdeel-teken" aria-hidden="true">
                <component :is="onderdeel.icoon" class="size-3.5" />
            </span>
            <span class="brand-site-onderdeel-tekst">{{
                onderdeel.tekst
            }}</span>
        </li>
    </ul>
</template>
