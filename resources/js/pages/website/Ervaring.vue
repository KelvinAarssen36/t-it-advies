<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Eye,
    EyeOff,
    Globe,
    Hash,
    Languages,
    Pencil,
    Plus,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import ErvaringIcoon from '@/components/site/ErvaringIcoon.vue';
import VerlaatPortaal from '@/components/VerlaatPortaal.vue';
import CijfersDialoog from '@/components/website/CijfersDialoog.vue';
import ErvaringDialoog from '@/components/website/ErvaringDialoog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { bevestigBewerken, bevestigVerwijderen } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import site from '@/routes/site';
import website from '@/routes/website';
import ervaringRoutes from '@/routes/website/ervaring';
import type {
    ErvaringCijferRij,
    ErvaringOpties,
    ErvaringRij,
} from '@/types/ervaring';

/**
 * Het overzicht van de tijdlijn.
 *
 * **Een tabel en geen stapel kaarten.** Bij een loopbaan van vijfentwintig
 * functies gaat het hier om terugvinden, niet om lezen: één regel per
 * ervaring, zoeken erboven, en doorklikken naar de pagina van het item
 * zelf. Alles uitschrijven in het overzicht leverde een scherm op dat je
 * moest scrollen om te ontdekken wat er allemaal in staat.
 *
 * **De volgorde is niet instelbaar en dat zie je**: er zijn geen grepen en
 * geen pijltjes. Een loopbaan die niet op volgorde staat is gewoon fout, en
 * de server sorteert hem daarom zelf -- wat nu loopt bovenaan, daarna van
 * nieuw naar oud. Zie App\Models\Experience::scopeOpTijdlijn.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */

type Paginator<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    total: number;
};

const props = defineProps<{
    items: Paginator<ErvaringRij>;
    filters: { zoek: string | null };
    /** Staat er werkelijk niets, of levert alleen de zoekterm niets op? */
    leeg: boolean;
    opties: ErvaringOpties;
    /** De drie cijfers boven de tijdlijn, met hun berekende waarde erbij. */
    cijfers: ErvaringCijferRij[];
    kanVertalen: boolean;
}>();

defineOptions({
    layout: {
        // De route-hulpjes en niet een handgeschreven pad: verandert de
        // URL ooit, dan gaat het kruimelpad vanzelf mee. Zo doet de rest
        // van het portaal het ook.
        breadcrumbs: [
            { title: 'Website', href: website.index() },
            { title: 'Ervaring', href: ervaringRoutes.index() },
        ],
    },
});

/* --- Zoeken ---------------------------------------------------------- */

const zoek = ref(props.filters.zoek ?? '');

let wachten: ReturnType<typeof setTimeout> | undefined;

// Even wachten met zoeken, anders vuurt elke toetsaanslag een request af.
watch(zoek, () => {
    clearTimeout(wachten);

    wachten = setTimeout(() => {
        router.get(
            ervaringRoutes.index().url,
            { zoek: zoek.value || undefined },
            { preserveState: true, replace: true },
        );
    }, 300);
});

/*
 * De wachttijd afbreken als je de pagina verlaat. Typ je in het zoekveld
 * en klik je binnen die driehonderd milliseconden op een ervaring, dan
 * vuurt het verzoek anders alsnog af -- en word je van de detailpagina
 * teruggetrokken naar het overzicht.
 */
onBeforeUnmount(() => clearTimeout(wachten));

/* --- Het venster ----------------------------------------------------- */

const venster = ref(false);
const bewerkt = ref<ErvaringRij | null>(null);

const nieuw = (): void => {
    bewerkt.value = null;
    venster.value = true;
};

const bewerk = (item: ErvaringRij): void => {
    bewerkt.value = item;
    venster.value = true;
};

const verwijder = async (item: ErvaringRij): Promise<void> => {
    const akkoord = await bevestigVerwijderen({
        titel: t('":naam" verwijderen?', {
            naam: `${item.role_nl} bij ${item.organisation}`,
        }),
        tekst: t('Dit kan niet ongedaan worden gemaakt.'),
    });

    if (!akkoord) {
        return;
    }

    router.delete(ervaringRoutes.destroy(item.id).url, {
        preserveScroll: true,
    });
};

/** Het aparte venster voor de cijfers boven de tijdlijn. */
const cijferVenster = ref(false);

/**
 * Staat er een ingevuld cijfer dat niet meer klopt?
 *
 * Een cijfer dat de klant zelf heeft ingevuld werkt niet vanzelf bij. Komt
 * er een functie bij, of gaat er een jaar voorbij, dan loopt het achter --
 * en dat merkt niemand, want het staat op de voorpagina waar hij zelf
 * nooit naar kijkt. Vandaar dit teken op de knop.
 */
const cijferLooptAchter = computed(() =>
    props.cijfers.some(
        (cijfer) => cijfer.waarde !== null && cijfer.waarde !== cijfer.berekend,
    ),
);

/* --- Online of offline ----------------------------------------------- */

