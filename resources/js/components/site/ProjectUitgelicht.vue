<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, useTemplateRef } from 'vue';
import { Button } from '@/components/ui/button';
import { gsap, prefersReducedMotion } from '@/lib/motion';
import { project } from '@/routes';
import type { ProjectHerkomst, ProjectOpDeSite } from '@/types/projecten';

/**
 * Het uitgelichte project, groot: de casus.
 *
 * **Het beeld staat náást de tekst, in een vierkant kader.** `Logo::bewaar()`
 * tekent altijd in een vierkant van 640 pixels, dus een brede banner zou
 * een opgerekt en afgeknipt plaatje worden. Op een telefoon gaat het beeld
 * erboven, liggend.
 *
 * **Zonder beeld komt het letterveld in die kolom**: de eerste letter van
 * de organisatie, groot, in dezelfde opening. Een tekst die alleen op een
 * plaat staat leest als een vergeten beeld; dit leest als een keuze.
 * `data-zonder-beeld` maakt de kolom daarvoor smaller.
 *
 * **Het beeld bouwt zich op terwijl je scrolt**; zie `bouwOp()` hieronder.
 * Daarom draagt het kader geen `data-reveal`: twee systemen in hetzelfde
 * element is hoe je een knipperend beeld krijgt.
 *
 * **Twee plekken, en daarom het schuifje `animeren`.** Los op de voorpagina
 * komt dit blok op als je erlangs scrollt; in de slideshow mag dat niet.
 * Een dia die nog niet aan de beurt is, staat bij het laden al "in beeld"
 * maar is weggeknipt, en dan wacht hij op een scroll-trigger die nooit
 * komt -- waarna de tekst op nul blijft en de dia leeg lijkt. In de
 * slideshow verzorgt de omlijsting de beweging.
 *
 * Zie docs/architecture/modules/projecten.md.
 */
const props = withDefaults(
    defineProps<{
        item: ProjectOpDeSite;
        /** Zie het blok hierboven: uit binnen de slideshow. */
        animeren?: boolean;
        /**
         * Of dit beeld meteen moet laden.
         *
         * Standaard ja: los op de voorpagina is dit het eerste wat een
         * bezoeker van dit onderdeel ziet. In een slideshow staat hij
         * alleen op de eerste dia aan -- de rest is weggeknipt, en drie
         * afbeeldingen ophalen voor twee die niemand ziet is zonde van de
         * verbinding.
         */
        meteen?: boolean;
        /** Zie `ProjectKaart`: belandt als `?van=` in het adres. */
        herkomst?: ProjectHerkomst;
    }>(),
    { animeren: true, meteen: true, herkomst: null },
);

/** `''` zet het attribuut, `undefined` laat het weg. */
const merk = computed(() => (props.animeren ? '' : undefined));

/** Verborgen beginnen hoort bij de scanner; zonder scanner niet. */
const verborgen = computed(() => (props.animeren ? 'opacity-0' : ''));

/**
 * Een lange titel krijgt een kleinere maat.
 *
 * De eigenaar typt de titel zelf, en wat bij "Migratie naar Exchange
 * Online" past, maakt van een titel van honderd tekens zes regels naast
 * een beeld dat dan te klein oogt.
 */
const lengte = computed(() =>
    props.item.titel.trim().length > 70 ? 'lang' : 'gewoon',
);

const adres = computed(() =>
    props.herkomst
        ? project(props.item.slug, { query: { van: props.herkomst } })
        : project(props.item.slug),
);

const plaat = useTemplateRef<HTMLElement>('plaat');
const kader = useTemplateRef<HTMLElement>('kader');
const tekst = useTemplateRef<HTMLElement>('tekst');
const beeld = useTemplateRef<HTMLElement>('beeld');

/**
 * De casus bouwt zich op terwijl je scrolt.
 *
 * Het kader begint als een kleiner, ronder venster midden in zijn vlak en
 * gaat open tot het volle beeld, precies zo snel als jij scrolt: je kijkt
 * door een lens die zich opent. Het beeld erin zakt ondertussen van iets
 * te dichtbij naar zijn plek, en op een breed scherm schuift het een
 * fractie mee met de scroll -- de diepte van een venster, niet van een
 * plaatje.
 *
 * **Op de positie en niet eenmalig.** Scrol je terug, dan sluit het venster
 * weer. Dat mag hier: het is geen waarde die terugloopt, alleen de opening
 * schuift mee met waar je bent (zie de les over `scrub` in
 * docs/architecture/frontend-en-animatie.md).
 *
 * Alleen `clip-path` en `transform`. Onder reduced motion staat het beeld
 * gewoon open; in de slideshow (`animeren` uit) ook, want daar verzorgt de
 * veeg van de dia de beweging.
 */
