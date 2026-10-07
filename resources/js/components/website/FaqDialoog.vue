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
import {
    bevestigAanmaken,
    bevestigBewerken,
    bevestigVerwijderen,
} from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import website from '@/routes/website';
import faq from '@/routes/website/faq';
import type { FaqOpties, VraagRij } from '@/types/faq';

/**
 * Een vraag aanmaken of bewerken.
 *
 * Vier velden: de vraag en het antwoord, twee keer. Het Engels staat onder
 * een streep, precies zoals bij de kop van een blok en bij een
 * contactonderwerp.
 *
 * **De vraag is een `Input` en het antwoord een `Textarea`**, en dat is
 * geen opmaakkeuze maar de regel zelf: een vraag die niet op één regel
 * past is geen vraag meer. Het veld dwingt dat af in plaats van het
 * achteraf af te keuren.
 *
 * **De teller onder het antwoord rekent met de grens van de server.** Die
 * komt mee in `opties.antwoordMax` en staat hier niet apart; anders krijgt
 * de eigenaar een foutmelding op iets wat dit venster net nog goedkeurde.
 *
 * Zie docs/architecture/modules/faq.md.
 */
const props = defineProps<{
    item: VraagRij | null;
    opties: FaqOpties;
    kanVertalen: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const bewerkt = computed(() => props.item !== null);

const bezig = ref(false);
const fouten = ref<Record<string, string>>({});

const formulier = ref({
    question_nl: '',
    question_en: '',
    answer_nl: '',
    answer_en: '',
    published: true,
});

/** Of de eigenaar hem meteen online wil; alleen bij een nieuwe. */
const meteenOnline = ref(true);

/** Het Engels zoals het bij het openen van het venster stond. */
let engelsBijOpenen = '';

const automatisch = ref(false);
const vertaalt = ref(false);
const vertaalFout = ref<string | null>(null);

/** Of het venster zichzelf opnieuw opent en de inhoud dus moet blijven. */
let behoudInhoud = false;

const openOpnieuw = (): void => {
    behoudInhoud = true;
    open.value = true;
};

/** De twee Engelse velden als één tekst, om te zien of er iets wijzigde. */
const engelsNu = (): string =>
    `${formulier.value.question_en}\u0000${formulier.value.answer_en}`;

const vulIn = (): void => {
    const item = props.item;

    formulier.value = {
        question_nl: item?.question_nl ?? '',
        question_en: item?.question_en ?? '',
        answer_nl: item?.answer_nl ?? '',
        answer_en: item?.answer_en ?? '',
        published: item?.published ?? true,
    };

    engelsBijOpenen = engelsNu();
    automatisch.value = item?.automatisch_vertaald ?? false;
    meteenOnline.value = true;
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

/* --- De teller onder het antwoord -------------------------------------- */

const overNl = computed(
    () => props.opties.antwoordMax - formulier.value.answer_nl.length,
);

const overEn = computed(
    () => props.opties.antwoordMax - formulier.value.answer_en.length,
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

        if (typeof velden.question_en === 'string') {
            formulier.value.question_en = velden.question_en;
        }

        if (typeof velden.answer_en === 'string') {
            formulier.value.answer_en = velden.answer_en;
        }

        automatisch.value = true;
        vertaalFout.value = null;
    });
});

onBeforeUnmount(() => stopLuisteren?.());

const kanVertalen = computed(
    () =>
        props.kanVertalen &&
        formulier.value.question_nl.trim() !== '' &&
        formulier.value.answer_nl.trim() !== '',
);

