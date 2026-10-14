<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, useTemplateRef } from 'vue';
import LinkedinMerk from '@/components/site/LinkedinMerk.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import {
    alInBeeld,
    gsap,
    opEenTelefoon,
    prefersReducedMotion,
    ScrollTrigger,
    spotlicht,
} from '@/lib/motion';
import type { LinkedinBlok } from '@/types/linkedin';
import type { SectieProps } from '@/types/secties';

/**
 * De uitnodiging om verder te kijken op LinkedIn.
 *
 * **De teksten zijn van de eigenaar, de link niet.** Het opschrift, de
 * titel, de zin en de knoptekst beheert hij onder Website → LinkedIn, in
 * beide talen; ze komen als `linkedinBlock` van de server, met de keuze
 * tussen Nederlands en Engels al gemaakt. Het adres ligt vast in
 * `config/site.php` en komt uit de gedeelde prop `linkedin`, dezelfde als
 * die van de knop in de hero.
 *
 * **Waarom dit een eigen onderdeel is en geen regeltje in de voet.** Op
 * een zakelijke site is LinkedIn waar het gesprek verdergaat: iemand die
 * je site heeft gelezen en nog niet wil mailen, wil je wel volgen. Het
 * staat daarom standaard ná het contactformulier.
 *
 * **Het rustmoment van de pagina.** Eén brede plaat met weinig erin:
 * links de uitnodiging, rechts het merkteken met ringen die er langzaam
 * vandaan gaan. Wat aan de scroll hangt is alleen de baan om het teken:
 * een stip die meedraait terwijl je langs het blok scrolt, en terugdraait
 * als je teruggaat. De tekst staat stil; die moet je kunnen lezen.
 *
 * Zie docs/architecture/modules/linkedin.md.
 */
defineProps<SectieProps>();

const page = usePage();

/**
 * Het adres komt van de server en staat niet in dit bestand.
 *
 * Twee redenen. Het is echte gegevens van de klant, en die horen niet in
 * een Vue-component. En straks staat er meer dan één knop naar LinkedIn;
 * dan wijzen ze allemaal naar dezelfde bron. Zie config/site.php.
 */
const adres = computed(() => (page.props.linkedin as string | undefined) ?? '');

/**
 * De kop en de knoptekst. `null` als het onderdeel niet op de pagina
 * staat; dan wordt dit component ook niet getoond, maar een vangnet kost
 * niets.
 */
const inhoud = computed(
    () => (page.props.linkedinBlock as LinkedinBlok | null | undefined) ?? null,
);

/**
 * Het adres zonder `https://www.`, als korte regel onder de knop. Dat is
 * geen versiering: zo zie je vóór het klikken waar je heen gaat.
 */
const kortAdres = computed(() =>
    adres.value.replace(/^https?:\/\/(www\.)?/, '').replace(/\/$/, ''),
);

const sectie = useTemplateRef<HTMLElement>('sectie');
const plaat = useTemplateRef<HTMLElement>('plaat');

const opruimers: Array<() => void> = [];

/**
 * Het blok komt op, en zodra het merkteken in beeld is gaat het signaal uit.
 *
 * Dit stond eerst in `zendSignaal()`, en daar zaten twee fouten in die je
 * niet zag maar wel betaalde: de herhalende ringen begonnen twee seconden
 * na het laden van de pagina, of het blok nu in beeld was of tien schermen
 * verderop, en ze liepen daarna eindeloos door. Nu:
 *
 * - **De entree hangt aan het merkteken** en niet aan het opschrift: de
 *   eerste ring vertrekt op het moment dat je het teken ziet.
 * - **De herhaling staat stil buiten beeld.** Een `ScrollTrigger` met
 *   `onToggle` pauzeert de tijdlijn zodra het blok uit beeld scrolt en
 *   hervat hem als het terugkomt.
 * - **Alleen vanaf 50rem herhalen de ringen**; op een telefoon zendt het
 *   teken één keer, bij binnenkomst. Een eindeloze laag naast scherpe tekst
 *   maakt die op een telefoon zacht; zie frontend-en-animatie.md.
 *
 * Onder reduced motion staat alles meteen, en zenden de ringen niet.
 */
