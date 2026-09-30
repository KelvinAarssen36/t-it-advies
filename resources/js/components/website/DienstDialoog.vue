<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    Languages,
    Loader2,
    Plus,
    Trash2,
} from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import BrandSelect from '@/components/BrandSelect.vue';
import InputError from '@/components/InputError.vue';
import LocaleFlag from '@/components/LocaleFlag.vue';
import DienstIcoon from '@/components/site/DienstIcoon.vue';
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
import diensten from '@/routes/website/diensten';
import type { DienstRij, DienstenOpties } from '@/types/diensten';

/**
 * Een dienst aanmaken of bewerken.
 *
 * **Twee stappen, één opslag.** Stap 1 is het Nederlands, stap 2 het
 * Engels met de Nederlandse bron erboven. Opslaan aan het eind van stap
 * 1 zou een half ingevulde dienst live zetten; dezelfde afweging als bij
 * ErvaringDialoog.
 *
 * **De expertisepunten staan in stap 1 als een lijst**, met hun Engelse
 * vertaling in stap 2. Dat is met opzet zo verdeeld: in stap 1 bedenk je
 * wat er onder deze dienst valt, in stap 2 vertaal je wat je bedacht
 * hebt. Ze in één stap naast elkaar zetten maakt van elk punt twee
 * velden, en dan is acht punten een muur van zestien invoervakken.
 *
 * Zie docs/architecture/modules/diensten.md.
 */