/**
 * Dit schuifje verandert wat bezoekers zien, en valt dus onder dezelfde
 * afspraak als elke andere wijziging: twee keer vragen. Het schuifje zelf
 * volgt de waarde van de server en niet een eigen kopie -- zegt de klant
 * nee, dan staat hij daarmee vanzelf nog op zijn oude stand.
 */
const wisselOnline = async (item: ErvaringRij): Promise<void> => {
    const aan = !item.published;

    const akkoord = await bevestigBewerken({
        titel: aan
            ? t('":naam" op je website zetten?', { naam: item.role_nl })
            : t('":naam" van je website halen?', { naam: item.role_nl }),
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
        ervaringRoutes.online(item.id).url,
        { published: aan },
        { preserveScroll: true, preserveState: true },
    );
};

/** Doorklikken naar de pagina van één ervaring. */
const bekijk = (item: ErvaringRij): void => {
    router.visit(ervaringRoutes.show(item.id).url);
};
</script>

<template>
    <Head :title="$t('Ervaring')" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <header class="max-w-xl space-y-1">
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ $t('Ervaring') }}
                </h1>
                <p class="text-sm text-pretty text-muted-foreground">
                    {{
                        $t(
                            'De tijdlijn op je website. Wat nu loopt staat bovenaan; de rest staat op datum, van nieuw naar oud.',
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

                <!--
                    De cijfers staan op de website boven de tijdlijn, maar
                    het zijn geen ervaringen. Daarom een eigen knop en een
                    eigen venster, en geen rij in de tabel hieronder.
                -->
                <Button
                    variant="outline"
                    class="gap-2"
                    @click="cijferVenster = true"
                >
                    <Hash class="size-4" />
                    {{ $t('Cijfers') }}

                    <!--
                        Een ingevuld cijfer werkt niet vanzelf bij. Loopt er
                        eentje achter op wat wij tellen -- omdat er een
                        functie bij kwam of een jaar voorbijging -- dan hoort
                        dat hier te blijken. Anders staat er een verouderd
                        getal op de voorpagina waar niemand naar kijkt.
                    -->
                    <TriangleAlert
                        v-if="cijferLooptAchter"
                        class="size-4 text-warning"
                        :aria-label="$t('Een cijfer loopt achter')"
                    />
                </Button>

                <Button variant="aanmaken" class="gap-2" @click="nieuw">
                    <Plus class="size-4" />
                    {{ $t('Nieuwe ervaring') }}
                </Button>
            </div>
        </div>

        <!--
            Nog niets ingevuld. Dit is precies de toestand waarvoor de
            indelingspagina een uitroepteken toont: het onderdeel staat aan
            maar is leeg, dus het staat niet op de website. Die uitleg hoort
            hier ook te staan, want dit is waar je hem oplost.
        -->
        <div v-if="props.leeg" class="brand-ervaring-leeg">
            <TriangleAlert class="size-5 shrink-0 text-warning" />
            <div class="space-y-1">
                <p class="font-medium">
                    {{ $t('Er staat nog geen ervaring op je tijdlijn.') }}
                </p>
                <p class="text-sm text-pretty text-muted-foreground">
                    {{
                        $t(
                            'Zolang dit leeg is, laten we het hele onderdeel niet op je website zien. Voeg je eerste ervaring toe en hij verschijnt vanzelf.',
                        )
                    }}
                </p>
            </div>
        </div>

        <template v-else>
            <div class="flex flex-wrap items-center gap-3">
                <Input
                    v-model="zoek"
                    type="search"
                    :placeholder="$t('Zoek op functie, organisatie of plaats')"
                    class="max-w-xs"
                />
                <span class="text-sm text-muted-foreground">
                    {{
                        props.items.total === 1
                            ? $t('1 ervaring')
                            : $t(':aantal ervaringen', {
                                  aantal: props.items.total,
                              })
                    }}
                </span>
            </div>

            <div class="brand-scrollbar overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="px-3 py-2 font-medium">
                                {{ $t('Functie') }}
                            </th>
                            <!--
                                Deze drie vallen weg op een telefoon. De
                                organisatie en de periode komen daar onder
                                de functietitel te staan, en de staat van
                                het Engels zie je op de detailpagina. Zo
                                hoef je de tabel niet zijwaarts te
                                scrollen om bij het schuifje te komen.
                            -->
                            <th
                                class="hidden px-3 py-2 font-medium md:table-cell"
                            >
                                {{ $t('Organisatie') }}
                            </th>
                            <th
                                class="hidden px-3 py-2 font-medium lg:table-cell"
                            >
                                {{ $t('Periode') }}
                            </th>
                            <th
                                class="hidden px-3 py-2 font-medium lg:table-cell"
                            >
                                {{ $t('Engels') }}
                            </th>
                            <th class="px-3 py-2 text-center font-medium">
                                {{ $t('Online') }}
                            </th>
                            <th class="px-3 py-2 font-medium">
                                <span class="sr-only">{{ $t('Acties') }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <!--
                            De hele regel is aanklikbaar voor het gemak, maar
                            de functietitel is een echte link. Anders kom je
                            er met het toetsenbord niet, en kun je hem ook
                            niet in een nieuw tabblad openen.
                        -->
                        <tr
                            v-for="item in props.items.data"
                            :key="item.id"
                            class="brand-ervaring-regel border-t"
                            :data-offline="item.published ? undefined : ''"
                            @click="bekijk(item)"
                        >
                            <td class="px-3 py-2">
                                <span class="flex items-center gap-2.5">
                                    <span class="brand-logo-rondje is-klein">
                                        <img
                                            v-if="item.logo"
                                            :src="item.logo"
                                            alt=""
                                        />
                                        <ErvaringIcoon
                                            v-else
                                            :icoon="item.icon"
                                            class="size-4"
                                        />
                                    </span>

                                    <span class="min-w-0">
                                        <Link
                                            :href="ervaringRoutes.show(item.id)"
                                            class="font-medium underline-offset-4 hover:underline"
                                            @click.stop
                                        >
                                            {{ item.role_nl }}
                                        </Link>

                                        <span
                                            v-if="item.loopt"
                                            class="brand-ervaring-merk ml-2"
                                            data-nu
                                        >
                                            {{ $t('Loopt nu') }}
                                        </span>

                                        <!--
                                            Op een telefoon staan de twee
                                            weggevallen kolommen hier, onder
                                            de titel. Dan is de regel nog
                                            steeds compleet zonder dat je
                                            zijwaarts moet scrollen.
                                        -->
                                        <span
                                            class="block text-xs text-muted-foreground md:hidden"
                                        >
                                            {{ item.organisation }}
                                            <span aria-hidden="true">·</span>
                                            {{ item.periode }}
                                        </span>
                                    </span>
                                </span>
                            </td>

                            <td
                                class="hidden px-3 py-2 text-muted-foreground md:table-cell"
                            >
                                {{ item.organisation }}
                            </td>

                            <td
                                class="hidden px-3 py-2 whitespace-nowrap text-muted-foreground tabular-nums lg:table-cell"
                            >
                                {{ item.periode }}
                            </td>

                            <td class="hidden px-3 py-2 lg:table-cell">
                                <!--
                                    Alleen de verplichte functietitel telt als
                                    "nog niet vertaald". Een lege beschrijving
                                    is geen ontbrekende vertaling maar een
                                    lege beschrijving.
                                -->
                                <span
                                    v-if="!item.vertaald"
                                    class="brand-ervaring-merk"
                                    data-let-op
                                >
                                    <TriangleAlert class="size-3" />
                                    {{ $t('Ontbreekt') }}
                                </span>
                                <span
                                    v-else-if="item.automatisch_vertaald"
                                    class="brand-ervaring-merk"
                                    data-automatisch
                                >
                                    <Languages class="size-3" />
                                    {{ $t('Automatisch') }}
                                </span>
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ $t('Klaar') }}
                                </span>
                            </td>

                            <td class="px-3 py-2" @click.stop>
                                <span
                                    class="flex items-center justify-center gap-2"
                                >
                                    <Switch
                                        :model-value="item.published"
                                        :aria-label="$t('Op de website zetten')"
                                        @update:model-value="wisselOnline(item)"
                                    />
                                    <component
                                        :is="item.published ? Eye : EyeOff"
                                        class="size-4 shrink-0"
                                        :class="
                                            item.published
                                                ? 'text-success'
                                                : 'text-muted-foreground'
                                        "
                                        aria-hidden="true"
                                    />
                                </span>
                            </td>

                            <!--
                                Snel bewerken en verwijderen zonder eerst
                                door te klikken. Alles wat hier gebeurt
                                staat óók op de detailpagina; dit is de
                                korte weg voor wie weet wat hij zoekt.
                            -->
                            <td
                                class="px-3 py-2 text-right whitespace-nowrap"
                                @click.stop
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
                            </td>
                        </tr>

                        <tr v-if="props.items.data.length === 0">
                            <td
                                colspan="6"
                                class="px-3 py-8 text-center text-muted-foreground"
                            >
                                {{
                                    $t(
                                        'Geen ervaring gevonden. Probeer een andere zoekterm.',
                                    )
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav
                v-if="props.items.links.length > 3"
                class="flex flex-wrap gap-1"
            >
                <template v-for="link in props.items.links" :key="link.label">
                    <span
                        v-if="!link.url"
                        class="rounded px-3 py-1 text-sm text-muted-foreground"
                        v-html="link.label"
                    />
                    <Link
                        v-else
                        :href="link.url"
                        class="rounded px-3 py-1 text-sm"
                        :class="
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : 'hover:bg-muted'
                        "
                        preserve-state
                        v-html="link.label"
                    />
                </template>
            </nav>
        </template>

        <p class="text-xs text-muted-foreground">
            <Link
                :href="website.index()"
                class="underline-offset-4 hover:underline"
            >
                {{ $t('Terug naar de indeling van je website') }}
            </Link>
        </p>
    </div>

    <ErvaringDialoog
        v-model:open="venster"
        :item="bewerkt"
        :opties="props.opties"
        :kan-vertalen="props.kanVertalen"
    />

    <CijfersDialoog v-model:open="cijferVenster" :cijfers="props.cijfers" />
</template>
