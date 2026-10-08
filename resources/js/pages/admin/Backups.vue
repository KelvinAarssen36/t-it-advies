<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Archive,
    CloudOff,
    Download,
    Pin,
    PinOff,
    RotateCcw,
    ShieldCheck,
    Trash2,
    TriangleAlert,
    Upload,
} from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import BackupCodeDialoog from '@/components/admin/BackupCodeDialoog.vue';
import BackupRegel from '@/components/admin/BackupRegel.vue';
import BackupVolDialoog from '@/components/admin/BackupVolDialoog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { bevestigAanmaken } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/admin';
import backups from '@/routes/admin/backups';
import type { BackupCijfers, BackupRij, Verschil } from '@/types/backups';

/**
 * Beheer → Back-ups.
 *
 * **Dit is geen volledige back-up van je hosting, en dat moet op het
 * scherm staan.** Hier gaat de inhoud van de website in: de teksten, de
 * indeling en de beelden. Níet het account en níet de berichten van
 * bezoekers -- die hebben hun eigen bewaartermijn en horen niet in een
 * bestand zonder klok.
 *
 * De eigenaar leest "back-up" en denkt "alles". Daarom staat dat verschil
 * boven de lijst in gewone taal, en niet alleen in de handleiding.
 *
 * Zie docs/operations/back-ups.md.
 */
const props = defineProps<{
    /** Alleen je eigen back-ups: zelf gemaakt of teruggeplaatst. */
    backups: BackupRij[];
    /** De veiligheidskopieën van vlak vóór een terugzetting. */
    kopieen: BackupRij[];
    cijfers: BackupCijfers;
    verschil?: Verschil | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beheer', href: dashboard() },
            { title: 'Back-ups' },
        ],
        breed: true,
    },
});

const bezig = ref(false);
const vensterOpen = ref(false);
const gekozen = ref<BackupRij | null>(null);
const actie = ref<'terugzetten' | 'verwijderen'>('terugzetten');
const verschilLaadt = ref(false);
const bestandKiezer = ref<HTMLInputElement | null>(null);

/** Wat er mag wijken als de lijst vol is: eigen, en niet vastgezet. */
const keuzes = computed(() =>
    props.backups.filter((rij) => rij.eigen && !rij.vastgezet),
);

/**
 * Wat er gebeurt zodra de lijst vol is.
 *
 * Eerst verdween de oudste vanzelf. Nu kiest de eigenaar; `wachtendeActie`
 * onthoudt wat hij wilde doen terwijl hij dat kiest.
 */
const volOpen = ref(false);
const wachtendeActie = ref<'maken' | 'uploaden' | 'opruimen' | null>(null);
const wachtendBestand = ref<File | null>(null);
const volFouten = ref<Record<string, string>>({});

/** Hoeveel er gekozen moeten worden: het teveel, of anders één. */
const aantalNodig = computed(() =>
    props.cijfers.teveel > 0 ? props.cijfers.teveel : 1,
);

/*
 * Staan er te veel, dan komt het venster ongevraagd open. Dat hoort niet
 * te kunnen -- maar het kán: zet er twee vast terwijl je er vijf hebt en
 * laat ze daarna los, en je staat op zeven. Stil laten staan zou
 * betekenen dat de eigenaar een grens leest die niet geldt.
 */
onMounted(() => {
    if (props.cijfers.teveel > 0) {
        wachtendeActie.value = 'opruimen';
        volOpen.value = true;
    }
});

const maken = async (): Promise<void> => {
    const akkoord = await bevestigAanmaken({
        titel: t('Een back-up maken van je website?'),
        tekst: t(
            'Alles wat je zelf hebt ingevuld wordt vastgelegd, inclusief je afbeeldingen. Je account en de berichten van bezoekers gaan er niet in.',
        ),
    });

    if (!akkoord) {
        return;
    }

    if (props.cijfers.vol) {
        wachtendeActie.value = 'maken';
        volOpen.value = true;

        return;
    }

    verstuurMaken();
};

