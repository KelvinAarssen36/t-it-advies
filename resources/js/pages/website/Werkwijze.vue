<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowUpDown,
    Eye,
    EyeOff,
    Globe,
    Heading,
    Pencil,
    Plus,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import VerlaatPortaal from '@/components/VerlaatPortaal.vue';
import KoptekstDialoog from '@/components/website/KoptekstDialoog.vue';
import StapDialoog from '@/components/website/StapDialoog.vue';
import StapVolgordeDialoog from '@/components/website/StapVolgordeDialoog.vue';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { bevestigBewerken, bevestigVerwijderen } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import site from '@/routes/site';
import website from '@/routes/website';
import werkwijze from '@/routes/website/werkwijze';
import type { KoptekstRij } from '@/types/secties';
import type { StapOpties, StapRij } from '@/types/werkwijze';

/**
 * De stappen van je werkwijze.
 *
 * Het vaste patroon van dit project, met één ding dat hier zwaarder weegt
 * dan elders: **de volgorde ís de inhoud.** Bij de vragen bepaalt slepen
 * alleen wat er eerst staat; hier bepaalt het ook het nummer op de kaart.
 * Daarom staat het nummer in de tabel, verschijnt het sleepvenster al
 * vanaf twee stappen, en zegt het scherm wat slepen doet.
 *
 * **Er is geen veld voor het nummer**, en dat is met opzet. Zou de
 * eigenaar het apart kunnen invullen, dan krijg je vroeg of laat een lijst
 * die begint bij 01, 03, 02.
 *
 * Zie docs/architecture/modules/werkwijze.md.
 */
const props = defineProps<{
    items: StapRij[];
    leeg: boolean;
    kop: KoptekstRij;
    opties: StapOpties;
    kanVertalen: boolean;
}>();

/*
 * De kruimel staat er in gewoon Nederlands; Breadcrumbs.vue vertaalt hem.
 * Hier `$t()` gebruiken zou niet werken: defineOptions draait bij het
 * laden van de module, en dan zijn de vertalingen er nog niet.
 */
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Website', href: website.index() },
            { title: 'Werkwijze' },
        ],
    },
});

const venster = ref(false);
const kopVenster = ref(false);
const volgordeVenster = ref(false);

/** De stap die in het venster staat; null is een nieuwe. */
const gekozen = ref<StapRij | null>(null);

const nieuw = (): void => {
    gekozen.value = null;
    venster.value = true;
};

const bewerk = (item: StapRij): void => {
    gekozen.value = item;
    venster.value = true;
};

/** Hoeveel er daadwerkelijk op de website staan. */
const online = computed(
    () => props.items.filter((item) => item.published).length,
);

/**
 * Het nummer dat een stap op de website krijgt.
 *
 * Alleen de stappen die online staan tellen mee: een offline stap staat
 * niet op de website, dus hij hoort ook geen nummer te bezetten. Zonder
 * deze telling zou de bezoeker 01, 02, 04 zien.
 */
const nummers = computed(() => {
    const uit = new Map<number, number>();
    let teller = 0;

    props.items.forEach((item) => {
        if (item.published) {
            teller++;
            uit.set(item.id, teller);
        }
    });

    return uit;
});

/**
 * Of er nog een stap bij kan.
 *
 * De grens is inhoudelijk en niet technisch; zie `WorkStep::MAXIMUM`. Het
 * scherm schakelt de knop uit en zegt waarom -- de server weigert het ook,
 * want een uitgeschakelde knop is geen grendel.
 */
const vol = computed(() => props.items.length >= props.opties.maximum);

/** Of er een eigen pagina bestaat; die hangt aan de uitgebreide teksten. */
const eigenPagina = computed(() =>
    props.items.some((item) => item.published && (item.body_nl ?? '') !== ''),
);

const wisselOnline = async (item: StapRij): Promise<void> => {
    const aan = !item.published;

    const akkoord = await bevestigBewerken({
        titel: aan
            ? t('Deze stap op je website zetten?')
            : t('Deze stap van je website halen?'),
        tekst: aan
            ? t('De stappen erna schuiven een nummer op.')
            : t(
                  'Hij blijft hier gewoon staan; bezoekers zien hem alleen niet meer. De stappen erna schuiven een nummer op.',
              ),
    });

    if (!akkoord) {
        return;
    }

    router.patch(
        werkwijze.online(item.id).url,
        { published: aan },
        { preserveScroll: true, preserveState: true },
    );
};

