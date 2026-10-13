<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { INTRO_DUUR, introSpeeltAf } from '@/lib/intro';
import { gsap } from '@/lib/motion';

/**
 * De merkintro bij binnenkomst.
 *
 * Een navy laag met de merkgloed, het logo dat zich neerzet, en dan vloeit
 * het geheel naar boven weg. Acht tienden van een seconde, één keer per
 * bezoek.
 *
 * **Dit is geen laadscherm.** De hero staat er al volledig onder; deze laag
 * ligt er alleen overheen. Dat onderscheid is het hele verschil: een
 * laadscherm houdt de pagina tegen, dit is een gordijn dat opengaat voor
 * iets wat er al is. Zou de animatie om wat voor reden dan ook stukgaan,
 * dan staat de site eronder gewoon klaar.
 *
 * **Hij vangt niets af.** `aria-hidden` en `inert` houden hem uit de
 * voorleesvolgorde en buiten het bereik van de tabtoets, en er wordt niet
 * aan de scrollpositie gezeten. Wie meteen begint te scrollen wordt niet
 * tegengehouden; hij ziet de laag alleen even.
 *
 * Wanneer hij níet speelt -- reduced motion, al eens gezien deze sessie,
 * of een pagina die toch al traag binnenkwam -- staat in lib/intro.ts. Die
 * beslissing wordt daar genomen en niet hier, omdat de hero hem ook nodig
 * heeft.
 */

const speelt = ref(introSpeeltAf());

const laag = useTemplateRef<HTMLElement>('laag');

let tijdlijn: gsap.core.Timeline | undefined;

onMounted(() => {
    if (!speelt.value || laag.value === null) {
        return;
    }

    tijdlijn = gsap
        .timeline({
            // Uit de DOM halen en niet alleen onzichtbaar maken. Een laag
            // over het hele scherm die blijft staan met `opacity: 0` vangt
            // nog steeds elke klik af.
            onComplete: () => {
                speelt.value = false;
            },
        })
        .fromTo(
            '[data-intro-gloed]',
            { opacity: 0, scale: 0.8 },
            { opacity: 1, scale: 1, duration: 0.45, ease: 'power2.out' },
            0,
        )
        .fromTo(
            '[data-intro-merk]',
            { opacity: 0, scale: 0.9, filter: 'blur(6px)' },
            {
                opacity: 1,
                scale: 1,
                filter: 'blur(0px)',
                duration: 0.4,
                ease: 'power3.out',
            },
            0.12,
        )
        .to(
            laag.value,
            {
                opacity: 0,
                yPercent: -8,
                duration: 0.35,
                ease: 'power2.in',
            },
            INTRO_DUUR - 0.35,
        );
});

onBeforeUnmount(() => {
    tijdlijn?.kill();
});
</script>

<template>
    <div v-if="speelt" ref="laag" class="brand-intro" aria-hidden="true" inert>
        <span data-intro-gloed class="brand-intro-gloed" />
        <AppLogoIcon data-intro-merk class="brand-intro-merk" />
    </div>
</template>
