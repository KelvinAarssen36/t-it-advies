<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    useTemplateRef,
    type Component,
} from 'vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import StatistiekBalk from '@/components/site/StatistiekBalk.vue';
import StatistiekRing from '@/components/site/StatistiekRing.vue';
import StatistiekTeller from '@/components/site/StatistiekTeller.vue';
import { vulStatistieken, type TeVullenStatistiek } from '@/lib/motion';
import type { SectieKop, SectieProps } from '@/types/secties';
import type { StatistiekGroep, Weergave } from '@/types/statistieken';

/**
 * De statistieken: vaardigheden en kengetallen, als instrumenten.
 *
 * **Het beeld is een meetpaneel.** Ringen zijn wijzers met een schaal van
 * maatstreepjes, tellers zijn grote mono-cijfers, balken zijn een dunne
 * lijn over een schaalverdeling. Geen gevulde grijze banen: op een
 * instrument zie je de schaal en de naald, niet een balk die half leeg is.
 *
 * **Dit blok is gebouwd rond één animatie.** De ringen tekenen zich, de
 * balken lopen vol en de getallen tellen op, als een golf van boven naar
 * beneden -- één keer, zodra het in beeld komt. Daarna blijft er een
 * lichtpunt rondgaan: over de boog van een ring, over het gevulde stuk
 * van een balk, langs de randlijn van een teller. Hoe dat werkt en
 * waarom het niet meer aan de scrollpositie hangt, staat bij
 * `vulStatistieken` in motion.ts.
 *
 * **De indeling komt van de server.** Welke groepen er zijn en hoe ze in
 * banden zijn verdeeld is al uitgezocht in
 * `HomeController::statistiekGroepen()`. Dit component tekent alleen nog.
 * Dat is met opzet: groeperen op de vertaalde groepsnaam zou de pagina
 * in twee talen anders indelen, en een ring naast een balk naast een
 * teller ziet er kapot uit -- allebei beslissingen die niet in de
 * opmaaklaag horen.
 *
 * Binnen een groep volgt de pagina de sleepvolgorde van de klant, met
 * een nieuwe band zodra de vorm verandert. Zo doet slepen altijd iets
 * zichtbaars; eerder stonden alle ringen vooraan en leek de sleepgreep
 * niets te doen.
 *
 * `tone` en `divided` komen van buiten: welke achtergrond deze sectie
 * krijgt hangt af van waar hij in de rij staat. Zie Welcome.vue.
 *
 * Zie docs/architecture/modules/statistieken.md.
 */
defineProps<SectieProps>();

/**
 * Van vorm naar component, en van vorm naar raster.
 *
 * Twee vaste tabellen en geen namen die tijdens het draaien ontstaan.
 * Dat is dezelfde keuze als in Welcome.vue: de bundler moet kunnen zien
 * welke bestanden er nodig zijn, en Tailwind leest de broncode om te
 * weten welke klassen bestaan. Een naam die hij pas tijdens het draaien
 * tegenkomt, vindt hij niet -- en dan mist die klasse in de gebouwde
 * stylesheet.
 */
const VORMEN: Record<Weergave, Component> = {
    ring: StatistiekRing,
    teller: StatistiekTeller,
    balk: StatistiekBalk,
};

const RASTER: Record<Weergave, string> = {
    ring: 'brand-site-statistiek-ringen',
    teller: 'brand-site-statistiek-tellers',
    balk: 'brand-site-statistiek-balken',
};

const page = usePage();

const groepen = computed<StatistiekGroep[]>(
    () => (page.props.statistics as StatistiekGroep[] | undefined) ?? [],
);

const kop = computed<SectieKop | null>(
    () => (page.props.statisticHeading as SectieKop | undefined) ?? null,
);

/**
 * De duizendtalscheiding hoort bij de taal van de bezoeker.
 *
 * In het Nederlands een punt, in het Engels een komma. De helper die de
 * getallen schrijft weet niets van talen; die krijgt het teken mee.
 */
const scheiding = computed(() =>
    (page.props.locale as string | undefined) === 'en' ? ',' : '.',
);

const blok = useTemplateRef<HTMLElement>('blok');

let opruimen: (() => void) | undefined;

onMounted(() => {
    if (blok.value === null) {
        return;
    }

    /*
     * **Per band een eigen golf, en niet één voor het hele blok.** Eerst
     * hing alles aan de bovenkant van de sectie: die komt binnen, en het
     * hele blok van anderhalve schermhoogte begon tegelijk te vullen. De
     * rijen onderaan waren dan al klaar voordat je erbij was, en wie snel
     * scrolde zag alleen nog stilstaande meters. Nu vult een band pas als
     * hij zelf in beeld komt: je ziet elke golf gebeuren, en er lopen
     * nooit meer tweens tegelijk dan er op het scherm staan.
     *
     * Binnen een band de volgorde waarin ze op het scherm staan, want die
     * bepaalt het golfje; `querySelectorAll` geeft documentvolgorde.
     */
    const banden = Array.from(
        blok.value.querySelectorAll<HTMLElement>('[data-bundel]'),
    );

    const stoppers = banden.map((band) => {
        const onderdelen: TeVullenStatistiek[] = Array.from(
            band.querySelectorAll<HTMLElement>('[data-vul]'),
        ).map((vak) => ({
            vak,
            getal: vak.querySelector<HTMLElement>('[data-getal]'),
            waarde: Number(vak.dataset.waarde ?? 0),
        }));

        /*
         * Het anker en niet de band zelf als trigger. `vulStatistieken`
         * begint als zijn blok op 80% van het scherm staat; een rij balken
         * die daaronder in beeld kwam, stond dan zichtbaar leeg. Het anker
         * begint een stukje boven de band (zie
         * `.brand-site-statistiek-wacht`), dus de golf start zodra de band
         * onderaan in beeld komt.
         */
        const anker = band.querySelector<HTMLElement>('[data-wacht]') ?? band;

        return vulStatistieken(anker, onderdelen, {
            scheiding: scheiding.value,
        });
    });

    opruimen = () => stoppers.forEach((stop) => stop());
});

onBeforeUnmount(() => opruimen?.());
</script>

<template>
    <SiteSection id="statistieken" :tone="tone" :divided="divided">
        <div ref="blok" class="brand-site-statistieken">
            <SectionHeading
                v-if="kop"
                :eyebrow="kop.opschrift ?? undefined"
                :title="kop.titel"
                :intro="kop.inleiding ?? undefined"
            />

            <div
                v-for="groep in groepen"
                :key="groep.sleutel"
                class="brand-site-statistiek-groep"
            >
                <!--
                    De naamloze groep heeft geen kopje. Die staat vooraan;
                    daar horen de losse cijfers die nergens bij horen.
                -->
                <h3 v-if="groep.naam" class="brand-site-statistiek-groepkop">
                    {{ groep.naam }}
                </h3>

                <!--
                    De banden, in de volgorde die de klant heeft gesleept.
                    Elke band bevat opeenvolgende statistieken van dezelfde
                    vorm; die verdeling komt van de server.
                -->
                <div
                    v-for="(bundel, index) in groep.bundels"
                    :key="`${groep.sleutel}-${index}`"
                    data-bundel
                    :class="RASTER[bundel.weergave]"
                >
                    <span
                        data-wacht
                        class="brand-site-statistiek-wacht"
                        aria-hidden="true"
                    />
                    <component
                        :is="VORMEN[bundel.weergave]"
                        v-for="item in bundel.items"
                        :key="item.id"
                        :item="item"
                        :data-waarde="item.waarde"
                    />
                </div>
            </div>
        </div>
    </SiteSection>
</template>
