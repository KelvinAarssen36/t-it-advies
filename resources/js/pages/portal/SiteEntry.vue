<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BrandLoader from '@/components/BrandLoader.vue';

/**
 * Het tussenscherm van het portaal terug naar de website.
 *
 * De spiegel van [`auth/PortalEntry`](../auth/PortalEntry.vue), en om
 * dezelfde reden: de publieke site heeft een eigen bundel met de hele
 * animatielaag erin -- GSAP en Lenis, ruim honderd kilobyte -- die het
 * portaal nooit ophaalt. Zonder dit scherm kijk je daar even tegen een
 * pagina aan die zich nog aan het opbouwen is.
 *
 * Die tijd wordt hier nuttig gebruikt: BrandLoader haalt de bestemming
 * alvast op terwijl het scherm staat.
 *
 * Er zit nog een tweede reden achter. Het portaal kan in het lichte thema
 * staan en de website staat altijd donker. Dit scherm is donker, dus het
 * bereidt je voor op waar je heen gaat in plaats van je er middenin te
 * laten vallen.
 */
defineProps<{
    destination: string;
    duration: number;
}>();
</script>

<template>
    <Head :title="$t('Even geduld')" />

    <BrandLoader
        variant="website"
        :destination="destination"
        :duration="duration"
        :message="$t('De website wordt geopend…')"
        :continue-label="$t('Nu doorgaan')"
    />
</template>
