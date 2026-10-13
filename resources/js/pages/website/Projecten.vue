<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowUpDown,
    LayoutGrid,
    Eye,
    EyeOff,
    Globe,
    Heading,
    Pencil,
    Plus,
    Star,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import VerlaatPortaal from '@/components/VerlaatPortaal.vue';
import KoptekstDialoog from '@/components/website/KoptekstDialoog.vue';
import ProjectDialoog from '@/components/website/ProjectDialoog.vue';
import ProjectVolgordeDialoog from '@/components/website/ProjectVolgordeDialoog.vue';
import ProjectWeergaveDialoog from '@/components/website/ProjectWeergaveDialoog.vue';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { bevestigBewerken, bevestigVerwijderen } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import site from '@/routes/site';
import website from '@/routes/website';
import projecten from '@/routes/website/projecten';
import type {
    ProjectOpties,
    ProjectRij,
    ProjectWeergave,
} from '@/types/projecten';
import type { KoptekstRij } from '@/types/secties';

/**
 * De etalage: wat de eigenaar heeft gedaan.
 *
 * Het vaste patroon van dit project -- kaarttabel, bewerkvenster,
 * sleepvenster, kop erboven -- met één ding erbij: **een sterretje per
 * regel om een project uit te lichten**. Dat is een sterretje op de regel
 * en geen keuzelijst bovenaan: je kiest het project op de plek waar je het
 * ziet staan, en je ziet in één oogopslag welke het zijn.
 *
 * **Er mogen er meerdere uitgelicht staan.** Samen draaien ze als
 * slideshow bovenaan `/projecten`; op de voorpagina staat het bovenste uit
 * de eigen volgorde groot. Het sterretje is een schakelaar: nog een keer
 * drukken zet hem uit, dus niets uitlichten kan ook zonder aparte knop.
 *
 * Zie docs/architecture/modules/projecten.md.
 */
const props = defineProps<{
    items: ProjectRij[];
    leeg: boolean;
    kop: KoptekstRij;
    /** Hoe de pagina met alle projecten eruitziet. */
    weergave: ProjectWeergave;
    opties: ProjectOpties;
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
            { title: 'Projecten' },
        ],
    },
});

const venster = ref(false);
const kopVenster = ref(false);
const volgordeVenster = ref(false);
const weergaveVenster = ref(false);

/** Het project dat in het venster staat; null is een nieuw. */
const gekozen = ref<ProjectRij | null>(null);

const nieuw = (): void => {
    gekozen.value = null;
    venster.value = true;
};

const bewerk = (item: ProjectRij): void => {
    gekozen.value = item;
    venster.value = true;
};

/** Hoeveel er daadwerkelijk op de website staan. */
const online = computed(
    () => props.items.filter((item) => item.published).length,
);

/** De uitgelichte projecten, in de volgorde van de tabel. */
const uitgelichte = computed(() => props.items.filter((item) => item.featured));

/**
 * Het project dat groot op de voorpagina staat.
 *
 * Dat is het bovenste uitgelichte project dat ook online staat -- niet
 * zomaar het eerste met een sterretje. De teksten hieronder hangen eraan,
 * en die moeten kloppen met wat de bezoeker ziet.
 */
const voorop = computed(
    () => uitgelichte.value.find((item) => item.published) ?? null,
);

/**
 * Of de uitleg over de voorpagina zin heeft.
 *
 * Pas zodra er meer projecten zijn dan er passen; daarvoor is het ruis.
 */
const meerDanPast = computed(
    () => online.value > props.opties.opDeVoorpagina + 1,
);

const wisselOnline = async (item: ProjectRij): Promise<void> => {
    const aan = !item.published;

    const akkoord = await bevestigBewerken({
        titel: aan
            ? t('Dit project op je website zetten?')
            : t('Dit project van je website halen?'),
        tekst: aan
            ? undefined
            : item.featured
              ? t(
                    'Het staat uitgelicht, dus het verdwijnt ook uit de slideshow bovenaan je projectenpagina. Er komt geen ander project voor in de plaats.',
                )
              : t(
                    'Het blijft hier gewoon staan; bezoekers zien het alleen niet meer.',
                ),
    });

    if (!akkoord) {
        return;
    }

    router.patch(
        projecten.online(item.id).url,
        { published: aan },
        { preserveScroll: true, preserveState: true },
    );
};

