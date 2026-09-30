<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, useTemplateRef } from 'vue';
import FeatureCard from '@/components/site/FeatureCard.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import { t } from '@/lib/i18n';
import { kaartenBinnen, kantelKaarten } from '@/lib/motion';
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
 *
 * **De kaarten komen één voor één binnen** zodra ze in beeld scrollen:
 * van onderen op, iets te klein en licht van je af gekanteld, en dan
 * zetten ze zich recht.
 *
 * Hier zat eerst een vastgezette sectie waarin ze uit een stapel
 * openwaaierden. Die is eruit: de pin duwde de sectiekop achter de
 * plakkende balk en liet een schermhoogte aan lege ruimte onder de
 * kaarten achter. Zie `kaartenBinnen` in motion.ts.
 *
 * Zonder pin werkt dit onderdeel vanzelf op elke plek in de rij, en
 * verdwijnt het netjes als de klant het uitzet.
 */
defineProps<SectieProps>();

/**
 * De drie diensten.
 *
 * Een `computed` en geen kale lijst: `t()` leest de taal uit de props van
 * Inertia, en die veranderen zodra de bezoeker wisselt. Zou dit een
 * gewone constante zijn, dan stond de vertaling van het moment van laden
 * er voor de rest van het bezoek in.
 */
const services = computed(() => [
    {
        eyebrow: '01',
        title: t('Advies'),
        body: t(
            'Meedenken over wat er nodig is en wat niet. Een keuze die je over drie jaar nog kunt uitleggen.',
        ),
    },
    {
        eyebrow: '02',
        title: t('Realisatie'),
        body: t(
            'Bouwen wat er is afgesproken, met documentatie die meegroeit met de code.',
        ),
    },
    {
        eyebrow: '03',
        title: t('Beheer'),
        body: t(
            'Draaiend houden, in de gaten houden en bijsturen voordat het een storing wordt.',
        ),
    },
]);

const sectie = useTemplateRef<HTMLElement>('sectie');

const opruimers: Array<() => void> = [];

onMounted(() => {
    if (sectie.value === null) {
        return;
    }

    const kaarten = Array.from(
        sectie.value.querySelectorAll<HTMLElement>('[data-kaart]'),
    );

    opruimers.push(kaartenBinnen(kaarten));
    opruimers.push(kantelKaarten(kaarten));
});

onBeforeUnmount(() => {
    opruimers.forEach((opruimen) => opruimen());
});
</script>

<template>
    <SiteSection id="diensten" :tone="tone" :divided="divided">
        <div ref="sectie">
            <SectionHeading
                :eyebrow="$t('Diensten')"
                :title="$t('Wat we doen')"
                :intro="
                    $t(
                        'Drie dingen, en die goed. De rest besteden we liever uit dan half te doen.',
                    )
                "
            />

            <div class="mt-12 grid gap-6 tablet:grid-cols-3">
                <FeatureCard
                    v-for="service in services"
                    :key="service.title"
                    data-kaart
                    :eyebrow="service.eyebrow"
                    :title="service.title"
                >
                    {{ service.body }}
                </FeatureCard>
            </div>
        </div>
    </SiteSection>
</template>
