<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, useTemplateRef } from 'vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import { Button } from '@/components/ui/button';
import { gsap, irisOpen, prefersReducedMotion } from '@/lib/motion';
import { overMij } from '@/routes';
import type { OverMijOpDeSite } from '@/types/over-mij';
import type { SectieKop, SectieProps } from '@/types/secties';

/**
 * Het korte stuk over de eigenaar.
 *
 * **Eén alinea en een foto, en niet meer dan dat.** De lengte wordt op de
 * server afgedwongen (400 tekens); dit component vertrouwt daarop en zet er
 * geen eigen afkapping overheen. Een blok dat zijn eigen tekst inkort
 * verbergt dat de grens is overschreden, en dan merkt de eigenaar het pas
 * als een bezoeker het zegt.
 *
 * **Een editoriaal portret.** Op een breed scherm staat de foto groot
 * links, in een ronde uitsnede met een schaal van maatstreepjes eromheen
 * -- dezelfde taal als de wijzers bij de statistieken -- en rechts de kop
 * met de samenvatting als grote leestekst. Op een telefoon staat de foto
 * kleiner bovenaan: hij hoort bij de alinea, en dan is erboven duidelijker
 * dan eronder.
 *
 * **De foto gaat open als een diafragma** zodra hij in beeld scrolt; zie
 * `irisOpen` in motion.ts. Onder reduced motion staat hij er gewoon.
 *
 * **De samenvatting kiest haar maat op lengte**, net als de koppen: de
 * klant mag tot 400 tekens typen, en wat bij twee zinnen een mooie
 * leestekst is, wordt bij vier een muur.
 *
 * **De knop staat er alleen als de aparte pagina echt bestaat.** Dat is
 * `pagina` uit de server, en die zegt "aangezet én er staat een verhaal in"
 * -- zie AboutSetting::paginaStaatKlaar(). Een knop naar een 404 is erger
 * dan geen knop.
 *
 * Zie docs/architecture/modules/over-mij.md.
 */
defineProps<SectieProps>();

const page = usePage();

const over = computed<OverMijOpDeSite | null>(
    () => (page.props.about as OverMijOpDeSite | undefined) ?? null,
);

const kop = computed<SectieKop | null>(
    () => (page.props.aboutHeading as SectieKop | undefined) ?? null,
);

/** Drie maten voor de samenvatting; zie de docblock hierboven. */
const lengte = computed(() => {
    const tekens = over.value?.samenvatting.trim().length ?? 0;

    if (tekens <= 160) {
        return 'kort';
    }

    return tekens <= 280 ? 'middel' : 'lang';
});

const lens = useTemplateRef<HTMLElement>('lens');
const beeld = useTemplateRef<HTMLElement>('beeld');
const schaal = useTemplateRef<SVGElement>('schaal');
const tekst = useTemplateRef<HTMLElement>('tekst');
const opruimers: Array<() => void> = [];

/**
 * Het diafragma gaat open terwijl je scrolt.
 *
 * **Op een breed scherm hangt de opening aan je positie** (`scrub`): het
 * portret komt binnen als een smalle cirkel en gaat open naarmate je het
 * naar het midden van het scherm haalt, terwijl de schaal eromheen een
 * stukje meedraait en het portret iets trager beweegt dan de tekst ernaast.
 * Dat mag hier, want het drukt uit wáár je bent en geen waarde; scrol je
 * terug, dan gaat het oog weer dicht, en dat klopt ook.
 *
 * **De opening begint nooit dicht.** Van 22% en niet van nul: wie snel
 * langs scrolt of hier binnenkomt via een anker, ziet altijd een gezicht en
 * nooit een lege cirkel. Staat de sectie al voorbij het eind, dan rekent
 * `scrub` meteen de eindstand uit.
 *
 * Op een telefoon gaat het diafragma één keer open bij binnenkomst
 * (`irisOpen`): daar geen scrub en geen parallax, zie
 * docs/architecture/frontend-en-animatie.md. Onder reduced motion staat
 * het portret meteen helemaal open.
 */
