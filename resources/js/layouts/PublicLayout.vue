<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import SiteFooter from '@/components/site/SiteFooter.vue';
import SiteHeader from '@/components/site/SiteHeader.vue';
import { revealOnScroll, startSmoothScroll } from '@/lib/motion';

/**
 * De publieke site.
 *
 * Staat altijd in het donkere thema, ook als de bezoeker licht heeft
 * ingesteld. Midnight Navy is het fundament van de huisstijl; een lichte
 * variant van dezelfde pagina zou een tweede ontwerp zijn en geen
 * instelling. Het beheergedeelte volgt de voorkeur van de gebruiker wél.
 *
 * De animatielaag zit hier en niet in de pagina's zelf: smooth scrolling en
 * de scroll-reveals horen bij de hele site, en zo hoeft elke nieuwe pagina
 * de opruimfuncties niet opnieuw te regelen.
 *
 * Zie docs/architecture/frontend-en-animatie.md.
 */

const root = ref<HTMLElement | null>(null);

let stopScroll: (() => void) | undefined;
let stopReveal: (() => void) | undefined;
let stopListening: (() => void) | undefined;

const scanReveals = () => {
    stopReveal?.();
    stopReveal = revealOnScroll('[data-reveal]', root.value);
};

onMounted(() => {
    stopScroll = startSmoothScroll();
    scanReveals();

    // Deze layout blijft bij een Inertia-navigatie staan, dus onMounted
    // draait maar één keer. Zonder deze herscan blijven de reveals van een
    // volgende pagina op opacity 0 hangen -- onzichtbare inhoud.
    stopListening = router.on('navigate', () => {
        void nextTick(scanReveals);
    });
});

onBeforeUnmount(() => {
    stopListening?.();
    stopReveal?.();
    stopScroll?.();
});
</script>

<template>
    <div
        id="top"
        ref="root"
        class="dark min-h-screen bg-background text-foreground"
    >
        <SiteHeader />

        <main>
            <slot />
        </main>

        <SiteFooter />
    </div>
</template>