const props = defineProps<{
    item: DienstRij | null;
    opties: DienstenOpties;
    kanVertalen: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const bewerkt = computed(() => props.item !== null);

const stap = ref(1);
const bezig = ref(false);
const fouten = ref<Record<string, string>>({});

/**
 * Een expertisepunt zoals het in dit venster bewerkt wordt.
 *
 * `rijId` bestaat alleen hier. Twee nieuwe punten hebben allebei geen
 * id, en dan is de index als `:key` niet genoeg: haal je de eerste van
 * twee weg, dan hergebruikt Vue het verkeerde invoervak en lijkt de
 * verkeerde regel te verdwijnen.
 */
type PuntRij = {
    rijId: number;
    text_nl: string;
    text_en: string;
};

let volgendeRijId = 0;

const formulier = ref({
    icon: 'advies',
    published: true,
    title_nl: '',
    title_en: '',
    summary_nl: '',
    summary_en: '',
    body_nl: '',
    body_en: '',
});

const punten = ref<PuntRij[]>([]);

/** Of de klant hem meteen online wil; alleen bij een nieuwe dienst. */
const meteenOnline = ref(true);

/**
 * Het Engels zoals het bij het openen van het venster stond.
 *
 * Hiermee bepalen we of het merkje "automatisch vertaald" nog klopt.
 */
let engelsBijOpenen = '';

const automatisch = ref(false);
const vertaalt = ref(false);
const vertaalFout = ref<string | null>(null);

/**
 * Welk punt op dit moment op zijn vertaling wacht, of null.
 *
 * De vertaalroute krijgt één tekst en geeft er één terug; hij weet niet
 * bij welk punt het hoort. Dat onthoudt dit venster, want het heeft de
 * knop zelf ingedrukt.
 */
const vertaaltPunt = ref<number | null>(null);

/**
 * Of het venster zichzelf opnieuw opent en de inhoud dus moet blijven.
 *
 * Het venster gaat dicht en weer open bij een bevestiging die de klant
 * afbreekt, bij de waarschuwing over het Engels, en bij een fout van de
 * server. Zonder deze vlag vult het zich dan opnieuw uit de props -- en
 * dan is zijn invoer weg **en** wordt de foutmelding die net was gezet
 * meteen weer gewist.
 */
let behoudInhoud = false;

const openOpnieuw = (): void => {
    behoudInhoud = true;
    open.value = true;
};

/** Alle Engelse velden bij elkaar, om te zien of er iets in staat. */
const engelsNu = (): string =>
    [
        formulier.value.title_en,
        formulier.value.summary_en,
        formulier.value.body_en,
        ...punten.value.map((punt) => punt.text_en),
    ].join('\u0000');

const vulIn = (): void => {
    const item = props.item;

    formulier.value = {
        icon: item?.icon ?? 'advies',
        published: item?.published ?? true,
        title_nl: item?.title_nl ?? '',
        title_en: item?.title_en ?? '',
        summary_nl: item?.summary_nl ?? '',
        summary_en: item?.summary_en ?? '',
        body_nl: item?.body_nl ?? '',
        body_en: item?.body_en ?? '',
    };

    punten.value = (item?.punten ?? []).map((punt) => ({
        rijId: volgendeRijId++,
        text_nl: punt.text_nl,
        text_en: punt.text_en ?? '',
    }));

    engelsBijOpenen = engelsNu();
    automatisch.value = item?.automatisch_vertaald ?? false;
    meteenOnline.value = true;
    vertaalFout.value = null;
    vertaaltPunt.value = null;
    fouten.value = {};
    stap.value = 1;
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

/* --- De stappen -------------------------------------------------------- */

const richting = ref<'vooruit' | 'terug'>('vooruit');

const naarStap = (nieuw: number): void => {
    richting.value = nieuw > stap.value ? 'vooruit' : 'terug';
    stap.value = nieuw;
};

/**
 * Welke velden bij stap 1 horen.
 *
 * Komt er een fout van de server terug, dan springt het venster naar de
 * stap waar dat veld staat. Anders staat er een rood randje op een
 * scherm dat je niet ziet.
 */
const veldenVanStap1 = ['icon', 'title_nl', 'summary_nl', 'body_nl', 'punten'];

const hoortBijStap1 = (veld: string): boolean =>
    veldenVanStap1.includes(veld) || veld.endsWith('.text_nl');

/* --- De expertisepunten ------------------------------------------------ */

const kanErbij = computed(
    () => punten.value.length < props.opties.puntenMaximum,
);

const voegPuntToe = (): void => {
    if (!kanErbij.value) {
        return;
    }

    punten.value.push({ rijId: volgendeRijId++, text_nl: '', text_en: '' });
};

/**
 * Een punt weghalen, maar niet zonder het te vragen.
 *
 * Het gebeurt pas echt bij Opslaan, en toch hoort hier een bevestiging:
 * een prullenbakje dat een ingevuld punt meteen laat verdwijnen voelt
 * als iets kwijtraken, en er is geen ongedaan maken in dit formulier.
 * Een leeg punt hoeft die vraag niet -- daar valt niets aan kwijt te
 * raken.
 */
const haalPuntWeg = async (index: number): Promise<void> => {
    const punt = punten.value[index];

    if (punt === undefined) {
        return;
    }

    if (punt.text_nl.trim() !== '' || punt.text_en.trim() !== '') {
        open.value = false;
        await adem();

        const akkoord = await bevestigVerwijderen({
            titel: t('Dit expertisepunt weghalen?'),
            tekst: t('Het verdwijnt van je website zodra je opslaat.'),
            knop: t('Ja, weghalen'),
        });

        await adem();
        openOpnieuw();

        if (!akkoord) {
            return;
        }
    }

    punten.value.splice(index, 1);

    /*
     * De foutmeldingen van de server hangen aan een plek in de lijst
     * (`punten.2.text_nl`) en niet aan een punt. Schuift er eentje weg,
     * dan wijzen ze naar de verkeerde regel.
     */
    fouten.value = {};
};

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

        /*
         * Een los expertisepunt komt op dezelfde route terug, maar hoort
         * bij een andere knop. Welk punt het was onthoudt
         * `vertaaltPunt`; de server weet daar niets van.
         */
        if (typeof velden.punt_en === 'string') {
            const rij = punten.value.find(
                (punt) => punt.rijId === vertaaltPunt.value,
            );

            if (rij !== undefined) {
                rij.text_en = velden.punt_en;
            }

            vertaalFout.value = null;

            return;
        }

        if (typeof velden.title_en === 'string') {
            formulier.value.title_en = velden.title_en;
        }

        if (typeof velden.summary_en === 'string') {
            formulier.value.summary_en = velden.summary_en;
        }

        if (typeof velden.description_en === 'string') {
            formulier.value.body_en = velden.description_en;
        }

        /*
         * De expertisepunten komen terug met hun nummer als sleutel, en
         * niet als rijtje. Lege punten gaan niet naar de vertaaldienst
         * en komen er dus ook niet uit; zou dit op volgorde inlezen,
         * dan schuift de vertaling van punt vier naar punt drie zodra
         * punt twee leeg was.
         */
        const vertaaldePunten = velden.punten_en as
            | Record<string, string>
            | undefined;

        if (vertaaldePunten) {
            Object.entries(vertaaldePunten).forEach(([index, tekst]) => {
                const rij = punten.value[Number(index)];

                if (rij !== undefined && typeof tekst === 'string') {
                    rij.text_en = tekst;
                }
            });
        }

        automatisch.value = true;
        vertaalFout.value = null;
    });
});

