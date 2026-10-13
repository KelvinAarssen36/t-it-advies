<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Loader2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import SortableList from '@/components/SortableList.vue';
import DienstIcoon from '@/components/site/DienstIcoon.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { bevestigBewerken } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import diensten from '@/routes/website/diensten';
import type { DienstRij } from '@/types/diensten';

/**
 * De volgorde van de diensten verslepen.
 *
 * **In een venster en niet op de pagina zelf**, zoals overal in dit
 * portaal: het overzicht erachter blijft staan zoals het was. Dat is het
 * verschil tussen "ik kijk" en "ik ben aan het wijzigen", en dat verschil
 * hoort zichtbaar te zijn als alles wat je doet direct live staat.
 *
 * Slepen én pijltjestoetsen, allebei uit `SortableList`. Die pijltjes
 * zijn niet de tweede keus maar de betrouwbare weg: slepen op een
 * aanraakscherm binnen een schuifbaar venster gaat niet altijd goed.
 *
 * Zie docs/architecture/modules/diensten.md.
 */
const props = defineProps<{ items: DienstRij[] }>();

const open = defineModel<boolean>('open', { required: true });

const bezig = ref(false);

/**
 * De werkkopie.
 *
 * Elke regel wordt gekopieerd en niet doorgegeven: annuleren moet echt
 * annuleren. Zou hier de lijst van de pagina staan, dan verspringt het
 * overzicht erachter al terwijl er niets is opgeslagen -- en dan laat
 * het scherm iets zien wat de website niet doet.
 */
const concept = ref<DienstRij[]>([]);

/**
 * Of het venster zichzelf opnieuw opent en de volgorde dus moet blijven.
 *
 * Het gaat dicht vóór de bevestiging en weer open als de eigenaar daar
 * "nee" zegt. Zonder deze vlag vult het zich dan opnieuw uit de props --
 * en is alles wat hij net had gesleept weg, terwijl hij alleen op "nee"
 * drukte tegen de vraag óf het opgeslagen mocht worden.
 */
let behoudInhoud = false;

watch(open, (isOpen) => {
    if (!isOpen) {
        behoudInhoud = false;

        return;
    }

    if (behoudInhoud) {
        behoudInhoud = false;

        return;
    }

    concept.value = props.items.map((item) => ({ ...item }));
});

const alsRij = (lijst: DienstRij[]): number[] => lijst.map((item) => item.id);

const veranderd = computed(
    () => alsRij(concept.value).join('|') !== alsRij(props.items).join('|'),
);

const opslaan = async (): Promise<void> => {
    // Niets veranderd is geen opslag waard: geen vraag, geen verzoek,
    // geen melding. De server weigert hem ook, maar dan heeft de
    // eigenaar al twee keer "ja" gezegd tegen niets.
    if (!veranderd.value) {
        open.value = false;

        return;
    }

    /*
     * Het venster gaat dicht vóór de bevestiging in plaats van eronder
     * te liggen; zie de toelichting in pages/website/Indeling.vue.
     */
    open.value = false;
    await adem();

    const akkoord = await bevestigBewerken({
        titel: t('De volgorde van je diensten aanpassen?'),
        tekst: t('Ze komen in deze volgorde op je website te staan.'),
    });

    if (!akkoord) {
        await adem();
        behoudInhoud = true;
        open.value = true;

        return;
    }

    bezig.value = true;

    router.put(
        diensten.volgorde().url,
        { diensten: alsRij(concept.value) },
        {
            preserveScroll: true,
            onFinish: () => {
                bezig.value = false;
            },
        },
    );
};

const adem = (): Promise<void> =>
    new Promise((klaar) => setTimeout(klaar, 200));
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="max-h-[85vh] brand-scrollbar overflow-y-auto sm:max-w-xl"
        >
            <DialogHeader>
                <DialogTitle>{{
                    $t('De volgorde van je diensten')
                }}</DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Sleep ze in de volgorde waarin je ze op je website wilt. Met het toetsenbord kan het ook, met de pijltjes naast elke regel.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <SortableList
                v-model="concept"
                :label="$t('De volgorde van je diensten')"
            >
                <!--
                    De greep en de pijltjes zet SortableList er zelf
                    omheen; hier staat alleen wat er op de regel komt.
                -->
                <template #default="{ item }">
                    <div class="flex items-center gap-2.5">
                        <span class="brand-dienst-merk" aria-hidden="true">
                            <DienstIcoon :icoon="item.icon" class="size-4" />
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium">
                                {{ item.title_nl }}
                            </span>
                            <span
                                v-if="!item.published"
                                class="block text-xs text-muted-foreground"
                            >
                                {{ $t('Staat niet op je website') }}
                            </span>
                        </span>
                    </div>
                </template>
            </SortableList>

            <DialogFooter class="gap-2">
                <div class="flex gap-2">
                    <Button
                        variant="ghost"
                        :disabled="bezig"
                        @click="open = false"
                    >
                        {{ $t('Annuleren') }}
                    </Button>
                    <Button
                        variant="bewerken"
                        class="gap-2"
                        :disabled="bezig"
                        @click="opslaan"
                    >
                        <Loader2 v-if="bezig" class="size-4 animate-spin" />
                        {{ $t('Opslaan') }}
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