const verstuurMaken = (vervang?: number[], code?: string): void => {
    bezig.value = true;

    router.post(
        backups.store().url,
        vervang === undefined ? {} : { vervang, code },
        {
            preserveScroll: true,
            onError: (ontvangen: Record<string, string>) => {
                volFouten.value = ontvangen;
            },
            onSuccess: () => {
                volOpen.value = false;
            },
            onFinish: () => {
                bezig.value = false;
                wachtendeActie.value = null;
            },
        },
    );
};

/** De keuze is gemaakt: doorgaan met wat de eigenaar wilde. */
const vervangEnGaDoor = (ids: number[], code: string): void => {
    volFouten.value = {};

    if (wachtendeActie.value === 'opruimen') {
        verstuurOpruimen(ids, code);

        return;
    }

    if (wachtendeActie.value === 'uploaden') {
        verstuurUpload(wachtendBestand.value, ids, code);

        return;
    }

    verstuurMaken(ids, code);
};

/** Alleen opruimen: er komt geen nieuwe back-up achteraan. */
const verstuurOpruimen = (ids: number[], code: string): void => {
    bezig.value = true;

    router.post(
        backups.opruimen().url,
        { ids, code },
        {
            preserveScroll: true,
            onError: (ontvangen: Record<string, string>) => {
                volFouten.value = ontvangen;
            },
            onSuccess: () => {
                volOpen.value = false;
            },
            onFinish: () => {
                bezig.value = false;
                wachtendeActie.value = null;
            },
        },
    );
};

const controleren = (rij: BackupRij): void => {
    bezig.value = true;

    router.post(
        backups.controleer(rij.id).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                bezig.value = false;
            },
        },
    );
};

const vastzetten = (rij: BackupRij): void => {
    router.put(
        backups.vastzetten(rij.id).url,
        { vastgezet: !rij.vastgezet },
        { preserveScroll: true },
    );
};

/**
 * Het venster openen, en het verschil erbij halen.
 *
 * Dat verschil wordt pas nú berekend en niet bij het laden van de lijst:
 * het zijn negentien tellingen per back-up, en niemand ziet die cijfers
 * tot dit venster opengaat.
 */
const openVenster = (
    rij: BackupRij,
    welke: 'terugzetten' | 'verwijderen',
): void => {
    gekozen.value = rij;
    actie.value = welke;
    vensterOpen.value = true;

    if (welke !== 'terugzetten') {
        return;
    }

    verschilLaadt.value = true;

    router.reload({
        only: ['verschil'],
        data: { verschil: rij.id },
        onFinish: () => {
            verschilLaadt.value = false;
        },
    });
};

const uploaden = (gebeurtenis: Event): void => {
    const invoer = gebeurtenis.target as HTMLInputElement;
    const bestand = invoer.files?.[0];

    invoer.value = '';

    if (!bestand) {
        return;
    }

    if (props.cijfers.vol) {
        wachtendBestand.value = bestand;
        wachtendeActie.value = 'uploaden';
        volOpen.value = true;

        return;
    }

    verstuurUpload(bestand);
};

const verstuurUpload = (
    bestand: File | null,
    vervang?: number[],
    code?: string,
): void => {
    if (!bestand) {
        return;
    }

    bezig.value = true;

    router.post(
        backups.upload().url,
        vervang === undefined ? { bestand } : { bestand, vervang, code },
        {
            preserveScroll: true,
            forceFormData: true,
            onError: (ontvangen: Record<string, string>) => {
                volFouten.value = ontvangen;
            },
            onSuccess: () => {
                volOpen.value = false;
            },
            onFinish: () => {
                bezig.value = false;
                wachtendeActie.value = null;
                wachtendBestand.value = null;
            },
        },
    );
};
</script>