onBeforeUnmount(() => stopLuisteren?.());

/** Er valt pas iets te vertalen als er Nederlandse tekst staat. */
const kanVertalen = computed(
    () =>
        props.kanVertalen &&
        (formulier.value.title_nl.trim() !== '' ||
            formulier.value.summary_nl.trim() !== ''),
);

/**
 * Staat er Engels dat de knop zou overschrijven?
 *
 * De punten tellen hier wél mee, want de grote knop neemt ze mee. Het
 * losse knopje per punt blijft daarnaast bestaan: dat is er om er eentje
 * opnieuw te doen zonder de rest aan te raken.
 */
const eigenEngels = (): boolean =>
    !automatisch.value &&
    [
        formulier.value.title_en,
        formulier.value.summary_en,
        formulier.value.body_en,
        ...punten.value.map((punt) => punt.text_en),
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
            title_nl: formulier.value.title_nl,
            summary_nl: formulier.value.summary_nl,
            description_nl: formulier.value.body_nl,

            /*
             * De punten gaan mee, op hun plek in de lijst. Lege punten
             * slaat de vertaaldienst over -- die kosten tekens van het
             * tegoed en leveren niets op -- en het nummer zorgt dat wat
             * er wél uitkomt bij het goede punt terechtkomt.
             */
            punten_nl: punten.value.map((punt) => punt.text_nl),
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

/**
 * Eén expertisepunt vertalen.
 *
 * Een klein knopje naast het Engelse veld, net als bij het woord onder
 * een cijfer. Geen bevestiging: het gaat om een paar woorden, en die
 * typt de eigenaar sneller terug dan hij een venster wegklikt.
 */
const vertaalPunt = (punt: PuntRij): void => {
    if (punt.text_nl.trim() === '' || vertaaltPunt.value !== null) {
        return;
    }

    vertaaltPunt.value = punt.rijId;
    vertaalFout.value = null;

    router.post(
        website.vertalen().url,
        { punt_nl: punt.text_nl.trim() },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                vertaalFout.value = t(
                    'Het vertalen is niet gelukt. Probeer het zo nog eens, of vul het Engels zelf in.',
                );
            },
            /*
             * Pas een tik later vrijgeven: de melding met de vertaling
             * komt binnen terwijl het antwoord wordt verwerkt, dus vóór
             * `onFinish`, en die melding heeft dit nummer nog nodig.
             */
            onFinish: () => {
                void nextTick(() => {
                    vertaaltPunt.value = null;
                });
            },
        },
    );
};

/* --- Opslaan ----------------------------------------------------------- */

