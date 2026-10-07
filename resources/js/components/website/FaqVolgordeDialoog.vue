<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CircleQuestionMark } from '@lucide/vue';
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
import faq from '@/routes/website/faq';
import type { VraagRij } from '@/types/faq';

/**
 * De volgorde van de vragen verslepen.
 *
 * Dezelfde opzet als bij de andere modules: in een venster en niet op de
 * pagina zelf, zodat het overzicht erachter blijft staan zoals het was.
 * Slepen én pijltjestoetsen, allebei uit `SortableList`.
 *
 * **Hier betekent de volgorde meer dan bij de andere modules.** Het blok
 * op de website bladert per zes, dus wat je bovenaan sleept staat op de
 * eerste bladzijde -- en dat is de enige die de meeste bezoekers zien. De
 * uitleg in dit venster zegt dat ook.
 *
 * Zie docs/architecture/modules/faq.md.
 */
const props = defineProps<{ items: VraagRij[] }>();

const open = defineModel<boolean>('open', { required: true });

const bezig = ref(false);

/**
 * De werkkopie.
 *
 * Elke regel wordt gekopieerd en niet doorgegeven: annuleren moet echt
 * annuleren. Zou hier de lijst van de pagina staan, dan verspringt het
 * overzicht erachter al terwijl er niets is opgeslagen.
 */
const concept = ref<VraagRij[]>([]);

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

const alsRij = (lijst: VraagRij[]): number[] => lijst.map((item) => item.id);

const veranderd = computed(
    () => alsRij(concept.value).join('|') !== alsRij(props.items).join('|'),
);

/** Of deze regel op de eerste bladzijde van de website komt. */
const opEersteBladzijde = (index: number): boolean => index < 6;

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
        titel: t('De volgorde van je vragen aanpassen?'),
        tekst: t('De eerste zes komen op de eerste bladzijde van je website.'),
    });

    if (!akkoord) {
        await adem();
        behoudInhoud = true;
        open.value = true;

        return;
    }

    bezig.value = true;

    router.put(
        faq.volgorde().url,
        { vragen: concept.value.map((item) => ({ id: item.id })) },
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
                    {{ $t('De volgorde van je vragen') }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Sleep ze in de volgorde waarin je ze wilt. Op je website staan er zes per bladzijde, dus zet bovenaan wat je het vaakst krijgt gevraagd. Met het toetsenbord kan het ook, met de pijltjes naast elke regel.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <SortableList
                v-model="concept"
                :label="$t('De volgorde van je vragen')"
            >
                <!--
                    De greep en de pijltjes zet SortableList er zelf omheen;
                    hier staat alleen wat er op de regel komt.
                -->
                <template #default="{ item, index }">
                    <div class="flex items-center gap-2.5">
                        <span class="brand-logo-rondje is-klein">
                            <CircleQuestionMark class="size-3.5" />
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium">
                                {{ item.question_nl }}
                            </span>
                            <span
                                class="block truncate text-xs text-muted-foreground"
                            >
                                <template v-if="!item.published">
                                    {{ $t('Staat niet op je website') }}
                                </template>
                                <template v-else-if="opEersteBladzijde(index)">
                                    {{ $t('Op de eerste bladzijde') }}
                                </template>
                                <template v-else>
                                    {{
                                        $t('Op bladzijde :nummer', {
                                            nummer: Math.floor(index / 6) + 1,
                                        })
                                    }}
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
