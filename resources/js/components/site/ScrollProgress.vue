<script setup lang="ts">
import { onBeforeUnmount, onMounted, useTemplateRef } from 'vue';
import { trackScrollProgress } from '@/lib/motion';

/**
 * De voortgangsbalk bovenaan de publieke site.
 *
 * `aria-hidden` op de baan: het is versiering bovenop iets wat de bezoeker
 * al weet -- hij scrollt zelf. Een schermlezer heeft er niets aan en zou er
 * alleen door onderbroken worden.
 *
 * Deze balk blijft ook staan bij `prefers-reduced-motion`, in tegenstelling
 * tot de rest van de animatielaag. Hij volgt namelijk de beweging die de
 * bezoeker zélf maakt; er beweegt niets uit zichzelf. Juist bij wie langzaam
 * en bewust scrollt is hij nuttig.
 */

const bar = useTemplateRef<HTMLElement>('bar');

let stop: (() => void) | undefined;

onMounted(() => {
    if (bar.value) {
        stop = trackScrollProgress(bar.value);
    }
});

onBeforeUnmount(() => {
    stop?.();
});
</script>

<template>
    <div class="brand-scroll-progress" aria-hidden="true">
        <span ref="bar" />
    </div>
</template>
