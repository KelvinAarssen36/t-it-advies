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
import { Textarea } from '@/components/ui/textarea';
import { bevestigBewerken, bevestigVerwijderen } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import website from '@/routes/website';
import contact from '@/routes/website/contact';
import type { ContactInstellingen } from '@/types/contact';

/**
 * De tekst van de bevestigingsmail aan de bezoeker.
 *
 * **Deze mail is een visitekaartje.** Voor veel mensen is het de eerste
 * mail die ze van dit bedrijf krijgen, en vaak de enige tot er antwoord
 * komt. Daarom is de tekst van de eigenaar en niet van ons.
 *
 * **De taal volgt de bezoeker en niet het portaal.** Vulde hij het
 * formulier op de Engelse site in, dan krijgt hij de Engelse tekst --
 * zonder dat hij opnieuw een taal hoeft te kiezen. Laat je het Engels
 * leeg, dan krijgt hij het Nederlands; een mail zonder tekst is erger dan
 * een mail in de verkeerde taal.
 *
 * Zie docs/architecture/modules/contact.md.
 */
const props = defineProps<{
    instellingen: ContactInstellingen;
    kanVertalen: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const bezig = ref(false);
const fouten = ref<Record<string, string>>({});

const formulier = ref({
    confirmation_subject_nl: '',
    confirmation_subject_en: '',
    confirmation_body_nl: '',
    confirmation_body_en: '',
});

/** Het Engels zoals het bij het openen van het venster stond. */
let engelsBijOpenen = '';

const automatisch = ref(false);
const vertaalt = ref(false);
const vertaalFout = ref<string | null>(null);

let behoudInhoud = false;

const openOpnieuw = (): void => {
    behoudInhoud = true;
    open.value = true;
};

const engelsNu = (): string =>
    [
        formulier.value.confirmation_subject_en,
        formulier.value.confirmation_body_en,
    ].join('\u0000');

const vulIn = (): void => {
    formulier.value = {
        confirmation_subject_nl:
            props.instellingen.bevestigingOnderwerpNl ?? '',
        confirmation_subject_en:
            props.instellingen.bevestigingOnderwerpEn ?? '',
        confirmation_body_nl: props.instellingen.bevestigingTekstNl ?? '',
        confirmation_body_en: props.instellingen.bevestigingTekstEn ?? '',
    };

    engelsBijOpenen = engelsNu();
    automatisch.value = props.instellingen.automatischVertaald;
    vertaalFout.value = null;
    fouten.value = {};
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

        if (typeof velden.title_en === 'string') {
            formulier.value.confirmation_subject_en = velden.title_en;
        }

        if (typeof velden.body_en === 'string') {
            formulier.value.confirmation_body_en = velden.body_en;
        }

        automatisch.value = true;
        vertaalFout.value = null;
    });
});

onBeforeUnmount(() => stopLuisteren?.());

const kanVertalen = computed(
    () =>
        props.kanVertalen && formulier.value.confirmation_body_nl.trim() !== '',
);

const vertaal = async (): Promise<void> => {
    if (!automatisch.value && engelsNu().trim() !== '') {
        open.value = false;
        await adem();

        const akkoord = await bevestigVerwijderen({
            titel: t('Het Engels dat er staat overschrijven?'),
            tekst: t(
                'De vertaaldienst zet er zijn eigen tekst voor in de plaats. Wat je zelf hebt geschreven is dan weg.',
            ),
            knop: t('Ja, opnieuw vertalen'),
        });

        await adem();
        openOpnieuw();

        if (!akkoord) {
            return;
        }
    }

    vertaalt.value = true;
    vertaalFout.value = null;

    router.post(
        website.vertalen().url,
        {
            title_nl: formulier.value.confirmation_subject_nl,
            body_nl: formulier.value.confirmation_body_nl,
        },
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
        titel: t('De bevestigingsmail aanpassen?'),
        tekst: t(
            'Iedereen die hierna het formulier invult krijgt deze tekst te lezen.',
        ),
    });

    if (!akkoord) {
        await adem();
        openOpnieuw();

        return;
    }

    bezig.value = true;

    router.put(
        contact.instellingen().url,
        {
            ...formulier.value,
            automatisch_vertaald:
                automatisch.value && engelsNu() === engelsBijOpenen,
        },
        {
            preserveScroll: true,
            onError: (ontvangen: Record<string, string>) => {
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
                <DialogTitle>{{ $t('De bevestigingsmail') }}</DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Dit is wat iemand terugkrijgt zodra hij het formulier heeft verstuurd. Vaak is het de eerste mail die hij van je krijgt, dus hij mag goed klinken.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="mail-onderwerp-nl" verplicht>
                        <LocaleFlag locale="nl" size="sm" />
                        {{ $t('Onderwerp van de mail') }}
                    </Label>
                    <Input
                        id="mail-onderwerp-nl"
                        v-model="formulier.confirmation_subject_nl"
                        :maxlength="160"
                    />
                    <InputError :message="fouten.confirmation_subject_nl" />
                </div>

                <div class="grid gap-2">
                    <Label for="mail-tekst-nl" verplicht>
                        <LocaleFlag locale="nl" size="sm" />
                        {{ $t('Tekst van de mail') }}
                    </Label>
                    <Textarea
                        id="mail-tekst-nl"
                        v-model="formulier.confirmation_body_nl"
                        :maxlength="2000"
                        rows="6"
                    />
                    <InputError :message="fouten.confirmation_body_nl" />
                    <p class="text-xs text-pretty text-muted-foreground">
                        {{
                            $t(
                                'Witregels die je hier maakt blijven in de mail staan. De aanhef met de naam en je afzender staan er automatisch om.',
                            )
                        }}
                    </p>
                </div>

                <div class="border-t border-border pt-4">
                    <div class="grid gap-4">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <p
                                class="flex items-center gap-2 text-sm font-medium"
                            >
                                <LocaleFlag locale="en" size="sm" />
                                {{ $t('De Engelse versie') }}
                            </p>

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

                        <div class="grid gap-2">
                            <Label for="mail-onderwerp-en">
                                {{ $t('Onderwerp van de mail') }}
                            </Label>
                            <Input
                                id="mail-onderwerp-en"
                                v-model="formulier.confirmation_subject_en"
                                :maxlength="160"
                            />
                            <InputError
                                :message="fouten.confirmation_subject_en"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="mail-tekst-en">
                                {{ $t('Tekst van de mail') }}
                            </Label>
                            <Textarea
                                id="mail-tekst-en"
                                v-model="formulier.confirmation_body_en"
                                :maxlength="2000"
                                rows="6"
                            />
                            <InputError
                                :message="fouten.confirmation_body_en"
                            />
                            <InputError :message="vertaalFout ?? undefined" />
                        </div>

                        <p class="text-xs text-pretty text-muted-foreground">
                            {{
                                $t(
                                    'Vulde iemand het formulier op de Engelse versie van je website in, dan krijgt hij deze tekst. Laat je hem leeg, dan krijgt hij het Nederlands.',
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
                    :disabled="
                        bezig ||
                        formulier.confirmation_subject_nl.trim() === '' ||
                        formulier.confirmation_body_nl.trim() === ''
                    "
                    @click="opslaan"
                >
                    {{ bezig ? $t('Bezig...') : $t('Opslaan') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