const zend = (): (() => void) => {
    const blok = sectie.value;

    if (blok === null) {
        return () => {};
    }

    const onderdelen = Array.from(
        blok.querySelectorAll<HTMLElement>('[data-op]'),
    );
    const ringen = Array.from(
        blok.querySelectorAll<HTMLElement>('[data-ring]'),
    );
    const merk = blok.querySelector<HTMLElement>('.brand-site-linkedin-zender');

    if (prefersReducedMotion()) {
        gsap.set(onderdelen, { opacity: 1, y: 0 });
        gsap.set(ringen, { opacity: 0 });

        return () => {};
    }

    const vanaf = opEenTelefoon() ? 1.15 : 0.85;

    gsap.set(onderdelen, { opacity: 0, y: 18 });
    gsap.set(ringen, { opacity: 0, scale: 0.6 });

    const entree = gsap.timeline({ paused: true });

    entree.to(onderdelen, {
        opacity: 1,
        y: 0,
        duration: 0.7,
        stagger: 0.08,
        ease: 'expo.out',
    });

    if (ringen[0] !== undefined) {
        entree.fromTo(
            ringen[0],
            { opacity: 0.6, scale: 0.6 },
            { opacity: 0, scale: 2.6, duration: 1.6, ease: 'power2.out' },
            0.2,
        );
    }

    const doel = merk ?? onderdelen[0] ?? blok;
    let opkomst: ScrollTrigger | undefined;

    if (alInBeeld(doel, vanaf)) {
        entree.play();
    } else {
        opkomst = ScrollTrigger.create({
            trigger: doel,
            start: `top ${vanaf * 100}%`,
            once: true,
            onEnter: () => {
                entree.play();
            },
        });
    }

    /*
     * De herhaling: om beurten een ring, met lucht ertussen. Begint pas na
     * de entree, en staat stil zodra het blok uit beeld is.
     */
    const mm = gsap.matchMedia();

    mm.add('(min-width: 50rem)', () => {
        const herhaling = gsap.timeline({
            paused: true,
            repeat: -1,
            repeatDelay: 1.2,
        });

        ringen.forEach((ring, index) => {
            herhaling.fromTo(
                ring,
                { opacity: 0.45, scale: 0.6 },
                { opacity: 0, scale: 2.6, duration: 2.4, ease: 'power2.out' },
                index * 1.6,
            );
        });

        const kijker = ScrollTrigger.create({
            trigger: blok,
            start: 'top bottom',
            end: 'bottom top',
            onToggle: (zelf) => {
                if (zelf.isActive && entree.progress() > 0) {
                    herhaling.play();
                } else {
                    herhaling.pause();
                }
            },
        });

        entree.eventCallback('onComplete', () => {
            if (kijker.isActive) {
                herhaling.play();
            }
        });

        return () => {
            kijker.kill();
            herhaling.kill();
        };
    });

    /*
     * Het enige dat aan de scroll hangt: de baan om het merkteken. De stip
     * draait een derde slag mee terwijl het blok door het beeld gaat, en
     * het teken zelf zakt een fractie trager dan de pagina. Allebei
     * `scrub`, dus terugscrollen draait het terug. Alleen vanaf 50rem: op
     * een telefoon staat het teken klein boven de tekst, zonder baan.
     */
    mm.add('(min-width: 50rem)', () => {
        const draai = blok.querySelector<HTMLElement>('[data-linkedin-draai]');
        const baan = blok.querySelector<HTMLElement>('[data-linkedin-baan]');

        if (draai === null || baan === null) {
            return;
        }

        const scrub = gsap.timeline({
            defaults: { ease: 'none' },
            scrollTrigger: {
                trigger: blok,
                start: 'top bottom',
                end: 'bottom top',
                scrub: 0.6,
                invalidateOnRefresh: true,
            },
        });

        scrub
            .fromTo(draai, { rotation: -60 }, { rotation: 60 }, 0)
            .fromTo(baan, { yPercent: 8 }, { yPercent: -8 }, 0);

        return () => {
            scrub.scrollTrigger?.kill();
            scrub.kill();
        };
    });

    return () => {
        opkomst?.kill();
        entree.kill();
        mm.revert();
    };
};

