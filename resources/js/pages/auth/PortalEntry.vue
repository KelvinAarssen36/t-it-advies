<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BrandLoader from '@/components/BrandLoader.vue';

/**
 * Het tussenscherm tussen inloggen en het portaal.
 *
 * De schil komt uit [`BrandLoader`](../../components/BrandLoader.vue), die
 * ook de weg terug naar de website verzorgt. Hier staat alleen wat er anders
 * is: waar je heen gaat en wat er te lezen valt.
 *
 * De variant `portaal` toont het skelet van het portaal -- de zijbalk, de
 * kop en de vlakken, in jouw eigen thema. Wat er daarna komt staat op
 * precies dezelfde plek, dus het voelt alsof de pagina zich vult in plaats
 * van dat hij wordt vervangen.
 *
 * De bestemming is aan de serverkant gecontroleerd -- zie
 * PortalEntryController -- dus hier hoeft niets meer getoetst te worden.
 */
defineProps<{
    destination: string;
    duration: number;
}>();
</script>

<template>
    <Head :title="$t('Even geduld')" />

    <BrandLoader
        variant="portaal"
        :destination="destination"
        :duration="duration"
        :message="$t('Het portaal wordt klaargezet…')"
        :continue-label="$t('Nu doorgaan')"
    />
</template>
