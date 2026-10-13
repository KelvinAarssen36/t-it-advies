<script setup lang="ts">
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { home } from '@/routes';
import type { BreadcrumbItem } from '@/types/navigation';

/**
 * Het kruimelpad bovenaan een subpagina van de publieke site.
 *
 * **Dit zegt waar je bent; de kop zegt waar je heen kunt.** Twee
 * verschillende vragen, en daarom staan ze naast elkaar zonder te
 * dubbelen. Hier stond eerst op elke subpagina een teruglink, terwijl de
 * kop er ook al een had -- dezelfde bestemming, twee keer, en op /privacy
 * zelfs drie keer.
 *
 * **De vorm komt uit het portaal** ([`Breadcrumbs.vue`](../Breadcrumbs.vue)),
 * dus de scheidingstekens, de ruimte ertussen en de markering voor een
 * schermlezer staan op één plek. Wat hier staat is alleen: er zijn altijd
 * twee niveaus, en het eerste heet "Voorpagina".
 *
 * Dat woord is met opzet hetzelfde als op de knop onderaan en nergens meer
 * "de website": je gaat niet naar de website -- daar ben je al -- je gaat
 * naar de voorpagina.
 */
const props = defineProps<{
    /** De naam van deze pagina, in het Nederlands; dat is ook de sleutel. */
    titel: string;
}>();

/**
 * Het laatste niveau krijgt het adres van de pagina zelf.
 *
 * Het wordt nooit als link getekend -- `Breadcrumbs` maakt van het laatste
 * item een `BreadcrumbPage` -- maar `BreadcrumbItem` vraagt een adres, en
 * een leeg adres doorgeven is minder eerlijk dan het eigen adres.
 */
const kruimels = computed<BreadcrumbItem[]>(() => [
    { title: 'Voorpagina', href: home().url },
    { title: props.titel, href: '' },
]);
</script>

<template>
    <nav class="brand-sitekruimels" :aria-label="$t('Kruimelpad')">
        <Breadcrumbs :breadcrumbs="kruimels" />
    </nav>
</template>
