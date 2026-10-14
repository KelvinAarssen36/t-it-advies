<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ArrowRight, ChevronDown, Plus } from '@lucide/vue';
import {
    AccordionContent,
    AccordionHeader,
    AccordionItem,
    AccordionRoot,
    AccordionTrigger,
} from 'reka-ui';
import { computed, ref, watch } from 'vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import { scrollNaar } from '@/lib/motion';
import { useSectieLink } from '@/lib/sectielink';
import type { VraagOpDeSite } from '@/types/faq';
import type { SectieKop, SectieProps } from '@/types/secties';

/**
 * De veelgestelde vragen.
 *
 * **De accordeon komt uit reka-ui en is niet zelf gebouwd.** Dat pakket
 * staat er al, en het regelt drie dingen die je met een eigen `v-if` alle
 * drie fout doet: de pijltjestoetsen tussen de vragen, `aria-expanded` op
 * de knop met `aria-controls` naar het antwoord, en een hoogte om naartoe
 * te animeren (`--reka-accordion-content-height`). Een vragenlijst die je
 * alleen met de muis kunt openen is geen vragenlijst.
 *
 * `type="single"` met `collapsible`: er staat er **één open tegelijk** en
 * je kunt hem ook weer dichtklikken. Zo groeit dit blok nooit met meer
 * dan één antwoord, en blijft de pagina eronder staan -- dezelfde zorg
 * als bij de diensten en de tijdlijn, die daarom bladeren.
 *
 * **Er wordt gebladerd per zes.** Elke bladzijde is daarmee even hoog, dus
 * wat eronder staat verschuift niet als de eigenaar zijn dertigste vraag
 * toevoegt.
 *
 * **De vragen buiten deze bladzijde zijn `hidden` én `disabled`, en dat
 * tweede is geen overdaad.** Reka laat de pijltjestoetsen langs de vragen
 * lopen met `useArrowNavigation`, en die slaat een `disabled` element over
 * -- maar een `hidden` element niet. Alleen `hidden` zou dus betekenen dat
 * je met de pijltjes vanaf de zesde vraag naar iets onzichtbaars springt en
 * je focus kwijt bent. Nagemeten in
 * node_modules/reka-ui/dist/shared/useArrowNavigation.js.
 *
 * **Álle vragen staan in de pagina, ook die van bladzijde twee en drie.**
 * Dat is het verschil met de diensten en de tijdlijn, die alleen de
 * huidige bladzijde opbouwen. De reden is zoeken: deze pagina wordt op de
 * server gerenderd, dus wat niet in de HTML staat ziet Google niet -- en
 * bij een vragenlijst is dat precies de inhoud waarop gezocht wordt. De
 * vragen buiten de huidige bladzijde krijgen daarom `hidden` en worden
 * niet weggelaten. Voor een voorleesprogramma is dat ook juist: wat niet
 * in beeld staat hoort niet meegelezen te worden.
 *
 * **Twee kolommen op een breed scherm.** Links staat de kop stil terwijl je
 * door de vragen gaat, met daaronder de weg naar het contactformulier voor
 * wie zijn vraag niet vindt; rechts de vragen zelf, groot gezet. Op een
 * telefoon staat het gewoon onder elkaar.
 *
 * **Het antwoord klapt open met `grid-template-rows`, en daarom staat het
 * altijd in de pagina** (`force-mount`). Een rij van `0fr` naar `1fr` kan
 * de browser zelf vloeiend overgaan, zonder een hoogte te meten. Een
 * dichte vraag is daarbij `visibility: hidden`, zodat een schermlezer hem
 * net zo overslaat als toen hij er niet stond. Zie
 * resources/css/site/faq.css.
 *
 * Zie docs/architecture/modules/faq.md.
 */
defineProps<SectieProps>();

const page = usePage();

const vragen = computed<VraagOpDeSite[]>(
    () => (page.props.faq as VraagOpDeSite[] | undefined) ?? [],
);

const kop = computed<SectieKop | null>(
    () => (page.props.faqHeading as SectieKop | undefined) ?? null,
);

/* --- De weg naar contact ---------------------------------------------- */

