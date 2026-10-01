<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Languages, Loader2 } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import BrandSelect from '@/components/BrandSelect.vue';
import InputError from '@/components/InputError.vue';
import LocaleFlag from '@/components/LocaleFlag.vue';
import StatistiekBalk from '@/components/site/StatistiekBalk.vue';
import StatistiekRing from '@/components/site/StatistiekRing.vue';
import StatistiekTeller from '@/components/site/StatistiekTeller.vue';
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
import statistieken from '@/routes/website/statistieken';
import type {
    StatistiekRij,
    StatistiekenOpties,
    Weergave,
} from '@/types/statistieken';

/**
 * Een statistiek aanmaken of bewerken.
 *
 * **Eén scherm en geen twee stappen**, anders dan bij een dienst of een
 * certificaat. Daar zijn vijf tot acht velden te vertalen en is een
 * tweede stap rust; hier zijn het er drie, en dan kost een stap meer
 * klikken dan hij scheelt. Het Engels staat onder een streep, precies
 * zoals bij de kop van een blok.
 *
 * **Met een voorbeeld ernaast.** Kies je "ring" en vul je 85 in, dan
 * staat er een ring van 85 procent naast het formulier -- met dezelfde
 * componenten als op de website, dus het is geen nabootsing maar het
 * echte ding. Dezelfde keuze als op het scherm van de kop: een lijst
 * met veldnamen zegt wat er in de database staat, een voorbeeld zegt
 * wat de bezoeker ziet.
 *
 * Dat voorbeeld is ook waarom er geen schuifbalk voor de waarde is: een
 * getalveld met een voorbeeld ernaast is nauwkeuriger dan slepen.
 *
 * Zie docs/architecture/modules/statistieken.md.
 */
const props = defineProps<{
    item: StatistiekRij | null;
    opties: StatistiekenOpties;
    kanVertalen: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const bewerkt = computed(() => props.item !== null);

const bezig = ref(false);
const fouten = ref<Record<string, string>>({});

const formulier = ref({
    display: 'balk' as Weergave,
    published: true,
    label_nl: '',
    label_en: '',
    value: 0,
    prefix: '',
    suffix: '',
    note_nl: '',
    note_en: '',
    group_nl: '',
    group_en: '',
});

/** Of de klant hem meteen online wil; alleen bij een nieuwe. */
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

const engelsNu = (): string =>
    [
        formulier.value.label_en,
        formulier.value.note_en,
        formulier.value.group_en,
    ].join('\u0000');

const vulIn = (): void => {
    const item = props.item;

    formulier.value = {
        display: item?.display ?? 'balk',
        published: item?.published ?? true,
        label_nl: item?.label_nl ?? '',
        label_en: item?.label_en ?? '',
        value: item?.value ?? 0,
        prefix: item?.prefix ?? '',
        suffix: item?.suffix ?? '',
        note_nl: item?.note_nl ?? '',
        note_en: item?.note_en ?? '',
        group_nl: item?.group_nl ?? '',
        group_en: item?.group_en ?? '',
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

/* --- De weergave en de grens eromheen ---------------------------------- */

const gekozenWeergave = computed(() =>
    props.opties.weergave.find(
        (optie) => optie.value === formulier.value.display,
    ),
);

/** 100 bij een balk of ring, zeven cijfers bij een teller. */
const maximum = computed(() => gekozenWeergave.value?.maximum ?? 100);

const isPercentage = computed(() => formulier.value.display !== 'teller');

/**
 * Een waarde boven de grens knippen zodra de weergave verandert.
 *
 * Zet je een teller van 5000 om naar een balk, dan is 5000 ineens geen
 * geldige waarde meer. Het stil laten staan en de server erover laten
 * klagen kan ook, maar dan is het formulier al fout terwijl je er nog
 * in zit -- en dat leest als een fout van jou in plaats van als een
 * gevolg van je keuze.
 */
watch(
    () => formulier.value.display,
    () => {
        if (formulier.value.value > maximum.value) {
            formulier.value.value = maximum.value;
        }
    },
);

/** De statistiek zoals de site hem zou krijgen, voor het voorbeeld. */
const voorbeeld = computed(() => ({
    id: props.item?.id ?? 0,
    naam: formulier.value.label_nl.trim() || t('Je statistiek'),
    waarde: Number(formulier.value.value) || 0,
    voor: formulier.value.prefix.trim() || null,
    na: formulier.value.suffix.trim() || null,
    notitie: formulier.value.note_nl.trim() || null,
}));

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

        if (typeof velden.label_en === 'string') {
            formulier.value.label_en = velden.label_en;
        }

        if (typeof velden.notitie_en === 'string') {
            formulier.value.note_en = velden.notitie_en;
        }

        if (typeof velden.groep_en === 'string') {
            formulier.value.group_en = velden.groep_en;
        }

        automatisch.value = true;
        vertaalFout.value = null;
    });
});