onMounted(() => {
    opruimers.push(zend());

    if (plaat.value !== null) {
        opruimers.push(spotlicht([plaat.value]));
    }
});

onBeforeUnmount(() => {
    opruimers.forEach((opruimen) => opruimen());
});
</script>

<template>
    <SiteSection id="linkedin" :tone="tone" :divided="divided">
        <div ref="sectie" class="brand-site-linkedin">
            <div
                ref="plaat"
                class="brand-site-linkedin-plaat brand-site-spot brand-site-glas"
            >
                <div class="brand-site-linkedin-tekst">
                    <p
                        v-if="inhoud?.kop.opschrift"
                        data-op
                        class="brand-site-opschrift"
                    >
                        {{ inhoud.kop.opschrift }}
                    </p>

                    <h2
                        data-op
                        class="brand-site-linkedin-titel"
                        :data-lang="
                            (inhoud?.kop.titel.length ?? 0) > 48 || undefined
                        "
                    >
                        {{ inhoud?.kop.titel ?? $t('LinkedIn') }}
                    </h2>

                    <p
                        v-if="inhoud?.kop.inleiding"
                        data-op
                        class="brand-site-linkedin-zin"
                    >
                        {{ inhoud.kop.inleiding }}
                    </p>

                    <!--
                        Een echte link en geen knop met een klikafhandelaar:
                        zo kun je hem kopiëren, in een nieuw venster openen
                        en met het toetsenbord bereiken.

                        Wél in een nieuw tabblad: dit is een ander domein,
                        en een bezoeker die doorklikt hoort zijn plek op
                        deze pagina niet kwijt te raken. Het pijltje zegt
                        dat vooraf.

                        Zonder adres geen knop: een knop die nergens heen
                        gaat is erger dan geen knop.
                    -->
                    <div v-if="adres" data-op class="brand-site-linkedin-actie">
                        <a
                            :href="adres"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="brand-site-linkedin-knop"
                        >
                            <span>{{
                                inhoud?.knop ?? $t('Bekijk het profiel')
                            }}</span>
                            <ArrowUpRight class="size-4 shrink-0" />
                        </a>

                        <span class="brand-site-linkedin-adres font-data">
                            {{ kortAdres }}
                        </span>
                    </div>
                </div>

                <!--
                    Het merkteken met de ringen die er vandaan gaan en de
                    baan die met de scroll meedraait. Puur decoratief, dus
                    `aria-hidden`: wat ze zeggen staat er in woorden naast.
                -->
                <span
                    data-op
                    class="brand-site-linkedin-zender"
                    aria-hidden="true"
                >
                    <span data-linkedin-baan class="brand-site-linkedin-baan">
                        <span class="brand-site-linkedin-cirkel" />
                        <span class="brand-site-linkedin-cirkel" />
                        <span
                            data-linkedin-draai
                            class="brand-site-linkedin-draai"
                        >
                            <span class="brand-site-linkedin-stip" />
                        </span>
                        <span data-ring class="brand-site-linkedin-ring" />
                        <span data-ring class="brand-site-linkedin-ring" />

                        <span class="brand-site-linkedin-merk">
                            <LinkedinMerk />
                        </span>
                    </span>
                </span>
            </div>
        </div>
    </SiteSection>
</template>
