<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Languages, Loader2 } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import BrandSelect from '@/components/BrandSelect.vue';
import InputError from '@/components/InputError.vue';
import LocaleFlag from '@/components/LocaleFlag.vue';
import KerngegevenIcoon from '@/components/site/KerngegevenIcoon.vue';
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
import {
    bevestigAanmaken,
    bevestigBewerken,
    bevestigVerwijderen,
} from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import website from '@/routes/website';
import kerngegevens from '@/routes/website/kerngegevens';
import type { KerngegevenOpties, KerngegevenRij } from '@/types/kerngegevens';

/**
 * Een kerngegeven aanmaken of bewerken.
 *
 * Een soort en drie velden, twee keer. Het Engels staat onder een streep,
 * precies zoals bij een vraag en bij de kop van een blok.
 *
 * **Alle drie de velden zijn een `Input` en geen `Textarea`**, en dat is
 * geen opmaakkeuze maar de regel zelf: een kerngegeven dat niet op één
 * regel past is geen kerngegeven meer. Het veld dwingt dat af in plaats
 * van het achteraf af te keuren.
 *
 * **Het soort stelt het label voor.** Kies je "Reactietijd" terwijl het
 * label nog leeg is, dan staat het er meteen. Heb je er al iets getypt,
 * dan blijft dat staan -- een keuzelijst die je tekst overschrijft is een
 * keuzelijst waar je bang voor wordt.
 *
 * Zie docs/architecture/modules/kerngegevens.md.
 */
