<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Languages, Loader2 } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import LocaleFlag from '@/components/LocaleFlag.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { bevestigBewerken } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import website from '@/routes/website';
import overMij from '@/routes/website/over-mij';
import type { OverMijInstelling, OverMijOpties } from '@/types/over-mij';

/**
 * Het korte stuk op de voorpagina bewerken: de samenvatting, in twee talen.
 *
 * **Alleen de velden van dít blok.** De teksten van de aparte pagina zitten
 * in een eigen venster met een eigen eindpunt. Zouden ze samen in één
 * verzoek gaan, dan bewaart dit venster de velden van het andere als leeg
 * -- en dat is een fout die je pas ziet als je je verhaal kwijt bent.
 *
 * **De foto zit hier niet meer bij, en dat is een tweede correctie.** Hij
 * werd hier gekozen, dus hij stond hier. Maar hij staat op de voorpagina én
 * op de aparte pagina, en dan is "hij hoort bij het blok" een afspraak die
 * je moet onthouden in plaats van iets wat je ziet. Hij heeft nu zijn eigen
 * venster met zijn eigen eindpunt; zie OverMijFotoDialoog.
 *
 * Zie docs/architecture/modules/over-mij.md.
 */
const props = defineProps<{
    instelling: OverMijInstelling;
    opties: OverMijOpties;
    kanVertalen: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const bezig = ref(false);
const fouten = ref<Record<string, string>>({});

const formulier = ref({ summary_nl: '', summary_en: '' });

const automatisch = ref(false);
const vertaalt = ref(false);
const vertaalFout = ref<string | null>(null);

/** Het Engels zoals het bij het openen stond. */
let engelsBijOpenen = '';

/** Of het venster zichzelf opnieuw opent en de inhoud dus moet blijven. */
let behoudInhoud = false;

const openOpnieuw = (): void => {
    behoudInhoud = true;
    open.value = true;
};

const vulIn = (): void => {
    formulier.value = {
        summary_nl: props.instelling.summary_nl ?? '',
        summary_en: props.instelling.summary_en ?? '',
    };

    engelsBijOpenen = formulier.value.summary_en;
    automatisch.value = props.instelling.automatisch_vertaald;

    fouten.value = {};
    vertaalFout.value = null;
};

watch(open, (isOpen) => {
    if (!isOpen) {
        behoudInhoud = false;

        return;
    }

    if (behoudInhoud) {
        behoudInhoud = false;

        return;
    }

    vulIn();
});

const over = computed(
    () => props.opties.samenvattingMax - formulier.value.summary_nl.length,
);

const overEn = computed(
    () => props.opties.samenvattingMax - formulier.value.summary_en.length,
);

/* --- Automatisch vertalen ---------------------------------------------- */

let stopLuisteren: (() => void) | undefined;

onMounted(() => {
    stopLuisteren = router.on('flash', (gebeurtenis) => {
        const velden = (gebeurtenis as CustomEvent).detail?.flash?.vertaling as
            | Record<string, unknown>
            | undefined;

        if (!velden || !open.value) {
            return;
        }

        if (typeof velden.summary_en === 'string') {
            formulier.value.summary_en = velden.summary_en;
        }

        automatisch.value = true;
        vertaalFout.value = null;
    });
});

onBeforeUnmount(() => stopLuisteren?.());

const kanVertalen = computed(
    () => props.kanVertalen && formulier.value.summary_nl.trim() !== '',
);

const vertaal = (): void => {
    vertaalt.value = true;
    vertaalFout.value = null;

    router.post(
        website.vertalen().url,
        { summary_nl: formulier.value.summary_nl },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                vertaalFout.value = t(
                    'Het vertalen is niet gelukt. Probeer het zo nog eens, of vul het Engels zelf in.',
                );
            },
            onFinish: () => {
                vertaalt.value = false;
            },
        },
    );
};

/* --- Opslaan ----------------------------------------------------------- */

const opslaan = async (): Promise<void> => {
    open.value = false;
    await adem();

    const akkoord = await bevestigBewerken({
        titel: t('Het stuk op je voorpagina aanpassen?'),
    });

    if (!akkoord) {
        await adem();
        openOpnieuw();

        return;
    }

    bezig.value = true;

    router.put(
        overMij.blok().url,
        {
            ...formulier.value,
            machine_translated:
                automatisch.value &&
                formulier.value.summary_en === engelsBijOpenen,
        },
        {
            preserveScroll: true,
            onError: (ontvangen) => {
                fouten.value = ontvangen;
                openOpnieuw();
            },
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
            class="max-h-[85vh] brand-scrollbar overflow-y-auto sm:max-w-2xl"
        >
            <DialogHeader>
                <DialogTitle>{{ $t('Op je website') }}</DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Dit staat altijd op je voorpagina, met je foto ernaast. Hou het kort -- het is een kennismaking en niet je hele verhaal.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="samenvatting-nl">
                        <LocaleFlag locale="nl" size="sm" />
                        {{ $t('Samenvatting') }}
                    </Label>
                    <Textarea
                        id="samenvatting-nl"
                        v-model="formulier.summary_nl"
                        :maxlength="props.opties.samenvattingMax"
                        rows="4"
                        :placeholder="
                            $t(
                                'Bijvoorbeeld: Ik werk sinds 2008 in de IT, en sinds 2016 voor mezelf.',
                            )
                        "
                        v-focus
                    />
                    <InputError :message="fouten.summary_nl" />
                    <p class="text-xs text-muted-foreground">
                        {{ $t(':aantal tekens over', { aantal: over }) }}
                    </p>
                </div>

                <div class="border-t border-border pt-4">
                    <div class="grid gap-2">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <Label for="samenvatting-en">
                                <LocaleFlag locale="en" size="sm" />
                                {{ $t('Samenvatting') }}
                            </Label>

                            <Button
                                v-if="kanVertalen"
                                type="button"
                                variant="ghost"
                                size="sm"
                                :disabled="vertaalt"
                                @click="vertaal"
                            >
                                <Loader2
                                    v-if="vertaalt"
                                    class="size-3.5 animate-spin"
                                />
                                <Languages v-else class="size-3.5" />
                                {{ $t('Vertaal') }}
                            </Button>
                        </div>

                        <Textarea
                            id="samenvatting-en"
                            v-model="formulier.summary_en"
                            :maxlength="props.opties.samenvattingMax"
                            rows="4"
                        />
                        <InputError :message="fouten.summary_en" />
                        <InputError :message="vertaalFout ?? undefined" />
                        <p class="text-xs text-muted-foreground">
                            {{ $t(':aantal tekens over', { aantal: overEn }) }}
                        </p>

                        <p class="text-xs text-pretty text-muted-foreground">
                            {{
                                $t(
                                    'Laat je dit leeg, dan staat dit blok niet op je Engelse website. Dat is met opzet: een Nederlandse alinea over jezelf zegt een Engelse bezoeker niets, en dan is weglaten eerlijker.',
                                )
                            }}
                        </p>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button type="button" variant="secondary" @click="open = false">
                    {{ $t('Annuleren') }}
                </Button>
                <Button
                    type="button"
                    variant="bewerken"
                    :disabled="bezig"
                    @click="opslaan"
                >
                    {{ bezig ? $t('Bezig...') : $t('Opslaan') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
