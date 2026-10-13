<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

/**
 * Het merkteken van @T IT Advies.
 *
 * Een afbeelding en geen SVG: het logo is een render met verlopen en
 * metallic vlakken, en dat laat zich niet in vectoren vangen zonder het
 * karakter te verliezen. Het bestand is 4 kB.
 *
 * Komt er ooit een echte SVG-versie, vervang dan alleen dit component --
 * de rest van de applicatie gebruikt het via deze ene plek.
 */

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <img
        src="/images/logo-merk.webp"
        alt=""
        :class="className"
        v-bind="$attrs"
        decoding="async"
    />
</template>
