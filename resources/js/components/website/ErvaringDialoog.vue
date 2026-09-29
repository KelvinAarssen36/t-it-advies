<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    Languages,
    Loader2,
    TriangleAlert,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import BrandSelect from '@/components/BrandSelect.vue';
import InputError from '@/components/InputError.vue';
import LocaleFlag from '@/components/LocaleFlag.vue';
import LogoKiezer from '@/components/LogoKiezer.vue';
import MaandKiezer from '@/components/MaandKiezer.vue';
import ErvaringIcoon from '@/components/site/ErvaringIcoon.vue';
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
import {
    bevestigAanmaken,
    bevestigBewerken,
    bevestigVerwijderen,
} from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import ervaring from '@/routes/website/ervaring';
import type { ErvaringOpties, ErvaringRij } from '@/types/ervaring';

/**
 * Het formulier voor één ervaring, in twee stappen.
 *
 * **Waarom twee stappen en niet twee opslagrondes.** De afspraak is dat de
 * klant eerst het Nederlands invult en daarna doorklikt naar het Engels.
 * Dat gebeurt hier binnen hetzelfde formulier, met één keer opslaan aan het
 * eind. Zou stap 1 al wegschrijven, dan staat er een half item live zodra
 * hij tussendoor stopt -- en dan moet je ook nog gaan uitleggen waarom
 * aanmaken ineens twee bevestigingen vraagt.
 *
 * De twee stappen dragen het vlaggetje van hun taal en schuiven in de
 * richting waarin je loopt. Dat is niet alleen versiering: bij een
 * formulier dat van gedaante verandert is de eerste vraag "ben ik ergens
 * anders terechtgekomen of veranderde dit scherm?", en die beantwoordt de
 * beweging.
 *
 * **De vertaalknop slaat niets op.** Hij vult alleen de Engelse velden met
 * een voorstel; de klant leest na, past aan en drukt dan zelf op Opslaan.
 * Zie docs/architecture/automatisch-vertalen.md.
 *
 * **Verplichte velden hebben een sterretje.** Dat is een projectafspraak en
 * geen keuze van dit scherm; zie de `verplicht`-prop op Label.vue.
 */

const props = defineProps<{
    /** Null betekent: een nieuwe ervaring. */
    item: ErvaringRij | null;
    opties: ErvaringOpties;
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
    icon: string;
    published: boolean;
    role_nl: string;
    role_en: string;
    organisation: string;
    organisation_url: string;
    employment: string;
    workplace: string;
    location_nl: string;
    location_en: string;
    description_nl: string;
    description_en: string;
    /** Als "2021-03"; de maandkiezer levert hem zo aan. */
    start: string;
    eind: string;
    loopt: boolean;
};

const leeg = (): Formulier => ({
    icon: 'werk',
    published: true,
    role_nl: '',
    role_en: '',
    organisation: '',
    organisation_url: '',
    employment: '',
    workplace: '',
    location_nl: '',
    location_en: '',
    description_nl: '',
    description_en: '',
    start: '',
    eind: '',
    loopt: false,
});

const formulier = ref<Formulier>(leeg());

/* --- Het beeldmerk --------------------------------------------------- */

/**
 * Het kiezen en bijsnijden zit in LogoKiezer; hier staan alleen de
 * waarden die mee moeten naar de server.
 *
 * Er zijn er vijf, en dat lijkt veel voor één plaatje. Het zijn precies de
 * knoppen die de klant voor zich heeft: welk bestand, of het oude weg
 * moet, hoe ver ingezoomd, hoe verschoven, en of er een witte ondergrond
 * onder komt.
 */
const logo = ref<File | null>(null);
const logoWeg = ref(false);
const logoZoom = ref(1);
const logoX = ref(0);
const logoY = ref(0);
const logoPlaat = ref(true);

/* --- Vullen en legen ------------------------------------------------- */