/**
 * Uitlichten, of het uitlichten ongedaan maken.
 *
 * **De bevestiging zegt wat er met je website gebeurt en niet wat er met
 * het vinkje gebeurt.** Dat verschilt per geval: het laatste sterretje
 * uitzetten haalt het hele blok van de voorpagina, maar één van de drie
 * uitzetten haalt alleen een dia weg. Zou er één vaste zin staan, dan is
 * die in de helft van de gevallen onwaar.
 */
const wisselUitgelicht = async (item: ProjectRij): Promise<void> => {
    const uit = item.featured;

    /** Hoeveel er straks nog uitgelicht staan, dit project meegerekend. */
    const straks = uitgelichte.value.length + (uit ? -1 : 1);

    const akkoord = await bevestigBewerken({
        titel: uit
            ? t('Dit project niet meer uitlichten?')
            : t('Dit project uitlichten?'),
        tekst: uit
            ? straks === 0
                ? t(
                      'Er staat dan niets meer uitgelicht: het grote blok verdwijnt van je voorpagina en je projectenpagina begint met de lijst. Het project zelf blijft gewoon tussen de andere staan.',
                  )
                : t(
                      'Het verdwijnt uit de slideshow bovenaan je projectenpagina. Het blijft gewoon tussen de andere staan.',
                  )
            : straks === 1
              ? t(
                    'Het komt groot op je voorpagina en bovenaan je projectenpagina te staan.',
                )
              : t(
                    'Dan staan er :aantal uitgelicht; samen draaien ze als slideshow bovenaan je projectenpagina. Groot op je voorpagina staat het bovenste uit de volgorde hieronder.',
                    { aantal: straks },
                ),
    });

    if (!akkoord) {
        return;
    }

    router.patch(
        projecten.uitlichten(item.id).url,
        {},
        { preserveScroll: true, preserveState: true },
    );
};

const verwijder = async (item: ProjectRij): Promise<void> => {
    const akkoord = await bevestigVerwijderen({
        titel: t('":naam" verwijderen?', { naam: item.title_nl }),
        tekst:
            item.featured && uitgelichte.value.length === 1
                ? t(
                      'Dit kan niet ongedaan worden gemaakt. Het is het enige uitgelichte project, dus daarna staat er niets meer groot op je voorpagina.',
                  )
                : item.featured
                  ? t(
                        'Dit kan niet ongedaan worden gemaakt. Het staat uitgelicht, dus het verdwijnt ook uit de slideshow op je projectenpagina.',
                    )
                  : t('Dit kan niet ongedaan worden gemaakt.'),
    });

    if (!akkoord) {
        return;
    }

    router.delete(projecten.destroy(item.id).url, { preserveScroll: true });
};
</script>

