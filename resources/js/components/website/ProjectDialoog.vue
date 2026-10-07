<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, Languages, Loader2 } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import BrandSelect from '@/components/BrandSelect.vue';
import InputError from '@/components/InputError.vue';
import LocaleFlag from '@/components/LocaleFlag.vue';
import LogoKiezer from '@/components/LogoKiezer.vue';
import MaandKiezer from '@/components/MaandKiezer.vue';
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { bevestigAanmaken, bevestigBewerken } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import website from '@/routes/website';
import projecten from '@/routes/website/projecten';
import type { ProjectOpties, ProjectRij } from '@/types/projecten';

/**
 * Het formulier voor één project, in twee stappen.
 *
 * **Twee stappen en één opslag aan het eind**, net als bij Ervaring: de
 * afspraak in dit portaal is dat een bewerking één handeling is met één
 * bevestiging. Zou stap 1 al wegschrijven, dan staat er een half project
 * live zodra iemand het venster wegklikt.
 *
 * Stap 1 is het Nederlands plus alles wat geen taal heeft: de soort, de
 * organisatie, de periode en het beeld. Stap 2 is het Engels, met de
 * vertaalknop erboven.
 *
 * **Het eigen soort verschijnt alleen bij "Anders".** De server valideert
 * dat ook zo (`required_if`), en zet de labelvelden leeg zodra er weer
 * een gewoon soort wordt gekozen -- anders komt het woord van een oud
 * project terug zodra iemand ooit weer "Anders" kiest.
 *
 * **Verplichte velden hebben een sterretje.** Dat is een projectafspraak;
 * zie de `verplicht`-prop op Label.vue.
 *
 * Zie docs/architecture/modules/projecten.md.
 */
const props = defineProps<{
    /** Null betekent: een nieuw project. */
    item: ProjectRij | null;
    opties: ProjectOpties;
    kanVertalen: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const bewerkt = computed(() => props.item !== null);
const stap = ref(1);
const bezig = ref(false);
const fouten = ref<Record<string, string>>({});

/**
 * Welke kant de stappen op schuiven.
 *
 * Zonder dit schuift "Terug" dezelfde richting op als "Volgende", en dan
 * vertelt de beweging niets meer.
 */
const richting = ref<'vooruit' | 'terug'>('vooruit');

const naarStap = (nieuw: number): void => {
    richting.value = nieuw > stap.value ? 'vooruit' : 'terug';
    stap.value = nieuw;
};

type Formulier = {
    published: boolean;
    type: string;
    type_label_nl: string;
    type_label_en: string;
    title_nl: string;
    title_en: string;
    organisation: string;
    role_nl: string;
    role_en: string;
    summary_nl: string;
    summary_en: string;
    body_nl: string;
    body_en: string;
    result_nl: string;
    result_en: string;
    /** Als "2021-03"; de maandkiezer levert hem zo aan. */
    start: string;
    eind: string;
    loopt: boolean;
};

const leeg = (): Formulier => ({
    published: true,
    type: 'project',
    type_label_nl: '',
    type_label_en: '',
    title_nl: '',
    title_en: '',
    organisation: '',
    role_nl: '',
    role_en: '',
    summary_nl: '',
    summary_en: '',
    body_nl: '',
    body_en: '',
    result_nl: '',
    result_en: '',
    start: '',
    eind: '',
    loopt: false,
});

const formulier = ref<Formulier>(leeg());

/** Of de eigenaar zijn eigen woord moet invullen. */
const eigenSoort = computed(() => formulier.value.type === 'anders');

/* --- Het beeld ------------------------------------------------------- */

const beeld = ref<File | null>(null);
const beeldWeg = ref(false);
const beeldZoom = ref(1);
const beeldX = ref(0);
const beeldY = ref(0);
const beeldPlaat = ref(true);

/* --- Vullen en openen ------------------------------------------------ */

/** Het Engels zoals het stond toen het venster openging. */
const engelsBijOpenen = ref({
    type: '',
    titel: '',
    rol: '',
    samenvatting: '',
    omschrijving: '',
    resultaat: '',
});

const vulIn = (item: ProjectRij | null): void => {
    if (item === null) {
        formulier.value = leeg();
    } else {
        formulier.value = {
            published: item.published,
            type: item.type,
            type_label_nl: item.type_label_nl ?? '',
            type_label_en: item.type_label_en ?? '',
            title_nl: item.title_nl,
            title_en: item.title_en ?? '',
            organisation: item.organisation,
            role_nl: item.role_nl,
            role_en: item.role_en ?? '',
            summary_nl: item.summary_nl,
            summary_en: item.summary_en ?? '',
            body_nl: item.body_nl ?? '',
            body_en: item.body_en ?? '',
            result_nl: item.result_nl ?? '',
            result_en: item.result_en ?? '',
            start: item.start,
            eind: item.eind ?? '',
            loopt: item.loopt,
        };
    }

    engelsBijOpenen.value = {
        type: formulier.value.type_label_en,
        titel: formulier.value.title_en,
        rol: formulier.value.role_en,
        samenvatting: formulier.value.summary_en,
        omschrijving: formulier.value.body_en,
        resultaat: formulier.value.result_en,
    };

    beeld.value = null;
    beeldWeg.value = false;
    beeldZoom.value = 1;
    beeldX.value = 0;
    beeldY.value = 0;
    beeldPlaat.value = true;

    stap.value = 1;
    richting.value = 'vooruit';
    fouten.value = {};
    automatisch.value = null;
    vertaalFout.value = null;
};

/**
 * Of het venster zichzelf opnieuw opent en de inhoud dus moet blijven.
 *
 * Het gaat een paar keer dicht en weer open zonder dat de klant iets
 * anders is gaan doen: bij een bevestiging die hij afbreekt en bij een
 * validatiefout van de server. Zonder deze vlag vult het zich dan
 * opnieuw uit `props.item` en is alles wat hij typte weg.
 */
let behoudInhoud = false;

const openOpnieuw = (): void => {
    behoudInhoud = true;
    open.value = true;
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

    vulIn(props.item);
});

