<script setup lang="ts">
/**
 * Een kaart op een donkere achtergrond.
 *
 * De glow zit alleen op hover en is bewust zwak. Volgens het palet is glow
 * een accent, geen standaardopmaak: laat je elke kaart gloeien, dan valt
 * niets meer op.
 */
defineProps<{
    title: string;
    eyebrow?: string;
}>();
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
    <article
        class="brand-kantel group relative rounded-xl border border-border bg-card p-6 opacity-0 transition-shadow duration-300 hover:brand-glow"
    >
        <p
            v-if="eyebrow"
            class="mb-3 font-mono text-xs tracking-widest text-brand-cyan uppercase"
        >
            {{ eyebrow }}
        </p>

        <h3 class="text-lg font-medium text-white">
            {{ title }}
        </h3>

        <div class="mt-2 text-muted-foreground">
            <slot />
        </div>
    </article>
</template>