<template>
    <Head :title="$t('Projecten')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <header class="max-w-xl space-y-1">
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ $t('Projecten') }}
                </h1>
                <p class="text-sm text-pretty text-muted-foreground">
                    {{
                        $t(
                            'Wat je hebt gedaan: opdrachten, migraties, trajecten. Je kiest zelf de volgorde en welk project groot bovenaan komt te staan.',
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

                <!-- Vanaf twee projecten: met één valt er niets te slepen. -->
                <Button
                    v-if="props.items.length > 1"
                    variant="outline"
                    class="gap-2"
                    @click="volgordeVenster = true"
                >
                    <ArrowUpDown class="size-4" />
                    {{ $t('Volgorde') }}
                </Button>

                <!--
                    Vanaf twee projecten: met één is er niets om af te
                    wisselen en doen allebei de vormen hetzelfde.
                -->
                <Button
                    v-if="props.items.length > 1"
                    variant="outline"
                    class="gap-2"
                    @click="weergaveVenster = true"
                >
                    <LayoutGrid class="size-4" />
                    {{ $t('Weergave') }}
                </Button>

                <Button variant="aanmaken" class="gap-2" @click="nieuw">
                    <Plus class="size-4" />
                    {{ $t('Nieuw project') }}
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
                    {{ $t('Je hebt nog geen projecten.') }}
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
                v-if="meerDanPast"
                class="text-sm text-pretty text-muted-foreground"
            >
                {{
                    $t(
                        'Op je voorpagina staat het bovenste uitgelichte project groot, met daarnaast de eerste :aantal uit deze lijst en een knop naar al je projecten. De volgorde hieronder bepaalt dus welke daar staan.',
                        { aantal: props.opties.opDeVoorpagina },
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
                                {{ $t('Project') }}
                            </th>
                            <th class="px-3 py-2 font-medium">
                                {{ $t('Periode') }}
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
                            <td :data-label="$t('Project')" class="px-3 py-2">
                                <span class="brand-projectregel">
                                    <!--
                                        Het beeld, of de eerste letter van de
                                        organisatie. Een leeg vierkant zou
                                        suggereren dat er iets mist; een
                                        letter leest als een merkteken.
                                    -->
                                    <span class="brand-projectbeeld">
                                        <img
                                            v-if="item.beeld"
                                            :src="item.beeld"
                                            alt=""
                                            width="640"
                                            height="640"
                                        />
                                        <span v-else aria-hidden="true">
                                            {{ item.letter }}
                                        </span>
                                    </span>

                                    <span class="min-w-0">
                                        <span class="block font-medium">
                                            {{ item.title_nl }}
                                        </span>
                                        <!--
                                            `font-normal` is hier nodig: op
                                            een telefoon is de eerste cel de
                                            kop van het kaartje en staat die
                                            in het vet.
                                        -->
                                        <span
                                            class="block text-xs font-normal text-muted-foreground"
                                        >
                                            {{ item.type_naam }} ·
                                            {{ item.organisation }}
                                        </span>
                                    </span>
                                </span>
                            </td>

                            <td
                                :data-label="$t('Periode')"
                                class="px-3 py-2 whitespace-nowrap tabular-nums"
                            >
                                {{ item.periode }}
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
                                    <!--
                                        Het sterretje staat vóór het potlood
                                        en is geen bewerkknop: het verandert
                                        niets aan de inhoud, alleen aan waar
                                        het project staat.
                                    -->
                                    <Button
                                        :variant="
                                            item.featured
                                                ? 'bewerken'
                                                : 'bewerken-zacht'
                                        "
                                        size="icon-sm"
                                        :aria-label="
                                            item.featured
                                                ? $t('Niet meer uitlichten')
                                                : $t('Uitlichten')
                                        "
                                        :aria-pressed="item.featured"
                                        @click="wisselUitgelicht(item)"
                                    >
                                        <Star
                                            class="size-4"
                                            :fill="
                                                item.featured
                                                    ? 'currentColor'
                                                    : 'none'
                                            "
                                        />
                                    </Button>
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
                        'Er staat op dit moment geen enkel project op je website; alles staat uit.',
                    )
                }}
            </p>

            <p
                v-else-if="uitgelichte.length === 0"
                class="text-sm text-pretty text-muted-foreground"
            >
                {{
                    $t(
                        'Er staat niets uitgelicht. Druk op het sterretje bij de projecten die je vooraan wilt hebben; het bovenste komt groot op je voorpagina en samen draaien ze bovenaan je projectenpagina.',
                    )
                }}
            </p>

            <p
                v-else-if="uitgelichte.length > 1 && voorop"
                class="text-sm text-pretty text-muted-foreground"
            >
                {{
                    $t(
                        'Er staan :aantal projecten uitgelicht. Ze draaien als slideshow bovenaan je projectenpagina; groot op je voorpagina staat ":naam".',
                        {
                            aantal: uitgelichte.length,
                            naam: voorop.title_nl,
                        },
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

    <ProjectDialoog
        v-model:open="venster"
        :item="gekozen"
        :opties="props.opties"
        :kan-vertalen="props.kanVertalen"
    />

    <ProjectVolgordeDialoog
        v-model:open="volgordeVenster"
        :items="props.items"
    />

    <ProjectWeergaveDialoog
        v-model:open="weergaveVenster"
        :weergave="props.weergave"
        :opties="props.opties"
    />

    <KoptekstDialoog
        v-model:open="kopVenster"
        :kop="props.kop"
        :actie="projecten.kop().url"
        :titel="$t('De kop boven je projecten')"
        :uitleg="
            $t(
                'Het opschrift, de titel en de zin eronder. Deze kop staat zowel boven het blok op je voorpagina als boven de pagina met al je projecten.',
            )
        "
        :bevestiging="$t('De kop boven je projecten aanpassen?')"
        sleutel="projecten"
        :kan-vertalen="props.kanVertalen"
    />
</template>
