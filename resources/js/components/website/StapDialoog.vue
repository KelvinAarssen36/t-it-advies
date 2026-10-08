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
import werkwijze from '@/routes/website/werkwijze';
import type { StapOpties, StapRij } from '@/types/werkwijze';

/**
 * Een stap van de werkwijze aanmaken of bewerken.
 *
 * Vijf velden per taal, het Engels onder een streep -- hetzelfde patroon
 * als bij een vraag en bij de kop van een blok.
 *
 * **Er is geen veld voor het nummer.** Dat volgt uit de volgorde; zie het
 * beheerscherm en `App\Models\WorkStep`.
 *
 * **Alleen de titel en de korte tekst zijn verplicht.** De duur, het
 * resultaat en het uitgebreide verhaal mogen leeg blijven: dan staat er
 * precies dezelfde kaart als voorheen. Zo verandert deze module niets aan
 * de website van de eigenaar zolang hij niets invult.
 *
 * **De duur is een `Input` en geen getal met een eenheid erachter.** "Een
 * middag", "1-2 weken", "doorlopend" -- dat laat zich niet in dagen
 * uitdrukken, en een schatting die te precies oogt is een belofte die je
 * niet wilde doen.
 *
 * Zie docs/architecture/modules/werkwijze.md.
 */
const props = defineProps<{
    item: StapRij | null;
    opties: StapOpties;
    kanVertalen: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const bewerkt = computed(() => props.item !== null);

const bezig = ref(false);
const fouten = ref<Record<string, string>>({});

const formulier = ref({
    title_nl: '',
    title_en: '',
    summary_nl: '',
    summary_en: '',
    duration_nl: '',
    duration_en: '',
    result_nl: '',
    result_en: '',
    body_nl: '',
    body_en: '',
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
        formulier.value.title_en,
        formulier.value.summary_en,
        formulier.value.duration_en,
        formulier.value.result_en,
        formulier.value.body_en,
    ].join('\u0000');

const vulIn = (): void => {
    const item = props.item;

    formulier.value = {
        title_nl: item?.title_nl ?? '',
        title_en: item?.title_en ?? '',
        summary_nl: item?.summary_nl ?? '',
        summary_en: item?.summary_en ?? '',
        duration_nl: item?.duration_nl ?? '',
        duration_en: item?.duration_en ?? '',
        result_nl: item?.result_nl ?? '',
        result_en: item?.result_en ?? '',
        body_nl: item?.body_nl ?? '',
        body_en: item?.body_en ?? '',
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

/* --- De tellers --------------------------------------------------------- */

const overNl = computed(
    () => props.opties.samenvattingMax - formulier.value.summary_nl.length,
);

const overEn = computed(
    () => props.opties.samenvattingMax - formulier.value.summary_en.length,
);

/* --- Automatisch vertalen ---------------------------------------------- */

let stopLuisteren: (() => void) | undefined;

/** Welke Engelse velden de vertaaldienst mag vullen. */
const VERTAALD = [
    'title_en',
    'summary_en',
    'duration_en',
    'result_en',
    'body_en',
] as const;

onMounted(() => {
    stopLuisteren = router.on('flash', (gebeurtenis) => {
        const velden = (gebeurtenis as CustomEvent).detail?.flash?.vertaling as
            | Record<string, unknown>
            | undefined;

        if (!velden || !open.value) {
            return;
        }

        VERTAALD.forEach((sleutel) => {
            const waarde = velden[sleutel];

            if (typeof waarde === 'string') {
                formulier.value[sleutel] = waarde;
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
        formulier.value.title_nl.trim() !== '' &&
        formulier.value.summary_nl.trim() !== '',
);

const vertaal = async (): Promise<void> => {
    const erStaatAlEngels = VERTAALD.some(
        (sleutel) => formulier.value[sleutel].trim() !== '',
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
     * Vijf velden, en de lege gaan gewoon mee: de vertaaldienst slaat ze
     * over. Zou dit scherm ze eruit filteren, dan moet het weten wat de
     * server met een lege waarde doet -- en dat is precies het soort
     * kennis dat op twee plekken uiteen gaat lopen.
     */
    router.post(
        website.vertalen().url,
        {
            title_nl: formulier.value.title_nl,
            summary_nl: formulier.value.summary_nl,
            duration_nl: formulier.value.duration_nl,
            result_nl: formulier.value.result_nl,
            body_nl: formulier.value.body_nl,
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
            titel: t('Deze stap aanpassen?'),
        });
    } else {
        meteenOnline.value = true;

        akkoord = await bevestigAanmaken({
            titel: t('Deze stap toevoegen?'),
            tekst: t(
                'Hij komt achteraan te staan; sleep hem daarna op zijn plek. De nummering loopt mee met die volgorde.',
            ),
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
        router.put(werkwijze.update(props.item!.id).url, lading, opties);

        return;
    }

    router.post(werkwijze.store().url, lading, opties);
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
                    {{ bewerkt ? $t('Stap aanpassen') : $t('Nieuwe stap') }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Schrijf op wat er in deze stap gebeurt, niet wat hij heet. "Kennismaken" zegt een bezoeker weinig; "wat speelt er, wat is er al, en waar loopt het vast" zegt alles.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4">
                <div class="grid gap-4 sm:grid-cols-[1fr_12rem]">
                    <div class="grid gap-2">
                        <Label for="stap-titel-nl" verplicht>
                            <LocaleFlag locale="nl" size="sm" />
                            {{ $t('Titel') }}
                        </Label>
                        <Input
                            id="stap-titel-nl"
                            v-model="formulier.title_nl"
                            :maxlength="80"
                            :placeholder="$t('Bijvoorbeeld: Kennismaken')"
                            v-focus
                        />
                        <InputError :message="fouten.title_nl" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="stap-duur-nl">
                            <LocaleFlag locale="nl" size="sm" />
                            {{ $t('Duur') }}
                        </Label>
                        <Input
                            id="stap-duur-nl"
                            v-model="formulier.duration_nl"
                            :maxlength="40"
                            :placeholder="$t('Bijvoorbeeld: 1-2 weken')"
                        />
                        <InputError :message="fouten.duration_nl" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="stap-kort-nl" verplicht>
                        <LocaleFlag locale="nl" size="sm" />
                        {{ $t('Korte tekst') }}
                    </Label>
                    <Textarea
                        id="stap-kort-nl"
                        v-model="formulier.summary_nl"
                        :maxlength="props.opties.samenvattingMax"
                        rows="2"
                        :placeholder="
                            $t('Wat er in deze stap gebeurt, in één zin.')
                        "
                    />
                    <InputError :message="fouten.summary_nl" />
                    <p class="text-xs text-muted-foreground">
                        {{ $t(':aantal tekens over', { aantal: overNl }) }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="stap-resultaat-nl">
                        <LocaleFlag locale="nl" size="sm" />
                        {{ $t('Wat het oplevert') }}
                    </Label>
                    <Input
                        id="stap-resultaat-nl"
                        v-model="formulier.result_nl"
                        :maxlength="props.opties.resultaatMax"
                        :placeholder="
                            $t('Bijvoorbeeld: een plan met een prijs')
                        "
                    />
                    <InputError :message="fouten.result_nl" />
                    <p class="text-xs text-pretty text-muted-foreground">
                        {{
                            $t(
                                'Wat je klant na deze stap in handen heeft. Komt op je website als losse regel onder de tekst te staan.',
                            )
                        }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="stap-verhaal-nl">
                        <LocaleFlag locale="nl" size="sm" />
                        {{ $t('Uitgebreid verhaal') }}
                    </Label>
                    <Textarea
                        id="stap-verhaal-nl"
                        v-model="formulier.body_nl"
                        :maxlength="props.opties.verhaalMax"
                        rows="4"
                        :placeholder="
                            $t(
                                'Een witregel tussen twee stukken maakt er op je website twee alinea\'s van.',
                            )
                        "
                    />
                    <InputError :message="fouten.body_nl" />
                    <p class="text-xs text-pretty text-muted-foreground">
                        {{
                            $t(
                                'Vul je dit bij minstens één stap in, dan krijgt je werkwijze een eigen pagina met een knop ernaartoe. Laat je het overal leeg, dan blijft het bij de korte kaarten.',
                            )
                        }}
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

                        <div class="grid gap-4 sm:grid-cols-[1fr_12rem]">
                            <div class="grid gap-2">
                                <Label for="stap-titel-en" class="text-xs">
                                    {{ $t('Titel') }}
                                </Label>
                                <Input
                                    id="stap-titel-en"
                                    v-model="formulier.title_en"
                                    :maxlength="80"
                                />
                                <InputError :message="fouten.title_en" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="stap-duur-en" class="text-xs">
                                    {{ $t('Duur') }}
                                </Label>
                                <Input
                                    id="stap-duur-en"
                                    v-model="formulier.duration_en"
                                    :maxlength="40"
                                />
                                <InputError :message="fouten.duration_en" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="stap-kort-en" class="text-xs">
                                {{ $t('Korte tekst') }}
                            </Label>
                            <Textarea
                                id="stap-kort-en"
                                v-model="formulier.summary_en"
                                :maxlength="props.opties.samenvattingMax"
                                rows="2"
                            />
                            <InputError :message="fouten.summary_en" />
                            <p class="text-xs text-muted-foreground">
                                {{
                                    $t(':aantal tekens over', {
                                        aantal: overEn,
                                    })
                                }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="stap-resultaat-en" class="text-xs">
                                {{ $t('Wat het oplevert') }}
                            </Label>
                            <Input
                                id="stap-resultaat-en"
                                v-model="formulier.result_en"
                                :maxlength="props.opties.resultaatMax"
                            />
                            <InputError :message="fouten.result_en" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="stap-verhaal-en" class="text-xs">
                                {{ $t('Uitgebreid verhaal') }}
                            </Label>
                            <Textarea
                                id="stap-verhaal-en"
                                v-model="formulier.body_en"
                                :maxlength="props.opties.verhaalMax"
                                rows="4"
                            />
                            <InputError :message="fouten.body_en" />
                        </div>

                        <InputError :message="vertaalFout ?? undefined" />

                        <!--
                            Anders dan bij een vraag valt hier alleen de
                            titel terug. Een stap met een Engelse titel en
                            géén zin eronder leest nog steeds als een
                            werkwijze -- het nummer en de titel dragen hem.
                            Bij een vraag is het antwoord het enige dat er
                            staat, en dan kan dat niet.
                        -->
                        <p class="text-xs text-pretty text-muted-foreground">
                            {{
                                $t(
                                    'Laat je dit leeg, dan ziet een Engelse bezoeker de Nederlandse titel; de andere velden blijven dan gewoon weg. Een losse Nederlandse zin tussen Engelse tekst leest als een fout.',
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
                        formulier.title_nl.trim() === '' ||
                        formulier.summary_nl.trim() === ''
                    "
                    @click="opslaan"
                >
                    {{ bezig ? $t('Bezig...') : $t('Opslaan') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
