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
 *
 * Met `image` krijgt de sectie een foto als achtergrond. Er komt dan altijd
 * een afdeklaag overheen: witte tekst haalt op de lichte plekken van een
 * foto het contrast niet, en welke plekken dat zijn verschilt per foto.
 * Geef ook `imageMobile` mee -- een telefoon hoeft geen desktopbestand te
 * downloaden.
 */
const props = withDefaults(
    defineProps<{
        tone?: 'base' | 'raised' | 'gradient';
        width?: 'default' | 'narrow';
        divided?: boolean;
        image?: string;
        imageMobile?: string;
        /** Alleen invullen als de foto inhoud is. Een sfeerbeeld blijft leeg. */
        imageAlt?: string;
        /** True voor de eerste sectie van de pagina: die laadt met voorrang. */
        priority?: boolean;
    }>(),
    {
        tone: 'base',
        width: 'default',
        divided: false,
        image: undefined,
        imageMobile: undefined,
        imageAlt: '',
        priority: false,
    },
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
        :class="[
            toneClass,
            divided ? 'border-t border-border' : '',
            image ? 'relative isolate overflow-hidden' : '',
        ]"
        class="scroll-mt-16"
    >
        <template v-if="image">
            <picture>
                <source
                    v-if="imageMobile"
                    media="(max-width: 40rem)"
                    :srcset="imageMobile"
                />
                <img
                    :src="image"
                    :alt="imageAlt"
                    class="absolute inset-0 -z-10 size-full object-cover"
                    :loading="priority ? 'eager' : 'lazy'"
                    :fetchpriority="priority ? 'high' : 'auto'"
                    decoding="async"
                />
            </picture>

            <div
                class="brand-image-overlay absolute inset-0 -z-10"
                aria-hidden="true"
            />
        </template>

        <div :class="widthClass" class="mx-auto px-6 py-20 sm:py-28">
            <slot />
        </div>
    </section>
</template>
