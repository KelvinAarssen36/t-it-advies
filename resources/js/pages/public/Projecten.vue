<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ProjectBreed from '@/components/site/ProjectBreed.vue';
import ProjectKaart from '@/components/site/ProjectKaart.vue';
import ProjectSlideshow from '@/components/site/ProjectSlideshow.vue';
import SiteKruimels from '@/components/site/SiteKruimels.vue';
import SiteTerug from '@/components/site/SiteTerug.vue';
import type { ProjectOpDeSite, ProjectWeergave } from '@/types/projecten';
import type { SectieKop } from '@/types/secties';

/**
 * De pagina met alle projecten.
 *
 * **Hier geen grens en geen bladeren.** Op de voorpagina staan er vier;
 * dit is de plek waar ze allemaal staan, in de volgorde uit het beheer.
 * Bladeren zou betekenen dat een bezoeker die op zoek is naar één project
 * moet gaan klikken, en dat is precies wat deze pagina moet voorkomen.
 *
 * Geen filters. Die bouwen we pas als er genoeg projecten zijn om ze
 * nodig te hebben -- het datamodel kan het aan (het type staat als eigen
 * kolom in de tabel), maar een filterbalk boven zes kaarten is
 * gereedschap voor een probleem dat niet bestaat.
 *
 * ## De opbouw
 *
 * Bovenaan de uitgelichte projecten als slideshow over de volle breedte.
 * Daaronder de rest, in de vorm die de eigenaar heeft gekozen:
 *
 * - **Lijst** (standaard): elk project een eigen regel onder elkaar, alle
 *   regels even groot en compact. Dit is de overzichtelijke vorm -- je
 *   scant de titels van boven naar beneden, en wie meer wil weten klikt
 *   door naar de projectpagina.
 * - **Raster**: dezelfde projecten als kaarten naast elkaar. Luchtiger, en
 *   fijner zodra er afbeeldingen bij staan.
 *
 * **Waarom geen afwisseling van groot en klein meer.** Dat heeft hier
 * gestaan -- één brede regel, dan twee smalle -- en het riep steeds
 * dezelfde vraag op: waarom is die ene anders? Het eerlijke antwoord was
 * "omdat hij toevallig op plek één van de ronde staat", en dat is geen
 * antwoord. Een lijst waarin alles gelijk is, is makkelijker te lezen dan
 * een ritme dat je moet doorzien.
 *
 * De layout komt automatisch: alles in `pages/public/` krijgt
 * `PublicLayout`; zie resources/js/app.ts.
 *
 * Zie docs/architecture/modules/projecten.md.
 */
const props = defineProps<{
    kop: SectieKop;
    /** Onder elkaar als lijst, of naast elkaar als kaarten. */
    weergave: ProjectWeergave;
    /** De uitgelichte projecten; kan leeg zijn. */
    featured: ProjectOpDeSite[];
    /** Al het overige, in de volgorde van de eigenaar. */
    projects: ProjectOpDeSite[];
}>();
</script>

<template>
    <Head :title="props.kop.titel" />

    <div class="mx-auto max-w-5xl px-6 py-16 sm:py-24">
        <SiteKruimels :titel="'Projecten'" />

        <header class="max-w-2xl">
            <p v-if="props.kop.opschrift" class="brand-projectpagina-opschrift">
                {{ props.kop.opschrift }}
            </p>

            <h1 class="brand-projectpagina-titel">{{ props.kop.titel }}</h1>

            <p v-if="props.kop.inleiding" class="brand-projectpagina-inleiding">
                {{ props.kop.inleiding }}
            </p>
        </header>

        <!--
            Licht de eigenaar niets uit, dan staat hier niets. Geen leeg
            vlak en geen willekeurig project dat de plek opvult.
        -->
        <div
            v-if="props.featured.length > 0"
            data-reveal
            class="mt-12 opacity-0"
        >
            <ProjectSlideshow :items="props.featured" />
        </div>

        <template v-if="props.projects.length > 0">
            <!-- Onder elkaar: de overzichtelijke vorm. -->
            <div
                v-if="props.weergave === 'lijst'"
                class="brand-projectlijst mt-14"
            >
                <ProjectBreed
                    v-for="item in props.projects"
                    :key="item.slug"
                    :item="item"
                />
            </div>

            <!-- Of naast elkaar als kaarten. -->
            <div v-else class="brand-projectraster mt-14">
                <ProjectKaart
                    v-for="item in props.projects"
                    :key="item.slug"
                    :item="item"
                />
            </div>
        </template>

        <!-- Het einde van de pagina: je bent klaar met kijken. -->
        <SiteTerug />
    </div>
</template>
