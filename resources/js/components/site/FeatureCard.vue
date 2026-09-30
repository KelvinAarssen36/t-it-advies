<script setup lang="ts">
import { ArrowRight } from '@lucide/vue';
import DienstIcoon from '@/components/site/DienstIcoon.vue';

/**
 * Een kaart op een donkere achtergrond.
 *
 * De glow zit alleen op hover en is bewust zwak. Volgens het palet is glow
 * een accent, geen standaardopmaak: laat je elke kaart gloeien, dan valt
 * niets meer op.
 *
 * **De kaart is een knop zodra er iets te openen valt.** Heeft een dienst
 * een uitgebreide tekst, dan geeft de aanroeper `open` mee en wordt het
 * element een `<button>`: aanklikbaar, maar ook bereikbaar met de
 * tabtoets en te bedienen met Enter. Is er niets te openen, dan blijft
 * het een `<article>` -- een knop die niets doet is erger dan geen knop.
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
    <!--
        `brand-kantel` zet alleen de glans klaar die de cursor volgt; de
        kanteling zelf komt van kantelKaarten() in motion.ts en gebeurt
        alleen waar er een muis is. Zonder die functie is de klasse stil:
        de glans staat dan op doorzichtigheid nul.

        Bewust géén `data-reveal`: de sectie waarin deze kaart staat laat
        hem zelf binnenkomen met `kaartenBinnen()`, en twee dingen die om
        beurten dezelfde doorzichtigheid schrijven laten de kaart
        knipperen. Zet je deze kaart ergens neer zonder zo'n aanroep,
        zorg dan dat hij zichtbaar wordt -- `opacity-0` blijft anders
        staan.
    -->
    <component
        :is="open ? 'button' : 'article'"
        :type="open ? 'button' : undefined"
        class="brand-dienstkaart brand-kantel group relative flex flex-col rounded-xl border border-border bg-card p-6 text-left opacity-0 transition-shadow duration-300 hover:brand-glow"
        :class="{ 'is-klikbaar': open }"
        @click="open ? emit('openen') : undefined"
    >
        <span v-if="icoon" class="brand-dienstkaart-icoon" aria-hidden="true">
            <DienstIcoon :icoon="icoon" class="size-5" />
        </span>

        <h3 class="text-lg font-medium text-white">
            {{ title }}
        </h3>

        <div class="mt-2 text-muted-foreground">
            <slot />
        </div>

        <!--
            De expertisepunten. Ze staan er als losse labels en niet als
            opsomming met bolletjes: het zijn steekwoorden, en die scan je
            eerder dan dat je ze leest.
        -->
        <ul v-if="punten.length > 0" class="brand-dienstkaart-punten">
            <!--
                `--punt` is het nummer in de rij. De CSS rekent daar een
                vertraging mee uit, zodat de labels bij hover van links
                naar rechts oplichten in plaats van allemaal tegelijk.
                Eén getal in een variabele is genoeg; de rest staat in
                app.css.
            -->
            <li
                v-for="(punt, index) in punten"
                :key="punt"
                :style="{ '--punt': index }"
            >
                {{ punt }}
            </li>
        </ul>

        <!--
            `mt-auto` duwt dit naar onderen, zodat het bij kaarten van
            verschillende hoogte toch op één lijn staat.
        -->
        <span v-if="open" class="brand-dienstkaart-meer mt-auto">
            {{ $t('Lees meer') }}
            <ArrowRight class="size-3.5" />
        </span>
    </component>
</template>
