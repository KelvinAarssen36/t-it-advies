<script setup lang="ts">
import { ArrowRight } from '@lucide/vue';
import DienstIcoon from '@/components/site/DienstIcoon.vue';

/**
 * Een dienst als kaart, voor een smal scherm.
 *
 * Op een breed scherm staan de diensten als index met een paneel ernaast
 * (zie DienstenSection); daar bestaat aanwijzen, hier niet. Daarom staat de
 * samenvatting hier altijd zichtbaar, en is er geen hover-effect waar een
 * telefoon toch niets mee kan.
 *
 * **De kaart is een knop zodra er iets te openen valt.** Heeft een dienst
 * een uitgebreide tekst, dan geeft de aanroeper `open` mee en wordt het
 * element een `<button>`: aanklikbaar, maar ook bereikbaar met de tabtoets
 * en te bedienen met Enter. Is er niets te openen, dan blijft het een
 * `<article>` -- een knop die niets doet is erger dan geen knop.
 *
 * Bewust géén `data-reveal`: de sectie laat de kaart zelf binnenkomen met
 * `kaartenBinnen()`, en twee dingen die om beurten dezelfde
 * doorzichtigheid schrijven laten hem knipperen. Zet je deze kaart ergens
 * neer zonder zo'n aanroep, zorg dan dat hij zichtbaar wordt -- `opacity-0`
 * blijft anders staan.
 *
 * Zie docs/architecture/modules/diensten.md.
 */
withDefaults(
    defineProps<{
        title: string;
        /** De sleutel uit App\Enums\ServiceIcon, of niets. */
        icoon?: string | null;
        /** De expertisepunten, als kleine labels onder de tekst. */
        punten?: string[];
        /** Of de kaart een venster opent. */
        open?: boolean;
    }>(),
    { icoon: null, punten: () => [], open: false },
);

const emit = defineEmits<{ openen: [] }>();
</script>

<template>
    <component
        :is="open ? 'button' : 'article'"
        :type="open ? 'button' : undefined"
        class="brand-site-dienst-kaart brand-site-glas opacity-0"
        :data-klikbaar="open ? '' : undefined"
        @click="open ? emit('openen') : undefined"
    >
        <span
            v-if="icoon"
            class="brand-site-dienst-kaart-icoon"
            aria-hidden="true"
        >
            <DienstIcoon :icoon="icoon" class="size-5" />
        </span>

        <h3 class="brand-site-dienst-kaart-titel">
            {{ title }}
        </h3>

        <div class="brand-site-dienst-kaart-zin">
            <slot />
        </div>

        <!--
            De expertisepunten als losse labels en niet als opsomming met
            bolletjes: het zijn steekwoorden, en die scan je eerder dan dat
            je ze leest.
        -->
        <ul v-if="punten.length > 0" class="brand-site-diensten-punten">
            <li v-for="punt in punten" :key="punt">
                {{ punt }}
            </li>
        </ul>

        <span v-if="open" class="brand-site-dienst-kaart-meer">
            {{ $t('Lees meer') }}
            <ArrowRight class="size-4" aria-hidden="true" />
        </span>
    </component>
</template>
