<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    ref,
    useId,
    watchEffect,
} from 'vue';
import { meldAan, meldAf, past, zoekt } from '@/lib/handleiding';

/**
 * Eén onderwerp op de handleidingspagina.
 *
 * Een kop met een pictogram, de uitleg eronder, en optioneel een strookje
 * waarin het besproken ding echt te zien is. Dat laatste is de reden dat
 * deze pagina in het portaal staat en niet in een PDF: een knop uitleggen
 * met de knop ernaast scheelt een alinea.
 *
 * **`onder` zegt bij welk scherm dit hoort.** Zonder dat staat een kaart
 * als "De kop en de cijfers" er als los onderwerp bij, en dan zoekt de
 * eigenaar zich suf: hij weet niet dat het achter een knop op de
 * ervaringenpagina zit. Met dat label leest de kaart als een onderdeel
 * van dat scherm en niet als iets ernaast.
 *
 * Zie resources/js/pages/settings/Documentatie.vue en
 * docs/architecture/uitleg-voor-de-eigenaar.md.
 */
const props = defineProps<{
    titel: string;
    icoon: LucideIcon;
    /** Het scherm waar dit onderwerp onder valt, als het er een heeft. */
    onder?: string;
}>();

/*
 * --- Zoeken ---------------------------------------------------------------
 *
 * De kaart doorzoekt zichzelf: na het tekenen leest hij de tekst die er
 * écht staat uit zijn eigen element. Daardoor is er geen tweede lijst met
 * trefwoorden die kan verouderen -- zie lib/handleiding.ts.
 *
 * `textContent` en geen eigen opsomming van de slots: dan telt alles mee
 * wat de lezer ziet, inclusief de tekst op een knop in het voorbeeld.
 */
const wortel = ref<HTMLElement>();
const inhoud = ref('');

const sleutel = useId();

onMounted(() => {
    inhoud.value = wortel.value?.textContent ?? '';
});

const zichtbaar = computed(() => past(`${props.titel} ${inhoud.value}`));

/*
 * De pagina moet weten of er íets gevonden is, anders staat er bij nul
 * treffers een leeg scherm zonder uitleg. Elke kaart meldt daarom zijn
 * eigen uitkomst; `watchEffect` houdt dat vanzelf bij.
 */
watchEffect(() => meldAan(sleutel, zichtbaar.value));

onBeforeUnmount(() => meldAf(sleutel));
</script>

<template>
    <article
        v-show="zichtbaar"
        ref="wortel"
        class="brand-uitleg-kaart"
        :data-onder="onder ? '' : undefined"
        :data-treffer="zoekt && zichtbaar ? '' : undefined"
    >
        <header class="flex items-center gap-3">
            <span class="brand-uitleg-merk" aria-hidden="true">
                <component :is="icoon" class="size-4" />
            </span>

            <div class="min-w-0">
                <p v-if="onder" class="brand-uitleg-onder">
                    {{ $t('Onderdeel van :scherm', { scherm: onder }) }}
                </p>
                <h3 class="text-sm font-semibold">{{ titel }}</h3>
            </div>
        </header>

        <div class="mt-3 space-y-2.5 text-sm text-pretty text-muted-foreground">
            <slot />
        </div>

        <!--
            Het voorbeeld staat ná de uitleg en niet ervoor: eerst weet je
            waar je naar kijkt, dan pas zie je het.

            De gestippelde rand zegt voor wie kijkt dat dit een voorbeeld
            is. Voor wie luistert doet de verborgen kop dat; de knoppen
            erin staan zelf buiten de tabvolgorde, want ze doen niets.
        -->
        <div v-if="$slots.voorbeeld" class="brand-uitleg-voorbeeld mt-3.5">
            <h4 class="sr-only">{{ $t('Voorbeeld') }}</h4>
            <slot name="voorbeeld" />
        </div>
    </article>
</template>
