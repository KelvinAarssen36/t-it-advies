<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { LayoutList, Radius, Rows3 } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';
import ErvaringVenster from '@/components/site/ErvaringVenster.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import ErvaringCarrousel from '@/components/site/sections/ErvaringCarrousel.vue';
import ErvaringJaarband from '@/components/site/sections/ErvaringJaarband.vue';
import ErvaringLijst from '@/components/site/sections/ErvaringLijst.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import { countUp, prefersReducedMotion } from '@/lib/motion';
import type {
    ErvaringCijfer,
    ErvaringKop,
    ErvaringOpDeSite,
} from '@/types/ervaring';
import type { SectieProps } from '@/types/secties';

/**
 * De ervaringen op de publieke site.
 *
 * Dit component doet zelf weinig: het haalt de gegevens op, zet de kop en
 * de cijfers erboven, en laat de weergave over aan een van de twee
 * onderdelen eronder.
 *
 * | Weergave                  | Waarvoor                                                                                              |
 * | ------------------------- | ----------------------------------------------------------------------------------------------------- |
 * | **Carrousel** (standaard) | Eén functie groot, met de buren eromheen. Scroll-gestuurd en één scherm hoog, dus de hele loopbaan past altijd in beeld. |
 * | **Lijst**                 | Alles onder elkaar. Voor wie het geheel wil overzien of wil doorzoeken met Ctrl+F.                     |
 *
 * **Waarom allebei.** De lijst was er eerst en is compleet; de carrousel
 * kwam erbij omdat een loopbaan van vijfentwintig functies anders de halve
 * pagina vult. Ze naast elkaar laten staan kost weinig en levert twee
 * dingen op: de bezoeker kiest zelf, en als de carrousel toch niet bevalt
 * is de lijst geen herbouw maar een andere standaard -- één woord in
 * `STANDAARD` hieronder.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
defineProps<SectieProps>();

type Weergave = 'carrousel' | 'lijst';

/**
 * Waarmee de bezoeker binnenkomt.
 *
 * Allebei de weergaven zijn er **altijd**, en de knop erboven staat er
 * ook altijd. Alleen waar je mee begint verschilt: op een telefoon is dat
 * de lijst, want daar scrol je met hetzelfde gebaar waarmee je leest, en
 * een vak dat die veeg afvangt vecht met de pagina. Wil de bezoeker daar
 * toch doorbladeren, dan is dat één tik.
 */
const STANDAARD: Weergave = 'carrousel';

const weergave = ref<Weergave>(STANDAARD);

const page = usePage();

const items = computed<ErvaringOpDeSite[]>(
    () => (page.props.experiences as ErvaringOpDeSite[] | undefined) ?? [],
);

const cijfers = computed<ErvaringCijfer[]>(
    () => (page.props.experienceSummary as ErvaringCijfer[] | undefined) ?? [],
);

/**
 * De kop boven de tijdlijn.
 *
 * Die stond hier als vaste tekst, en dat betekende dat de klant het enige
 * onderdeel van dit blok niet kon aanpassen terwijl de rest wel van hem
 * is. Nu komt hij van de server, uit dezelfde knop als de cijfers.
 *
 * De terugval hier is er voor het geval de prop ontbreekt -- een oude
 * pagina in de cache, of een verse database zonder seeder. Dan staat er
 * een kop in plaats van een gat.
 */
/*
 * Het opschrift komt mee van de server (`SectionHeading::voorDeSite`), ook
 * al heeft het beheerscherm van de tijdlijn er geen veld voor. Hier stond
 * het woord "Ervaring" vast in het sjabloon -- ook op de Engelse site, en
 * los van wat er in de database stond. Nu staat er wat de server zegt, en
 * staat er niets als dat leeg is.
 */
type KopOpDeSite = ErvaringKop & { opschrift?: string | null };

const kop = computed<KopOpDeSite>(
    () =>
        (page.props.experienceHeading as KopOpDeSite | undefined) ?? {
            titel: 'Waar dit vandaan komt',
            inleiding: 'De weg ernaartoe, van nu naar toen.',
        },
);

/*
 * Onder dit aantal heeft doorbladeren geen zin: dan staat de hele loopbaan
 * al in beeld en is het alleen maar extra werk voor de bezoeker.
 */
