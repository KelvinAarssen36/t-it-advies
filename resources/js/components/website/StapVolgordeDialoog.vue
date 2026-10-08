<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import SortableList from '@/components/SortableList.vue';
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
import werkwijze from '@/routes/website/werkwijze';
import type { StapRij } from '@/types/werkwijze';

/**
 * De volgorde van de stappen verslepen.
 *
 * Dezelfde opzet als bij de andere modules: in een venster en niet op de
 * pagina zelf, zodat het overzicht erachter blijft staan zoals het was.
 * Slepen én pijltjestoetsen, allebei uit `SortableList`.
 *
 * **Hier betekent de volgorde meer dan waar dan ook.** Bij de vragen
 * bepaalt slepen op welke bladzijde iets komt; hier bepaalt het ook het
 * nummer op de kaart, en daarmee wat de bezoeker leest als "stap 1".
 * Vandaar dat elke regel zijn nummer meteen laat zien terwijl je sleept --
 * je ziet de nummering veranderen voordat je opslaat.
 *
 * Zie docs/architecture/modules/werkwijze.md.
 */
const props = defineProps<{ items: StapRij[] }>();

const open = defineModel<boolean>('open', { required: true });

const bezig = ref(false);

/**
 * De werkkopie.
 *
 * Elke regel wordt gekopieerd en niet doorgegeven: annuleren moet echt
 * annuleren. Zou hier de lijst van de pagina staan, dan verspringt het
 * overzicht erachter al terwijl er niets is opgeslagen.
 */
const concept = ref<StapRij[]>([]);

/** Of het venster zichzelf opnieuw opent en de volgorde dus moet blijven. */
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

const alsRij = (lijst: StapRij[]): number[] => lijst.map((item) => item.id);

const veranderd = computed(
    () => alsRij(concept.value).join('|') !== alsRij(props.items).join('|'),
);

/**
 * Het nummer dat een regel op de website krijgt, terwijl je sleept.
 *
 * Alleen de stappen die online staan tellen mee: een offline stap staat
 * niet op de website en hoort dus geen nummer te bezetten. Dezelfde
 * telling als op het beheerscherm -- zou die hier anders zijn, dan ziet
 * de eigenaar tijdens het slepen andere nummers dan erna.
 */
const nummers = computed(() => {
    const uit = new Map<number, number>();
    let teller = 0;

    concept.value.forEach((item) => {
        if (item.published) {
            teller++;
            uit.set(item.id, teller);
        }
    });

    return uit;
});

const opslaan = async (): Promise<void> => {
    // Niets veranderd is geen opslag waard: geen vraag, geen verzoek, geen
    // melding.
    if (!veranderd.value) {
        open.value = false;

        return;
    }

    open.value = false;
    await adem();

    const akkoord = await bevestigBewerken({
        titel: t('De volgorde van je werkwijze aanpassen?'),
        tekst: t('De nummers op je website lopen mee met de nieuwe volgorde.'),
    });

    if (!akkoord) {
        await adem();
        behoudInhoud = true;
        open.value = true;

        return;
    }

    bezig.value = true;

    router.put(
        werkwijze.volgorde().url,
        { stappen: concept.value.map((item) => ({ id: item.id })) },
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
                <DialogTitle>
                    {{ $t('De volgorde van je werkwijze') }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Sleep de stappen in de volgorde waarin ze gebeuren. De nummers lopen mee -- je hoeft ze nergens in te vullen. Met het toetsenbord kan het ook, met de pijltjes naast elke regel.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <SortableList
                v-model="concept"
                :label="$t('De volgorde van je werkwijze')"
            >
                <!--
                    De greep en de pijltjes zet SortableList er zelf omheen;
                    hier staat alleen wat er op de regel komt.
                -->
                <template #default="{ item }">
                    <div class="flex items-center gap-2.5">
                        <!--
                            Het nummer in plaats van een pictogram: dat is
                            hier het enige dat verandert terwijl je sleept,
                            en dus precies wat je wil zien.
                        -->
                        <span
                            class="brand-logo-rondje is-klein font-mono text-xs"
                        >
                            <template v-if="nummers.get(item.id)">
                                {{
                                    String(nummers.get(item.id)).padStart(
                                        2,
                                        '0',
                                    )
                                }}
                            </template>
                            <template v-else>&mdash;</template>
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium">
                                {{ item.title_nl }}
                            </span>
                            <span
                                class="block truncate text-xs text-muted-foreground"
                            >
                                <template v-if="!item.published">
                                    {{ $t('Staat niet op je website') }}
                                </template>
                                <template v-else-if="item.duration_nl">
                                    {{ item.duration_nl }}
                                </template>
                                <template v-else>
                                    {{ item.summary_nl }}
                                </template>
                            </span>
                        </span>
                    </div>
                </template>
            </SortableList>

            <DialogFooter>
                <Button type="button" variant="secondary" @click="open = false">
                    {{ $t('Annuleren') }}
                </Button>
                <Button
                    type="button"
                    variant="bewerken"
                    :disabled="bezig || !veranderd"
                    @click="opslaan"
                >
                    {{ bezig ? $t('Bezig...') : $t('Opslaan') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
