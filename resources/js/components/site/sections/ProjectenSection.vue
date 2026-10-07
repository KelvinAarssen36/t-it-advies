<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import ProjectKaart from '@/components/site/ProjectKaart.vue';
import ProjectSlideshow from '@/components/site/ProjectSlideshow.vue';
import ProjectUitgelicht from '@/components/site/ProjectUitgelicht.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import { Button } from '@/components/ui/button';
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
</script>

<template>
    <SiteSection id="projecten" :tone="tone" :divided="divided">
        <SectionHeading
            v-if="kop"
            :eyebrow="kop.opschrift ?? undefined"
            :title="kop.titel"
            :intro="kop.inleiding ?? undefined"
        />

        <div class="mt-10 flex flex-col gap-10">
            <!--
                `herkomst="start"` zet `?van=start` in de adressen, zodat
                de knop onder aan de projectpagina terugwijst naar hier en
                niet naar een lijst waar de bezoeker nooit is geweest.
            -->
            <!--
                De slideshow krijgt zijn opkomanimatie van buitenaf.

                Binnenin staat `animeren` op `false` -- een dia die nog
                weggeschoven staat hoort niet op zijn scroll-trigger te
                wachten. Maar het blok als geheel hoort wél op te komen
                zoals alles eromheen; zonder dit plofte het er ineens in
                terwijl de kaarten eronder rustig naar boven kwamen.
            -->
            <div v-if="uitgelicht.length > 1" data-reveal class="opacity-0">
                <ProjectSlideshow :items="uitgelicht" herkomst="start" />
            </div>

            <ProjectUitgelicht
                v-else-if="uitgelicht.length === 1"
                :item="uitgelicht[0]"
                herkomst="start"
            />

            <div v-if="lijst.length > 0" class="brand-projectraster">
                <ProjectKaart
                    v-for="item in lijst"
                    :key="item.slug"
                    :item="item"
                    herkomst="start"
                />
            </div>

            <div v-if="meerDanHier" data-reveal class="opacity-0">
                <Button variant="outline" as-child>
                    <Link :href="projecten()">
                        {{ $t('Bekijk alle projecten') }}
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </Button>
            </div>
        </div>
    </SiteSection>
</template>
