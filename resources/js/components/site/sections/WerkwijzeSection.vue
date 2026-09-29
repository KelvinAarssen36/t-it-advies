<script setup lang="ts">
import SectionHeading from '@/components/site/SectionHeading.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import type { SectieProps } from '@/types/secties';

/**
 * De werkwijze: van kennismaken tot overdragen.
 *
 * De stappen staan nog in dit bestand; zie de toelichting in
 * DienstenSection.vue over wat er verandert zodra de module er is.
 */
defineProps<SectieProps>();

const steps = [
    {
        title: 'Kennismaken',
        body: 'Wat speelt er, wat is er al, en waar loopt het vast.',
    },
    {
        title: 'Voorstel',
        body: 'Een plan met een prijs, en wat er buiten valt.',
    },
    {
        title: 'Bouwen',
        body: 'In korte stappen, zodat je onderweg kunt bijsturen.',
    },
    {
        title: 'Overdragen',
        body: 'Werkende software plus de documentatie om er zelf mee verder te kunnen.',
    },
];
</script>

<template>
    <SiteSection id="werkwijze" :tone="tone" :divided="divided">
        <SectionHeading
            eyebrow="Werkwijze"
            title="Hoe het gaat"
            intro="Geen verrassingen achteraf. Je weet van tevoren wat er gebeurt en wat het kost."
        />

        <ol class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
            <li
                v-for="(step, index) in steps"
                :key="step.title"
                data-reveal
                class="opacity-0"
            >
                <span class="font-mono text-sm text-brand-cyan">
                    {{ String(index + 1).padStart(2, '0') }}
                </span>
                <h3 class="mt-2 text-lg font-medium text-white">
                    {{ step.title }}
                </h3>
                <p class="mt-1 text-muted-foreground">{{ step.body }}</p>
            </li>
        </ol>
    </SiteSection>
</template>
