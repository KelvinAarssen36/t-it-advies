<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
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
        <div class="brand-faq">
            <SectionHeading
                v-if="kop"
                :eyebrow="kop.opschrift ?? undefined"
                :title="kop.titel"
                :intro="kop.inleiding ?? undefined"
            />

            <AccordionRoot
                v-model="open"
                type="single"
                collapsible
                class="brand-faq-lijst"
            >
                <AccordionItem
                    v-for="(vraag, index) in vragen"
                    :key="vraag.id"
                    :value="String(vraag.id)"
                    class="brand-faq-item"
                    :hidden="!staatErNu(index)"
                    :disabled="!staatErNu(index)"
                >
                    <AccordionHeader class="brand-faq-kop">
                        <!--
                            **Geen `brand-glans` hier, en dat is een
                            correctie.** Die utility is gemaakt voor de
                            actieknop: 35% van de breedte aan 55% wit, in
                            een lus van zeven seconden. Op een knop is dat
                            een glinstering; op een regel van de volle
                            sectiebreedte is 35% een witte baan, en zes
                            regels die dat in een lus doen zien eruit als
                            een laadanimatie.

                            Erger nog: hem overrulen lukte niet, want
                            `brand-glans` staat in een andere cascadelaag
                            dan deze module -- dus de lus liep gewoon door.
                            Vandaar een eigen veeg in `brand-faq-knop`:
                            vaste maat, veel lichter, en één keer bij het
                            openklappen. Zie app.css.
                        -->
                        <AccordionTrigger class="brand-faq-knop">
                            <span class="brand-faq-vraag">
                                {{ vraag.vraag }}
                            </span>
                            <ChevronDown
                                class="brand-faq-pijl"
                                aria-hidden="true"
                            />
                        </AccordionTrigger>
                    </AccordionHeader>

                    <AccordionContent class="brand-faq-antwoord">
                        <!--
                            Eigen klasse en niet `brand-blad-tekst`, al
                            doet die het alineawerk ook: die is gemaakt
                            voor een venster en krijgt op een breed scherm
                            een eigen schuifgebied. Zie app.css.
                        -->
                        <p class="brand-faq-tekst">{{ vraag.antwoord }}</p>
                    </AccordionContent>
                </AccordionItem>
            </AccordionRoot>

            <!--
                De knopjes om te bladeren. Pas vanaf twee bladzijden: met
                één staat er een rij van één knop die niets doet.
            -->
            <nav
                v-if="bladzijden > 1"
                class="brand-faq-bladeren"
                :aria-label="$t('Bladzijden met vragen')"
            >
                <button
                    type="button"
                    class="brand-faq-stap"
                    :disabled="bladzijde === 1"
                    :aria-label="$t('Vorige bladzijde')"
                    @click="ga(bladzijde - 1)"
                >
                    <ChevronDown class="brand-faq-stap-pijl is-vorige" />
                </button>

                <template
                    v-for="(nummer, i) in nummers"
                    :key="`${nummer}-${i}`"
                >
                    <span v-if="nummer === 'gat'" class="brand-faq-gat">
                        &hellip;
                    </span>
                    <button
                        v-else
                        type="button"
                        class="brand-faq-nummer"
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
                    class="brand-faq-stap"
                    :disabled="bladzijde === bladzijden"
                    :aria-label="$t('Volgende bladzijde')"
                    @click="ga(bladzijde + 1)"
                >
                    <ChevronDown class="brand-faq-stap-pijl is-volgende" />
                </button>
            </nav>
        </div>
    </SiteSection>
</template>