/**
 * Alleen als het contactblok er ook staat.
 *
 * Zet de klant het uit, dan zou deze link naar een anker springen dat niet
 * bestaat. Het menu van de server zegt welke onderdelen er zijn; dezelfde
 * bron als de kop gebruikt.
 */
const { anker, opDeVoorpagina, secties } = useSectieLink();

const heeftContact = computed(() =>
    secties.value.some((item) => item.key === 'contact'),
);

/**
 * Naar het contactblok, met dezelfde glijbeweging als het menu.
 *
 * Een klik met Ctrl of de middelste knop gaat gewoon door: wie hem in een
 * nieuw tabblad wil openen, krijgt dat. Bestaat het doel niet, dan doet
 * de browser zijn eigen sprong.
 */
const naarContact = (gebeurtenis: MouseEvent): void => {
    if (
        !opDeVoorpagina.value ||
        gebeurtenis.metaKey ||
        gebeurtenis.ctrlKey ||
        gebeurtenis.shiftKey ||
        gebeurtenis.button !== 0
    ) {
        return;
    }

    if (scrollNaar('contact')) {
        gebeurtenis.preventDefault();
        history.replaceState(history.state, '', '#contact');
    }
};

/* --- Bladeren ---------------------------------------------------------- */

/**
 * Hoeveel vragen er per bladzijde staan.
 *
 * Zes, en op een telefoon ook zes. Anders dan bij de diensten, waar een
 * kaart op een smal scherm veel hoger is: een dichtgeklapte vraag is
 * overal één regel, dus er is geen reden om er daar minder te zetten.
 */
const PER_BLADZIJDE = 6;

const bladzijde = ref(1);

const bladzijden = computed(() =>
    Math.max(1, Math.ceil(vragen.value.length / PER_BLADZIJDE)),
);

/** Op welke bladzijde deze vraag staat, één-gebaseerd. */
const bladzijdeVan = (index: number): number =>
    Math.floor(index / PER_BLADZIJDE) + 1;

/** Of deze vraag op de bladzijde staat die je nu ziet. */
const staatErNu = (index: number): boolean =>
    bladzijdeVan(index) === bladzijde.value;

/**
 * De nummers onder het blok, met puntjes waar er een gat zit.
 *
 * Dezelfde opzet als bij de diensten: altijd de eerste, de laatste en de
 * buren van waar je staat. Bij vier bladzijden staan ze er gewoon alle
 * vier.
 */
const nummers = computed<Array<number | 'gat'>>(() => {
    const totaal = bladzijden.value;

    if (totaal <= 5) {
        return Array.from({ length: totaal }, (_, i) => i + 1);
    }

    const rond = [
        1,
        totaal,
        bladzijde.value - 1,
        bladzijde.value,
        bladzijde.value + 1,
    ]
        .filter((nummer) => nummer >= 1 && nummer <= totaal)
        .sort((a, b) => a - b);

    const uniek = [...new Set(rond)];
    const rij: Array<number | 'gat'> = [];

    uniek.forEach((nummer, index) => {
        if (index > 0 && nummer - (uniek[index - 1] ?? 0) > 1) {
            rij.push('gat');
        }

        rij.push(nummer);
    });

    return rij;
});

/* --- Wat er open staat ------------------------------------------------- */

/**
 * De vraag die open staat, als tekstsleutel; een lege string is "alles
 * dicht". Dat is wat `AccordionRoot` met `type="single"` verwacht.
 */
const open = ref('');

/**
 * Van bladzijde wisselen sluit wat er open stond.
 *
 * Anders staat er een antwoord open dat je niet meer ziet, en klapt het
 * weer in beeld zodra je terugbladert -- terwijl je inmiddels iets anders
 * aan het lezen was.
 */
watch(bladzijde, () => {
    open.value = '';
});

/**
 * Naar een andere bladzijde.
 *
 * Anders dan bij de diensten staat hier geen scroll naar de bovenkant van
 * het blok: elke bladzijde is hier even hoog, dus het blok verschuift
 * niet onder je muis weg.
 */
const ga = (nummer: number): void => {
    bladzijde.value = Math.min(Math.max(1, nummer), bladzijden.value);
};

/*
 * Komt er een vraag bij of af terwijl je op de laatste bladzijde staat,
 * dan kan je nummer buiten de lijst vallen. Zie DienstenSection.
 */
