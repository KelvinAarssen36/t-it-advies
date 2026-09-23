<script setup lang="ts">
import { computed } from 'vue';

/**
 * Een sectie op de publieke site.
 *
 * Alle secties delen dezelfde breedte en dezelfde verticale ruimte. Dat is
 * de reden dat dit component bestaat: zodra elke pagina zijn eigen padding
 * kiest, staat niets meer op één lijn en is dat achteraf niet meer recht te
 * trekken.
 *
 * `tone` bepaalt op welk niveau de sectie ligt. Zie de drie niveaus in
 * docs/architecture/huisstijl-en-kleuren.md: pagina, sectie, verhoogd.
 */
const props = withDefaults(
    defineProps<{
        tone?: 'base' | 'raised' | 'gradient';
        width?: 'default' | 'narrow';
        divided?: boolean;
    }>(),
    { tone: 'base', width: 'default', divided: false },
);

const toneClass = computed(
    () =>
        ({
            base: 'bg-background',
            raised: 'bg-card',
            gradient: 'brand-surface-dark',
        })[props.tone],
);

const widthClass = computed(() =>
    props.width === 'narrow' ? 'max-w-2xl' : 'max-w-6xl',
);
</script>

<template>
    <section
        :class="[toneClass, divided ? 'border-t border-border' : '']"
        class="scroll-mt-16"
    >
        <div :class="widthClass" class="mx-auto px-6 py-20 sm:py-28">
            <slot />
        </div>
    </section>
</template>