/* --- Automatisch vertalen -------------------------------------------- */

/** Wat de vertaaldienst voorstelde, precies zoals het binnenkwam. */
const automatisch = ref<Record<string, string> | null>(null);
const vertaalt = ref(false);
const vertaalFout = ref<string | null>(null);

let stopLuisteren: (() => void) | undefined;

onMounted(() => {
    stopLuisteren = router.on('flash', (gebeurtenis) => {
        const velden = (gebeurtenis as CustomEvent).detail?.flash?.vertaling as
            | Record<string, unknown>
            | undefined;

        if (!velden || !open.value) {
            return;
        }

        const voorstel: Record<string, string> = {};

        const zet = (sleutel: keyof Formulier): void => {
            const waarde = velden[sleutel];

            if (typeof waarde === 'string') {
                formulier.value[sleutel] = waarde as never;
                voorstel[sleutel] = waarde;
            }
        };

        zet('title_en');
        zet('summary_en');
        zet('body_en');
        zet('result_en');

        automatisch.value = voorstel;
        vertaalFout.value = null;

        // De vertaling landt in het Engels, dus daar hoort hij zichtbaar.
        naarStap(2);
    });
});

onBeforeUnmount(() => stopLuisteren?.());

const kanVertalen = computed(
    () => props.kanVertalen && formulier.value.title_nl.trim() !== '',
);

/**
 * Vertalen.
 *
 * **Vier velden en niet alles.** De vertaaldienst kapt af boven de twaalf
 * velden; de rol en het eigen soort zijn korte woorden die de eigenaar
 * sneller zelf typt dan nakijkt.
 */
