<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, useTemplateRef } from 'vue';
import KerngegevenIcoon from '@/components/site/KerngegevenIcoon.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import { rolbord } from '@/lib/motion';
import type { KerngegevenOpDeSite } from '@/types/kerngegevens';
import type { SectieKop, SectieProps } from '@/types/secties';

/**
 * De feitenstrook: beschikbaarheid, werkgebied, reactietijd.
 *
 * **De waarde is groot en het label klein**, en dat is precies andersom
 * dan bij de statistieken ernaast. Daar draagt de meter de boodschap en is
 * het label het bijschrift; hier ís de waarde het antwoord. "Vanaf
 * januari" is wat de bezoeker zoekt, "Beschikbaar" zegt alleen waar hij
 * kijkt.
 *
 * **Eén omlijnd blok met scheidingslijntjes, geen losse tegels.** Tegels
 * zijn er al bij de statistieken en kaarten bij de diensten. Een strook
 * leest als één mededeling in plaats van als acht losse, en dat is wat
 * deze gegevens zijn: samen vormen ze het antwoord op "kan ik met deze
 * man in zee".
 *
 * **De laatste rij vult zichzelf.** Zeven gegevens worden vier plus drie
 * en niet vier plus drie plus een leeg vak; zie
 * `.brand-kerngegevens-strook` in app.css voor waarom dat flex is en geen
 * raster.
 *
 * **De animatie is tweeledig.** De strook komt als één blok op met de
 * gedeelde `revealOnScroll` uit PublicLayout, en daarná zetten de waarden
 * zich vast als een rolbord -- letter voor letter, of per woord als de
 * waarde daar te lang voor is. Zie `rolbord()` in
 * resources/js/lib/motion.ts voor waarom dat zo werkt en wat er met een
 * schermlezer gebeurt.
 *
 * Zie docs/architecture/modules/kerngegevens.md.
 */
defineProps<SectieProps>();

const page = usePage();

const gegevens = computed<KerngegevenOpDeSite[]>(
    () => (page.props.coreFacts as KerngegevenOpDeSite[] | undefined) ?? [],
);

const kop = computed<SectieKop | null>(
    () => (page.props.coreFactsHeading as SectieKop | undefined) ?? null,
);

/**
 * Hoeveel vakken er op een breed scherm naast elkaar staan.
 *
 * Vier is het maximum, maar bij vijf of zes staan drie kolommen rustiger:
 * drie plus twee en drie plus drie lezen als twee volle rijen, waar vier
 * plus een eruitziet alsof er iets is weggevallen. De laatste rij vult
 * zichzelf sowieso, dus dit gaat over de rust en niet over gaten.
 */
const kolommen = computed(() => {
    const aantal = gegevens.value.length;

    if (aantal <= 4) {
        return Math.max(aantal, 1);
    }

    return aantal <= 6 ? 3 : 4;
});

const strook = useTemplateRef<HTMLElement>('strook');

let stop: (() => void) | undefined;

onMounted(() => {
    stop = rolbord('[data-rolbord]', strook.value);
});

onBeforeUnmount(() => stop?.());
</script>

<template>
    <SiteSection id="kerngegevens" :tone="tone" :divided="divided">
        <div class="brand-kerngegevens">
            <SectionHeading
                v-if="kop"
                :eyebrow="kop.opschrift ?? undefined"
                :title="kop.titel"
                :intro="kop.inleiding ?? undefined"
            />

            <!--
                Een `dl` en geen `ul`: dit zijn termen met hun omschrijving,
                en dat is precies wat een definitielijst is. Een
                schermlezer kondigt hem dan ook zo aan.
            -->
            <!--
                `data-reveal` zet hem in de rij van de gedeelde reveal in
                PublicLayout, net als de projectkaarten: de strook komt als
                één blok op. Op de vakken afzonderlijk zouden het acht
                losse fades worden, en dan is het geen strook meer maar een
                rij tegels.
            -->
            <dl
                ref="strook"
                data-reveal
                class="brand-kerngegevens-strook opacity-0"
                :style="{ '--kolommen-breed': kolommen }"
            >
                <div
                    v-for="gegeven in gegevens"
                    :key="gegeven.id"
                    class="brand-kerngegevens-vak"
                >
                    <dt class="brand-kerngegevens-label">
                        <KerngegevenIcoon
                            :icoon="gegeven.soort"
                            class="brand-kerngegevens-icoon"
                            aria-hidden="true"
                        />
                        {{ gegeven.label }}
                    </dt>

                    <dd class="brand-kerngegevens-waarde">
                        <!--
                            `opacity-0` tot de animatie hem zichtbaar zet,
                            net als bij de andere reveals. Staat er geen
                            JavaScript, dan zet `rolbord()` hem meteen goed
                            -- en bij `prefers-reduced-motion` ook.
                        -->
                        <span data-rolbord class="opacity-0">
                            {{ gegeven.waarde }}
                        </span>

                        <span
                            v-if="gegeven.notitie"
                            class="brand-kerngegevens-notitie"
                        >
                            {{ gegeven.notitie }}
                        </span>
                    </dd>
                </div>
            </dl>
        </div>
    </SiteSection>
</template>
