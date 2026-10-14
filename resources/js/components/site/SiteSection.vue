<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, useTemplateRef } from 'vue';
import { plaatOpkomst } from '@/lib/motion';

/**
 * Een sectie op de publieke site.
 *
 * Alle secties delen dezelfde breedte en dezelfde verticale ruimte. Dat is
 * de reden dat dit component bestaat: zodra elke pagina zijn eigen padding
 * kiest, staat niets meer op één lijn en is dat achteraf niet meer recht te
 * trekken.
 *
 * `tone` bepaalt op welk niveau de sectie ligt, en komt van Welcome.vue op
 * **plek in de rij**: `base` is een open veld op het donker, `raised` een
 * iets ingesprongen plaat van Deep Navy met een glazen rand. Doordat het op
 * plek gaat en niet op naam, liggen er nooit twee platen tegen elkaar --
 * hoe de klant de onderdelen ook schuift. `divided` zet de horizonlijn
 * boven een open veld.
 *
 * Met `image` krijgt de sectie een foto als achtergrond. Er komt dan altijd
 * een afdeklaag overheen: witte tekst haalt op de lichte plekken van een
 * foto het contrast niet, en welke plekken dat zijn verschilt per foto.
 * Geef `imageSrcset` mee met meerdere breedtes; de browser kiest zelf op
 * schermbreedte en pixeldichtheid.
 *
 * **Een plaat komt uit de diepte omhoog terwijl je hem binnenscrolt**
 * (`plaatOpkomst`), alleen op een breed scherm. Het open veld beweegt
 * niet: zo wisselen rust en beweging elkaar af, net als de tonen.
 *
 * Zie docs/architecture/pagina-indeling.md, "Veld en plaat".
 */
const props = withDefaults(
    defineProps<{
        tone?: 'base' | 'raised' | 'gradient';
        width?: 'default' | 'narrow';
        divided?: boolean;
        image?: string;
        /** Varianten met breedtes, bijvoorbeeld '/klein.webp 900w, /groot.webp 3344w'. */
        imageSrcset?: string;
        /** Hoe breed de foto op de pagina staat. Standaard de volle breedte. */
        imageSizes?: string;
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
        imageSrcset: undefined,
        imageSizes: '100vw',
        imageAlt: '',
        priority: false,
    },
);

const plaat = useTemplateRef<HTMLElement>('plaat');
let stopOpkomst: (() => void) | undefined;

onMounted(() => {
    if (props.tone === 'raised' && plaat.value !== null) {
        stopOpkomst = plaatOpkomst(plaat.value);
    }
});

onBeforeUnmount(() => stopOpkomst?.());

const widthClass = computed(() =>
    props.width === 'narrow' ? 'max-w-3xl' : '',
);
</script>

<template>
    <section
        class="brand-site-sectie"
        :class="[
            tone === 'gradient' ? 'brand-surface-dark' : '',
            image ? 'isolate overflow-hidden' : '',
        ]"
        :data-toon="tone"
        :data-lijn="divided ? '' : undefined"
    >
        <template v-if="image">
            <img
                :src="image"
                :srcset="imageSrcset"
                :sizes="imageSrcset ? imageSizes : undefined"
                :alt="imageAlt"
                class="absolute inset-0 -z-10 size-full object-cover"
                :loading="priority ? 'eager' : 'lazy'"
                :fetchpriority="priority ? 'high' : 'auto'"
                decoding="async"
            />

            <div
                class="brand-image-overlay absolute inset-0 -z-10"
                aria-hidden="true"
            />
        </template>

        <!--
            De plaat is er altijd, ook bij een open veld: dan is hij
            onzichtbaar. Zo heeft elke sectie dezelfde opbouw en hoeft een
            onderdeel niet te weten op welke plek het staat.
        -->
        <div ref="plaat" class="brand-site-sectie-plaat">
            <div :class="widthClass" class="brand-site-raam">
                <slot />
            </div>
        </div>
    </section>
</template>
