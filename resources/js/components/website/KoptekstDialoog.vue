<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Languages, Loader2 } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
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
import { bevestigBewerken, bevestigVerwijderen } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import website from '@/routes/website';
import type { KoptekstRij } from '@/types/secties';

/**
 * De kop boven een onderdeel: het opschrift, de titel en de zin eronder.
 *
 * **Eén venster voor alle onderdelen.** Dit stond drie keer bijna
 * identiek in evenveel bestanden -- de landingspagina, de diensten en de
 * tijdlijn -- en met de certificaten erbij zou het vier keer worden. Het
 * is de tegenhanger van wat er in de database gebeurde: daar werden drie
 * koptabellen één `section_headings`. Zie docs/architecture/kopteksten.md.
 *
 * **Waarom dit één venster is en niet drie stappen.** Op de website
 * staan de drie teksten in hetzelfde blok. Ze los laten bewerken zou
 * betekenen dat de eigenaar drie keer bevestigt voor één zichtbare
 * verandering, en dat er een tussenstand kan bestaan waarin een deel
 * live staat.
 *
 * Het Engels staat eronder en niet in een tweede stap zoals bij een
 * ervaring: het zijn drie korte regels, en een stap ervoor bouwen kost
 * meer klikken dan het scheelt.
 *
 * De aanroeper zegt vier dingen: waar het heen moet, hoe het venster
 * heet, wat er in de bevestiging komt te staan, en of het opschrift
 * verplicht is. Dat laatste verschilt echt per onderdeel -- de tijdlijn
 * heeft nooit een opschrift gehad.
 */
const props = withDefaults(
    defineProps<{
        /** De rij zoals de server hem meestuurt. */
        kop: KoptekstRij;
        /** Waar de PUT heen gaat, bijvoorbeeld `diensten.kop().url`. */
        actie: string;
        /** De titel van dit venster. */
        titel: string;
        /** De zin eronder, die zegt waar deze kop op de site staat. */
        uitleg: string;
        /** Wat er in de bevestiging staat: ":naam aanpassen?" */
        bevestiging: string;
        /**
         * Voor de `id`-attributen van de velden. Twee vensters op
         * dezelfde pagina zouden anders dezelfde `id` krijgen, en dan
         * wijst het label van het ene naar het veld van het andere.
         */
        sleutel: string;
        kanVertalen: boolean;
        /** Heeft dit onderdeel een opschrift, en is dat verplicht? */
        opschrift?: boolean;
    }>(),
    { opschrift: true },
);

const open = defineModel<boolean>('open', { required: true });

const bezig = ref(false);
const fouten = ref<Record<string, string>>({});

/** De zes velden, altijd als tekst zodat een leeg veld "" is. */
const tekst = ref({
    eyebrow_nl: '',
    eyebrow_en: '',
    title_nl: '',
    title_en: '',
    intro_nl: '',
    intro_en: '',
});

/**
 * Het Engels zoals het bij het openen van het venster stond.
 *
 * Hiermee bepalen we of het merkje "automatisch vertaald" nog klopt. Zou
 * je alleen naar de vlag uit de database kijken, dan verdwijnt dat
 * merkje bij elke opslag -- ook als de klant het Engels niet aanraakte.
 */
let engelsBijOpenen = { eyebrow_en: '', title_en: '', intro_en: '' };

const automatisch = ref(false);
const vertaalt = ref(false);
const vertaalFout = ref<string | null>(null);

/**
 * Of het venster zichzelf opnieuw opent en de inhoud dus moet blijven.
 *
 * Het venster gaat dicht en weer open bij een bevestiging die de klant
 * afbreekt en bij een fout van de server. Zonder deze vlag vult het zich
 * dan opnieuw uit de props -- en dan is zijn invoer weg **en** wordt de
 * foutmelding die net was gezet meteen weer gewist.
 */
let behoudInhoud = false;

const openOpnieuw = (): void => {
    behoudInhoud = true;
    open.value = true;
};

