<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue';
import SiteSection from '@/components/site/SiteSection.vue';
import { Button } from '@/components/ui/button';
import { gsap, prefersReducedMotion } from '@/lib/motion';

/**
 * De kop van de landingspagina.
 *
 * Staat altijd bovenaan en is niet te verslepen; dat is geen instelling
 * maar wat dit onderdeel is. Zie App\Enums\PageSectionKey.
 *
 * De binnenkomstanimatie staat hier en niet in de layout, omdat hij bij
 * déze sectie hoort: de rest van de pagina komt binnen door te scrollen,
 * deze is er al als je aankomt.
 */
const props = defineProps<{
    /**
     * Welke onderdelen er verder op de pagina staan.
     *
     * Alleen om te bepalen of de knop naar de diensten ergens heen gaat.
     * Zet de klant dat onderdeel uit, dan zou die knop naar een anker
     * springen dat niet bestaat -- en dan gebeurt er bij het klikken niets,
     * wat er van buiten uitziet als een kapotte website.
     */
    sections: string[];
}>();

// Vier maten van dezelfde achtergrond; de browser kiest op schermbreedte en
// pixeldichtheid. Zie docs/architecture/frontend-en-animatie.md.
const heroSrcset = [
    '/images/hero-achtergrond-960.webp 960w',
    '/images/hero-achtergrond-1600.webp 1600w',
    '/images/hero-achtergrond-2560.webp 2560w',
    '/images/hero-achtergrond-3840.webp 3840w',
].join(', ');

let intro: gsap.core.Timeline | undefined;

onMounted(() => {
    // Bij reduced motion zetten we de elementen meteen op hun eindtoestand.
    // De animatie overslaan zou ze onzichtbaar laten; zie motion.ts.
    if (prefersReducedMotion()) {
        gsap.set('[data-intro]', { opacity: 1, y: 0 });

        return;
    }

    intro = gsap
        .timeline({ defaults: { ease: 'power3.out' } })
        .fromTo(
            '[data-intro]',
            { opacity: 0, y: 28 },
            { opacity: 1, y: 0, duration: 0.9, stagger: 0.12 },
        );
});

onBeforeUnmount(() => {
    intro?.kill();
});
</script>

<template>
    <SiteSection
        tone="gradient"
        image="/images/hero-achtergrond-1600.webp"
        :image-srcset="heroSrcset"
        priority
    >
        <div class="py-10 sm:py-16">
            <p
                data-intro
                class="mb-4 text-sm tracking-[0.2em] text-brand-cyan uppercase opacity-0"
            >
                IT-advies en realisatie
            </p>

            <h1
                data-intro
                class="max-w-3xl text-4xl leading-[1.05] font-semibold tracking-tight text-balance text-white opacity-0 sm:text-6xl"
            >
                Techniek die doet wat je bedrijf nodig heeft.
            </h1>

            <p
                data-intro
                class="mt-6 max-w-xl text-lg text-pretty text-muted-foreground opacity-0"
            >
                Van advies tot bouw en beheer. Zonder ruis, zonder
                afhankelijkheid van één leverancier.
            </p>

            <div data-intro class="mt-10 flex flex-wrap gap-3 opacity-0">
                <Button
                    v-if="props.sections.includes('contact')"
                    as="a"
                    href="#contact"
                    size="lg"
                    variant="brand"
                >
                    Neem contact op
                </Button>
                <Button
                    v-if="props.sections.includes('diensten')"
                    as="a"
                    href="#diensten"
                    size="lg"
                    variant="brand-outline"
                >
                    Bekijk de diensten
                </Button>
            </div>
        </div>
    </SiteSection>
</template>
