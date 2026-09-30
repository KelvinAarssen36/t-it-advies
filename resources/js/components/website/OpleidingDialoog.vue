<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ArrowRight, Check, Languages, Loader2 } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import LocaleFlag from '@/components/LocaleFlag.vue';
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
import {
    bevestigAanmaken,
    bevestigBewerken,
    bevestigVerwijderen,
} from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import website from '@/routes/website';
import certificaten from '@/routes/website/certificaten';
import type { CertificatenOpties, OpleidingRij } from '@/types/certificaten';

/**
 * Een opleiding aanmaken of bewerken.
 *
 * Hetzelfde venster als voor een certificaat, maar een stuk kleiner: dit
 * is een regel in een lijstje en geen tegel in een etalage. Geen logo,
 * geen nummer, geen lang verhaal -- alleen de opleiding, waar, op welk
 * niveau en wanneer.
 *
 * Nog steeds twee stappen, en om dezelfde reden als overal: opslaan aan
 * het eind van stap 1 zou een half ingevulde opleiding live zetten.
 *
 * Zie docs/architecture/modules/certificaten.md.
 */
const props = defineProps<{
    item: OpleidingRij | null;
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
    institution: '',
    level_nl: '',
    level_en: '',
    started_on: '',
    ended_on: '',
});

/** Of de klant hem meteen online wil; alleen bij een nieuwe opleiding. */
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
    [formulier.value.title_en, formulier.value.level_en].join('\u0000');

const vulIn = (): void => {
    const item = props.item;

    formulier.value = {
        published: item?.published ?? true,
        title_nl: item?.title_nl ?? '',
        title_en: item?.title_en ?? '',
        institution: item?.institution ?? '',
        level_nl: item?.level_nl ?? '',
        level_en: item?.level_en ?? '',
        started_on: item?.started_on ?? '',
        ended_on: item?.ended_on ?? '',
    };

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

const veldenVanStap1 = [
    'title_nl',
    'institution',
    'level_nl',
    'started_on',
    'ended_on',
];

const hoortBijStap1 = (veld: string): boolean => veldenVanStap1.includes(veld);

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

        if (typeof velden.niveau_en === 'string') {
            formulier.value.level_en = velden.niveau_en;
        }

        automatisch.value = true;
        vertaalFout.value = null;
    });
});

onBeforeUnmount(() => stopLuisteren?.());

const kanVertalen = computed(
    () => props.kanVertalen && formulier.value.title_nl.trim() !== '',
);

const eigenEngels = (): boolean =>
    !automatisch.value &&
    [formulier.value.title_en, formulier.value.level_en].some(
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
            niveau_nl: formulier.value.level_nl,
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
            titel: t('Deze opleiding aanpassen?'),
        });
    } else {
        meteenOnline.value = true;

        akkoord = await bevestigAanmaken({
            titel: t('Deze opleiding toevoegen?'),
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
            stap.value = Object.keys(ontvangen).some(hoortBijStap1) ? 1 : 2;
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
        router.put(
            certificaten.opleidingen.update(props.item!.id).url,
            lading,
            opties,
        );

        return;
    }

    router.post(certificaten.opleidingen.store().url, lading, opties);
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
                    {{
                        bewerkt
                            ? $t('Opleiding aanpassen')
                            : $t('Nieuwe opleiding')
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Eerst het Nederlands, dan het Engels. Je slaat het in één keer op, zodat er nooit een halve opleiding op je website staat.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

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
                        <Label for="opleiding-title-nl" verplicht>
                            {{ $t('Opleiding') }}
                        </Label>
                        <Input
                            id="opleiding-title-nl"
                            v-model="formulier.title_nl"
                            maxlength="120"
                        />
                        <InputError :message="fouten.title_nl" />
                    </div>

                    <!--
                        `items-start`, anders rekken de kolommen zich tot
                        de hoogste uit en staan de twee velden scheef
                        naast elkaar zodra er onder de ene wél een regel
                        uitleg staat en onder de andere niet.
                    -->
                    <div class="grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="opleiding-instelling" verplicht>
                                {{ $t('Instelling') }}
                            </Label>
                            <Input
                                id="opleiding-instelling"
                                v-model="formulier.institution"
                                maxlength="120"
                                placeholder="Avans Hogeschool"
                            />
                            <InputError :message="fouten.institution" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="opleiding-niveau">
                                {{ $t('Niveau') }}
                            </Label>
                            <Input
                                id="opleiding-niveau"
                                v-model="formulier.level_nl"
                                maxlength="60"
                                placeholder="HBO bachelor"
                            />
                            <InputError :message="fouten.level_nl" />
                        </div>
                    </div>

                    <div class="grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="opleiding-begin" verplicht>
                                {{ $t('Van') }}
                            </Label>
                            <MaandKiezer
                                id="opleiding-begin"
                                v-model="formulier.started_on"
                                :maanden="props.opties.maanden"
                                :jaren="props.opties.jaren"
                                :aria-label="$t('Van')"
                            />
                            <InputError :message="fouten.started_on" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="opleiding-eind">
                                {{ $t('Tot') }}
                            </Label>
                            <MaandKiezer
                                id="opleiding-eind"
                                v-model="formulier.ended_on"
                                :maanden="props.opties.maanden"
                                :jaren="props.opties.jaren"
                                :aria-label="$t('Tot')"
                                wisbaar
                            />
                            <InputError :message="fouten.ended_on" />
                        </div>
                    </div>

                    <p class="-mt-1 text-xs text-pretty text-muted-foreground">
                        {{
                            $t(
                                'De instelling vertalen we niet -- een eigennaam is in beide talen hetzelfde. Laat "tot" leeg als je er nog mee bezig bent; dan staat er "heden".',
                            )
                        }}
                    </p>
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
                                'Laat je de opleiding leeg, dan staat de Nederlandse er ook voor Engelse bezoekers. Een leeg niveau valt niet terug; dan staat er gewoon geen.',
                            )
                        }}
                    </p>

                    <div class="grid gap-2">
                        <Label for="opleiding-title-en">
                            <LocaleFlag locale="en" size="sm" />
                            {{ $t('Opleiding') }}
                        </Label>
                        <p class="brand-bron">
                            {{ formulier.title_nl || $t('Nog niets ingevuld') }}
                        </p>
                        <Input
                            id="opleiding-title-en"
                            v-model="formulier.title_en"
                            maxlength="120"
                        />
                        <InputError :message="fouten.title_en" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="opleiding-niveau-en">
                            <LocaleFlag locale="en" size="sm" />
                            {{ $t('Niveau') }}
                        </Label>
                        <p class="brand-bron">
                            {{ formulier.level_nl || $t('Nog niets ingevuld') }}
                        </p>
                        <Input
                            id="opleiding-niveau-en"
                            v-model="formulier.level_en"
                            maxlength="60"
                        />
                        <InputError :message="fouten.level_en" />
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