const KEUZE_VANAF = 4;

/**
 * Of er iets te kiezen valt.
 *
 * Alleen het aantal telt. Eerder verdween de carrousel hier helemaal bij
 * `prefers-reduced-motion` en op een smal scherm -- **en dat was te ver
 * doorgeschoten.** Een voorkeur voor minder beweging betekent dat je iets
 * rustiger laat verlopen, niet dat je een half onderdeel weghaalt; de
 * carrousel springt daar nu gewoon meteen naar de volgende in plaats van
 * ernaartoe te glijden. En op een telefoon is het genoeg om met de lijst
 * te beginnen.
 */
const genoeg = computed(() => items.value.length >= KEUZE_VANAF);

/**
 * Of de carrousel er mag zijn.
 *
 * **Niet bij `prefers-reduced-motion`.** Een vak dat je scroll overneemt is
 * precies waar iemand met bewegingsklachten last van heeft, en een
 * carrousel die alleen niet glijdt neemt je scroll nog steeds over. Dan is
 * er de lijst, en die is compleet. De keuzeknop verdwijnt dan ook: een knop
 * die iets aanbiedt wat we bewust niet doen, is een kapotte knop. "Toon
 * alles" blijft, want dat venster scrollt gewoon.
 *
 * Een ref die pas in `onMounted` wordt gezet, om dezelfde reden als de
 * beginstand hieronder: op de server bestaat `window` niet.
 */
const bewegingMag = ref(true);

const keuzeTonen = computed(() => genoeg.value && bewegingMag.value);

const cijferrij = ref<HTMLElement | null>(null);

/* --- De jaarband ------------------------------------------------------ */

/**
 * Welke ervaring nu zichtbaar is: de voorste kaart van de carrousel, of in
 * de lijst de gekozen (anders de eerste van de pagina). Beide weergaven
 * melden dat met `wissel`; de jaarband licht dat jaar op.
 *
 * De band is de jaarbediening voor allebei de weergaven. Hier schoof hij
 * eerst met de scroll mee als los beeld (`aria-hidden`), naast een eigen
 * rail met jaartallen in de carrousel; nu is er één tijdlijn, en die doet
 * iets. Zie ErvaringJaarband.
 */
const zichtbaarIndex = ref<number | null>(null);

type Bestuurbaar = { naar: (index: number, focus?: boolean) => void };

const carrousel = useTemplateRef<Bestuurbaar>('carrousel');
const lijst = useTemplateRef<Bestuurbaar>('lijst');

/** Een jaar gekozen in de band: de weergave die er staat gaat erheen. */
const naarErvaring = (index: number, focus: boolean): void => {
    (carrousel.value ?? lijst.value)?.naar(index, focus);
};

let stopTellen: (() => void) | undefined;

/* --- Het venster met één ervaring ------------------------------------ */

const venster = ref(false);
const gekozen = ref<ErvaringOpDeSite | null>(null);

const toon = (item: ErvaringOpDeSite): void => {
    gekozen.value = item;
    venster.value = true;
};

/**
 * Hetzelfde venster, maar geopend op de lijst.
 *
 * Daarin kun je doorklikken naar één ervaring; dat venster verandert dan
 * van inhoud in plaats van er een tweede overheen te zetten. Zie
 * ErvaringVenster voor waarom dat uitmaakt.
 */
const toonAlles = (): void => {
    gekozen.value = null;
    venster.value = true;
};

onMounted(() => {
    /*
     * Pas hier en niet in de opzet: bij het opbouwen op de server bestaat
     * `window` niet, en dan zou dit een andere uitkomst geven dan in de
     * browser -- precies waar Vue over klaagt.
     *
     * Alleen de **beginstand** hangt hiervan af. Wisselen kan daarna
     * altijd, met de knop erboven.
     */
    bewegingMag.value = !prefersReducedMotion();

    if (
        !bewegingMag.value ||
        !window.matchMedia('(min-width: 50rem)').matches
    ) {
        weergave.value = 'lijst';
    }

    stopTellen = countUp(
        Array.from(
            cijferrij.value?.querySelectorAll<HTMLElement>('[data-teller]') ??
                [],
        ),
    );
});