const opslaan = async (): Promise<void> => {
    /*
     * Het venster gaat dicht vóór de bevestiging in plaats van eronder
     * te liggen; zie de toelichting in pages/website/Indeling.vue.
     */
    open.value = false;
    await adem();

    let akkoord: boolean;

    if (bewerkt.value) {
        akkoord = await bevestigBewerken({
            titel: t('Deze dienst aanpassen?'),
        });
    } else {
        meteenOnline.value = true;

        akkoord = await bevestigAanmaken({
            titel: t('Deze dienst toevoegen?'),
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

    const engelsOngemoeid = engelsNu() === engelsBijOpenen;

    const lading = {
        ...formulier.value,
        punten: punten.value.map((punt) => ({
            text_nl: punt.text_nl,
            text_en: punt.text_en,
        })),
        machine_translated: automatisch.value && engelsOngemoeid,
    };

    const opties = {
        preserveScroll: true,
        onError: (ontvangen: Record<string, string>) => {
            fouten.value = ontvangen;

            // Naar de stap waar de fout staat; anders staat er een rood
            // randje op een scherm dat de eigenaar niet ziet.
            stap.value = Object.keys(ontvangen).some(hoortBijStap1) ? 1 : 2;

            openOpnieuw();
        },
        onFinish: () => {
            bezig.value = false;
        },
    };

    /*
     * Twee volledige aanroepen en niet `const verzoek = bewerkt ?
     * router.put : router.post`. Dat laatste stond er, en daar ging het
     * mis: `router.put` losgetrokken van `router` verliest zijn `this`,
     * dus de aanroep viel stil en er werd niets aangemaakt -- zonder
     * foutmelding, want er vertrok ook geen verzoek.
     */
    if (bewerkt.value) {
        router.put(diensten.update(props.item!.id).url, lading, opties);

        return;
    }

    router.post(diensten.store().url, lading, opties);
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
                    {{ bewerkt ? $t('Dienst aanpassen') : $t('Nieuwe dienst') }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Eerst het Nederlands, dan het Engels. Je slaat het in één keer op, zodat er nooit een halve dienst op je website staat.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <!-- ---------------------- De stappen --------------------- -->
            <div class="brand-stappen">
                <button
                    type="button"
                    class="brand-stap"
                    :data-actief="stap === 1 ? '' : undefined"
                    :data-klaar="stap > 1 ? '' : undefined"
                    @click="naarStap(1)"
                >
                    <LocaleFlag locale="nl" size="sm" />
                    {{ $t('Nederlands') }}
                    <Check v-if="stap > 1" class="size-3 text-success" />
                </button>

                <span class="brand-stap-lijn" />

                <span
                    class="brand-stap"
                    :data-actief="stap === 2 ? '' : undefined"
                >
                    <LocaleFlag locale="en" size="sm" />
                    {{ $t('Engels') }}
                </span>
            </div>

            <Transition :name="`brand-stap-${richting}`" mode="out-in">
                <!-- ------------------- Stap 1: Nederlands ------------- -->
                <div
                    v-if="stap === 1"
                    key="stap-1"
                    class="brand-formulier-lijst brand-scrollbar"
                >
                    <div class="grid gap-3 sm:grid-cols-[12rem_1fr]">
                        <div class="grid gap-1.5">
                            <Label for="dienst-icon" verplicht>
                                {{ $t('Pictogram') }}
                            </Label>
                            <BrandSelect
                                id="dienst-icon"
                                v-model="formulier.icon"
                                :options="props.opties.icon"
                            />
                            <InputError :message="fouten.icon" />
                        </div>

                        <div class="flex items-end pb-1">
                            <span class="brand-dienst-merk" aria-hidden="true">
                                <DienstIcoon
                                    :icoon="formulier.icon"
                                    class="size-5"
                                />
                            </span>
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="dienst-title-nl" verplicht>
                            {{ $t('Titel') }}
                        </Label>
                        <Input
                            id="dienst-title-nl"
                            v-model="formulier.title_nl"
                            maxlength="80"
                        />
                        <InputError :message="fouten.title_nl" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="dienst-summary-nl" verplicht>
                            {{ $t('Korte tekst') }}
                        </Label>
                        <Textarea
                            id="dienst-summary-nl"
                            v-model="formulier.summary_nl"
                            rows="3"
                            maxlength="300"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{
                                $t(
                                    'Dit staat op de kaart zelf. Houd het kort; drie regels is wat er past.',
                                )
                            }}
                        </p>
                        <InputError :message="fouten.summary_nl" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="dienst-body-nl">
                            {{ $t('Uitgebreide tekst') }}
                        </Label>
                        <Textarea
                            id="dienst-body-nl"
                            v-model="formulier.body_nl"
                            rows="6"
                            maxlength="5000"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{
                                $t(
                                    'Laat leeg als er niet meer te vertellen valt. Vul je het in, dan kan een bezoeker op de kaart klikken en opent er een venster met dit verhaal. Een witregel maakt een nieuwe alinea.',
                                )
                            }}
                        </p>
                        <InputError :message="fouten.body_nl" />
                    </div>

                    <!-- ----------------- De expertisepunten ----------- -->
                    <div class="grid gap-3 border-t border-border pt-4">
                        <div
                            class="flex flex-wrap items-baseline justify-between gap-2"
                        >
                            <h3 class="text-sm font-semibold">
                                {{ $t('Expertise') }}
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    $t(':aantal van :maximum', {
                                        aantal: punten.length,
                                        maximum: props.opties.puntenMaximum,
                                    })
                                }}
                            </p>
                        </div>

                        <p class="text-sm text-pretty text-muted-foreground">
                            {{
                                $t(
                                    'Korte steekwoorden die zeggen wat er onder deze dienst valt. Ze komen als kleine labels onder de tekst op de kaart te staan.',
                                )
                            }}
                        </p>

                        <InputError :message="fouten.punten" />

                        <div
                            v-for="(punt, index) in punten"
                            :key="punt.rijId"
                            class="flex items-start gap-2"
                        >
                            <div class="grid flex-1 gap-1.5">
                                <Input
                                    v-model="punt.text_nl"
                                    maxlength="60"
                                    :aria-label="
                                        $t('Expertisepunt :nummer', {
                                            nummer: index + 1,
                                        })
                                    "
                                    :placeholder="
                                        $t('bijvoorbeeld: monitoring')
                                    "
                                />
                                <InputError
                                    :message="fouten[`punten.${index}.text_nl`]"
                                />
                            </div>

                            <Button
                                variant="verwijderen-zacht"
                                size="icon-sm"
                                :aria-label="$t('Dit expertisepunt weghalen')"
                                @click="haalPuntWeg(index)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>

                        <div v-if="kanErbij">
                            <Button
                                variant="aanmaken"
                                size="sm"
                                class="gap-2"
                                @click="voegPuntToe"
                            >
                                <Plus class="size-4" />
                                {{ $t('Punt erbij') }}
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- --------------------- Stap 2: Engels --------------- -->
                <div
                    v-else
                    key="stap-2"
                    class="brand-formulier-lijst brand-scrollbar"
                >
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

                    <div class="grid gap-1.5">
                        <p class="brand-bron">{{ formulier.title_nl }}</p>
                        <Label for="dienst-title-en">
                            <LocaleFlag locale="en" size="sm" />
                            {{ $t('Titel') }}
                        </Label>
                        <Input
                            id="dienst-title-en"
                            v-model="formulier.title_en"
                            maxlength="80"
                            :disabled="vertaalt"
                        />
                        <InputError :message="fouten.title_en" />
                    </div>

                    <div class="grid gap-1.5">
                        <p class="brand-bron">{{ formulier.summary_nl }}</p>
                        <Label for="dienst-summary-en">
                            <LocaleFlag locale="en" size="sm" />
                            {{ $t('Korte tekst') }}
                        </Label>
                        <Textarea
                            id="dienst-summary-en"
                            v-model="formulier.summary_en"
                            rows="3"
                            maxlength="300"
                            :disabled="vertaalt"
                        />
                        <InputError :message="fouten.summary_en" />
                    </div>

                    <!--
                        Alleen tonen als er in het Nederlands iets staat.
                        Een leeg Engels veld onder een leeg Nederlands
                        veld is een vraag zonder aanleiding.
                    -->
                    <div
                        v-if="formulier.body_nl.trim() !== ''"
                        class="grid gap-1.5"
                    >
                        <p class="brand-bron">{{ formulier.body_nl }}</p>
                        <Label for="dienst-body-en">
                            <LocaleFlag locale="en" size="sm" />
                            {{ $t('Uitgebreide tekst') }}
                        </Label>
                        <Textarea
                            id="dienst-body-en"
                            v-model="formulier.body_en"
                            rows="6"
                            maxlength="5000"
                            :disabled="vertaalt"
                        />
                        <InputError :message="fouten.body_en" />
                    </div>

                    <div
                        v-if="punten.length > 0"
                        class="grid gap-3 border-t border-border pt-4"
                    >
                        <h3 class="text-sm font-semibold">
                            {{ $t('Expertise') }}
                        </h3>

                        <div
                            v-for="(punt, index) in punten"
                            :key="punt.rijId"
                            class="grid gap-1.5"
                        >
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <p class="brand-bron">{{ punt.text_nl }}</p>

                                <!--
                                    Eén punt vertalen, met een klein
                                    knopje. Hij verschijnt pas als er
                                    Nederlands staat om te vertalen.
                                -->
                                <Button
                                    v-if="
                                        props.kanVertalen &&
                                        punt.text_nl.trim() !== ''
                                    "
                                    variant="ghost"
                                    size="sm"
                                    class="h-6 shrink-0 gap-1 px-1.5 text-xs"
                                    :disabled="vertaaltPunt !== null"
                                    :aria-label="
                                        $t('Dit punt naar het Engels vertalen')
                                    "
                                    @click="vertaalPunt(punt)"
                                >
                                    <Loader2
                                        v-if="vertaaltPunt === punt.rijId"
                                        class="size-3 animate-spin"
                                    />
                                    <Languages v-else class="size-3" />
                                    {{ $t('Vertaal') }}
                                </Button>
                            </div>

                            <Input
                                v-model="punt.text_en"
                                maxlength="60"
                                :aria-label="
                                    $t('Engels expertisepunt :nummer', {
                                        nummer: index + 1,
                                    })
                                "
                            />
                            <InputError
                                :message="fouten[`punten.${index}.text_en`]"
                            />
                        </div>
                    </div>
                </div>
            </Transition>

            <DialogFooter class="gap-2">
                <div class="flex flex-1 justify-between gap-2">
                    <Button
                        v-if="stap === 2"
                        variant="ghost"
                        :disabled="bezig"
                        @click="naarStap(1)"
                    >
                        {{ $t('Terug') }}
                    </Button>
                    <span v-else />

                    <div class="flex gap-2">
                        <Button
                            variant="ghost"
                            :disabled="bezig"
                            @click="open = false"
                        >
                            {{ $t('Annuleren') }}
                        </Button>

                        <Button
                            v-if="stap === 1"
                            class="gap-2"
                            @click="naarStap(2)"
                        >
                            {{ $t('Volgende') }}
                            <ArrowRight class="size-4" />
                        </Button>

                        <Button
                            v-else
                            :variant="bewerkt ? 'bewerken' : 'aanmaken'"
                            class="gap-2"
                            :disabled="bezig || vertaalt"
                            @click="opslaan"
                        >
                            <Loader2 v-if="bezig" class="size-4 animate-spin" />
                            {{ $t('Opslaan') }}
                        </Button>
                    </div>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
