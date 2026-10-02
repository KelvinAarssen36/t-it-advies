<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { t } from '@/lib/i18n';
import type { BezoekDag } from '@/types/bezoek';

/**
 * De staafjes met het bezoek per dag.
 *
 * **Zelf getekend en geen grafiekbibliotheek.** Een pakket als Chart.js
 * weegt meer dan het hele portaal samen en brengt zijn eigen vormgeving
 * mee, die we dan weer zouden moeten overstijlen. Dit is een rij `div`'s met
 * een hoogte -- dezelfde aanpak als de balken van de statistieken op de
 * website.
 *
 * **De hoogte is een percentage van de drukste dag.** Niet van een vast
 * maximum: of de drukste dag er tien of tienduizend waren, de vorm van de
 * week is wat je wil zien.
 *
 * **Een dag zonder bezoek krijgt een streepje en geen niets.** Anders is er
 * geen verschil tussen "hier was niemand" en "hier meten we nog niet", en
 * dat is precies het onderscheid dat dit scherm moet maken.
 *
 * De cijfers zelf staan niet in de staafjes maar in de tooltip en in de
 * tekst erboven: dertig getallen naast elkaar is geen grafiek meer.
 */
const props = defineProps<{ reeks: BezoekDag[] }>();

const hoogste = computed(() =>
    Math.max(1, ...props.reeks.map((dag) => dag.weergaven)),
);

const taal = computed(() =>
    (usePage().props.locale as string | undefined) === 'en' ? 'en-GB' : 'nl-NL',
);

/**
 * De datum zoals de eigenaar hem leest.
 *
 * Via `Intl` en niet met een eigen lijst maandnamen, zodat het in het
 * Engelse portaal ook klopt. Kort, want er staan er dertig naast elkaar.
 *
 * De tijd staat op het midden van de dag. Met `T00:00:00` zou de browser
 * hem als UTC lezen en in onze tijdzone op de dag ervoor uitkomen -- een
 * grafiek die een dag verschoven is, zonder dat iets eraan opvalt.
 */
const alsDatum = (iso: string): string =>
    new Intl.DateTimeFormat(taal.value, {
        day: 'numeric',
        month: 'short',
    }).format(new Date(`${iso}T12:00:00`));

/** Wat je ziet als je een staafje aanwijst. */
const omschrijving = (dag: BezoekDag): string =>
    `${alsDatum(dag.dag)} — ${t(':bezoekers bezoekers, :weergaven weergaven', {
        bezoekers: dag.bezoekers,
        weergaven: dag.weergaven,
    })}`;

const hoogte = (dag: BezoekDag): string =>
    `${Math.round((dag.weergaven / hoogste.value) * 100)}%`;
</script>

<template>
    <div class="brand-bezoekgrafiek">
        <!--
            De staafjes. `role="img"` met één omschrijving voor het geheel:
            dertig staafjes los voorlezen is geen informatie. Wie de
            getallen echt nodig heeft, leest ze in de tekst erboven.
        -->
        <div
            class="brand-bezoekgrafiek-staven"
            role="img"
            :aria-label="
                $t('Het bezoek per dag over de afgelopen :aantal dagen', {
                    aantal: props.reeks.length,
                })
            "
        >
            <span
                v-for="dag in props.reeks"
                :key="dag.dag"
                class="brand-bezoekgrafiek-staaf"
                :data-leeg="dag.weergaven === 0 ? '' : undefined"
                :style="{ '--hoogte': hoogte(dag) }"
                :title="omschrijving(dag)"
            />
        </div>

        <p v-if="props.reeks.length > 0" class="brand-bezoekgrafiek-as">
            <span>{{ alsDatum(props.reeks[0].dag) }}</span>
            <span>{{ alsDatum(props.reeks[props.reeks.length - 1].dag) }}</span>
        </p>
    </div>
</template>
