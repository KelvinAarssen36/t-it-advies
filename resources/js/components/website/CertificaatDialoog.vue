<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ArrowRight, Check, Languages, Loader2 } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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
import { Textarea } from '@/components/ui/textarea';
import {
    bevestigAanmaken,
    bevestigBewerken,
    bevestigVerwijderen,
} from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import website from '@/routes/website';
import certificaten from '@/routes/website/certificaten';
import type { CertificaatRij, CertificatenOpties } from '@/types/certificaten';

/**
 * Een certificaat aanmaken of bewerken.
 *
 * **Twee stappen, één opslag.** Stap 1 is het Nederlands met het logo en
 * de datums, stap 2 het Engels met de Nederlandse bron erboven. Opslaan
 * aan het eind van stap 1 zou een half ingevuld certificaat live zetten;
 * dezelfde afweging als bij DienstDialoog.
 *
 * **De uitgever en het nummer staan maar één keer**, in stap 1. Het
 * eerste is een eigennaam en het tweede een code; die twee keer laten
 * invullen levert alleen kans op verschil op.
 *
 * Zie docs/architecture/modules/certificaten.md.
 */
const props = defineProps<{
    item: CertificaatRij | null;
    opties: CertificatenOpties;
    kanVertalen: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const bewerkt = computed(() => props.item !== null);

const stap = ref(1);
const bezig = ref(false);
const fouten = ref<Record<string, string>>({});

const formulier = ref({
    published: true,
    title_nl: '',
    title_en: '',
    issuer: '',
    issued_on: '',
    expires_on: '',
    credential_id: '',
    body_nl: '',
    body_en: '',
});

/* --- Het logo ---------------------------------------------------------- */

const logo = ref<File | null>(null);
const logoWeg = ref(false);
const logoZoom = ref(1);
const logoX = ref(0);
const logoY = ref(0);
const logoPlaat = ref(true);

/** Of de klant hem meteen online wil; alleen bij een nieuw certificaat. */
const meteenOnline = ref(true);

/**
 * Het Engels zoals het bij het openen van het venster stond.
 *
 * Hiermee bepalen we of het merkje "automatisch vertaald" nog klopt. Zou
 * je alleen naar de vlag uit de database kijken, dan verdwijnt dat
 * merkje bij elke opslag -- ook als de klant het Engels niet aanraakte.
 */
let engelsBijOpenen = '';

const automatisch = ref(false);
const vertaalt = ref(false);
const vertaalFout = ref<string | null>(null);

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
    [formulier.value.title_en, formulier.value.body_en].join('\u0000');

const vulIn = (): void => {
    const item = props.item;

    formulier.value = {
        published: item?.published ?? true,
        title_nl: item?.title_nl ?? '',
        title_en: item?.title_en ?? '',
        issuer: item?.issuer ?? '',
        issued_on: item?.issued_on ?? '',
        expires_on: item?.expires_on ?? '',
        credential_id: item?.credential_id ?? '',
        body_nl: item?.body_nl ?? '',
        body_en: item?.body_en ?? '',
    };

    logo.value = null;
    logoWeg.value = false;
    logoZoom.value = 1;
    logoX.value = 0;
    logoY.value = 0;
    logoPlaat.value = true;

    engelsBijOpenen = engelsNu();
    automatisch.value = item?.automatisch_vertaald ?? false;
    meteenOnline.value = true;
    vertaalFout.value = null;
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
const veldenVanStap1 = [
    'logo',
    'title_nl',
    'issuer',
    'issued_on',
    'expires_on',
    'credential_id',
    'body_nl',
];

const hoortBijStap1 = (veld: string): boolean =>
    veldenVanStap1.includes(veld) || veld.startsWith('logo_');

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
            formulier.value.title_en = velden.title_en;
        }

        if (typeof velden.body_en === 'string') {
            formulier.value.body_en = velden.body_en;
        }

        automatisch.value = true;
        vertaalFout.value = null;
    });
});

onBeforeUnmount(() => stopLuisteren?.());

/** Er valt pas iets te vertalen als er Nederlandse tekst staat. */
const kanVertalen = computed(
    () => props.kanVertalen && formulier.value.title_nl.trim() !== '',
);

