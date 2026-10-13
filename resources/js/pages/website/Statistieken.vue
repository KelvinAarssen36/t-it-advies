<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowUpDown,
    ChartNoAxesColumn,
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
import StatistiekDialoog from '@/components/website/StatistiekDialoog.vue';
import StatistiekIndelingDialoog from '@/components/website/StatistiekIndelingDialoog.vue';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { bevestigBewerken, bevestigVerwijderen } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import site from '@/routes/site';
import website from '@/routes/website';
import statistieken from '@/routes/website/statistieken';
import type { KoptekstRij } from '@/types/secties';
import type { StatistiekRij, StatistiekenOpties } from '@/types/statistieken';

/**
 * De statistieken op je website.
 *
 * Qua opzet de eenvoudigste module: één lijst, geen bijlagen. Wat er
 * bijzonder aan is zit aan de kant van de bezoeker.
 *
 * **De tabel staat gegroepeerd en niet plat.** Dezelfde indeling als de
 * website -- groep na groep, met een kopje boven de eerste regel van elke
 * groep -- want een platte tabel naast een gegroepeerde pagina maakte niet
 * duidelijk waar een groep ophoudt, en daarmee ook niet waarom slepen soms
 * "niets" leek te doen. Alles wat met groepen te maken heeft zit achter de
 * knop Indeling; zie StatistiekIndelingDialoog.vue.
 *
 * Zie docs/architecture/modules/statistieken.md.
 */
const props = defineProps<{
    items: StatistiekRij[];
    leeg: boolean;
    kop: KoptekstRij;
    opties: StatistiekenOpties;
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
            { title: 'Statistieken' },
        ],
    },
});

const venster = ref(false);
const kopVenster = ref(false);
const indelingVenster = ref(false);

/** De statistiek die in het venster staat; null is een nieuwe. */
const gekozen = ref<StatistiekRij | null>(null);

const nieuw = (): void => {
    gekozen.value = null;
    venster.value = true;
};

const bewerk = (item: StatistiekRij): void => {
    gekozen.value = item;
    venster.value = true;
};

/** Hoeveel er daadwerkelijk op de website staan. */
const online = computed(
    () => props.items.filter((item) => item.published).length,
);

/**
 * Hoeveel groepen er zijn, voor de uitleg over de indeling.
 *
 * Die uitleg heeft alleen zin zodra er iets te groeperen valt; bij vier
 * losse cijfers is hij ruis.
 */
const groepen = computed(
    () =>
        new Set(
            props.items
                .filter((item) => item.group_nl)
                .map((item) => item.group_nl),
        ).size,
);

/**
 * De tabel in dezelfde volgorde als de website: groep na groep.
 *
 * **Dit was de tweede helft van een verwarring die de klant meldde.** De
 * tabel stond in platte `position`-volgorde, terwijl zijn site
 * gegroepeerd is -- dus hij zag hier een andere indeling dan daar. Nu
 * leest de tabel als zijn pagina, met een kopje boven elke groep.
 *
 * Dezelfde regel als op de server: de naamloze groep vooraan, daarna
 * elke groep in de volgorde waarin zijn eerste item staat. Zie
 * `HomeController::statistiekGroepen()`.
 */
const opGroep = computed<StatistiekRij[]>(() => {
    const groepen = new Map<string, StatistiekRij[]>();

    props.items.forEach((rij) => {
        const sleutel = rij.group_nl ?? '';

        if (!groepen.has(sleutel)) {
            groepen.set(sleutel, []);
        }

        groepen.get(sleutel)?.push(rij);
    });

    const zonderNaam = groepen.get('') ?? [];
    groepen.delete('');

    return [...zonderNaam, ...[...groepen.values()].flat()];
});

/**
 * Begint hier een nieuwe groep?
 *
 * Zo ja, dan komt er een kopjerij boven deze regel -- net als op de
 * website en in het indelingsvenster.
 */
const startGroep = (index: number): boolean => {
    if (index === 0) {
        return true;
    }

    return (
        (opGroep.value[index]?.group_nl ?? '') !==
        (opGroep.value[index - 1]?.group_nl ?? '')
    );
};

/** Hoe de waarde in de lijst leest: "85%" of gewoon "1250". */
const waarde = (item: StatistiekRij): string =>
    [
        item.prefix ?? '',
        item.value,
        item.suffix ?? (item.display === 'teller' ? '' : '%'),
    ].join('');

const wisselOnline = async (item: StatistiekRij): Promise<void> => {
    const aan = !item.published;

    const akkoord = await bevestigBewerken({
        titel: aan
            ? t('":naam" op je website zetten?', { naam: item.label_nl })
            : t('":naam" van je website halen?', { naam: item.label_nl }),
        tekst: aan
            ? undefined
            : t(
                  'Hij blijft hier gewoon staan; bezoekers zien hem alleen niet meer.',
              ),
    });

    if (!akkoord) {
        return;
    }

    router.patch(
        statistieken.online(item.id).url,
        { published: aan },
        { preserveScroll: true, preserveState: true },
    );
};

const verwijder = async (item: StatistiekRij): Promise<void> => {
    const akkoord = await bevestigVerwijderen({
        titel: t('":naam" verwijderen?', { naam: item.label_nl }),
        tekst: t('Dit kan niet ongedaan worden gemaakt.'),
    });

    if (!akkoord) {
        return;
    }

    router.delete(statistieken.destroy(item.id).url, { preserveScroll: true });
};
</script>