onMounted(() => {
    if (lens.value === null || beeld.value === null) {
        return;
    }

    if (prefersReducedMotion()) {
        opruimers.push(irisOpen(lens.value));

        return;
    }

    const mm = gsap.matchMedia();
    const lensEl = lens.value;
    const beeldEl = beeld.value;

    mm.add('(min-width: 50rem)', () => {
        const tijdlijn = gsap.timeline({
            defaults: { ease: 'none' },
            scrollTrigger: {
                trigger: beeldEl,
                start: 'top 95%',
                end: 'center 50%',
                scrub: true,
                invalidateOnRefresh: true,
            },
        });

        tijdlijn.fromTo(
            lensEl,
            { clipPath: 'circle(22% at 50% 50%)' },
            { clipPath: 'circle(50.5% at 50% 50%)' },
            0,
        );

        if (schaal.value !== null) {
            tijdlijn.fromTo(
                schaal.value,
                { rotation: -40, transformOrigin: '50% 50%' },
                { rotation: 0 },
                0,
            );
        }

        /*
         * De parallax: het portret iets trager dan de tekst. Over de hele
         * doortocht van de sectie en klein -- een paar tientallen pixels.
         */
        const diepte = gsap.fromTo(
            beeldEl,
            { y: 36 },
            {
                y: -36,
                ease: 'none',
                scrollTrigger: {
                    trigger: beeldEl,
                    start: 'top bottom',
                    end: 'bottom top',
                    scrub: true,
                    invalidateOnRefresh: true,
                },
            },
        );
        const tegen =
            tekst.value === null
                ? undefined
                : gsap.fromTo(
                      tekst.value,
                      { y: -14 },
                      {
                          y: 14,
                          ease: 'none',
                          scrollTrigger: {
                              trigger: beeldEl,
                              start: 'top bottom',
                              end: 'bottom top',
                              scrub: true,
                              invalidateOnRefresh: true,
                          },
                      },
                  );

        return () => {
            [tijdlijn, diepte, tegen].forEach((animatie) => {
                animatie?.scrollTrigger?.kill();
                animatie?.kill();
            });
            gsap.set([lensEl, beeldEl, schaal.value, tekst.value], {
                clearProps: 'clipPath,transform',
            });
        };
    });

    mm.add('(max-width: 49.999rem)', () => irisOpen(lensEl, { duur: 1.2 }));

    opruimers.push(() => mm.revert());
});

onBeforeUnmount(() => opruimers.forEach((opruimen) => opruimen()));
</script>

<template>
    <SiteSection id="over-mij" :tone="tone" :divided="divided">
        <div v-if="over" class="brand-site-overmij">
            <!--
                De foto. Het medaillon heeft vier maten en een eigen
                `sizes`; een eigen foto is één vierkant van 640 en krijgt er
                geen. Vandaar de `srcset` die `null` mag zijn.

                De schaal eromheen is decoratie (`aria-hidden`); het
                diafragma zit op `lens`, de foto zelf beweegt niet.
            -->
            <figure ref="beeld" class="brand-site-overmij-beeld">
                <svg
                    ref="schaal"
                    class="brand-site-overmij-schaal"
                    viewBox="0 0 120 120"
                    aria-hidden="true"
                >
                    <circle cx="60" cy="60" r="58" pathLength="100" />
                </svg>

                <div ref="lens" class="brand-site-overmij-lens">
                    <img
                        :src="over.foto.src"
                        :srcset="over.foto.srcset ?? undefined"
                        sizes="(min-width: 50rem) 18rem, 13rem"
                        :alt="$t('Foto van de eigenaar')"
                        width="640"
                        height="640"
                        loading="lazy"
                        decoding="async"
                    />
                </div>
            </figure>

            <div ref="tekst" class="brand-site-overmij-tekst">
                <SectionHeading
                    v-if="kop"
                    :eyebrow="kop.opschrift ?? undefined"
                    :title="kop.titel"
                    :intro="kop.inleiding ?? undefined"
                />

                <p
                    data-reveal
                    class="brand-site-overmij-samenvatting opacity-0"
                    :data-lengte="lengte"
                >
                    {{ over.samenvatting }}
                </p>

                <div v-if="over.pagina" data-reveal class="opacity-0">
                    <Button variant="brand" as-child>
                        <Link :href="overMij()">
                            {{ $t('Lees meer over mij') }}
                            <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                </div>
            </div>
        </div>
    </SiteSection>
</template>