const vertaal = (): void => {
    vertaalt.value = true;
    vertaalFout.value = null;

    router.post(
        website.vertalen().url,
        {
            title_nl: formulier.value.title_nl,
            summary_nl: formulier.value.summary_nl,
            body_nl: formulier.value.body_nl,
            result_nl: formulier.value.result_nl,
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

const engelsOngewijzigd = computed(
    () =>
        formulier.value.type_label_en === engelsBijOpenen.value.type &&
        formulier.value.title_en === engelsBijOpenen.value.titel &&
        formulier.value.role_en === engelsBijOpenen.value.rol &&
        formulier.value.summary_en === engelsBijOpenen.value.samenvatting &&
        formulier.value.body_en === engelsBijOpenen.value.omschrijving &&
        formulier.value.result_en === engelsBijOpenen.value.resultaat,
);

const isNogAutomatisch = computed(() => {
    const voorstel = automatisch.value;

    /*
     * Is de knop niet gebruikt, dan blijft staan wat er stond: was deze
     * tekst al een machinevertaling en is er niets aan veranderd, dan is
     * hij dat nog steeds.
     */
    if (voorstel === null) {
        return (
            (props.item?.automatisch_vertaald ?? false) &&
            engelsOngewijzigd.value
        );
    }

    return Object.entries(voorstel).every(
        ([veld, tekst]) => formulier.value[veld as keyof Formulier] === tekst,
    );
});

/* --- Opslaan --------------------------------------------------------- */

const payload = () => ({
    published: formulier.value.published,
    type: formulier.value.type,
    type_label_nl: formulier.value.type_label_nl,
    type_label_en: formulier.value.type_label_en,
    title_nl: formulier.value.title_nl,
    title_en: formulier.value.title_en,
    organisation: formulier.value.organisation,
    role_nl: formulier.value.role_nl,
    role_en: formulier.value.role_en,
    summary_nl: formulier.value.summary_nl,
    summary_en: formulier.value.summary_en,
    body_nl: formulier.value.body_nl,
    body_en: formulier.value.body_en,
    result_nl: formulier.value.result_nl,
    result_en: formulier.value.result_en,
    started_on: formulier.value.start,

    // Loopt het project nog, dan is er geen einddatum -- ook niet als er
    // per ongeluk nog een maand blijft staan van vóór het aanvinken.
    ended_on: formulier.value.loopt ? '' : formulier.value.eind,

    machine_translated: isNogAutomatisch.value,
    beeld: beeld.value,
    beeld_verwijderen: beeldWeg.value,
    beeld_zoom: beeldZoom.value,
    beeld_x: beeldX.value,
    beeld_y: beeldY.value,
    beeld_plaat: beeldPlaat.value,
});

/** Welke velden bij stap 1 horen, om een fout op de juiste plek te tonen. */
const veldenVanStap1 = [
    'type',
    'type_label_nl',
    'title_nl',
    'organisation',
    'role_nl',
    'started_on',
    'ended_on',
    'summary_nl',
    'body_nl',
    'result_nl',
    'beeld',
];

/** Bij een nieuw project: wil de klant het meteen online? */
const meteenOnline = ref(true);

const opslaan = async (): Promise<void> => {
    open.value = false;
    await adem();

    let akkoord: boolean;

    if (bewerkt.value) {
        akkoord = await bevestigBewerken({
            titel: t('Dit project aanpassen?'),
        });
    } else {
        meteenOnline.value = true;

        akkoord = await bevestigAanmaken({
            titel: t('Dit project toevoegen?'),
            keuze: {
                model: meteenOnline,
                label: t('Meteen op je website zetten'),
                tekst: t(
                    'Zet je het uit, dan bewaren we het wel maar zien bezoekers het niet. Je kunt het later alsnog online zetten.',
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

    /*
     * Altijd als formuliergegevens versturen, ook zonder bestand. Een
     * upload kan niet als JSON, en een wijziging moet dan via POST met
     * `_method` -- Laravel leest dat alleen uit formuliergegevens.
     */
    const doel = bewerkt.value
        ? projecten.update(props.item!.id)
        : projecten.store();

    const gegevens = bewerkt.value
        ? { ...payload(), _method: 'put' }
        : payload();

    router.post(doel.url, gegevens, {
        forceFormData: true,
        preserveScroll: true,
        onError: (ontvangen) => {
            fouten.value = ontvangen;

            // Naar de stap waar de fout staat; anders ziet de klant een
            // melding over een veld dat hij niet voor zich heeft.
            naarStap(
                Object.keys(ontvangen).some((veld) =>
                    veldenVanStap1.includes(veld),
                )
                    ? 1
                    : 2,
            );

            openOpnieuw();
        },
        onFinish: () => {
            bezig.value = false;
        },
    });
};

const adem = (): Promise<void> =>
    new Promise((klaar) => setTimeout(klaar, 200));

const samenvattingOver = computed(
    () => props.opties.samenvattingMax - formulier.value.summary_nl.length,
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="max-h-[85vh] brand-scrollbar overflow-y-auto sm:max-w-3xl"
        >
            <DialogHeader>
                <DialogTitle>
                    {{
                        bewerkt ? $t('Project aanpassen') : $t('Nieuw project')
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        stap === 1
                            ? $t(
                                  'Vul eerst de Nederlandse gegevens in. In de volgende stap komt het Engels.',
                              )
                            : $t(
                                  'De Engelse versie. Laat je iets leeg, dan staat dat stuk niet op je Engelse website.',
                              )
                    }}
                </DialogDescription>
            </DialogHeader>

            <!-- De stappen met hun vlaggetje. -->
            <div class="brand-stappen">
                <button
                    type="button"
                    class="brand-stap"
                    :data-actief="stap === 1 ? '' : undefined"
                    :data-klaar="stap > 1 ? '' : undefined"
                    @click="naarStap(1)"
                >
                    <LocaleFlag locale="nl" size="sm" />
                    <span>{{ $t('Nederlands') }}</span>
                    <Check v-if="stap > 1" class="size-3 text-success" />
                </button>

                <span class="brand-stap-lijn" aria-hidden="true" />

                <button
                    type="button"
                    class="brand-stap"
                    :data-actief="stap === 2 ? '' : undefined"
                    @click="naarStap(2)"
                >
                    <LocaleFlag locale="en" size="sm" />
                    <span>{{ $t('Engels') }}</span>
                </button>
            </div>

            <!-- ===== Stap 1: Nederlands en de gegevens ================ -->

            <div v-if="stap === 1" class="grid gap-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="project-soort" verplicht>
                            {{ $t('Soort') }}
                        </Label>
                        <BrandSelect
                            id="project-soort"
                            v-model="formulier.type"
                            :options="props.opties.soorten"
                        />
                        <InputError :message="fouten.type" />
                        <p class="text-xs text-pretty text-muted-foreground">
                            {{
                                $t(
                                    'Dit woord komt als klein label bij het project op je website te staan.',
                                )
                            }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="project-organisatie" verplicht>
                            {{ $t('Organisatie') }}
                        </Label>
                        <Input
                            id="project-organisatie"
                            v-model="formulier.organisation"
                            :maxlength="120"
                        />
                        <InputError :message="fouten.organisation" />
                        <p class="text-xs text-pretty text-muted-foreground">
                            {{
                                $t(
                                    'Een bedrijfsnaam vertalen we niet; hij staat op beide talen hetzelfde.',
                                )
                            }}
                        </p>
                    </div>
                </div>

                <!-- Alleen bij "Anders": je eigen woord. -->
                <div v-if="eigenSoort" class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="project-eigensoort-nl" verplicht>
                            <LocaleFlag locale="nl" size="sm" />
                            {{ $t('Je eigen soort') }}
                        </Label>
                        <Input
                            id="project-eigensoort-nl"
                            v-model="formulier.type_label_nl"
                            :maxlength="60"
                            :placeholder="
                                $t('Bijvoorbeeld: Haalbaarheidsonderzoek')
                            "
                        />
                        <InputError :message="fouten.type_label_nl" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="project-eigensoort-en">
                            <LocaleFlag locale="en" size="sm" />
                            {{ $t('Je eigen soort') }}
                        </Label>
                        <Input
                            id="project-eigensoort-en"
                            v-model="formulier.type_label_en"
                            :maxlength="60"
                        />
                        <InputError :message="fouten.type_label_en" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="project-titel" verplicht>
                            {{ $t('Titel') }}
                        </Label>
                        <Input
                            id="project-titel"
                            v-model="formulier.title_nl"
                            :maxlength="120"
                            v-focus
                        />
                        <InputError :message="fouten.title_nl" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="project-rol" verplicht>
                            {{ $t('Jouw rol') }}
                        </Label>
                        <Input
                            id="project-rol"
                            v-model="formulier.role_nl"
                            :maxlength="120"
                            :placeholder="
                                $t('Bijvoorbeeld: Technisch projectleider')
                            "
                        />
                        <InputError :message="fouten.role_nl" />
                    </div>
                </div>

                <!-- De periode. -->
                <div class="grid gap-4 border-t border-border pt-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="project-begin" verplicht>
                                {{ $t('Begin') }}
                            </Label>
                            <MaandKiezer
                                id="project-begin"
                                v-model="formulier.start"
                                :maanden="props.opties.maanden"
                                :jaren="props.opties.jaren"
                            />
                            <InputError :message="fouten.started_on" />
                        </div>

                        <div v-if="!formulier.loopt" class="grid gap-2">
                            <Label for="project-einde">{{ $t('Einde') }}</Label>
                            <MaandKiezer
                                id="project-einde"
                                v-model="formulier.eind"
                                :maanden="props.opties.maanden"
                                :jaren="props.opties.jaren"
                            />
                            <InputError :message="fouten.ended_on" />
                        </div>
                    </div>

                    <label class="flex items-center gap-3">
                        <Switch
                            v-model="formulier.loopt"
                            :aria-label="$t('Dit project loopt nog')"
                        />
                        <span class="text-sm font-medium">
                            {{ $t('Dit project loopt nog') }}
                        </span>
                    </label>
                </div>

                <!-- De teksten. -->
                <div class="grid gap-2 border-t border-border pt-4">
                    <Label for="project-samenvatting" verplicht>
                        {{ $t('Korte samenvatting') }}
                    </Label>
                    <Textarea
                        id="project-samenvatting"
                        v-model="formulier.summary_nl"
                        :maxlength="props.opties.samenvattingMax"
                        rows="3"
                    />
                    <InputError :message="fouten.summary_nl" />
                    <p class="text-xs text-muted-foreground">
                        {{
                            $t(':aantal tekens over', {
                                aantal: samenvattingOver,
                            })
                        }}
                    </p>
                    <p class="text-xs text-pretty text-muted-foreground">
                        {{
                            $t(
                                'Dit is de regel die op de kaart staat. Hou hem kort -- het hele verhaal komt hieronder.',
                            )
                        }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="project-omschrijving">
                        {{ $t('Wat je hebt gedaan') }}
                    </Label>
                    <Textarea
                        id="project-omschrijving"
                        v-model="formulier.body_nl"
                        :maxlength="props.opties.tekstMax"
                        rows="6"
                        :placeholder="
                            $t(
                                'Een witregel tussen twee stukken maakt er op je website twee alinea\'s van.',
                            )
                        "
                    />
                    <InputError :message="fouten.body_nl" />
                </div>

                <div class="grid gap-2">
                    <Label for="project-resultaat">
                        {{ $t('Wat het opleverde') }}
                    </Label>
                    <Textarea
                        id="project-resultaat"
                        v-model="formulier.result_nl"
                        :maxlength="props.opties.tekstMax"
                        rows="4"
                    />
                    <InputError :message="fouten.result_nl" />
                    <p class="text-xs text-pretty text-muted-foreground">
                        {{
                            $t(
                                'Bij een adviesopdracht is dit vaak het interessantste stuk: wat er na afloop anders was.',
                            )
                        }}
                    </p>
                </div>

                <!-- Het beeld. -->
                <div class="grid gap-2 border-t border-border pt-4">
                    <Label>{{ $t('Afbeelding') }}</Label>
                    <p class="text-xs text-pretty text-muted-foreground">
                        {{
                            $t(
                                'Een logo van de organisatie of een beeld bij het project. Zet je er geen in, dan komt er een vlak met de eerste letter van de organisatie -- het project staat er dan gewoon bij.',
                            )
                        }}
                    </p>
                    <LogoKiezer
                        :bestaand="props.item?.beeld ?? null"
                        v-model:bestand="beeld"
                        v-model:weghalen="beeldWeg"
                        v-model:zoom="beeldZoom"
                        v-model:x="beeldX"
                        v-model:y="beeldY"
                        v-model:plaat="beeldPlaat"
                    />
                    <InputError :message="fouten.beeld" />
                </div>
            </div>

            <!-- ===== Stap 2: Engels =================================== -->

            <div v-else class="grid gap-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-medium">
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

                <div v-if="eigenSoort" class="grid gap-2">
                    <Label for="project-eigensoort-en2">
                        {{ $t('Je eigen soort') }}
                    </Label>
                    <Input
                        id="project-eigensoort-en2"
                        v-model="formulier.type_label_en"
                        :maxlength="60"
                    />
                    <InputError :message="fouten.type_label_en" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="project-titel-en">{{ $t('Titel') }}</Label>
                        <Input
                            id="project-titel-en"
                            v-model="formulier.title_en"
                            :maxlength="120"
                        />
                        <InputError :message="fouten.title_en" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="project-rol-en">{{ $t('Jouw rol') }}</Label>
                        <Input
                            id="project-rol-en"
                            v-model="formulier.role_en"
                            :maxlength="120"
                        />
                        <InputError :message="fouten.role_en" />
                    </div>
                </div>

                <p class="text-xs text-pretty text-muted-foreground">
                    {{
                        $t(
                            'Laat je de titel of de rol leeg, dan staat het Nederlands er -- dat zijn korte namen. Bij de teksten hieronder is het andersom: die laten we dan weg.',
                        )
                    }}
                </p>

                <div class="grid gap-2 border-t border-border pt-4">
                    <Label for="project-samenvatting-en">
                        {{ $t('Korte samenvatting') }}
                    </Label>
                    <Textarea
                        id="project-samenvatting-en"
                        v-model="formulier.summary_en"
                        :maxlength="props.opties.samenvattingMax"
                        rows="3"
                    />
                    <InputError :message="fouten.summary_en" />
                    <InputError :message="vertaalFout ?? undefined" />
                </div>

                <div class="grid gap-2">
                    <Label for="project-omschrijving-en">
                        {{ $t('Wat je hebt gedaan') }}
                    </Label>
                    <Textarea
                        id="project-omschrijving-en"
                        v-model="formulier.body_en"
                        :maxlength="props.opties.tekstMax"
                        rows="6"
                    />
                    <InputError :message="fouten.body_en" />
                </div>

                <div class="grid gap-2">
                    <Label for="project-resultaat-en">
                        {{ $t('Wat het opleverde') }}
                    </Label>
                    <Textarea
                        id="project-resultaat-en"
                        v-model="formulier.result_en"
                        :maxlength="props.opties.tekstMax"
                        rows="4"
                    />
                    <InputError :message="fouten.result_en" />
                </div>
            </div>

            <DialogFooter>
                <Button
                    v-if="stap === 2"
                    type="button"
                    variant="secondary"
                    @click="naarStap(1)"
                >
                    {{ $t('Terug') }}
                </Button>
                <Button
                    v-else
                    type="button"
                    variant="secondary"
                    @click="open = false"
                >
                    {{ $t('Annuleren') }}
                </Button>

                <Button
                    v-if="stap === 1"
                    type="button"
                    variant="bewerken"
                    @click="naarStap(2)"
                >
                    {{ $t('Volgende') }}
                </Button>
                <Button
                    v-else
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