function bouwOp(): () => void {
    if (
        prefersReducedMotion() ||
        plaat.value === null ||
        kader.value === null
    ) {
        return () => {};
    }

    const vlak = plaat.value;
    const venster = kader.value;
    const foto = beeld.value;
    const mm = gsap.matchMedia();

    mm.add(
        { breed: '(min-width: 50rem)', smal: '(max-width: 49.999rem)' },
        (context) => {
            const { breed } = context.conditions as { breed: boolean };

            const opening = gsap.timeline({
                defaults: { ease: 'none' },
                scrollTrigger: {
                    trigger: vlak,
                    start: 'top 92%',
                    end: breed ? 'top 32%' : 'top 48%',
                    scrub: true,
                    invalidateOnRefresh: true,
                },
            });

            opening.fromTo(
                venster,
                {
                    clipPath: breed
                        ? 'inset(10% 16% 10% 16% round 2.5rem)'
                        : 'inset(7% 9% 7% 9% round 1.5rem)',
                },
                { clipPath: 'inset(0% 0% 0% 0% round 0.75rem)' },
                0,
            );

            if (foto !== null) {
                opening.fromTo(foto, { scale: 1.2 }, { scale: 1.06 }, 0);
            }

            /*
             * Beeld en tekst komen uit twee richtingen samen: het kader
             * schuift van links naar zijn plek, de tekst van rechts. Op
             * dezelfde scroll als de opening, dus ze staan stil zodra het
             * beeld open is -- de tekst en de knop bewegen niet meer als je
             * gaat lezen of klikken. Omhoogscrollen haalt ze weer uiteen.
             *
             * Op het kader en het tekstblok zelf, en niet op wat erin zit:
             * het beeld heeft al zijn eigen schaal en diepte, en de regels
             * van de kop hun eigen opkomst. Twee animaties die dezelfde
             * `transform` sturen, vechten om elk beeldje.
             *
             * De afstand is een deel van de breedte van de casus en wordt
             * bij elke verversing opnieuw gemeten (`invalidateOnRefresh`
             * staat op de tijdlijn). Op een telefoon niet: daar staan beeld
             * en tekst onder elkaar.
             */
            if (breed) {
                const afstand = (): number =>
                    Math.min(vlak.clientWidth * 0.12, 170);

                opening.fromTo(
                    venster,
                    { x: () => -afstand() },
                    { x: 0, ease: 'power2.out' },
                    0,
                );

                if (tekst.value !== null) {
                    opening.fromTo(
                        tekst.value,
                        { x: () => afstand() * 0.8 },
                        { x: 0, ease: 'power2.out' },
                        0,
                    );
                }
            }

            /*
             * De diepte: het beeld schuift binnen zijn kader een paar
             * procent mee, alleen op een breed scherm. Een losse tween met
             * zijn eigen trigger en niet in de tijdlijn hierboven: die
             * eindigt al als het kader open is, de diepte loopt door tot het
             * kader het scherm uit is.
             */
            const diepte =
                breed && foto !== null
                    ? gsap.fromTo(
                          foto,
                          { yPercent: -4 },
                          {
                              yPercent: 4,
                              ease: 'none',
                              scrollTrigger: {
                                  trigger: venster,
                                  start: 'top bottom',
                                  end: 'bottom top',
                                  scrub: true,
                                  invalidateOnRefresh: true,
                              },
                          },
                      )
                    : undefined;

            return () => {
                opening.scrollTrigger?.kill();
                opening.kill();
                diepte?.scrollTrigger?.kill();
                diepte?.kill();
                gsap.set(venster, { clearProps: 'clipPath,transform' });

                if (tekst.value !== null) {
                    gsap.set(tekst.value, { clearProps: 'transform' });
                }

                if (foto !== null) {
                    gsap.set(foto, { clearProps: 'transform' });
                }
            };
        },
    );

    return () => mm.revert();
}

let stopOpbouw: (() => void) | undefined;

onMounted(() => {
    if (props.animeren) {
        stopOpbouw = bouwOp();
    }
});

onBeforeUnmount(() => stopOpbouw?.());
</script>

<template>
    <article
        ref="plaat"
        class="brand-site-casus"
        :data-zonder-beeld="item.beeld ? undefined : ''"
        :data-in-dia="animeren ? undefined : ''"
    >
        <figure v-if="item.beeld" ref="kader" class="brand-site-casus-beeld">
            <img
                ref="beeld"
                :src="item.beeld"
                :alt="$t('Beeld bij :titel', { titel: item.titel })"
                width="640"
                height="640"
                :loading="meteen ? undefined : 'lazy'"
                decoding="async"
            />
        </figure>

        <!--
            Zonder beeld het letterveld, in dezelfde opening als een beeld.
            `aria-hidden`: de naam van de organisatie staat er voluit naast.
        -->
        <figure
            v-else
            ref="kader"
            class="brand-site-casus-beeld brand-site-casus-letter brand-site-data"
            aria-hidden="true"
        >
            {{ item.letter }}
        </figure>

        <div ref="tekst" class="brand-site-casus-tekst">
            <p
                :data-reveal="merk"
                class="brand-site-project-badge"
                :class="verborgen"
            >
                {{ item.type }}
            </p>

            <h3
                :data-split="merk"
                class="brand-site-casus-titel"
                :class="verborgen"
                :data-lengte="lengte"
            >
                {{ item.titel }}
            </h3>

            <!--
                Wie en wat op de eerste regel, wanneer en hoe lang op de
                tweede, in de mono van alles wat tijd is. De scheiding
                tussen de delen zet de CSS, zodat er geen los teken
                overblijft als een deel ooit wegvalt.
            -->
            <div
                :data-reveal="merk"
                class="brand-site-casus-meta"
                :class="verborgen"
            >
                <p class="brand-site-project-wie">
                    <span>{{ item.organisatie }}</span>
                    <span>{{ item.rol }}</span>
                </p>
                <p class="brand-site-project-wanneer brand-site-data">
                    <span>{{ item.periode }}</span>
                    <span>{{ item.duur }}</span>
                </p>
            </div>

            <p
                v-if="item.samenvatting"
                :data-reveal="merk"
                class="brand-site-casus-zin"
                :class="verborgen"
            >
                {{ item.samenvatting }}
            </p>

            <div
                :data-reveal="merk"
                class="brand-site-casus-knop"
                :class="verborgen"
            >
                <Button variant="brand" as-child>
                    <Link :href="adres">
                        {{ $t('Bekijk project') }}
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </Button>
            </div>
        </div>
    </article>
</template>
