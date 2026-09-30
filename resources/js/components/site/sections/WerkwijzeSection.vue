<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, useTemplateRef } from 'vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import { t } from '@/lib/i18n';
import { tekenPad } from '@/lib/motion';
import type { SectieProps } from '@/types/secties';

/**
 * De werkwijze: van kennismaken tot overdragen.
 *
 * De stappen staan nog in dit bestand; zie de toelichting in
 * DienstenSection.vue over wat er verandert zodra de module er is.
 *
 * **De lijn erboven tekent zichzelf terwijl je scrolt**, met een
 * lichtpuntje dat erlangs reist, en elke stap licht op zodra het punt hem
 * passeert. Dat maakt van vier losse blokjes één doorlopende beweging --
 * en dat is precies wat "werkwijze" hoort over te brengen: het is een
 * volgorde, geen opsomming.
 *
 * De lijn staat alleen op breed scherm. Onder de vier kolommen staan de
 * stappen onder elkaar, en dan zou een horizontale lijn nergens meer
 * langs lopen. Daar doen de gewone reveals het werk.
 */
defineProps<SectieProps>();

// Een `computed`, zodat de stappen meegaan met de taal; zie de
// toelichting bij `services` in DienstenSection.vue.
const steps = computed(() => [
    {
        title: t('Kennismaken'),
        body: t('Wat speelt er, wat is er al, en waar loopt het vast.'),
    },
    {
        title: t('Voorstel'),
        body: t('Een plan met een prijs, en wat er buiten valt.'),
    },
    {
        title: t('Bouwen'),
        body: t('In korte stappen, zodat je onderweg kunt bijsturen.'),
    },
    {
        title: t('Overdragen'),
        body: t(
            'Werkende software plus de documentatie om er zelf mee verder te kunnen.',
        ),
    },
]);

const pad = useTemplateRef<SVGPathElement>('pad');
const punt = useTemplateRef<SVGCircleElement>('punt');
const lijst = useTemplateRef<HTMLElement>('lijst');

let opruimen: (() => void) | undefined;

onMounted(() => {
    if (pad.value === null || lijst.value === null) {
        return;
    }

    const stappen = Array.from(
        lijst.value.querySelectorAll<HTMLElement>('[data-stap]'),
    );

    opruimen = tekenPad(pad.value, punt.value, stappen);
});

onBeforeUnmount(() => {
    opruimen?.();
});
</script>

<template>
    <SiteSection id="werkwijze" :tone="tone" :divided="divided">
        <SectionHeading
            :eyebrow="$t('Werkwijze')"
            :title="$t('Hoe het gaat')"
            :intro="
                $t(
                    'Geen verrassingen achteraf. Je weet van tevoren wat er gebeurt en wat het kost.',
                )
            "
        />

        <div class="relative mt-12">
            <!--
                De lijn ligt achter de nummers en loopt er met een flauwe
                golf doorheen. `preserveAspectRatio="none"` laat hem
                meerekken met de breedte van de sectie; de dikte staat in
                `vector-effect`, zodat die niet meerekt en de lijn overal
                even dik blijft.

                `aria-hidden`: de volgorde staat al in de genummerde lijst
                eronder. Een schermlezer heeft aan "een golvende lijn"
                niets.
            -->
            <svg
                class="brand-werkwijze-lijn"
                viewBox="0 0 1000 60"
                preserveAspectRatio="none"
                aria-hidden="true"
            >
                <path
                    ref="pad"
                    d="M 10 42 C 180 6, 270 6, 420 32 S 700 62, 830 24 L 990 18"
                    fill="none"
                    stroke="url(#werkwijze-verloop)"
                    stroke-width="2"
                    stroke-linecap="round"
                    vector-effect="non-scaling-stroke"
                />

                <circle ref="punt" class="brand-werkwijze-punt" r="4" />

                <defs>
                    <linearGradient id="werkwijze-verloop" x1="0" x2="1">
                        <stop offset="0%" stop-color="#0787e8" />
                        <stop offset="100%" stop-color="#13c7f3" />
                    </linearGradient>
                </defs>
            </svg>

            <ol
                ref="lijst"
                class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4 lg:pt-16"
            >
                <li
                    v-for="(step, index) in steps"
                    :key="step.title"
                    data-stap
                    data-reveal
                    class="brand-werkwijze-stap opacity-0"
                >
                    <span class="brand-werkwijze-nummer font-mono text-sm">
                        {{ String(index + 1).padStart(2, '0') }}
                    </span>
                    <h3 class="mt-2 text-lg font-medium text-white">
                        {{ step.title }}
                    </h3>
                    <p class="mt-1 text-muted-foreground">{{ step.body }}</p>
                </li>
            </ol>
        </div>
    </SiteSection>
</template>