const vulIn = (): void => {
    const item = props.item;

    logo.value = null;
    logoWeg.value = false;
    logoZoom.value = 1;
    logoX.value = 0;
    logoY.value = 0;
    logoPlaat.value = true;

    if (item === null) {
        formulier.value = leeg();
    } else {
        formulier.value = {
            icon: item.icon,
            published: item.published,
            role_nl: item.role_nl,
            role_en: item.role_en ?? '',
            organisation: item.organisation,
            organisation_url: item.organisation_url ?? '',
            employment: item.employment ?? '',
            workplace: item.workplace ?? '',
            location_nl: item.location_nl ?? '',
            location_en: item.location_en ?? '',
            description_nl: item.description_nl ?? '',
            description_en: item.description_en ?? '',
            start: item.started_on,
            eind: item.ended_on ?? '',
            loopt: item.loopt,
        };
    }

    // De stand van het Engels vastleggen, zodat we straks kunnen zien of
    // de klant er zelf aan heeft gezeten. Zie isNogAutomatisch.
    engelsBijOpenen.value = {
        role: formulier.value.role_en,
        plaats: formulier.value.location_en,
        tekst: formulier.value.description_en,
    };

    stap.value = 1;
    richting.value = 'vooruit';
    fouten.value = {};
    automatisch.value = null;
};

/**
 * Of het venster zichzelf opnieuw opent en de inhoud dus moet blijven.
 *
 * **Dit is de reden dat deze vlag bestaat.** Het venster gaat een paar
 * keer dicht en weer open zonder dat de klant iets anders is gaan doen:
 * bij een bevestiging die hij afbreekt, bij een waarschuwing over het
 * overschrijven van zijn Engels, en bij een validatiefout van de server.
 * Zonder deze vlag vult het zich dan opnieuw uit `props.item` -- en is
 * alles wat hij net had getypt weg. Dat is precies wat er gebeurde bij
 * een te lange beschrijving: je drukte op Opslaan en je tekst was terug
 * naar de oude.
 */
let behoudInhoud = false;

/** Opnieuw openen zonder dat het formulier wordt teruggezet. */
const openOpnieuw = (): void => {
    behoudInhoud = true;
    open.value = true;
};

// Bij elke keer openen opnieuw vullen. Zonder deze watch blijft de vorige
// ervaring in het formulier staan als je er twee achter elkaar bewerkt.
watch(open, (isOpen) => {
    if (!isOpen) {
        /*
         * Bij het sluiten de vlag weer neerleggen. Hij wordt altijd pas
         * gezet nadat het venster al dicht is, dus dit kan nooit een
         * geplande heropening dwarszitten -- en het voorkomt dat een vlag
         * die om wat voor reden ook is blijven staan, de volgende keer
         * het invullen overslaat.
         */
        behoudInhoud = false;

        return;
    }

    if (behoudInhoud) {
        behoudInhoud = false;

        return;
    }

    vulIn();
});

/*
 * Wat de vertaaldienst heeft voorgesteld, precies zoals het binnenkwam.
 * Wijkt het formulier daar bij het opslaan van af, dan heeft de klant het
 * nagelopen en is het geen machinevertaling meer. Null betekent: de knop is
 * niet gebruikt.
 */
const automatisch = ref<Record<string, string> | null>(null);

/**
 * Het Engels zoals het in het formulier stond toen het openging.
 *
 * Daarmee kunnen we zien of de klant er zelf aan heeft gezeten. Zonder
 * deze vergelijking **verdween het merkje "automatisch vertaald" bij elke
 * opslag**: het venster begint met `automatisch = null`, dus verbeterde je
 * een typefout in de Nederlandse tekst, dan ging het merkje eraf terwijl
 * niemand het Engels had nagelopen.
 */
const engelsBijOpenen = ref({ role: '', plaats: '', tekst: '' });