const props = defineProps<{
    item: KerngegevenRij | null;
    opties: KerngegevenOpties;
    kanVertalen: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const bewerkt = computed(() => props.item !== null);

const bezig = ref(false);
const fouten = ref<Record<string, string>>({});

const formulier = ref({
    icon: 'beschikbaarheid',
    label_nl: '',
    label_en: '',
    value_nl: '',
    value_en: '',
    note_nl: '',
    note_en: '',
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

/** De Engelse velden als één tekst, om te zien of er iets wijzigde. */
const engelsNu = (): string =>
    [
        formulier.value.label_en,
        formulier.value.value_en,
        formulier.value.note_en,
    ].join('\u0000');

const vulIn = (): void => {
    const item = props.item;

    formulier.value = {
        icon: item?.icon ?? 'beschikbaarheid',
        label_nl: item?.label_nl ?? '',
        label_en: item?.label_en ?? '',
        value_nl: item?.value_nl ?? '',
        value_en: item?.value_en ?? '',
        note_nl: item?.note_nl ?? '',
        note_en: item?.note_en ?? '',
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

/**
 * Het soort stelt het label voor, maar overschrijft nooit.
 *
 * Alleen als het label nog leeg is óf nog precies het voorstel van het
 * vorige soort bevat. Zo kun je rustig door de lijst klikken zonder je
 * eigen tekst kwijt te raken.
 */
watch(
    () => formulier.value.icon,
    (nieuw, oud) => {
        const voorstelOud = props.opties.soorten.find(
            (soort) => soort.value === oud,
        )?.label;

        const huidig = formulier.value.label_nl.trim();

        if (huidig !== '' && huidig !== voorstelOud) {
            return;
        }

        formulier.value.label_nl =
            props.opties.soorten.find((soort) => soort.value === nieuw)
                ?.label ?? '';
    },
);

/* --- De tellers --------------------------------------------------------- */

const overNl = computed(
    () => props.opties.waardeMax - formulier.value.value_nl.length,
);

const overEn = computed(
    () => props.opties.waardeMax - formulier.value.value_en.length,
);

/* --- Automatisch vertalen ---------------------------------------------- */

let stopLuisteren: (() => void) | undefined;

/** Welke Engelse velden de vertaaldienst mag vullen. */
const VERTAALD = {
    label_en: 'label_en',
    waarde_en: 'value_en',
    notitie_en: 'note_en',
} as const;

onMounted(() => {
    stopLuisteren = router.on('flash', (gebeurtenis) => {
        const velden = (gebeurtenis as CustomEvent).detail?.flash?.vertaling as
            | Record<string, unknown>
            | undefined;

        if (!velden || !open.value) {
            return;
        }

        Object.entries(VERTAALD).forEach(([vanDeServer, inHetFormulier]) => {
            const waarde = velden[vanDeServer];

            if (typeof waarde === 'string') {
                formulier.value[inHetFormulier] = waarde;
            }
        });

        automatisch.value = true;
        vertaalFout.value = null;
    });
});

onBeforeUnmount(() => stopLuisteren?.());

const kanVertalen = computed(
    () =>
        props.kanVertalen &&
        formulier.value.label_nl.trim() !== '' &&
        formulier.value.value_nl.trim() !== '',
);

const vertaal = async (): Promise<void> => {
    const erStaatAlEngels = Object.values(VERTAALD).some(
        (veld) => formulier.value[veld].trim() !== '',
    );

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

    /*
     * De lege velden gaan gewoon mee: de vertaaldienst slaat ze over.
     * Zou dit scherm ze eruit filteren, dan moet het weten wat de server
     * met een lege waarde doet -- en dat is precies het soort kennis dat
     * op twee plekken uiteen gaat lopen.
     */
    router.post(
        website.vertalen().url,
        {
            label_nl: formulier.value.label_nl,
            waarde_nl: formulier.value.value_nl,
            notitie_nl: formulier.value.note_nl,
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
            titel: t('Dit kerngegeven aanpassen?'),
        });
    } else {
        meteenOnline.value = true;

        akkoord = await bevestigAanmaken({
            titel: t('Dit kerngegeven toevoegen?'),
            tekst: t(
                'Het komt achteraan in de strook; sleep het daarna op zijn plek.',
            ),
            keuze: {
                model: meteenOnline,
                label: t('Meteen op je website zetten'),
                tekst: t(
                    'Zet je het uit, dan staat het hier klaar en zien bezoekers het pas als je het online zet.',
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
        router.put(kerngegevens.update(props.item!.id).url, lading, opties);

        return;
    }

    router.post(kerngegevens.store().url, lading, opties);
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
                    {{
                        bewerkt
                            ? $t('Kerngegeven aanpassen')
                            : $t('Nieuw kerngegeven')
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Een feit in één regel. Het label zegt waar iemand naar kijkt, de waarde is het antwoord.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-5" @submit.prevent="opslaan">
                <div class="grid gap-2">
                    <Label for="kerngegeven-soort" verplicht>
                        {{ $t('Soort') }}
                    </Label>

                    <div class="flex items-center gap-3">
                        <span class="brand-logo-rondje is-klein shrink-0">
                            <KerngegevenIcoon
                                :icoon="formulier.icon"
                                class="size-3.5"
                            />
                        </span>

                        <BrandSelect
                            id="kerngegeven-soort"
                            v-model="formulier.icon"
                            :options="props.opties.soorten"
                            class="min-w-0 flex-1"
                        />
                    </div>

                    <InputError :message="fouten.icon" />
                </div>

                <div class="grid gap-2">
                    <Label for="kerngegeven-label" verplicht>
                        {{ $t('Label') }}
                    </Label>
                    <Input
                        id="kerngegeven-label"
                        v-model="formulier.label_nl"
                        maxlength="60"
                        :placeholder="$t('Beschikbaarheid')"
                    />
                    <InputError :message="fouten.label_nl" />
                </div>

                <div class="grid gap-2">
                    <Label for="kerngegeven-waarde" verplicht>
                        {{ $t('Waarde') }}
                    </Label>
                    <Input
                        id="kerngegeven-waarde"
                        v-model="formulier.value_nl"
                        :maxlength="props.opties.waardeMax"
                        :placeholder="$t('Vanaf januari')"
                    />
                    <p class="text-xs text-muted-foreground">
                        {{ $t('Nog :aantal tekens', { aantal: overNl }) }}
                    </p>
                    <InputError :message="fouten.value_nl" />
                </div>

                <div class="grid gap-2">
                    <Label for="kerngegeven-notitie">
                        {{ $t('Toelichting') }}
                    </Label>
                    <Input
                        id="kerngegeven-notitie"
                        v-model="formulier.note_nl"
                        :maxlength="props.opties.notitieMax"
                        :placeholder="$t('Twee tot drie dagen per week.')"
                    />
                    <p class="text-xs text-pretty text-muted-foreground">
                        {{
                            $t(
                                'Eén regel onder de waarde. Mag leeg blijven; de strook oogt zonder net zo goed.',
                            )
                        }}
                    </p>
                    <InputError :message="fouten.note_nl" />
                </div>

                <!-- Het Engels, onder een streep. -->
                <div class="grid gap-5 border-t border-border pt-5">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <span
                            class="flex items-center gap-2 text-sm font-medium"
                        >
                            <LocaleFlag locale="en" class="size-4" />
                            {{ $t('Engels') }}
                        </span>

                        <Button
                            v-if="kanVertalen"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="gap-2"
                            :disabled="vertaalt"
                            @click="vertaal"
                        >
                            <Loader2
                                v-if="vertaalt"
                                class="size-4 animate-spin"
                            />
                            <Languages v-else class="size-4" />
                            {{ vertaalt ? $t('Bezig...') : $t('Vertaal') }}
                        </Button>
                    </div>

                    <p v-if="vertaalFout" class="text-sm text-destructive">
                        {{ vertaalFout }}
                    </p>

                    <div class="grid gap-2">
                        <Label for="kerngegeven-label-en">
                            {{ $t('Label') }}
                        </Label>
                        <Input
                            id="kerngegeven-label-en"
                            v-model="formulier.label_en"
                            maxlength="60"
                        />
                        <InputError :message="fouten.label_en" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="kerngegeven-waarde-en">
                            {{ $t('Waarde') }}
                        </Label>
                        <Input
                            id="kerngegeven-waarde-en"
                            v-model="formulier.value_en"
                            :maxlength="props.opties.waardeMax"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{ $t('Nog :aantal tekens', { aantal: overEn }) }}
                        </p>
                        <InputError :message="fouten.value_en" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="kerngegeven-notitie-en">
                            {{ $t('Toelichting') }}
                        </Label>
                        <Input
                            id="kerngegeven-notitie-en"
                            v-model="formulier.note_en"
                            :maxlength="props.opties.notitieMax"
                        />
                        <InputError :message="fouten.note_en" />
                    </div>

                    <!--
                        Laat je het Engels leeg, dan valt de site terug op
                        het Nederlands -- behalve bij de toelichting. Die
                        wordt dan weggelaten: één Nederlandse zin tussen
                        Engelse tekst valt meer op dan een ontbrekende
                        toelichting.
                    -->
                    <p class="text-xs text-pretty text-muted-foreground">
                        {{
                            $t(
                                'Laat je het label of de waarde leeg, dan staat het Nederlands er ook op de Engelse site -- handig bij een nummer. Een lege toelichting wordt weggelaten.',
                            )
                        }}
                    </p>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="secondary"
                        @click="open = false"
                    >
                        {{ $t('Annuleren') }}
                    </Button>
                    <Button
                        type="submit"
                        :variant="bewerkt ? 'bewerken' : 'aanmaken'"
                        :disabled="bezig"
                    >
                        {{
                            bezig
                                ? $t('Bezig...')
                                : bewerkt
                                  ? $t('Opslaan')
                                  : $t('Toevoegen')
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
