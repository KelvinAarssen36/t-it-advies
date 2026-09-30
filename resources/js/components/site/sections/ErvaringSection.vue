<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { LayoutList, Radius, Rows3 } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import ErvaringVenster from '@/components/site/ErvaringVenster.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import ErvaringCarrousel from '@/components/site/sections/ErvaringCarrousel.vue';
import ErvaringLijst from '@/components/site/sections/ErvaringLijst.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import { countUp } from '@/lib/motion';
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
const kop = computed<ErvaringKop>(
    () =>
        (page.props.experienceHeading as ErvaringKop | undefined) ?? {
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
const keuzeTonen = computed(() => items.value.length >= KEUZE_VANAF);

const cijferrij = ref<HTMLElement | null>(null);

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
    if (!window.matchMedia('(min-width: 50rem)').matches) {
        weergave.value = 'lijst';
    }

    stopTellen = countUp(
        Array.from(
            cijferrij.value?.querySelectorAll<HTMLElement>('[data-teller]') ??
                [],
        ),
    );
});

onBeforeUnmount(() => stopTellen?.());
</script>

<template>
    <SiteSection id="ervaring" :tone="tone" :divided="divided">
        <SectionHeading
            eyebrow="Ervaring"
            :title="kop.titel"
            :intro="kop.inleiding ?? undefined"
        />

        <!--
            Drie cijfers die de lijst eronder in één blik samenvatten. Ze
            tellen omhoog zodra je ze in beeld scrolt; het eindgetal staat
            al in de HTML, zodat er ook zonder JavaScript iets klopt.

            Wat erin staat bepaalt de klant: leeg laten betekent "rekenen
            jullie het maar uit". Zie App\Support\Loopbaan.
        -->
        <dl
            v-if="cijfers.length > 0"
            ref="cijferrij"
            class="brand-loopbaan-cijfers mt-10"
        >
            <div v-for="cijfer in cijfers" :key="cijfer.label">
                <dd data-teller :data-tot="cijfer.waarde">
                    {{ cijfer.waarde }}
                </dd>
                <dt>{{ cijfer.label }}</dt>
            </div>
        </dl>

        <!--
            `flex-wrap` en niet `whitespace-nowrap`: op een smalle telefoon
            passen drie knoppen niet naast elkaar, en dan zakt "Toon alles"
            netjes naar de regel erboven in plaats van dat de hele rij uit
            het scherm loopt.
        -->
        <div
            v-if="keuzeTonen"
            class="mt-8 flex flex-wrap items-center justify-between gap-2"
        >
            <button type="button" class="brand-toon-alles" @click="toonAlles">
                <Rows3 class="size-4" />
                {{ $t('Toon alles') }}
                <span class="tabular-nums opacity-60">
                    {{ items.length }}
                </span>
            </button>

            <div class="brand-weergave" role="group">
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
                :items="items"
                @open="toon"
            />
            <ErvaringLijst v-else key="lijst" :items="items" @open="toon" />
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
