<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { Component } from 'vue';
import ContactSection from '@/components/site/sections/ContactSection.vue';
import DienstenSection from '@/components/site/sections/DienstenSection.vue';
import ErvaringSection from '@/components/site/sections/ErvaringSection.vue';
import HeroSection from '@/components/site/sections/HeroSection.vue';
import WerkwijzeSection from '@/components/site/sections/WerkwijzeSection.vue';
import type { SectieSleutel } from '@/types/secties';

/**
 * De publieke landingspagina.
 *
 * Deze pagina bepaalt niet meer welke onderdelen er staan en in welke
 * volgorde -- dat doet de klant, in het portaal onder Website. De server
 * geeft alleen nog de sleutels door, op volgorde en al gezeefd: uitgezette
 * en nog lege onderdelen zitten er niet bij.
 *
 * De kop staat er los boven, want die is niet te verslepen. De voettekst
 * zit in PublicLayout, want die hoort bij elke pagina en niet bij deze.
 *
 * Smooth scrolling en de scroll-reveals zitten ook in PublicLayout; die
 * worden bij elke navigatie opnieuw gescand, dus een andere volgorde hoeft
 * daar niets voor te regelen.
 *
 * Zie docs/architecture/pagina-indeling.md.
 */

const props = defineProps<{ sections: SectieSleutel[] }>();

/**
 * Van sleutel naar component.
 *
 * Bewust met vaste imports en niet met een dynamische naam: de bundler moet
 * kunnen zien welke bestanden er nodig zijn, en TypeScript controleert zo
 * dat elke sleutel ook echt een component heeft. Een tikfout in de enum
 * loopt hier stuk in plaats van op de website.
 */
const componenten: Record<SectieSleutel, Component> = {
    diensten: DienstenSection,
    werkwijze: WerkwijzeSection,
    ervaring: ErvaringSection,
    contact: ContactSection,
};

/**
 * De achtergrond wisselt per sectie af, en dat gaat op plek in de rij en
 * niet op naam. Zou elke sectie zijn eigen tint kiezen, dan staan er na een
 * verplaatsing zomaar twee verhoogde vlakken tegen elkaar aan -- en dan is
 * de lijn ertussen weg.
 */
const toonVoor = (index: number): 'base' | 'raised' =>
    index % 2 === 0 ? 'base' : 'raised';
</script>

<template>
    <Head title="IT-advies dat blijft staan" />

    <HeroSection :sections="props.sections" />

    <component
        :is="componenten[key]"
        v-for="(key, index) in props.sections"
        :key="key"
        :tone="toonVoor(index)"
        divided
    />
</template>