const engelsOngewijzigd = computed(
    () =>
        formulier.value.role_en === engelsBijOpenen.value.role &&
        formulier.value.location_en === engelsBijOpenen.value.plaats &&
        formulier.value.description_en === engelsBijOpenen.value.tekst,
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

const payload = () => ({
    icon: formulier.value.icon,
    published: formulier.value.published,
    role_nl: formulier.value.role_nl,
    role_en: formulier.value.role_en,
    organisation: formulier.value.organisation,
    organisation_url: formulier.value.organisation_url,
    employment: formulier.value.employment,
    workplace: formulier.value.workplace,
    location_nl: formulier.value.location_nl,
    location_en: formulier.value.location_en,
    description_nl: formulier.value.description_nl,
    description_en: formulier.value.description_en,
    started_on: formulier.value.start,
    // Loopt de ervaring nog, dan is er geen einddatum -- ook niet als er
    // per ongeluk nog een maand blijft staan van vóór het aanvinken.
    ended_on: formulier.value.loopt ? '' : formulier.value.eind,
    machine_translated: isNogAutomatisch.value,
    logo: logo.value,
    logo_verwijderen: logoWeg.value,
    logo_zoom: logoZoom.value,
    logo_x: logoX.value,
    logo_y: logoY.value,
    logo_plaat: logoPlaat.value,
});

/** Welke velden bij welke stap horen, om een fout op de juiste plek te tonen. */
const veldenVanStap1 = [
    'icon',
    'logo',
    'role_nl',
    'organisation',
    'organisation_url',
    'employment',
    'workplace',
    'location_nl',
    'started_on',
    'ended_on',
    'description_nl',
];

/** Bij een nieuwe ervaring: wil de klant hem meteen online? */
const meteenOnline = ref(true);

const opslaan = async (): Promise<void> => {
    /*
     * Het venster gaat dicht vóór de bevestiging in plaats van eronder te
     * liggen; zegt de klant nee, dan komt het weer open met alles er nog
     * in. Zie de toelichting in pages/website/Indeling.vue -- twee
     * dialogen over elkaar laten `pointer-events` op elkaar achter.
     */
    open.value = false;
    await adem();

    let akkoord: boolean;

    if (bewerkt.value) {
        akkoord = await bevestigBewerken({
            titel: t('Deze ervaring aanpassen?'),
        });
    } else {
        /*
         * De laatste beslissing hoort bij de handeling en niet in het
         * formulier: gaat hij meteen live, of zet je hem er eerst
         * offline neer om later nog eens te lezen?
         */
        meteenOnline.value = true;

        akkoord = await bevestigAanmaken({
            titel: t('Deze ervaring toevoegen?'),
            keuze: {
                model: meteenOnline,
                label: t('Meteen op je website zetten'),
                tekst: t(
                    'Zet je hem uit, dan bewaren we hem wel maar zien bezoekers hem niet. Je kunt hem later alsnog online zetten.',
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
     * `_method` -- Laravel leest dat alleen uit formuliergegevens. Eén weg
     * dus, in plaats van twee die net iets anders werken.
     */
    const doel = bewerkt.value
        ? ervaring.update(props.item!.id)
        : ervaring.store();

    const gegevens = bewerkt.value
        ? { ...payload(), _method: 'put' }
        : payload();

    router.post(doel.url, gegevens, {
        forceFormData: true,
        preserveScroll: true,
        onError: (ontvangen) => {
            fouten.value = ontvangen;

            // Terug naar het venster, en naar de stap waar de fout staat.
            // Anders ziet de klant een melding over een veld dat hij niet
            // voor zich heeft.
            naarStap(
                Object.keys(ontvangen).some((veld) =>
                    veldenVanStap1.includes(veld),
                )
                    ? 1
                    : 2,
            );

            // Opnieuw open mét alles erin. Zou hier `open.value = true`
            // staan, dan vult het venster zich uit `props.item` en is de
            // ingetypte tekst weg -- precies het moment waarop je hem het
            // hardst nodig hebt.
            openOpnieuw();
        },
        onFinish: () => {
            bezig.value = false;
        },
    });
};

const adem = (): Promise<void> =>
    new Promise((klaar) => setTimeout(klaar, 200));

/* --- Automatisch vertalen ------------------------------------------- */

const vertaalt = ref(false);

/** Wat er misging bij de laatste vertaalpoging, als er iets misging. */
const vertaalFout = ref<string | null>(null);

/**
 * Hoeveel seconden de vertaling al onderweg is.
 *
 * Een lange beschrijving wordt op de server in stukken geknipt en stuk
 * voor stuk vertaald; dat kan bij een paar alinea's een halve minuut
 * duren. Een laadschermpje dat de hele tijd hetzelfde zegt, leest dan als
 * vastgelopen. Dit getal laat de tekst meelopen met de werkelijkheid.
 */
const seconden = ref(0);

let klok: ReturnType<typeof setInterval> | undefined;

/**
 * Hoeveel stukken de server er ongeveer van maakt.
 *
 * Dezelfde grens als in App\Support\Translation\MyMemoryVertaler. Het
 * hoeft niet precies te kloppen -- het is een verwachting die de klant
 * geruststelt, geen belofte -- maar hij moet wel meebewegen met wat hij
 * heeft getypt.
 */
const PER_VERZOEK = 440;

const stukken = computed(() =>
    [
        formulier.value.role_nl,
        formulier.value.location_nl,
        formulier.value.description_nl,
    ]
        .filter((tekst) => tekst.trim() !== '')
        .reduce(
            (totaal, tekst) => totaal + Math.ceil(tekst.length / PER_VERZOEK),
            0,
        ),
);

/** Wat er onder het draaiende wieltje staat. */
const wachtTekst = computed(() => {
    if (seconden.value >= 20) {
        return t('Het duurt langer dan verwacht. Nog heel even.');
    }

    if (stukken.value > 3) {
        return t(
            'Je tekst gaat in :aantal stukjes naar de vertaaldienst. Dat duurt even.',
            { aantal: stukken.value },
        );
    }

    return t('Dit duurt een paar tellen. Daarna kun je alles nog aanpassen.');
});

watch(vertaalt, (bezigNu) => {
    clearInterval(klok);
    seconden.value = 0;

    if (!bezigNu) {
        return;
    }

    klok = setInterval(() => {
        seconden.value += 1;
    }, 1000);
});

onBeforeUnmount(() => clearInterval(klok));

/**
 * De vertaling komt terug als flits-prop, net als de meldingen.
 *
 * Dat is hier de eenvoudigste weg: geen tweede HTTP-cliënt naast Inertia,
 * en de bestaande route hoeft niets terug te geven wat de pagina zelf niet
 * al kent. Zie App\Support\Toast voor hetzelfde mechanisme.
 */
let stopLuisteren: (() => void) | undefined;

onMounted(() => {
    stopLuisteren = router.on('flash', (gebeurtenis) => {
        const velden = (gebeurtenis as CustomEvent).detail?.flash?.vertaling as
            | Record<string, string>
            | undefined;

        if (!velden) {
            return;
        }

        for (const [veld, tekst] of Object.entries(velden)) {
            if (veld in formulier.value) {
                (formulier.value as unknown as Record<string, string>)[veld] =
                    tekst;
            }
        }

        automatisch.value = velden;
        vertaalFout.value = null;
    });
});

onBeforeUnmount(() => stopLuisteren?.());

/**
 * Staat er Engels dat de knop zou overschrijven?
 *
 * Wat de vertaaldienst zojuist zelf heeft neergezet telt niet mee: dat
 * opnieuw laten vertalen kost de klant niets. Het gaat om tekst die hij
 * zelf heeft getypt of aangepast.
 */
const eigenEngels = computed(
    () =>
        !isNogAutomatisch.value &&
        [
            formulier.value.role_en,
            formulier.value.location_en,
            formulier.value.description_en,
        ].some((tekst) => tekst.trim() !== ''),
);

const vertaal = async (): Promise<void> => {
    /*
     * Eerst waarschuwen als er Engels staat. De knop gooit dat zonder
     * pardon weg, en een vertaling die je net met de hand hebt
     * bijgeschaafd terugkrijgen als machinetaal is niet iets waar je van
     * terug kunt -- er is geen ongedaan maken in dit formulier.
     */
    if (eigenEngels.value) {
        open.value = false;
        await adem();

        const akkoord = await bevestigVerwijderen({
            titel: t('Het Engels dat er staat overschrijven?'),
            tekst: t(
                'Alles wat je zelf in de Engelse velden hebt gezet, wordt vervangen door de automatische vertaling.',
            ),
            knop: t('Ja, overschrijven'),
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
        ervaring.vertalen().url,
        {
            role_nl: formulier.value.role_nl,
            location_nl: formulier.value.location_nl,
            description_nl: formulier.value.description_nl,
        },
        {
            preserveState: true,
            preserveScroll: true,
            /*
             * Zonder dit gebeurt er bij een fout zichtbaar niets: het
             * laadschermpje verdwijnt en de velden blijven leeg. Dat is
             * precies hoe "soms doet hij het niet" voelt.
             */
            onError: (ontvangen) => {
                vertaalFout.value =
                    Object.values(ontvangen)[0] ??
                    t('Dat lukte niet. Probeer het zo nog eens.');
            },
            onFinish: () => {
                vertaalt.value = false;
            },
        },
    );
};

/** Er valt pas iets te vertalen als er Nederlandse tekst staat. */
const valtTeVertalen = computed(
    () =>
        formulier.value.role_nl.trim() !== '' ||
        formulier.value.location_nl.trim() !== '' ||
        formulier.value.description_nl.trim() !== '',
);
</script>

<template>
    <Dialog v-model:open="open">
        <!--
            Ruim, want dit is het formulier waar de klant het langst in
            zit: dertien velden in twee stappen. Op een krap venster staat
            alles onder elkaar en moet je scrollen om te zien wat je al
            hebt ingevuld.
        -->
        <DialogContent class="sm:max-w-4xl">
            <DialogHeader>
                <DialogTitle>
                    {{
                        bewerkt
                            ? $t('Ervaring aanpassen')
                            : $t('Nieuwe ervaring')
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        stap === 1
                            ? $t(
                                  'Vul eerst de Nederlandse gegevens in. In de volgende stap komt het Engels.',
                              )
                            : $t(
                                  'Alleen de velden die vertaald worden. Wat je leeg laat, laten we op de Engelse site gewoon weg.',
                              )
                    }}
                </DialogDescription>
            </DialogHeader>

            <!--
                De stappen met hun vlaggetje. Het vlaggetje zegt in één
                oogopslag welke taal je voor je hebt -- sneller dan het
                woord ernaast, en het is precies het onderscheid waar je bij
                twee bijna identieke schermen op moet letten.
            -->
            <div class="brand-stappen">
                <button
                    type="button"
                    class="brand-stap"
                    :data-actief="stap === 1 ? '' : undefined"
                    :data-klaar="stap > 1 ? '' : undefined"
                    @click="naarStap(1)"
                >
                    <!--
                        Het vlaggetje blijft staan, ook als de stap klaar
                        is. Stond er dan alleen een vinkje, dan draagt de
                        ene stap wel een vlag en de andere niet -- en juist
                        die vlaggen zijn hier het snelste onderscheid
                        tussen twee schermen die op elkaar lijken.
                    -->
                    <LocaleFlag locale="nl" />
                    <Check v-if="stap > 1" class="size-3 text-success" />
                    {{ $t('1. Nederlands') }}
                </button>

                <span class="brand-stap-lijn" aria-hidden="true" />

                <span
                    class="brand-stap"
                    :data-actief="stap === 2 ? '' : undefined"
                >
                    <LocaleFlag locale="en" />
                    {{ $t('2. Engels') }}
                </span>
            </div>

            <div class="brand-formulier-lijst brand-scrollbar">
                <Transition :name="`brand-stap-${richting}`" mode="out-in">
                    <!-- ---------------------- Stap 1 ---------------------- -->
                    <div v-if="stap === 1" key="1" class="grid gap-4">
                        <div class="grid gap-2">
                            <Label for="role_nl" verplicht>
                                {{ $t('Functie') }}
                            </Label>
                            <Input
                                id="role_nl"
                                v-model="formulier.role_nl"
                                :placeholder="
                                    $t('Bijvoorbeeld: Systeembeheerder')
                                "
                            />
                            <InputError :message="fouten.role_nl" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="organisation" verplicht>
                                    {{ $t('Organisatie') }}
                                </Label>
                                <Input
                                    id="organisation"
                                    v-model="formulier.organisation"
                                />
                                <InputError :message="fouten.organisation" />
                                <!--
                                    De organisatie heeft geen Engels veld. Een
                                    bedrijfsnaam is een eigennaam; die vertaal
                                    je niet. Zie de migratie.
                                -->
                            </div>

                            <div class="grid gap-2">
                                <Label for="organisation_url">
                                    {{ $t('Website') }}
                                </Label>
                                <Input
                                    id="organisation_url"
                                    v-model="formulier.organisation_url"
                                    type="url"
                                    placeholder="https://"
                                />
                                <InputError
                                    :message="fouten.organisation_url"
                                />
                            </div>
                        </div>

                        <!--
                            Het beeldmerk: een geüpload beeld als het er is,
                            en anders een pictogram uit de lijst. Die twee
                            staan bewust naast elkaar en niet als keuze
                            vooraf -- een logo heb je of je hebt het niet, en
                            tot die tijd hoort er iets te staan.

                            Hoe het beeld in het rondje komt, bepaalt de
                            klant zelf met zoomen en slepen. Zie LogoKiezer
                            voor waarom dat geen automatische regel is.
                        -->
                        <div class="grid gap-2">
                            <Label>{{ $t('Beeldmerk') }}</Label>

                            <LogoKiezer
                                v-model:bestand="logo"
                                v-model:weghalen="logoWeg"
                                v-model:zoom="logoZoom"
                                v-model:x="logoX"
                                v-model:y="logoY"
                                v-model:plaat="logoPlaat"
                                :bestaand="props.item?.logo ?? null"
                            />

                            <InputError :message="fouten.logo" />

                            <!--
                                Het pictogram is de terugval. Staat er een
                                beeld, dan zie je het niet op de website -- en
                                dan hoort de lijst hier ook niet te schreeuwen
                                om aandacht.
                            -->
                            <div class="mt-1 grid gap-2">
                                <Label class="text-xs text-muted-foreground">
                                    {{
                                        logo || (props.item?.logo && !logoWeg)
                                            ? $t(
                                                  'Pictogram, voor als het beeld ooit weggaat',
                                              )
                                            : $t('Pictogram')
                                    }}
                                </Label>
                                <div class="flex items-center gap-2">
                                    <span class="brand-logo-rondje is-klein">
                                        <ErvaringIcoon
                                            :icoon="formulier.icon"
                                            class="size-4"
                                        />
                                    </span>
                                    <BrandSelect
                                        v-model="formulier.icon"
                                        :options="opties.icon"
                                        :aria-label="$t('Pictogram')"
                                        class="flex-1"
                                    />
                                </div>
                                <InputError :message="fouten.icon" />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="location_nl">
                                    {{ $t('Plaats') }}
                                </Label>
                                <Input
                                    id="location_nl"
                                    v-model="formulier.location_nl"
                                    :placeholder="$t('Bijvoorbeeld: Utrecht')"
                                />
                                <InputError :message="fouten.location_nl" />
                            </div>

                            <div class="grid gap-2">
                                <Label>{{ $t('Dienstverband') }}</Label>
                                <BrandSelect
                                    v-model="formulier.employment"
                                    :options="opties.employment"
                                    :placeholder="$t('Niet opgeven')"
                                    :aria-label="$t('Dienstverband')"
                                />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label>{{ $t('Werkvorm') }}</Label>
                                <BrandSelect
                                    v-model="formulier.workplace"
                                    :options="opties.workplace"
                                    :placeholder="$t('Niet opgeven')"
                                    :aria-label="$t('Werkvorm')"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label verplicht>{{ $t('Van') }}</Label>
                                <MaandKiezer
                                    v-model="formulier.start"
                                    :maanden="opties.maanden"
                                    :jaren="opties.jaren"
                                    :placeholder="$t('Kies een maand')"
                                    :aria-label="$t('Begindatum')"
                                />
                                <InputError :message="fouten.started_on" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label>{{ $t('Tot') }}</Label>

                            <label class="flex items-center gap-2 text-sm">
                                <Switch
                                    v-model="formulier.loopt"
                                    :aria-label="$t('Ik werk hier nu')"
                                />
                                {{ $t('Ik werk hier nu') }}
                            </label>

                            <!--
                                De einddatum verdwijnt als de ervaring nog
                                loopt. Hem uitgeschakeld laten staan zou
                                suggereren dat er nog iets in te vullen valt.
                            -->
                            <MaandKiezer
                                v-if="!formulier.loopt"
                                v-model="formulier.eind"
                                :maanden="opties.maanden"
                                :jaren="opties.jaren"
                                :placeholder="$t('Kies een maand')"
                                :aria-label="$t('Einddatum')"
                                wisbaar
                            />
                            <InputError :message="fouten.ended_on" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="description_nl">
                                {{ $t('Beschrijving') }}
                            </Label>
                            <Textarea
                                id="description_nl"
                                v-model="formulier.description_nl"
                                :placeholder="
                                    $t(
                                        'Wat deed je daar? Laat leeg als het niet nodig is.',
                                    )
                                "
                            />
                            <InputError :message="fouten.description_nl" />
                        </div>
                    </div>

                    <!-- ---------------------- Stap 2 ---------------------- -->
                    <div v-else key="2" class="relative grid gap-4">
                        <div
                            v-if="kanVertalen"
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <p class="text-sm text-muted-foreground">
                                {{ $t('Geen zin om het zelf te typen?') }}
                            </p>
                            <Button
                                type="button"
                                variant="outline"
                                class="gap-2"
                                :disabled="vertaalt || !valtTeVertalen"
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

                        <!--
                            Ging het mis, dan hoort dat hier te staan.
                            Zonder deze regel verdwijnt het laadschermpje
                            en blijven de velden leeg -- en dat leest als
                            "soms doet die knop het niet".
                        -->
                        <p
                            v-if="vertaalFout && !vertaalt"
                            class="brand-cijfer-waarschuwing"
                        >
                            <TriangleAlert class="size-3.5 shrink-0" />
                            <span>{{ vertaalFout }}</span>
                        </p>

                        <div class="grid gap-2">
                            <Label for="role_en">
                                {{ $t('Functie in het Engels') }}
                            </Label>
                            <p v-if="formulier.role_nl" class="brand-bron">
                                {{ formulier.role_nl }}
                            </p>
                            <Input
                                id="role_en"
                                v-model="formulier.role_en"
                                :disabled="vertaalt"
                            />
                            <InputError :message="fouten.role_en" />
                            <p class="text-xs text-muted-foreground">
                                {{
                                    $t(
                                        'Laat je dit leeg, dan staat de Nederlandse functietitel op de Engelse site.',
                                    )
                                }}
                            </p>
                        </div>

                        <div v-if="formulier.location_nl" class="grid gap-2">
                            <Label for="location_en">
                                {{ $t('Plaats in het Engels') }}
                            </Label>
                            <p class="brand-bron">
                                {{ formulier.location_nl }}
                            </p>
                            <Input
                                id="location_en"
                                v-model="formulier.location_en"
                                :disabled="vertaalt"
                            />
                            <InputError :message="fouten.location_en" />
                        </div>

                        <div v-if="formulier.description_nl" class="grid gap-2">
                            <Label for="description_en">
                                {{ $t('Beschrijving in het Engels') }}
                            </Label>
                            <p class="brand-bron">
                                {{ formulier.description_nl }}
                            </p>
                            <Textarea
                                id="description_en"
                                v-model="formulier.description_en"
                                :disabled="vertaalt"
                            />
                            <InputError :message="fouten.description_en" />
                        </div>

                        <!--
                            Staan er geen optionele Nederlandse teksten, dan
                            is er ook niets om te vertalen behalve de functie.
                            Dan hoort daar één regel te staan in plaats van
                            een scherm met lege velden.
                        -->
                        <p
                            v-if="
                                !formulier.location_nl &&
                                !formulier.description_nl
                            "
                            class="text-sm text-muted-foreground"
                        >
                            {{
                                $t(
                                    'Meer valt er niet te vertalen: je hebt geen plaats en geen beschrijving ingevuld.',
                                )
                            }}
                        </p>

                        <!--
                            De laadstaat van de vertaalknop.

                            Een vertaling gaat naar een dienst buiten de deur
                            en kan een paar seconden duren. Zonder dit gebeurt
                            er in die seconden zichtbaar niets, en dan drukt
                            de klant nog een keer. De laag ligt over de velden
                            heen, want die zijn straks anders -- en zolang dat
                            nog niet zo is, valt er niets te typen.
                        -->
                        <Transition name="brand-vertaalt">
                            <div v-if="vertaalt" class="brand-vertaalt">
                                <Loader2 class="size-5 animate-spin" />
                                <p class="text-sm font-medium">
                                    {{ $t('Bezig met vertalen…') }}
                                    <span
                                        v-if="seconden > 2"
                                        class="text-muted-foreground tabular-nums"
                                    >
                                        {{ seconden }}s
                                    </span>
                                </p>

                                <!--
                                    De tekst loopt mee met hoe lang het
                                    duurt en met hoeveel er te vertalen is.
                                    Een laadschermpje dat de hele tijd
                                    hetzelfde zegt, leest bij een lange
                                    tekst als vastgelopen.
                                -->
                                <p
                                    class="max-w-xs text-xs text-pretty text-muted-foreground"
                                >
                                    {{ wachtTekst }}
                                </p>

                                <span
                                    class="brand-vertaalt-balk"
                                    aria-hidden="true"
                                />
                            </div>
                        </Transition>
                    </div>
                </Transition>
            </div>

            <DialogFooter class="gap-2 sm:justify-between sm:gap-2">
                <Button
                    v-if="stap === 2"
                    variant="ghost"
                    class="gap-2"
                    :disabled="bezig"
                    @click="naarStap(1)"
                >
                    <ArrowLeft class="size-4" />
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
                    <!--
                        Dezelfde knop doet twee dingen, dus draagt hij ook
                        twee kleuren: blauw als je iets nieuws toevoegt,
                        oker als je iets aanpast dat al op de website staat.
                    -->
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
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
