<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, useTemplateRef } from 'vue';
import ProjectKaart from '@/components/site/ProjectKaart.vue';
import ProjectSlideshow from '@/components/site/ProjectSlideshow.vue';
import ProjectUitgelicht from '@/components/site/ProjectUitgelicht.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import { Button } from '@/components/ui/button';
import { gsap, prefersReducedMotion, stapelBinnen } from '@/lib/motion';
import { projecten } from '@/routes';
import type { ProjectOpDeSite } from '@/types/projecten';
import type { SectieKop, SectieProps } from '@/types/secties';

/**
 * De etalage op de voorpagina.
 *
 * **Een kort blok, en de rest achter een knop.** Dat is het hele ontwerp
 * hier: de uitgelichte projecten groot -- als slideshow zodra het er meer
 * dan één zijn -- daarna hoogstens drie kleine kaarten, en dan een knop
 * naar `/projecten`. Een etalage die met elk project langer wordt maakt de
 * voorpagina op den duur onleesbaar, en dit is de module waarvan je mag
 * aannemen dat er elk jaar iets bij komt.
 *
 * **Het bladeren gebeurt dus níet hier maar op een eigen pagina.** Anders
 * dan bij de vragenlijst, waar álle vragen in de DOM staan en alleen de
 * huidige bladzijde zichtbaar is: daar waren het korte regels en ging het
 * om vindbaarheid binnen één pagina. Een project heeft zijn eigen adres,
 * dus een zoekmachine vindt het daar -- en dan is het hier niet nodig om
 * tien kaarten mee te sturen die niemand ziet.
 *
 * De server beslist welke kaarten dit zijn; dit component kiest niets.
 *
 * Zie docs/architecture/modules/projecten.md.
 */
defineProps<SectieProps>();

const page = usePage();

/**
 * De uitgelichte projecten.
 *
 * Meer dan één? Dan draait dit blok als slideshow, precies zoals bovenaan
 * `/projecten` -- dezelfde dia's, dezelfde bediening. Eén? Dan staat hij
 * gewoon stil; pijlen bij één dia zijn een belofte die niet wordt
 * waargemaakt. Geen? Dan staat er niets en beginnen de kaarten meteen.
 */
const uitgelicht = computed<ProjectOpDeSite[]>(
    () => (page.props.featuredProjects as ProjectOpDeSite[] | undefined) ?? [],
);

const lijst = computed<ProjectOpDeSite[]>(
    () => (page.props.projects as ProjectOpDeSite[] | undefined) ?? [],
);

/** Hoeveel er in totaal online staan; de knop hangt daaraan. */
const totaal = computed<number>(
    () => (page.props.projectsTotal as number | undefined) ?? 0,
);

const kop = computed<SectieKop | null>(
    () => (page.props.projectHeading as SectieKop | undefined) ?? null,
);

/**
 * Of de knop naar alle projecten zin heeft.
 *
 * Alleen als er meer zijn dan er hier staan. Een knop "Bekijk alle
 * projecten" die naar precies dezelfde vier leidt is een omweg.
 */
const meerDanHier = computed(
    () => totaal.value > lijst.value.length + uitgelicht.value.length,
);

/**
 * Hoeveel kaarten er staan, voor de compositie: één wordt een brede rij,
 * twee staan naast elkaar, drie worden één grote met twee gestapeld
 * ernaast. De server levert er hoogstens drie (`OP_DE_VOORPAGINA`); bij
 * meer valt de CSS terug op een gewoon raster.
 */
const aantal = computed(() => Math.min(lijst.value.length, 4));

/**
 * De kaarten schuiven van rechts naar hun plek, met de scroll mee.
 *
 * Op een breed scherm komt de rij als één beweging binnen: elke kaart iets
 * verder van rechts dan de vorige, zodat ze als een waaier dichtschuiven,
 * en gekoppeld aan de scroll -- omhoogscrollen schuift ze weer uit. Dat is
 * de tegenbeweging van de casus erboven, die van links komt.
 *
 * **Ze staan stil voordat ze bruikbaar moeten zijn.** De beweging eindigt
 * als de rij een derde van het scherm in is; daarna is een kaart een
 * gewone link die niet meer onder je cursor wegschuift. De afstand komt uit
 * de breedte van de rij zelf en wordt bij elke verversing opnieuw gemeten.
 *
 * Op een telefoon komen ze één voor één op (`stapelBinnen`), en onder
 * reduced motion staan ze er meteen.
 */
