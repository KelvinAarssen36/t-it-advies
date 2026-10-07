<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, Check, Mail } from '@lucide/vue';
import { computed } from 'vue';
import SiteKruimels from '@/components/site/SiteKruimels.vue';
import SiteTerug from '@/components/site/SiteTerug.vue';
import type { OverMijFoto } from '@/types/over-mij';

/**
 * De aparte pagina "Over mij".
 *
 * Bestaat alleen als de eigenaar hem aanzet én er een verhaal in staat; zie
 * PublicAboutController, die anders een 404 geeft.
 *
 * **Hij volgt de aparte contactpagina als voorbeeld, inclusief de drie
 * dingen die daar eerst misgingen**: een kop die echt wordt getekend (en
 * dus de veldnamen van de server gebruikt), een omhulsel met ruimte boven
 * en onder in plaats van tekst van rand tot rand, en iets dat van de pagina
 * meer maakt dan een lap tekst -- hier de foto en de punten.
 *
 * **Het verhaal staat in één kolom van hoogstens 68 tekens breed.** Een
 * alinea over de volle breedte van een scherm raak je kwijt als je naar het
 * begin van de volgende regel terugkijkt; dat is bij een persoonlijk
 * verhaal nog hinderlijker dan bij een opsomming.
 *
 * De layout komt automatisch: alles in `pages/public/` krijgt
 * `PublicLayout`; zie resources/js/app.ts.
 *
 * Zie docs/architecture/modules/over-mij.md.
 */
const props = defineProps<{
    titel: string;
    inleiding: string | null;
    verhaal: string;
    foto: OverMijFoto;
    punten: string[];
    email: string;
}>();

const page = usePage();

/** De naam van de eigenaar, uit de gedeelde props. */
const eigenaar = computed(
    () => (page.props.eigenaar as string | undefined) ?? '',
);

/** Zijn LinkedIn, als die is ingesteld. */
const linkedin = computed(
    () => (page.props.linkedin as string | undefined) ?? '',
);
</script>

<template>
    <Head :title="props.titel" />

    <div class="mx-auto max-w-5xl px-6 py-16 sm:py-24">
        <!--
            Waar je bent. Waar je heen kunt staat in de kop: die toont op
            deze pagina gewoon het menu van de voorpagina. De weg terug
            staat onderaan als knop.

            Naar de voorpagina en niet `history.back()`: je kunt deze pagina
            ook rechtstreeks openen uit een zoekresultaat.
        -->
        <SiteKruimels class="mb-10" :titel="'Over mij'" />

        <header class="brand-overmijpagina-kop">
            <figure class="brand-overmijpagina-beeld">
                <img
                    :src="props.foto.src"
                    :srcset="props.foto.srcset ?? undefined"
                    sizes="(min-width: 48rem) 14rem, 10rem"
                    :alt="
                        eigenaar
                            ? $t('Foto van :naam', { naam: eigenaar })
                            : $t('Foto van de eigenaar')
                    "
                    width="640"
                    height="640"
                    decoding="async"
                />
            </figure>

            <div class="min-w-0">
                <h1 class="brand-overmijpagina-titel">{{ props.titel }}</h1>

                <p v-if="props.inleiding" class="brand-overmijpagina-inleiding">
                    {{ props.inleiding }}
                </p>

                <!--
                    De naam en het adres staan hier en niet onderaan: wie
                    deze pagina leest wil weten met wie hij te maken heeft,
                    en dat is de reden dat hij hier is.
                -->
                <p v-if="eigenaar" class="brand-overmijpagina-naam">
                    {{ eigenaar }}
                </p>

                <!--
                    **Kaartjes en geen onderstreepte links.** Dit zijn de
                    twee dingen die je op deze pagina kunt doen, en dan
                    horen ze eruit te zien als iets om aan te klikken. Als
                    gewone link stond het icoontje bovendien bóven de tekst
                    in plaats van ernaast: Tailwind zet elke `svg` op
                    `display: block`, en in een inline-link breekt dat de
                    regel. Zie `brand-sitemail` in app.css, waar dat nu ook
                    voor de contactpagina is gerepareerd.
                -->
                <div class="brand-overmijpagina-links">
                    <a
                        :href="`mailto:${props.email}`"
                        class="brand-overmijpagina-kaartje"
                    >
                        <Mail class="size-4" aria-hidden="true" />
                        {{ props.email }}
                    </a>

                    <!--
                        Lucide heeft geen merklogo's, dus hetzelfde pijltje
                        als in LinkedinSection en op de contactpagina.
                    -->
                    <a
                        v-if="linkedin"
                        :href="linkedin"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="brand-overmijpagina-kaartje"
                    >
                        <ArrowUpRight class="size-4" aria-hidden="true" />
                        {{ $t('LinkedIn') }}
                    </a>
                </div>
            </div>
        </header>

        <!--
            `brand-overmijpagina-verhaal` zet de witregels om in alinea's,
            met `white-space: pre-line`. Geen opmaakbalkje in het portaal,
            dus hier ook niets dat scheef kan staan.
        -->
        <div class="brand-overmijpagina-blad">
            <p class="brand-overmijpagina-verhaal">{{ props.verhaal }}</p>

            <ul
                v-if="props.punten.length > 0"
                class="brand-overmijpagina-punten"
            >
                <li v-for="punt in props.punten" :key="punt">
                    <Check class="size-4 shrink-0" aria-hidden="true" />
                    <span>{{ punt }}</span>
                </li>
            </ul>
        </div>

        <!-- Het einde van de pagina: je bent klaar met lezen. -->
        <SiteTerug />
    </div>
</template>