onBeforeUnmount(() => stopLuisteren?.());

const kanVertalen = computed(
    () => props.kanVertalen && formulier.value.label_nl.trim() !== '',
);

const eigenEngels = (): boolean =>
    !automatisch.value &&
    [
        formulier.value.label_en,
        formulier.value.note_en,
        formulier.value.group_en,
    ].some((waarde) => waarde.trim() !== '');

const vertaal = async (): Promise<void> => {
    if (eigenEngels()) {
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
            label_nl: formulier.value.label_nl,
            notitie_nl: formulier.value.note_nl,
            groep_nl: formulier.value.group_nl,
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
            titel: t('Deze statistiek aanpassen?'),
        });
    } else {
        meteenOnline.value = true;

        akkoord = await bevestigAanmaken({
            titel: t('Deze statistiek toevoegen?'),
            keuze: {
                model: meteenOnline,
                label: t('Meteen op je website zetten'),
                tekst: t(
                    'Zet je hem uit, dan staat hij hier klaar en zie je hem pas op je site als je hem online zet.',
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
     * router.put : router.post`: dat laatste verliest zijn `this` en
     * doet dan stilletjes niets. Zie DienstDialoog.
     */
    if (bewerkt.value) {
        router.put(statistieken.update(props.item!.id).url, lading, opties);

        return;
    }

    router.post(statistieken.store().url, lading, opties);
};

const adem = (): Promise<void> =>
    new Promise((klaar) => setTimeout(klaar, 200));
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="max-h-[85vh] brand-scrollbar overflow-y-auto sm:max-w-3xl"
        >
            <DialogHeader>
                <DialogTitle>
                    {{
                        bewerkt
                            ? $t('Statistiek aanpassen')
                            : $t('Nieuwe statistiek')
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Kies een vorm, vul een getal in en geef het een naam. Rechts zie je meteen wat je bezoeker krijgt.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-6 sm:grid-cols-[1fr_15rem]">
                <div class="brand-formulier-lijst brand-scrollbar">
                    <div class="grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="statistiek-weergave" verplicht>
                                {{ $t('Weergave') }}
                            </Label>
                            <BrandSelect
                                id="statistiek-weergave"
                                v-model="formulier.display"
                                :options="props.opties.weergave"
                            />
                            <InputError :message="fouten.display" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="statistiek-waarde" verplicht>
                                {{
                                    isPercentage
                                        ? $t('Percentage')
                                        : $t('Getal')
                                }}
                            </Label>
                            <Input
                                id="statistiek-waarde"
                                v-model.number="formulier.value"
                                type="number"
                                min="0"
                                :max="maximum"
                            />
                            <InputError :message="fouten.value" />
                        </div>
                    </div>

                    <p
                        v-if="gekozenWeergave"
                        class="-mt-1 text-xs text-pretty text-muted-foreground"
                    >
                        {{ gekozenWeergave.omschrijving }}
                    </p>

                    <div class="grid gap-2">
                        <Label for="statistiek-label-nl" verplicht>
                            {{ $t('Naam') }}
                        </Label>
                        <Input
                            id="statistiek-label-nl"
                            v-model="formulier.label_nl"
                            maxlength="60"
                            placeholder="Microsoft 365"
                        />
                        <InputError :message="fouten.label_nl" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="statistiek-note-nl">
                            {{ $t('Regeltje eronder') }}
                        </Label>
                        <Input
                            id="statistiek-note-nl"
                            v-model="formulier.note_nl"
                            maxlength="120"
                        />
                        <InputError :message="fouten.note_nl" />
                    </div>

                    <div class="grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="statistiek-groep">
                                {{ $t('Groep') }}
                            </Label>
                            <!--
                                Een `list` met de groepen die er al zijn.
                                Zonder die suggesties belanden "netwerk"
                                en "Netwerk" naast elkaar als twee
                                groepen, en dan valt het blok uit elkaar
                                zonder dat iemand ziet waarom.
                            -->
                            <Input
                                id="statistiek-groep"
                                v-model="formulier.group_nl"
                                maxlength="60"
                                list="statistiek-groepen"
                                placeholder="Netwerk"
                            />
                            <datalist id="statistiek-groepen">
                                <option
                                    v-for="groep in props.opties.groepen"
                                    :key="groep"
                                    :value="groep"
                                />
                            </datalist>
                            <InputError :message="fouten.group_nl" />
                        </div>

                        <div class="grid grid-cols-2 items-start gap-3">
                            <div class="grid gap-2">
                                <Label for="statistiek-prefix">
                                    {{ $t('Ervoor') }}
                                </Label>
                                <Input
                                    id="statistiek-prefix"
                                    v-model="formulier.prefix"
                                    maxlength="8"
                                    placeholder="€"
                                />
                                <InputError :message="fouten.prefix" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="statistiek-suffix">
                                    {{ $t('Erachter') }}
                                </Label>
                                <Input
                                    id="statistiek-suffix"
                                    v-model="formulier.suffix"
                                    maxlength="8"
                                    :placeholder="isPercentage ? '%' : '+'"
                                />
                                <InputError :message="fouten.suffix" />
                            </div>
                        </div>
                    </div>

                    <p class="-mt-1 text-xs text-pretty text-muted-foreground">
                        {{
                            $t(
                                'De groep zet dit cijfer bij andere onder hetzelfde kopje; laat leeg en hij staat los bovenaan. Bij een balk of ring staat er vanzelf een procentteken achter.',
                            )
                        }}
                    </p>

                    <!-- ------------------- Het Engels ----------------- -->
                    <div class="grid gap-2 border-t border-border pt-4">
                        <Label for="statistiek-label-en">
                            <LocaleFlag locale="en" size="sm" />
                            {{ $t('Naam') }}
                        </Label>
                        <Input
                            id="statistiek-label-en"
                            v-model="formulier.label_en"
                            maxlength="60"
                        />
                        <InputError :message="fouten.label_en" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="statistiek-note-en">
                            <LocaleFlag locale="en" size="sm" />
                            {{ $t('Regeltje eronder') }}
                        </Label>
                        <Input
                            id="statistiek-note-en"
                            v-model="formulier.note_en"
                            maxlength="120"
                        />
                        <InputError :message="fouten.note_en" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="statistiek-groep-en">
                            <LocaleFlag locale="en" size="sm" />
                            {{ $t('Groep') }}
                        </Label>
                        <Input
                            id="statistiek-groep-en"
                            v-model="formulier.group_en"
                            maxlength="60"
                        />
                        <p class="text-xs text-pretty text-muted-foreground">
                            {{
                                $t(
                                    'Alleen het kopje. De indeling zelf gaat altijd op de Nederlandse groep, zodat je site in beide talen hetzelfde is opgebouwd.',
                                )
                            }}
                        </p>
                        <InputError :message="fouten.group_en" />
                    </div>

                    <div v-if="kanVertalen" class="grid gap-2">
                        <div>
                            <Button
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
                                {{
                                    vertaalt
                                        ? $t('Bezig met vertalen…')
                                        : $t('Vertaal automatisch')
                                }}
                            </Button>
                        </div>

                        <p
                            v-if="automatisch && !vertaalt"
                            class="text-sm text-pretty text-muted-foreground"
                        >
                            {{
                                $t(
                                    'Automatisch vertaald. Loop het even na en pas aan wat je anders wilt.',
                                )
                            }}
                        </p>

                        <p
                            v-if="vertaalFout"
                            class="text-sm text-pretty text-destructive"
                        >
                            {{ vertaalFout }}
                        </p>
                    </div>
                </div>

                <!-- ---------------------- Het voorbeeld ---------------- -->
                <div class="brand-statistiek-voorbeeld">
                    <p class="brand-blad-kopje">
                        {{ $t('Zo ziet het eruit') }}
                    </p>

                    <!--
                        De echte componenten van de website, en geen
                        nabootsing. Ze staan hier zonder de animatie:
                        `--vulling` blijft op 1, want er valt in een
                        formulier niets te scrollen.
                    -->
                    <div class="brand-statistieken mt-3">
                        <StatistiekRing
                            v-if="formulier.display === 'ring'"
                            :item="voorbeeld"
                        />
                        <StatistiekTeller
                            v-else-if="formulier.display === 'teller'"
                            :item="voorbeeld"
                        />
                        <StatistiekBalk v-else :item="voorbeeld" />
                    </div>
                </div>
            </div>

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
                        :variant="bewerkt ? 'bewerken' : 'aanmaken'"
                        class="gap-2"
                        :disabled="bezig"
                        @click="opslaan"
                    >
                        <Loader2 v-if="bezig" class="size-4 animate-spin" />
                        {{ bewerkt ? $t('Opslaan') : $t('Toevoegen') }}
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