const vertaal = async (): Promise<void> => {
    const erStaatAlEngels =
        formulier.value.question_en.trim() !== '' ||
        formulier.value.answer_en.trim() !== '';

    if (!automatisch.value && erStaatAlEngels) {
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
            question_nl: formulier.value.question_nl,
            answer_nl: formulier.value.answer_nl,
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

    let akkoord: boolean;

    if (bewerkt.value) {
        akkoord = await bevestigBewerken({
            titel: t('Deze vraag aanpassen?'),
        });
    } else {
        meteenOnline.value = true;

        akkoord = await bevestigAanmaken({
            titel: t('Deze vraag toevoegen?'),
            keuze: {
                model: meteenOnline,
                label: t('Meteen op je website zetten'),
                tekst: t(
                    'Zet je hem uit, dan staat hij hier klaar en zien bezoekers hem pas als je hem online zet.',
                ),
            },
        });

        formulier.value.published = meteenOnline.value;
    }

    if (!akkoord) {
        await adem();
        openOpnieuw();

        return;
    }

    bezig.value = true;

    const lading = {
        ...formulier.value,
        machine_translated: automatisch.value && engelsNu() === engelsBijOpenen,
    };

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
        router.put(faq.update(props.item!.id).url, lading, opties);

        return;
    }

    router.post(faq.store().url, lading, opties);
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
                <DialogTitle>
                    {{ bewerkt ? $t('Vraag aanpassen') : $t('Nieuwe vraag') }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Schrijf de vraag zoals een bezoeker hem zou stellen, niet zoals jij hem zou samenvatten. "Wat kost een migratie?" werkt beter dan "Tarieven".',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="vraag-nl" verplicht>
                        <LocaleFlag locale="nl" size="sm" />
                        {{ $t('Vraag') }}
                    </Label>
                    <Input
                        id="vraag-nl"
                        v-model="formulier.question_nl"
                        :maxlength="160"
                        :placeholder="
                            $t('Bijvoorbeeld: Wat kost een migratie?')
                        "
                        v-focus
                    />
                    <InputError :message="fouten.question_nl" />
                </div>

                <div class="grid gap-2">
                    <Label for="antwoord-nl" verplicht>
                        <LocaleFlag locale="nl" size="sm" />
                        {{ $t('Antwoord') }}
                    </Label>
                    <Textarea
                        id="antwoord-nl"
                        v-model="formulier.answer_nl"
                        :maxlength="props.opties.antwoordMax"
                        rows="5"
                        :placeholder="
                            $t(
                                'Een witregel tussen twee stukken maakt er op je website twee alinea\'s van.',
                            )
                        "
                    />
                    <InputError :message="fouten.answer_nl" />
                    <p class="text-xs text-muted-foreground">
                        {{ $t(':aantal tekens over', { aantal: overNl }) }}
                    </p>
                </div>

                <div class="border-t border-border pt-4">
                    <div class="grid gap-4">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <Label>
                                <LocaleFlag locale="en" size="sm" />
                                {{ $t('In het Engels') }}
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

                        <div class="grid gap-2">
                            <Label for="vraag-en" class="text-xs">
                                {{ $t('Vraag') }}
                            </Label>
                            <Input
                                id="vraag-en"
                                v-model="formulier.question_en"
                                :maxlength="160"
                            />
                            <InputError :message="fouten.question_en" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="antwoord-en" class="text-xs">
                                {{ $t('Antwoord') }}
                            </Label>
                            <Textarea
                                id="antwoord-en"
                                v-model="formulier.answer_en"
                                :maxlength="props.opties.antwoordMax"
                                rows="5"
                            />
                            <InputError :message="fouten.answer_en" />
                            <p class="text-xs text-muted-foreground">
                                {{
                                    $t(':aantal tekens over', {
                                        aantal: overEn,
                                    })
                                }}
                            </p>
                        </div>

                        <InputError :message="vertaalFout ?? undefined" />

                        <!--
                            **Hier staat met opzet "allebei of geen van
                            beide".** Bij een expertisepunt onder een
                            dienst wordt een onvertaald label weggelaten,
                            maar dat is één label in een rijtje van acht.
                            Hier is het de helft van het enige dat er
                            staat: een Engelse vraag die opengaat en een
                            Nederlands antwoord laat zien is erger dan een
                            vraag die helemaal in het Nederlands staat.
                            Vandaar dat allebei de velden terugvallen.
                        -->
                        <p class="text-xs text-pretty text-muted-foreground">
                            {{
                                $t(
                                    'Laat je dit leeg, dan ziet een Engelse bezoeker de Nederlandse vraag mét het Nederlandse antwoord. Dat is met opzet: een Engelse vraag met een Nederlands antwoord eronder leest slechter dan een vraag die helemaal in het Nederlands staat.',
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
                    :disabled="
                        bezig ||
                        formulier.question_nl.trim() === '' ||
                        formulier.answer_nl.trim() === ''
                    "
                    @click="opslaan"
                >
                    {{ bezig ? $t('Bezig...') : $t('Opslaan') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
