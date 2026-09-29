<script setup lang="ts">
import FeatureCard from '@/components/site/FeatureCard.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import type { SectieProps } from '@/types/secties';

/**
 * De diensten.
 *
 * De teksten staan nog in dit bestand. Zodra de module er is komen ze uit
 * de database en meldt dit onderdeel zijn teller aan in AppServiceProvider,
 * waarna het vanzelf van de website verdwijnt zolang er niets in staat.
 *
 * `tone` en `divided` komen van buiten: welke achtergrond deze sectie
 * krijgt hangt af van waar hij in de rij staat, en dat weet hij zelf niet.
 * Zie Welcome.vue.
 */
defineProps<SectieProps>();

const services = [
    {
        eyebrow: '01',
        title: 'Advies',
        body: 'Meedenken over wat er nodig is en wat niet. Een keuze die je over drie jaar nog kunt uitleggen.',
    },
    {
        eyebrow: '02',
        title: 'Realisatie',
        body: 'Bouwen wat er is afgesproken, met documentatie die meegroeit met de code.',
    },
    {
        eyebrow: '03',
        title: 'Beheer',
        body: 'Draaiend houden, in de gaten houden en bijsturen voordat het een storing wordt.',
    },
];
</script>

<template>
    <SiteSection id="diensten" :tone="tone" :divided="divided">
        <SectionHeading
            eyebrow="Diensten"
            title="Wat we doen"
            intro="Drie dingen, en die goed. De rest besteden we liever uit dan half te doen."
        />

        <div class="mt-12 grid gap-6 sm:grid-cols-3">
            <FeatureCard
                v-for="service in services"
                :key="service.title"
                :eyebrow="service.eyebrow"
                :title="service.title"
            >
                {{ service.body }}
            </FeatureCard>
        </div>
    </SiteSection>
</template>