const raster = useTemplateRef<HTMLElement>('raster');
let stopKaarten: (() => void) | undefined;

onMounted(() => {
    const vlak = raster.value;
    const kaarten = [
        ...(vlak?.querySelectorAll<HTMLElement>('.brand-site-projectkaart') ??
            []),
    ];

    if (vlak === null || kaarten.length === 0 || prefersReducedMotion()) {
        if (kaarten.length > 0) {
            stopKaarten = stapelBinnen(kaarten);
        }

        return;
    }

    const mm = gsap.matchMedia();

    mm.add('(min-width: 50rem)', () => {
        const afstand = (): number => Math.min(vlak.clientWidth * 0.18, 260);

        const tween = gsap.fromTo(
            kaarten,
            {
                x: (i: number) => afstand() * (1 + i * 0.45),
                opacity: 0,
            },
            {
                x: 0,
                opacity: 1,
                ease: 'power2.out',
                /*
                 * Geen `stagger`, en dat is een les. Met een stagger in een
                 * scrub-tween waarvan de afstand bij elke verversing opnieuw
                 * wordt berekend, tekent GSAP de beginstand van een kaart pas
                 * als zijn beurt komt: de tweede kaart stond eerst op zijn
                 * plek en schoot dan naar rechts weg. Nu beginnen ze samen,
                 * en komt de waaier uit de afstand: elke kaart start verder
                 * weg en legt dus sneller zijn weg af.
                 */
                scrollTrigger: {
                    trigger: vlak,
                    start: 'top 96%',
                    end: 'top 62%',
                    scrub: 0.6,
                    invalidateOnRefresh: true,
                },
            },
        );

        return () => {
            tween.scrollTrigger?.kill();
            tween.kill();
            gsap.set(kaarten, { clearProps: 'transform,opacity' });
        };
    });

    mm.add('(max-width: 49.999rem)', () =>
        stapelBinnen(kaarten, { van: { opacity: 0, y: 32 }, stagger: 0.08 }),
    );

    stopKaarten = () => mm.revert();
});

onBeforeUnmount(() => stopKaarten?.());
</script>

<template>
    <SiteSection id="projecten" :tone="tone" :divided="divided">
        <SectionHeading
            rol="verhaal"
            v-if="kop"
            :eyebrow="kop.opschrift ?? undefined"
            :title="kop.titel"
            :intro="kop.inleiding ?? undefined"
        />

        <div class="brand-site-projecten">
            <!--
                `herkomst="start"` zet `?van=start` in de adressen, zodat
                de knop onder aan de projectpagina terugwijst naar hier en
                niet naar een lijst waar de bezoeker nooit is geweest.
            -->
            <!--
                De slideshow krijgt zijn opkomanimatie van buitenaf.

                Binnenin staat `animeren` op `false` -- een dia die nog
                weggeknipt is hoort niet op zijn scroll-trigger te wachten.
                Maar het blok als geheel hoort wél op te komen zoals alles
                eromheen; zonder dit plofte het er ineens in terwijl de
                kaarten eronder rustig naar boven kwamen.
            -->
            <div v-if="uitgelicht.length > 1" data-reveal class="opacity-0">
                <ProjectSlideshow :items="uitgelicht" herkomst="start" opbouw />
            </div>

            <ProjectUitgelicht
                v-else-if="uitgelicht.length === 1"
                :item="uitgelicht[0]"
                herkomst="start"
            />

            <div
                v-if="lijst.length > 0"
                ref="raster"
                class="brand-site-projecten-raster"
                :data-aantal="aantal"
            >
                <!--
                    De eerste van drie is de grote kaart; zie `aantal`
                    hierboven. Bij één of twee is er geen grote: dan
                    vult de rij of het paar de breedte al.
                -->
                <ProjectKaart
                    v-for="(item, index) in lijst"
                    :key="item.slug"
                    :item="item"
                    :groot="aantal === 3 && index === 0"
                    binnenkomst="groep"
                    herkomst="start"
                />
            </div>

            <div v-if="meerDanHier" data-reveal class="opacity-0">
                <Button variant="brand-outline" as-child>
                    <Link :href="projecten()">
                        {{ $t('Bekijk alle projecten') }}
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </Button>
            </div>
        </div>
    </SiteSection>
</template>