onBeforeUnmount(() => {
    stopTellen?.();
});
</script>

<template>
    <SiteSection id="ervaring" :tone="tone" :divided="divided">
        <SectionHeading
            :eyebrow="kop.opschrift ?? undefined"
            :title="kop.titel"
            :intro="kop.inleiding ?? undefined"
        />

        <!--
            De cijfers die de loopbaan in één blik samenvatten, als
            gedeelde meting: het getal groot, het label eronder, een
            haarlijn ertussen (zie basis.css). Ze tellen omhoog zodra je ze in beeld
            scrolt; het eindgetal staat al in de HTML, zodat er ook zonder
            JavaScript iets klopt.

            Wat erin staat bepaalt de klant: leeg laten betekent "rekenen
            jullie het maar uit". Zie App\Support\Loopbaan.
        -->
        <dl
            v-if="cijfers.length > 0"
            ref="cijferrij"
            class="brand-site-ervaring-cijfers brand-site-metingen"
            :data-aantal="cijfers.length"
        >
            <div
                v-for="cijfer in cijfers"
                :key="cijfer.label"
                class="brand-site-meting"
            >
                <dt class="brand-site-meting-label">{{ cijfer.label }}</dt>
                <dd
                    class="brand-site-meting-waarde"
                    data-teller
                    :data-tot="cijfer.waarde"
                >
                    {{ cijfer.waarde }}
                </dd>
            </div>
        </dl>

        <!--
            "Toon alles" staat er vanaf vier functies, ook als de keuze
            tussen de weergaven er niet is (onder reduced motion): dat
            venster scrollt gewoon en neemt niets over.
        -->
        <div v-if="genoeg" class="brand-site-ervaring-bediening">
            <button
                type="button"
                class="brand-site-ervaring-alles"
                @click="toonAlles"
            >
                <Rows3 class="size-4" />
                {{ $t('Toon alles') }}
                <span class="brand-site-data opacity-60">
                    {{ items.length }}
                </span>
            </button>

            <div
                v-if="keuzeTonen"
                class="brand-site-ervaring-weergave"
                role="group"
                :aria-label="$t('Weergave van de tijdlijn')"
            >
                <button
                    type="button"
                    :data-actief="weergave === 'carrousel' ? '' : undefined"
                    :aria-pressed="weergave === 'carrousel'"
                    @click="weergave = 'carrousel'"
                >
                    <Radius class="size-4" />
                    {{ $t('Doorbladeren') }}
                </button>
                <button
                    type="button"
                    :data-actief="weergave === 'lijst' ? '' : undefined"
                    :aria-pressed="weergave === 'lijst'"
                    @click="weergave = 'lijst'"
                >
                    <LayoutList class="size-4" />
                    {{ $t('Alles op een rij') }}
                </button>
            </div>
        </div>

        <!--
            De jaarband: de jaarbediening van wat eronder staat. Alleen bij
            meer dan één beginjaar (dat beslist de band zelf), want één
            jaartal is geen tijdlijn.
        -->
        <ErvaringJaarband
            :items="items"
            :actief="zichtbaarIndex"
            @naar="naarErvaring"
        />

        <div class="mt-6">
            <!--
                `key` erop, zodat het wisselen van weergave een nieuw
                onderdeel oplevert. Zonder die sleutel zou Vue proberen het
                ene in het andere om te bouwen, en blijven de ScrollTriggers
                van de lijst hangen aan elementen die er niet meer zijn.
            -->
            <ErvaringCarrousel
                v-if="weergave === 'carrousel' && keuzeTonen"
                key="carrousel"
                ref="carrousel"
                :items="items"
                @open="toon"
                @wissel="zichtbaarIndex = $event"
            />
            <ErvaringLijst
                v-else
                key="lijst"
                ref="lijst"
                :items="items"
                @open="toon"
                @wissel="zichtbaarIndex = $event"
            />
        </div>

        <!--
            Eén ervaring helemaal uitgeschreven. Allebei de weergaven
            blijven daardoor compact: wat er niet in past, lees je hier.
        -->
        <ErvaringVenster
            v-model:open="venster"
            :items="items"
            :item="gekozen"
        />
    </SiteSection>
</template>
