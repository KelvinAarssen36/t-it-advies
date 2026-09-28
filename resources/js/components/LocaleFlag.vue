<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Het vlaggetje van één taal.
 *
 * Dit is de enige plek waar een taalcode aan een vlag wordt gekoppeld. Bij
 * twee talen zou een ternair in elk scherm ook werken, maar dat zijn dan net
 * zoveel plekken om te vergeten zodra er een derde taal bij komt.
 *
 * Zie docs/architecture/vertalingen.md.
 */
const props = withDefaults(
    defineProps<{
        locale: string;
        size?: 'sm' | 'md' | 'lg';
        class?: HTMLAttributes['class'];
    }>(),
    { size: 'sm' },
);

/**
 * Let op: een taal is geen land. Engels krijgt hier de vlag van het Verenigd
 * Koninkrijk, en dat is een keuze. Bij Nederlands en Engels valt dat niet op;
 * bij een taal die in meerdere landen wordt gesproken kies je er een kant
 * mee, en is een hokje met de taalcode eerlijker.
 */
const vlaggen: Record<string, string> = {
    nl: '/flags/nl.svg',
    en: '/flags/gb.svg',
};

/**
 * Alle drie ongeveer 3:2, dezelfde verhouding als de viewBox van de
 * bestanden, zodat er niets wordt uitgerekt.
 */
const maten = {
    sm: 'h-3 w-4.5', // accountmenu
    md: 'h-3.5 w-5', // landingspagina en inlogscherm
    lg: 'h-4.5 w-7', // instellingen
} as const;

const page = usePage();

const bron = computed(() => vlaggen[props.locale] ?? vlaggen.nl);

/**
 * De alt is de taalnaam en niet het land: een schermlezer leest dan
 * "Nederlands" waar een ziende het vlaggetje naast dat woord ziet. Zou hier
 * "Nederlandse vlag" staan, dan hoor je het twee keer.
 */
const naam = computed(() => page.props.locales?.[props.locale] ?? props.locale);
</script>

<template>
    <!--
        object-cover is een vangnet: kiest iemand later een maat die niet
        klopt, dan snijdt de vlag af in plaats van scheef te trekken.

        De ronding is net genoeg om de hoeken te breken zodat het een plaatje
        wordt in plaats van een gekleurd blokje. Meer, en het leest als knop.
    -->
    <img
        :src="bron"
        :alt="naam"
        :class="
            cn('rounded-[2px] object-cover', maten[props.size], props.class)
        "
        width="48"
        height="32"
        decoding="async"
    />
</template>
