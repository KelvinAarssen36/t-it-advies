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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { bevestigAanmaken, bevestigBewerken } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import website from '@/routes/website';
import punten from '@/routes/website/over-mij/punten';
import type { PuntRij } from '@/types/over-mij';

/**
 * Een punt onder het verhaal aanmaken of bewerken.
 *
 * Het kleinste venster van deze module: één tekstveld, twee keer. Een punt
 * is een regel en geen zin, dus er is niets om een tweede stap voor te
 * maken. Zelfde opzet als het venster van een contactonderwerp.
 *
 * Zie docs/architecture/modules/over-mij.md.
 */
const props = defineProps<{
    item: PuntRij | null;
    kanVertalen: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const bewerkt = computed(() => props.item !== null);

const bezig = ref(false);
const fouten = ref<Record<string, string>>({});

const formulier = ref({ text_nl: '', text_en: '' });

const vertaalt = ref(false);
const vertaalFout = ref<string | null>(null);

/** Of het venster zichzelf opnieuw opent en de inhoud dus moet blijven. */
let behoudInhoud = false;

const openOpnieuw = (): void => {
    behoudInhoud = true;
    open.value = true;
};

const vulIn = (): void => {
    formulier.value = {
        text_nl: props.item?.text_nl ?? '',
        text_en: props.item?.text_en ?? '',
    };

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

        if (typeof velden.punt_en === 'string') {
            formulier.value.text_en = velden.punt_en;
        }

        vertaalFout.value = null;
    });
});

onBeforeUnmount(() => stopLuisteren?.());

const kanVertalen = computed(
    () => props.kanVertalen && formulier.value.text_nl.trim() !== '',
);

const vertaal = (): void => {
    vertaalt.value = true;
    vertaalFout.value = null;

    /*
     * `punt_nl` is het gedeelde veld voor een kort label; het wordt ook
     * gebruikt door de expertisepunten van een dienst. Zie
     * TranslateController.
     */
    router.post(
        website.vertalen().url,
        { punt_nl: formulier.value.text_nl },
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

    const akkoord = bewerkt.value
        ? await bevestigBewerken({ titel: t('Dit punt aanpassen?') })
        : await bevestigAanmaken({ titel: t('Dit punt toevoegen?') });

    if (!akkoord) {
        await adem();
        openOpnieuw();

        return;
    }

    bezig.value = true;

    const opties = {
        preserveScroll: true,
        onError: (ontvangen: Record<string, string>) => {
            fouten.value = ontvangen;
            openOpnieuw();
        },
        onFinish: () => {
            bezig.value = false;
        },
    };

    /*
     * Twee volledige aanroepen en niet `const verzoek = bewerkt ?
     * router.put : router.post`: dat laatste verliest zijn `this` en doet
     * dan stilletjes niets. Zie DienstDialoog.
     */
    if (bewerkt.value) {
        router.put(punten.update(props.item!.id).url, formulier.value, opties);

        return;
    }

    router.post(punten.store().url, formulier.value, opties);
};

const adem = (): Promise<void> =>
    new Promise((klaar) => setTimeout(klaar, 200));
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ bewerkt ? $t('Punt aanpassen') : $t('Nieuw punt') }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Eén korte regel. Dit staat onder je verhaal op je aparte pagina -- bijvoorbeeld "Werkt met wat er al staat" of "Geen vendor lock-in".',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="punt-nl" verplicht>
                        <LocaleFlag locale="nl" size="sm" />
                        {{ $t('Punt') }}
                    </Label>
                    <Input
                        id="punt-nl"
                        v-model="formulier.text_nl"
                        :maxlength="80"
                        :placeholder="
                            $t(
                                'Bijvoorbeeld: Geen afhankelijkheid van één leverancier',
                            )
                        "
                        v-focus
                    />
                    <InputError :message="fouten.text_nl" />
                </div>

                <div class="border-t border-border pt-4">
                    <div class="grid gap-2">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <Label for="punt-en">
                                <LocaleFlag locale="en" size="sm" />
                                {{ $t('Punt') }}
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

                        <Input
                            id="punt-en"
                            v-model="formulier.text_en"
                            :maxlength="80"
                        />
                        <InputError :message="fouten.text_en" />
                        <InputError :message="vertaalFout ?? undefined" />

                        <!--
                            Hier valt het Engels níet terug op het
                            Nederlands, anders dan bij een vraag in de FAQ.
                            Een rijtje met drie Engelse en twee Nederlandse
                            punten is slordiger dan een rijtje van drie.
                        -->
                        <p class="text-xs text-pretty text-muted-foreground">
                            {{
                                $t(
                                    'Laat je dit leeg, dan staat dit punt niet op je Engelse pagina. Een rijtje met half Nederlandse punten is slordiger dan een rijtje dat korter is.',
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
                    :variant="bewerkt ? 'bewerken' : 'aanmaken'"
                    :disabled="bezig || formulier.text_nl.trim() === ''"
                    @click="opslaan"
                >
                    {{ bezig ? $t('Bezig...') : $t('Opslaan') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
