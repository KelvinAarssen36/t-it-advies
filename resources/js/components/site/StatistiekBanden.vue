<script setup lang="ts">
import type { Component } from 'vue';
import StatistiekBalk from '@/components/site/StatistiekBalk.vue';
import StatistiekRing from '@/components/site/StatistiekRing.vue';
import StatistiekTeller from '@/components/site/StatistiekTeller.vue';
import type { StatistiekGroep, Weergave } from '@/types/statistieken';

/**
 * De banden van één groep statistieken: een rij per opeenvolgende vorm.
 *
 * Los van StatistiekenSection omdat dezelfde banden op drie plekken staan:
 * in de samenvatting bovenaan, in een groep zonder tabs en in een tabpaneel.
 * De verdeling in banden komt van de server
 * (`HomeController::statistiekGroepen()`); dit tekent alleen.
 *
 * `data-bundel` en `data-wacht` zijn de haken voor `vulStatistieken`: per
 * band een eigen golf, met het anker als trigger. Zie StatistiekenSection.
 */
defineProps<{ groep: StatistiekGroep }>();

/**
 * Van vorm naar component, en van vorm naar raster. Twee vaste tabellen en
 * geen namen die tijdens het draaien ontstaan: de bundler en Tailwind
 * moeten kunnen zien welke bestanden en klassen er nodig zijn.
 */
const VORMEN: Record<Weergave, Component> = {
    ring: StatistiekRing,
    teller: StatistiekTeller,
    balk: StatistiekBalk,
};

const RASTER: Record<Weergave, string> = {
    ring: 'brand-site-statistiek-ringen',
    teller: 'brand-site-statistiek-tellers',
    balk: 'brand-site-statistiek-balken',
};
</script>

<template>
    <div
        v-for="(bundel, index) in groep.bundels"
        :key="`${groep.sleutel}-${index}`"
        data-bundel
        :class="RASTER[bundel.weergave]"
    >
        <span
            data-wacht
            class="brand-site-statistiek-wacht"
            aria-hidden="true"
        />
        <component
            :is="VORMEN[bundel.weergave]"
            v-for="item in bundel.items"
            :key="item.id"
            :item="item"
            :data-waarde="item.waarde"
        />
    </div>
</template>