<template>
    <Head :title="$t('Back-ups')" />

    <div class="flex flex-col gap-6 p-4">
        <header class="max-w-2xl space-y-1">
            <h1 class="text-xl font-semibold tracking-tight">
                {{ $t('Back-ups') }}
            </h1>
            <p class="text-sm text-pretty text-muted-foreground">
                {{
                    $t(
                        'Leg vast hoe je website er nu voor staat, zodat je er altijd naar terug kunt. Je teksten, je indeling en je afbeeldingen gaan mee.',
                    )
                }}
            </p>
            <!--
                Dit moet er staan. "Back-up" leest als "alles", en dan
                rekent de eigenaar op iets wat hier niet in zit.
            -->
            <p class="text-sm text-pretty text-muted-foreground">
                {{
                    $t(
                        'Wat er niet in gaat: je account en je passkeys, en de berichten die bezoekers je hebben gestuurd. Die blijven staan zoals ze zijn, ook als je iets terugzet.',
                    )
                }}
            </p>
        </header>

        <!--
            Er staan er te veel. Dit hoort niet te kunnen, dus als het
            toch gebeurt moet het hard opvallen: een melding die blijft
            staan, plus een venster dat meteen opengaat.
        -->
        <div
            v-if="props.cijfers.teveel > 0"
            class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-destructive/40 bg-destructive/5 p-4 text-sm sm:p-5"
        >
            <p class="text-pretty">
                {{
                    $t(
                        'Je hebt :nu back-ups terwijl er :maximum mogen staan. Er moeten er :teveel weg.',
                        {
                            nu: props.cijfers.eigen,
                            maximum: props.cijfers.maximumEigen,
                            teveel: props.cijfers.teveel,
                        },
                    )
                }}
            </p>
            <Button
                variant="verwijderen"
                size="sm"
                @click="
                    () => {
                        wachtendeActie = 'opruimen';
                        volOpen = true;
                    }
                "
            >
                {{ $t('Opruimen') }}
            </Button>
        </div>

        <!-- De enige manier waarop dit ooit een echte back-up wordt. -->
        <div
            v-if="props.cijfers.nooitGedownload"
            class="flex items-start gap-3 rounded-lg border border-border bg-muted/40 p-4 text-sm sm:p-5"
        >
            <CloudOff
                class="mt-0.5 size-4 shrink-0 text-warning"
                aria-hidden="true"
            />
            <p class="text-pretty text-muted-foreground">
                {{
                    $t(
                        'Je nieuwste back-up staat alleen op dezelfde server als je website. Gaat er met die server iets mis, dan ben je allebei kwijt. Download hem en bewaar hem ergens anders.',
                    )
                }}
            </p>
        </div>

        <div
            v-if="props.cijfers.oud"
            class="flex items-start gap-3 rounded-lg border border-border bg-muted/40 p-4 text-sm sm:p-5"
        >
            <TriangleAlert
                class="mt-0.5 size-4 shrink-0 text-warning"
                aria-hidden="true"
            />
            <p class="text-pretty text-muted-foreground">
                {{
                    $t(
                        'Je nieuwste back-up is alweer een tijd geleden gemaakt. Heb je sindsdien aan je website gewerkt, maak er dan even een nieuwe.',
                    )
                }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Button variant="aanmaken" :disabled="bezig" @click="maken">
                <Archive class="size-4" />
                {{ $t('Nieuwe back-up maken') }}
            </Button>

            <!--
                Uploaden heeft geen code nodig: er wordt niets
                overschreven. Het bestand komt in de lijst te staan, en
                pas het terugzetten vraagt om de sloten.
            -->
            <Button
                variant="outline"
                :disabled="bezig"
                @click="bestandKiezer?.click()"
            >
                <Upload class="size-4" />
                {{ $t('Bestand terugplaatsen') }}
            </Button>
            <input
                ref="bestandKiezer"
                type="file"
                accept=".zip,application/zip"
                class="hidden"
                @change="uploaden"
            />

            <p class="ml-auto text-sm text-muted-foreground">
                {{
                    $t('Samen :ruimte aan ruimte', {
                        ruimte: props.cijfers.ruimte,
                    })
                }}
            </p>
        </div>

        <div class="overflow-hidden rounded-lg border border-border">
            <div v-if="props.backups.length === 0" class="p-8 text-center">
                <div
                    class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl border border-border text-muted-foreground"
                >
                    <Archive class="size-7" />
                </div>
                <p class="font-medium">{{ $t('Nog geen back-ups') }}</p>
                <p
                    class="mx-auto mt-1 max-w-sm text-sm text-pretty text-muted-foreground"
                >
                    {{
                        $t(
                            'Maak er een zodra je website staat zoals je hem wil. Daarna kun je er altijd naar terug.',
                        )
                    }}
                </p>
            </div>

            <ul v-else class="divide-y divide-border">
                <BackupRegel
                    v-for="rij in props.backups"
                    :key="rij.id"
                    :rij="rij"
                    :bezig="bezig"
                    @controleren="controleren(rij)"
                    @vastzetten="vastzetten(rij)"
                    @terugzetten="openVenster(rij, 'terugzetten')"
                    @verwijderen="openVenster(rij, 'verwijderen')"
                />
            </ul>
        </div>

        <p class="max-w-2xl text-sm text-pretty text-muted-foreground">
            {{
                $t(
                    'Je bewaart er hoogstens :aantal; je hebt er nu :nu. Zit de lijst vol, dan vraagt het portaal welke er weg mag -- er verdwijnt nooit zomaar iets. Wil je er een voor altijd houden, zet hem dan vast met het speldje; die tellen niet mee.',
                    {
                        aantal: props.cijfers.maximumEigen,
                        nu: props.cijfers.eigen,
                    },
                )
            }}
        </p>

        <!--
            De veiligheidskopieën, apart en eronder.

            **Ze stonden eerst door elkaar met de eigen back-ups**, en dan
            telt de eigenaar zeven regels terwijl er vijf mogen staan. Het
            getal klopte, de lijst vertelde iets anders.

            Dit zijn ook geen geplande back-ups: er draait niets per nacht.
            Ze ontstaan alleen vlak vóór een terugzetting. Dat staat er met
            zoveel woorden bij, want "automatisch" laat zich makkelijk
            lezen als "er wordt voor me gezorgd".
        -->
        <div v-if="props.kopieen.length > 0" class="flex flex-col gap-3">
            <div class="max-w-2xl space-y-1">
                <h2 class="font-semibold tracking-tight">
                    {{ $t('Veiligheidskopieën') }}
                </h2>
                <p class="text-sm text-pretty text-muted-foreground">
                    {{
                        $t(
                            'Deze maakt het portaal zelf, vlak voordat je iets terugzet -- zodat ook een verkeerde terugzetting ongedaan te maken is. Er worden er hoogstens :aantal bewaard; de oudste verdwijnt vanzelf. Ze tellen niet mee in je eigen back-ups.',
                            { aantal: props.cijfers.maximumAutomatisch },
                        )
                    }}
                </p>
                <p class="text-sm text-pretty text-muted-foreground">
                    {{
                        $t(
                            'Er worden geen back-ups op vaste tijden gemaakt. Dat doe je zelf, met de knop hierboven.',
                        )
                    }}
                </p>
            </div>

            <div class="overflow-hidden rounded-lg border border-border">
                <ul class="divide-y divide-border">
                    <BackupRegel
                        v-for="rij in props.kopieen"
                        :key="rij.id"
                        :rij="rij"
                        :bezig="bezig"
                        @controleren="controleren(rij)"
                        @vastzetten="vastzetten(rij)"
                        @terugzetten="openVenster(rij, 'terugzetten')"
                        @verwijderen="openVenster(rij, 'verwijderen')"
                    />
                </ul>
            </div>
        </div>

        <BackupVolDialoog
            v-model:open="volOpen"
            :keuzes="keuzes"
            :nodig="aantalNodig"
            :teveel="props.cijfers.teveel > 0"
            :maximum="props.cijfers.maximumEigen"
            :bezig="bezig"
            :fouten="volFouten"
            @bevestig="vervangEnGaDoor"
        />

        <BackupCodeDialoog
            v-model:open="vensterOpen"
            :backup="gekozen"
            :actie="actie"
            :verschil="props.verschil ?? null"
            :laadt="verschilLaadt"
        />
    </div>
</template>