/**
 * Staat er Engels dat de knop zou overschrijven?
 *
 * Wat de vertaaldienst zelf heeft neergezet telt niet mee: dat opnieuw
 * laten vertalen kost de klant niets.
 */
const eigenEngels = (): boolean =>
    !automatisch.value &&
    [formulier.value.title_en, formulier.value.body_en].some(
        (waarde) => waarde.trim() !== '',
    );

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
    /*
     * Het venster gaat dicht vóór de bevestiging in plaats van eronder
     * te liggen; zie de toelichting in pages/website/Indeling.vue.
     */
    open.value = false;
    await adem();

    let akkoord: boolean;

    if (bewerkt.value) {
        akkoord = await bevestigBewerken({
            titel: t('Dit certificaat aanpassen?'),
        });
    } else {
        meteenOnline.value = true;

        akkoord = await bevestigAanmaken({
            titel: t('Dit certificaat toevoegen?'),
            keuze: {
                model: meteenOnline,
                label: t('Meteen op je website zetten'),
                tekst: t(
                    'Zet je het uit, dan staat het hier klaar en zie je het pas op je site als je het online zet.',
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
        logo: logo.value,
        logo_verwijderen: logoWeg.value,
        logo_zoom: logoZoom.value,
        logo_x: logoX.value,
        logo_y: logoY.value,
        logo_plaat: logoPlaat.value,
        machine_translated: automatisch.value && engelsOngemoeid,
    };

    const opties = {
        preserveScroll: true,

        /*
         * Er zit een bestand bij, dus het verzoek gaat als
         * `multipart/form-data`. Bij een PUT slikken de meeste servers
         * dat niet; Inertia lost dat op met `_method`, mits je het
         * vraagt. Zie ErvaringDialoog, waar hetzelfde speelt.
         */
        forceFormData: true,

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
     * router.put : router.post`. Dat laatste ging bij de diensten mis:
     * `router.put` losgetrokken van `router` verliest zijn `this`, dus
     * de aanroep viel stil en er werd niets aangemaakt -- zonder
     * foutmelding, want er vertrok ook geen verzoek.
     */
    if (bewerkt.value) {
        router.post(
            certificaten.update(props.item!.id).url,
            { ...lading, _method: 'put' },
            opties,
        );

        return;
    }

    router.post(certificaten.store().url, lading, opties);
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
                            ? $t('Certificaat aanpassen')
                            : $t('Nieuw certificaat')
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Eerst het Nederlands, dan het Engels. Je slaat het in één keer op, zodat er nooit een half certificaat op je website staat.',
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
                    <div class="grid gap-2">
                        <Label for="certificaat-title-nl" verplicht>
                            {{ $t('Naam van het certificaat') }}
                        </Label>
                        <Input
                            id="certificaat-title-nl"
                            v-model="formulier.title_nl"
                            maxlength="120"
                        />
                        <InputError :message="fouten.title_nl" />
                    </div>

                    <!--
                        Twee kolommen, en `items-start` is hier geen
                        versiering. Zonder dat rekken de kolommen zich
                        tot de hoogste uit: staat er onder de ene een
                        regel uitleg en onder de andere niet, dan schuift
                        het label van die andere naar beneden en staan de
                        twee velden scheef naast elkaar.
                    -->
                    <div class="grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="certificaat-issuer" verplicht>
                                {{ $t('Uitgever') }}
                            </Label>
                            <Input
                                id="certificaat-issuer"
                                v-model="formulier.issuer"
                                maxlength="120"
                                placeholder="Microsoft"
                            />
                            <InputError :message="fouten.issuer" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="certificaat-nummer">
                                {{ $t('Certificaatnummer') }}
                            </Label>
                            <Input
                                id="certificaat-nummer"
                                v-model="formulier.credential_id"
                                maxlength="120"
                                placeholder="AZ-104-1234567"
                            />
                            <InputError :message="fouten.credential_id" />
                        </div>
                    </div>

                    <div class="grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="certificaat-behaald" verplicht>
                                {{ $t('Behaald in') }}
                            </Label>
                            <MaandKiezer
                                id="certificaat-behaald"
                                v-model="formulier.issued_on"
                                :maanden="props.opties.maanden"
                                :jaren="props.opties.jaren"
                                :aria-label="$t('Behaald in')"
                            />
                            <InputError :message="fouten.issued_on" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="certificaat-geldig">
                                {{ $t('Geldig tot') }}
                            </Label>
                            <MaandKiezer
                                id="certificaat-geldig"
                                v-model="formulier.expires_on"
                                :maanden="props.opties.maanden"
                                :jaren="props.opties.jarenVooruit"
                                :aria-label="$t('Geldig tot')"
                                wisbaar
                            />
                            <InputError :message="fouten.expires_on" />
                        </div>
                    </div>

                    <p class="-mt-1 text-xs text-pretty text-muted-foreground">
                        {{
                            $t(
                                'De uitgever en het nummer vertalen we niet -- een merknaam en een code zijn in beide talen hetzelfde. Geldig tot mag leeg blijven als het certificaat niet verloopt.',
                            )
                        }}
                    </p>

                    <div class="grid gap-2">
                        <Label for="certificaat-body-nl">
                            {{ $t('Toelichting') }}
                        </Label>
                        <Textarea
                            id="certificaat-body-nl"
                            v-model="formulier.body_nl"
                            rows="4"
                            maxlength="2000"
                        />
                        <p class="text-xs text-pretty text-muted-foreground">
                            {{
                                $t(
                                    'Optioneel. Vul je hem in, dan kan een bezoeker op het certificaat klikken en opent er een venster met deze tekst.',
                                )
                            }}
                        </p>
                        <InputError :message="fouten.body_nl" />
                    </div>

                    <!-- ----------------------- Het logo -------------- -->
                    <div class="grid gap-2 border-t border-border pt-4">
                        <Label>{{ $t('Logo van de uitgever') }}</Label>

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
                        <p class="text-xs text-pretty text-muted-foreground">
                            {{
                                $t(
                                    'Zet je er geen, dan komt er een vast tekentje te staan.',
                                )
                            }}
                        </p>
                    </div>
                </div>

                <!-- ------------------- Stap 2: Engels ----------------- -->
                <div
                    v-else
                    key="stap-2"
                    class="brand-formulier-lijst brand-scrollbar"
                >
                    <p class="text-xs text-pretty text-muted-foreground">
                        {{
                            $t(
                                'Laat je de naam leeg, dan staat de Nederlandse er ook voor Engelse bezoekers -- vaak precies goed, want die is meestal al Engels. Een lege toelichting valt niet terug; dan staat er gewoon geen.',
                            )
                        }}
                    </p>

                    <div class="grid gap-2">
                        <Label for="certificaat-title-en">
                            <LocaleFlag locale="en" size="sm" />
                            {{ $t('Naam van het certificaat') }}
                        </Label>
                        <!--
                            De Nederlandse tekst erboven, zodat je niet
                            hoeft terug te bladeren om te weten wat je
                            aan het vertalen bent.
                        -->
                        <p class="brand-bron">
                            {{ formulier.title_nl || $t('Nog niets ingevuld') }}
                        </p>
                        <Input
                            id="certificaat-title-en"
                            v-model="formulier.title_en"
                            maxlength="120"
                        />
                        <InputError :message="fouten.title_en" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="certificaat-body-en">
                            <LocaleFlag locale="en" size="sm" />
                            {{ $t('Toelichting') }}
                        </Label>
                        <p class="brand-bron">
                            {{ formulier.body_nl || $t('Nog niets ingevuld') }}
                        </p>
                        <Textarea
                            id="certificaat-body-en"
                            v-model="formulier.body_en"
                            rows="4"
                            maxlength="2000"
                        />
                        <InputError :message="fouten.body_en" />
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
            </Transition>

            <!--
                Dezelfde voet als bij een dienst: "Terug" links zodra je
                in stap 2 zit, de rest rechts. Hier stond een badge met
                de naam van het certificaat erin; die is eruit, want de
                andere vensters hebben hem niet en hij zei niets wat er
                niet al twee keer stond.
            -->
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
                            :disabled="bezig"
                            @click="opslaan"
                        >
                            <Loader2 v-if="bezig" class="size-4 animate-spin" />
                            {{ bewerkt ? $t('Opslaan') : $t('Toevoegen') }}
                        </Button>
                    </div>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