<template>
    <Head :title="$t('Statistieken')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <header class="max-w-xl space-y-1">
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ $t('Statistieken') }}
                </h1>
                <p class="text-sm text-pretty text-muted-foreground">
                    {{
                        $t(
                            'Je vaardigheden en kengetallen. Per cijfer kies je zelf de vorm: een balk, een ring of een groot getal dat oploopt.',
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

                <!--
                    Vanaf één statistiek en niet vanaf twee, anders dan
                    bij de andere modules: met één cijfer valt er niets
                    te herschikken, maar wel een groep voor te maken.
                -->
                <Button
                    v-if="!props.leeg"
                    variant="outline"
                    class="gap-2"
                    @click="indelingVenster = true"
                >
                    <ArrowUpDown class="size-4" />
                    <!--
                        "Indeling" en niet "Volgorde": dit venster doet
                        de groepen én de volgorde, en een knop die
                        alleen de helft noemt laat de andere helft
                        onvindbaar.
                    -->
                    {{ $t('Indeling') }}
                </Button>

                <Button variant="aanmaken" class="gap-2" @click="nieuw">
                    <Plus class="size-4" />
                    {{ $t('Nieuwe statistiek') }}
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
                    {{ $t('Je hebt nog geen statistieken.') }}
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
            <p
                v-if="groepen > 0"
                class="flex items-center gap-2 text-sm text-muted-foreground"
            >
                <ChartNoAxesColumn class="size-4 shrink-0" />
                <span>
                    {{
                        $t(
                            'Deze tabel staat precies zoals je website: per groep, met een kopje erboven. Binnen een groep is het de volgorde die je zelf sleept, en staan er twee van dezelfde vorm naast elkaar, dan komen die op je site ook op één rij. Met de knop Indeling sleep je cijfers naar een andere groep, hernoem je een groep en maak je er een nieuwe bij.',
                        )
                    }}
                </span>
            </p>

            <div
                class="brand-tabelvak brand-schuif-x brand-scrollbar rounded-xl border"
            >
                <table class="brand-tabel-kaarten w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="px-3 py-2 font-medium">
                                {{ $t('Statistiek') }}
                            </th>
                            <th class="px-3 py-2 font-medium">
                                {{ $t('Weergave') }}
                            </th>
                            <th class="px-3 py-2 font-medium">
                                {{ $t('Waarde') }}
                            </th>
                            <th class="px-3 py-2 font-medium">
                                {{ $t('Op de website') }}
                            </th>
                            <th class="px-3 py-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template
                            v-for="(item, index) in opGroep"
                            :key="item.id"
                        >
                            <!--
                                Een kopjerij boven elke groep, zodat de
                                tabel leest als de website. Hij is
                                `aria-hidden` omdat hij geen gegevens
                                bevat: de groep staat ook als tekst op
                                elke regel eronder.
                            -->
                            <tr v-if="startGroep(index)" aria-hidden="true">
                                <td colspan="5" class="brand-tabel-groepkop">
                                    {{
                                        item.group_nl ||
                                        $t('Zonder groep, bovenaan')
                                    }}
                                </td>
                            </tr>

                            <tr
                                class="border-t"
                                :data-offline="item.published ? undefined : ''"
                            >
                                <td
                                    :data-label="$t('Statistiek')"
                                    class="px-3 py-2"
                                >
                                    <span class="block font-medium">
                                        {{ item.label_nl }}
                                    </span>
                                    <!--
                                    `font-normal` is hier nodig: op een
                                    telefoon is de eerste cel de kop van
                                    het kaartje en staat die in het vet.
                                -->
                                    <span
                                        class="block text-xs font-normal text-muted-foreground"
                                    >
                                        <template v-if="item.group_nl">
                                            {{ item.group_nl }}
                                        </template>
                                        <template v-else>
                                            {{ $t('Geen groep') }}
                                        </template>
                                    </span>
                                </td>

                                <td
                                    :data-label="$t('Weergave')"
                                    class="px-3 py-2"
                                >
                                    <span class="brand-statistiek-soort">
                                        {{ item.display_label }}
                                    </span>
                                </td>

                                <td
                                    :data-label="$t('Waarde')"
                                    class="px-3 py-2 tabular-nums"
                                >
                                    {{ waarde(item) }}
                                </td>

                                <td
                                    :data-label="$t('Op de website')"
                                    class="px-3 py-2"
                                    @click.stop
                                >
                                    <span
                                        class="inline-flex items-center gap-2"
                                    >
                                        <Switch
                                            :model-value="item.published"
                                            :aria-label="$t('Op de website')"
                                            @update:model-value="
                                                wisselOnline(item)
                                            "
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
                        </template>
                    </tbody>
                </table>
            </div>

            <p v-if="online === 0" class="text-sm text-muted-foreground">
                {{
                    $t(
                        'Er staat op dit moment niets van je statistieken op je website; alles staat uit.',
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

    <StatistiekDialoog
        v-model:open="venster"
        :item="gekozen"
        :opties="props.opties"
        :kan-vertalen="props.kanVertalen"
    />

    <KoptekstDialoog
        v-model:open="kopVenster"
        :kop="props.kop"
        :actie="statistieken.kop().url"
        :titel="$t('De kop boven je statistieken')"
        :uitleg="
            $t(
                'Het opschrift, de titel en de zin eronder. Op je website staat dit boven de cijfers.',
            )
        "
        :bevestiging="$t('De kop boven je statistieken aanpassen?')"
        sleutel="statistieken"
        :kan-vertalen="props.kanVertalen"
    />

    <StatistiekIndelingDialoog
        v-model:open="indelingVenster"
        :items="props.items"
        :kan-vertalen="props.kanVertalen"
    />
</template>