const verwijder = async (item: StapRij): Promise<void> => {
    const akkoord = await bevestigVerwijderen({
        titel: t('":naam" verwijderen?', { naam: item.title_nl }),
        tekst: t(
            'Dit kan niet ongedaan worden gemaakt. De stappen erna schuiven een nummer op.',
        ),
    });

    if (!akkoord) {
        return;
    }

    router.delete(werkwijze.destroy(item.id).url, { preserveScroll: true });
};
</script>

<template>
    <Head :title="$t('Werkwijze')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <header class="max-w-xl space-y-1">
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ $t('Werkwijze') }}
                </h1>
                <p class="text-sm text-pretty text-muted-foreground">
                    {{
                        $t(
                            'Wat er gebeurt als iemand met je in zee gaat, stap voor stap. Op je website staan ze genummerd naast elkaar, met een lijn die zich tekent terwijl de bezoeker scrolt.',
                        )
                    }}
                </p>
            </header>

            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" as-child class="gap-2">
                    <Link :href="site.enter()">
                        <Globe class="size-4" />
                        {{ $t('Bekijk het resultaat') }}
                        <VerlaatPortaal />
                    </Link>
                </Button>

                <Button
                    variant="outline"
                    class="gap-2"
                    @click="kopVenster = true"
                >
                    <Heading class="size-4" />
                    {{ $t('Kop erboven') }}
                </Button>

                <!-- Vanaf twee stappen: met één valt er niets te slepen. -->
                <Button
                    v-if="props.items.length > 1"
                    variant="outline"
                    class="gap-2"
                    @click="volgordeVenster = true"
                >
                    <ArrowUpDown class="size-4" />
                    {{ $t('Volgorde') }}
                </Button>

                <Button
                    variant="aanmaken"
                    class="gap-2"
                    :disabled="vol"
                    @click="nieuw"
                >
                    <Plus class="size-4" />
                    {{ $t('Nieuwe stap') }}
                </Button>
            </div>
        </div>

        <!--
            Nog niets ingevuld. Dit is precies de toestand waarvoor de
            indelingspagina een uitroepteken toont: het onderdeel staat
            aan maar is leeg, dus het staat niet op de website.
        -->
        <div v-if="props.leeg" class="brand-ervaring-leeg">
            <TriangleAlert class="size-5 shrink-0 text-warning" />
            <div class="space-y-1">
                <p class="font-medium">
                    {{ $t('Je hebt nog geen stappen.') }}
                </p>
                <p class="text-sm text-pretty text-muted-foreground">
                    {{
                        $t(
                            'Zolang dit leeg is laten we het hele blok weg van je website. Een kopje met niets eronder is slordiger dan geen kopje.',
                        )
                    }}
                </p>
            </div>
        </div>

        <template v-else>
            <p class="text-sm text-pretty text-muted-foreground">
                {{
                    $t(
                        'De volgorde hieronder is ook de nummering op je website. Sleep je een stap naar voren, dan wordt dat 01 -- je hoeft dus nergens een nummer in te vullen.',
                    )
                }}
            </p>

            <p v-if="vol" class="text-sm text-pretty text-warning">
                {{
                    $t(
                        'Je zit op het maximum van :aantal stappen. Dat is geen technische grens: een werkwijze is een verhaal dat iemand moet kunnen onthouden, en voorbij een stuk of zes lukt dat niet meer. Wil je er toch een bij, voeg dan eerst twee bestaande stappen samen.',
                        { aantal: props.opties.maximum },
                    )
                }}
            </p>

            <div
                class="brand-tabelvak brand-schuif-x brand-scrollbar rounded-xl border"
            >
                <table class="brand-tabel-kaarten w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="px-3 py-2 font-medium">
                                {{ $t('Stap') }}
                            </th>
                            <th class="px-3 py-2 font-medium">
                                {{ $t('Duur') }}
                            </th>
                            <th class="px-3 py-2 font-medium">
                                {{ $t('Engels') }}
                            </th>
                            <th class="px-3 py-2 font-medium">
                                {{ $t('Op de website') }}
                            </th>
                            <th class="px-3 py-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in props.items"
                            :key="item.id"
                            class="border-t"
                            :data-offline="item.published ? undefined : ''"
                        >
                            <td :data-label="$t('Stap')" class="px-3 py-2">
                                <span
                                    class="flex items-center gap-2 font-medium"
                                >
                                    <!--
                                        Het nummer zoals de bezoeker het
                                        ziet. Een offline stap heeft er
                                        geen: die staat niet op de website.
                                    -->
                                    <span
                                        v-if="nummers.get(item.id)"
                                        class="brand-stapnummer font-mono"
                                    >
                                        {{
                                            String(
                                                nummers.get(item.id),
                                            ).padStart(2, '0')
                                        }}
                                    </span>
                                    {{ item.title_nl }}
                                </span>
                                <span
                                    class="block text-xs font-normal text-muted-foreground"
                                >
                                    {{ item.summary_nl }}
                                </span>
                            </td>

                            <td :data-label="$t('Duur')" class="px-3 py-2">
                                <span
                                    v-if="item.duration_nl"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ item.duration_nl }}
                                </span>
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                >
                                    &mdash;
                                </span>
                            </td>

                            <td :data-label="$t('Engels')" class="px-3 py-2">
                                <!--
                                    Of het Engels er staat, en niet de tekst
                                    zelf: die staat in het venster. Wat je
                                    hier wil weten is of er nog werk ligt.
                                -->
                                <span
                                    v-if="item.title_en && item.summary_en"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{
                                        item.automatisch_vertaald
                                            ? $t('Automatisch vertaald')
                                            : $t('Ingevuld')
                                    }}
                                </span>
                                <span v-else class="text-xs text-warning">
                                    {{ $t('Nog niet volledig') }}
                                </span>
                            </td>

                            <td
                                :data-label="$t('Op de website')"
                                class="px-3 py-2"
                                @click.stop
                            >
                                <span class="inline-flex items-center gap-2">
                                    <Switch
                                        :model-value="item.published"
                                        :aria-label="$t('Op de website')"
                                        @update:model-value="wisselOnline(item)"
                                    />
                                    <Eye
                                        v-if="item.published"
                                        class="size-4 text-success"
                                    />
                                    <EyeOff
                                        v-else
                                        class="size-4 text-muted-foreground"
                                    />
                                </span>
                            </td>

                            <td class="px-3 py-2">
                                <span
                                    class="flex items-center justify-end gap-1"
                                >
                                    <Button
                                        variant="bewerken-zacht"
                                        size="icon-sm"
                                        :aria-label="$t('Bewerken')"
                                        @click="bewerk(item)"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        variant="verwijderen-zacht"
                                        size="icon-sm"
                                        :aria-label="$t('Verwijderen')"
                                        @click="verwijder(item)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-if="online === 0" class="text-sm text-muted-foreground">
                {{
                    $t(
                        'Er staat op dit moment geen enkele stap op je website; alles staat uit.',
                    )
                }}
            </p>

            <p
                v-else-if="!eigenPagina"
                class="text-sm text-pretty text-muted-foreground"
            >
                {{
                    $t(
                        'Vul je bij een stap ook het uitgebreide verhaal in, dan krijg je er een eigen pagina bij met een knop ernaartoe. Zonder dat verhaal blijft het bij de korte kaarten, en dat mag ook.',
                    )
                }}
            </p>
        </template>

        <p class="text-sm text-muted-foreground">
            <Link
                :href="website.index()"
                class="underline-offset-4 hover:underline"
            >
                {{ $t('Terug naar de indeling van je website') }}
            </Link>
        </p>
    </div>

    <StapDialoog
        v-model:open="venster"
        :item="gekozen"
        :opties="props.opties"
        :kan-vertalen="props.kanVertalen"
    />

    <KoptekstDialoog
        v-model:open="kopVenster"
        :kop="props.kop"
        :actie="werkwijze.kop().url"
        :titel="$t('De kop boven je werkwijze')"
        :uitleg="
            $t(
                'Het opschrift, de titel en de zin eronder. Zet in die laatste zin wat een bezoeker aan deze stappen heeft -- bijvoorbeeld dat hij van tevoren weet wat er gebeurt en wat het kost.',
            )
        "
        :bevestiging="$t('De kop boven je werkwijze aanpassen?')"
        sleutel="werkwijze"
        :kan-vertalen="props.kanVertalen"
    />

    <StapVolgordeDialoog v-model:open="volgordeVenster" :items="props.items" />
</template>