watch(bladzijden, () => {
    bladzijde.value = Math.min(bladzijde.value, bladzijden.value);
});
</script>

<template>
    <SiteSection id="faq" :tone="tone" :divided="divided">
        <div class="brand-site-faq">
            <div class="brand-site-faq-zij">
                <SectionHeading
                    v-if="kop"
                    :eyebrow="kop.opschrift ?? undefined"
                    :title="kop.titel"
                    :intro="kop.inleiding ?? undefined"
                />

                <!--
                    Alleen de link, zonder zin ervoor: de inleiding die de
                    klant boven de vragen zet zegt vaak al "staat je vraag
                    er niet bij?", en dan stond het er twee keer.
                -->
                <p
                    v-if="heeftContact"
                    data-reveal
                    class="brand-site-faq-verder opacity-0"
                >
                    <a
                        :href="anker('contact')"
                        class="brand-site-link"
                        @click="naarContact"
                    >
                        {{ $t('Neem contact op') }}
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </a>
                </p>
            </div>

            <div class="brand-site-faq-vragen">
                <AccordionRoot
                    v-model="open"
                    type="single"
                    collapsible
                    class="brand-site-faq-lijst"
                >
                    <AccordionItem
                        v-for="(vraag, index) in vragen"
                        :key="vraag.id"
                        :value="String(vraag.id)"
                        class="brand-site-faq-item"
                        :hidden="!staatErNu(index)"
                        :disabled="!staatErNu(index)"
                    >
                        <AccordionHeader class="brand-site-faq-kop">
                            <AccordionTrigger class="brand-site-faq-knop">
                                <span class="brand-site-faq-vraag">
                                    {{ vraag.vraag }}
                                </span>
                                <!--
                                    Een plus die een kruisje wordt. Hij
                                    staat in een eigen rondje, zodat het
                                    aanraakvlak ook op een telefoon groot
                                    genoeg is om te raken.
                                -->
                                <span
                                    class="brand-site-faq-teken"
                                    aria-hidden="true"
                                >
                                    <Plus class="size-4" />
                                </span>
                            </AccordionTrigger>
                        </AccordionHeader>

                        <AccordionContent
                            force-mount
                            class="brand-site-faq-antwoord"
                        >
                            <div class="brand-site-faq-binnen">
                                <p class="brand-site-faq-tekst">
                                    {{ vraag.antwoord }}
                                </p>
                            </div>
                        </AccordionContent>
                    </AccordionItem>
                </AccordionRoot>

                <!--
                    De knopjes om te bladeren. Pas vanaf twee bladzijden:
                    met één staat er een rij van één knop die niets doet.
                -->
                <nav
                    v-if="bladzijden > 1"
                    class="brand-site-faq-bladeren"
                    :aria-label="$t('Bladzijden met vragen')"
                >
                    <button
                        type="button"
                        class="brand-site-faq-stap"
                        :disabled="bladzijde === 1"
                        :aria-label="$t('Vorige bladzijde')"
                        @click="ga(bladzijde - 1)"
                    >
                        <ChevronDown
                            class="brand-site-faq-stap-pijl is-vorige"
                        />
                    </button>

                    <template
                        v-for="(nummer, i) in nummers"
                        :key="`${nummer}-${i}`"
                    >
                        <span
                            v-if="nummer === 'gat'"
                            class="brand-site-faq-gat"
                        >
                            &hellip;
                        </span>
                        <button
                            v-else
                            type="button"
                            class="brand-site-faq-nummer font-data"
                            :data-hier="nummer === bladzijde ? '' : undefined"
                            :aria-current="
                                nummer === bladzijde ? 'true' : undefined
                            "
                            :aria-label="$t('Bladzijde :nummer', { nummer })"
                            @click="ga(nummer)"
                        >
                            {{ nummer }}
                        </button>
                    </template>

                    <button
                        type="button"
                        class="brand-site-faq-stap"
                        :disabled="bladzijde === bladzijden"
                        :aria-label="$t('Volgende bladzijde')"
                        @click="ga(bladzijde + 1)"
                    >
                        <ChevronDown
                            class="brand-site-faq-stap-pijl is-volgende"
                        />
                    </button>
                </nav>
            </div>
        </div>
    </SiteSection>
</template>