const vulIn = (): void => {
    tekst.value = {
        eyebrow_nl: props.kop.eyebrow_nl ?? '',
        eyebrow_en: props.kop.eyebrow_en ?? '',
        title_nl: props.kop.title_nl,
        title_en: props.kop.title_en ?? '',
        intro_nl: props.kop.intro_nl ?? '',
        intro_en: props.kop.intro_en ?? '',
    };

    engelsBijOpenen = {
        eyebrow_en: tekst.value.eyebrow_en,
        title_en: tekst.value.title_en,
        intro_en: tekst.value.intro_en,
    };

    automatisch.value = props.kop.automatisch_vertaald;
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

/* --- Automatisch vertalen --------------------------------------------- */

let stopLuisteren: (() => void) | undefined;

onMounted(() => {
    stopLuisteren = router.on('flash', (gebeurtenis) => {
        const velden = (gebeurtenis as CustomEvent).detail?.flash?.vertaling as
            | Record<string, string>
            | undefined;

        if (!velden || !open.value) {
            return;
        }

        if (typeof velden.eyebrow_en === 'string') {
            tekst.value.eyebrow_en = velden.eyebrow_en;
        }

        if (typeof velden.title_en === 'string') {
            tekst.value.title_en = velden.title_en;
        }

        if (typeof velden.intro_en === 'string') {
            tekst.value.intro_en = velden.intro_en;
        }

        automatisch.value = true;
        vertaalFout.value = null;
    });
});

onBeforeUnmount(() => stopLuisteren?.());

/**
 * Staat er Engels dat de knop zou overschrijven?
 *
 * Wat de vertaaldienst zelf heeft neergezet telt niet mee: dat opnieuw
 * laten vertalen kost de klant niets.
 */
const eigenEngels = (): boolean =>
    !automatisch.value &&
    [tekst.value.eyebrow_en, tekst.value.title_en, tekst.value.intro_en].some(
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
            eyebrow_nl: tekst.value.eyebrow_nl,
            title_nl: tekst.value.title_nl,
            intro_nl: tekst.value.intro_nl,
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

    const akkoord = await bevestigBewerken({ titel: props.bevestiging });

    if (!akkoord) {
        await adem();
        openOpnieuw();

        return;
    }

    bezig.value = true;

    const engelsOngemoeid =
        tekst.value.eyebrow_en === engelsBijOpenen.eyebrow_en &&
        tekst.value.title_en === engelsBijOpenen.title_en &&
        tekst.value.intro_en === engelsBijOpenen.intro_en;

    router.put(
        props.actie,
        {
            ...tekst.value,
            automatisch_vertaald: automatisch.value && engelsOngemoeid,
        },
        {
            preserveScroll: true,
            onError: (ontvangen) => {
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
                <DialogTitle>{{ props.titel }}</DialogTitle>
                <DialogDescription>{{ props.uitleg }}</DialogDescription>
            </DialogHeader>

            <div class="grid gap-4">
                <div v-if="props.opschrift" class="grid gap-1.5">
                    <Label :for="`${props.sleutel}-eyebrow-nl`" verplicht>
                        <LocaleFlag locale="nl" size="sm" />
                        {{ $t('Opschrift') }}
                    </Label>
                    <Input
                        :id="`${props.sleutel}-eyebrow-nl`"
                        v-model="tekst.eyebrow_nl"
                        maxlength="60"
                    />
                    <InputError :message="fouten.eyebrow_nl" />
                </div>

                <div class="grid gap-1.5">
                    <Label :for="`${props.sleutel}-title-nl`" verplicht>
                        <LocaleFlag locale="nl" size="sm" />
                        {{ $t('Titel') }}
                    </Label>
                    <Input
                        :id="`${props.sleutel}-title-nl`"
                        v-model="tekst.title_nl"
                        maxlength="120"
                    />
                    <InputError :message="fouten.title_nl" />
                </div>

                <div class="grid gap-1.5">
                    <Label :for="`${props.sleutel}-intro-nl`">
                        <LocaleFlag locale="nl" size="sm" />
                        {{ $t('Zin eronder') }}
                    </Label>
                    <Input
                        :id="`${props.sleutel}-intro-nl`"
                        v-model="tekst.intro_nl"
                        maxlength="300"
                    />
                    <p class="text-xs text-muted-foreground">
                        {{
                            $t(
                                'Laat leeg als je alleen een titel wilt; dan staat er gewoon geen zin onder.',
                            )
                        }}
                    </p>
                    <InputError :message="fouten.intro_nl" />
                </div>

                <div
                    v-if="props.opschrift"
                    class="grid gap-1.5 border-t border-border pt-4"
                >
                    <Label :for="`${props.sleutel}-eyebrow-en`">
                        <LocaleFlag locale="en" size="sm" />
                        {{ $t('Opschrift') }}
                    </Label>
                    <Input
                        :id="`${props.sleutel}-eyebrow-en`"
                        v-model="tekst.eyebrow_en"
                        maxlength="60"
                    />
                    <InputError :message="fouten.eyebrow_en" />
                </div>

                <div
                    class="grid gap-1.5"
                    :class="
                        props.opschrift ? '' : 'border-t border-border pt-4'
                    "
                >
                    <Label :for="`${props.sleutel}-title-en`">
                        <LocaleFlag locale="en" size="sm" />
                        {{ $t('Titel') }}
                    </Label>
                    <Input
                        :id="`${props.sleutel}-title-en`"
                        v-model="tekst.title_en"
                        maxlength="120"
                    />
                    <InputError :message="fouten.title_en" />
                </div>

                <div class="grid gap-1.5">
                    <Label :for="`${props.sleutel}-intro-en`">
                        <LocaleFlag locale="en" size="sm" />
                        {{ $t('Zin eronder') }}
                    </Label>
                    <Input
                        :id="`${props.sleutel}-intro-en`"
                        v-model="tekst.intro_en"
                        maxlength="300"
                    />
                    <InputError :message="fouten.intro_en" />
                </div>

                <div v-if="props.kanVertalen" class="grid gap-2">
                    <div>
                        <Button
                            variant="outline"
                            size="sm"
                            class="gap-2"
                            :disabled="vertaalt || tekst.title_nl.trim() === ''"
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
                        variant="bewerken"
                        class="gap-2"
                        :disabled="bezig"
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
