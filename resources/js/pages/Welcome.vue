<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, type Component } from 'vue';
import CertificatenSection from '@/components/site/sections/CertificatenSection.vue';
import ContactSection from '@/components/site/sections/ContactSection.vue';
import DienstenSection from '@/components/site/sections/DienstenSection.vue';
import ErvaringSection from '@/components/site/sections/ErvaringSection.vue';
import FaqSection from '@/components/site/sections/FaqSection.vue';
import HeroSection from '@/components/site/sections/HeroSection.vue';
import LinkedinSection from '@/components/site/sections/LinkedinSection.vue';
import OverMijSection from '@/components/site/sections/OverMijSection.vue';
import StatistiekenSection from '@/components/site/sections/StatistiekenSection.vue';
import WerkwijzeSection from '@/components/site/sections/WerkwijzeSection.vue';
import { scrollNaar } from '@/lib/motion';
import type { SiteKop } from '@/types/ervaring';
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

const props = defineProps<{
    sections: SectieSleutel[];
    /** De drie teksten van de kop; de klant beheert ze onder Website. */
    heroHeading: SiteKop;
}>();

/**
 * Van sleutel naar component.
 *
 * Bewust met vaste imports en niet met een dynamische naam: de bundler moet
 * kunnen zien welke bestanden er nodig zijn, en TypeScript controleert zo
 * dat elke sleutel ook echt een component heeft. Een tikfout in de enum
 * loopt hier stuk in plaats van op de website.
 */
const componenten: Record<SectieSleutel, Component> = {
    'over-mij': OverMijSection,
    diensten: DienstenSection,
    werkwijze: WerkwijzeSection,
    ervaring: ErvaringSection,
    certificaten: CertificatenSection,
    statistieken: StatistiekenSection,
    faq: FaqSection,
    contact: ContactSection,
    linkedin: LinkedinSection,
};

/**
 * De achtergrond wisselt per sectie af, en dat gaat op plek in de rij en
 * niet op naam. Zou elke sectie zijn eigen tint kiezen, dan staan er na een
 * verplaatsing zomaar twee verhoogde vlakken tegen elkaar aan -- en dan is
 * de lijn ertussen weg.
 */
const toonVoor = (index: number): 'base' | 'raised' =>
    index % 2 === 0 ? 'base' : 'raised';

/**
 * Aankomen bij het onderdeel dat in het adres staat.
 *
 * **Dit hoort bij de navigatie op de subpagina's.** Daar is elk menu-item
 * een link naar `/#diensten`; zonder dit kom je dus bovenaan de voorpagina
 * uit en moet je zelf gaan zoeken -- en dan is zo'n link niets beter dan de
 * terugknop die er eerst stond.
 *
 * **Een sprong en geen glijbeweging.** Dit draait terwijl deze pagina nog
 * onzichtbaar is -- hij komt net binnen in de overgang van PublicLayout --
 * dus een glijbeweging zou zich achter een doorzichtige laag afspelen. Je
 * hoort er gewoon te staan zodra het beeld komt.
 *
 * Twee frames wachten, om dezelfde reden als in SiteHeader: de secties
 * moeten eerst getekend zijn, anders staat de plek van het doel nog niet
 * vast. Dat valt ruim binnen de tijd die het infaden kost.
 *
 * **Hier en niet in de layout**, want dit werkt ook zonder overgang: open je
 * `/#diensten` rechtstreeks uit een zoekresultaat of ververs je de pagina,
 * dan is er geen wissel en dus geen `before-enter`, maar wel een `onMounted`.
 * De layout doet het omgekeerde geval: naar boven, als er géén anker staat.
 */
onMounted(() => {
    const sleutel = window.location.hash.replace(/^#/, '');

    if (sleutel === '') {
        return;
    }

    requestAnimationFrame(() =>
        requestAnimationFrame(() => scrollNaar(sleutel, { direct: true })),
    );
});
</script>

<template>
    <Head :title="$t('IT-advies dat blijft staan')" />

    <HeroSection :sections="props.sections" :heading="props.heroHeading" />

    <component
        :is="componenten[key]"
        v-for="(key, index) in props.sections"
        :key="key"
        :tone="toonVoor(index)"
        divided
    />
</template>
