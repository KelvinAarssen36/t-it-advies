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
import KerngegevenIcoon from '@/components/site/KerngegevenIcoon.vue';
import VerlaatPortaal from '@/components/VerlaatPortaal.vue';
import KerngegevenDialoog from '@/components/website/KerngegevenDialoog.vue';
import KerngegevenVolgordeDialoog from '@/components/website/KerngegevenVolgordeDialoog.vue';
import KoptekstDialoog from '@/components/website/KoptekstDialoog.vue';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { bevestigBewerken, bevestigVerwijderen } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import site from '@/routes/site';
import website from '@/routes/website';
import kerngegevens from '@/routes/website/kerngegevens';
import type { KerngegevenOpties, KerngegevenRij } from '@/types/kerngegevens';
import type { KoptekstRij } from '@/types/secties';

/**
 * De kerngegevens op je website.
 *
 * Het vaste patroon van dit project, met twee dingen die afwijken:
 *
 * - **Er is een maximum van acht.** De knop verdwijnt zodra je er acht
 *   hebt, met de reden erbij. Zou hij blijven staan en de server het
 *   weigeren, dan is de uitleg een foutmelding in plaats van een
 *   mededeling.
 * - **Het lege scherm toont de acht soorten met een voorbeeld.** Wij
 *   verzinnen de soorten, de eigenaar vult de feiten -- en zonder die
 *   voorbeelden is "kerngegeven" een woord zonder betekenis.
 *
 * Zie docs/architecture/modules/kerngegevens.md.
 */
const props = defineProps<{
    items: KerngegevenRij[];
    leeg: boolean;
    kop: KoptekstRij;
    opties: KerngegevenOpties;
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
            { title: 'Kerngegevens' },
        ],
    },
});

const venster = ref(false);
const kopVenster = ref(false);
const volgordeVenster = ref(false);

/** Het kerngegeven dat in het venster staat; null is een nieuwe. */
const gekozen = ref<KerngegevenRij | null>(null);

const nieuw = (): void => {
    gekozen.value = null;
    venster.value = true;
};

const bewerk = (item: KerngegevenRij): void => {
    gekozen.value = item;
    venster.value = true;
};

/** Hoeveel er daadwerkelijk op de website staan. */
const online = computed(
    () => props.items.filter((item) => item.published).length,
);

const vol = computed(() => props.items.length >= props.opties.maximum);

const wisselOnline = async (item: KerngegevenRij): Promise<void> => {
    const aan = !item.published;

    const akkoord = await bevestigBewerken({
        titel: aan
            ? t('Dit kerngegeven op je website zetten?')
            : t('Dit kerngegeven van je website halen?'),
        tekst: aan
            ? undefined
            : t(
                  'Het blijft hier gewoon staan; bezoekers zien het alleen niet meer.',
              ),
    });

    if (!akkoord) {
        return;
    }

    router.patch(
        kerngegevens.online(item.id).url,
        { published: aan },
        { preserveScroll: true, preserveState: true },
    );
};

const verwijder = async (item: KerngegevenRij): Promise<void> => {
    const akkoord = await bevestigVerwijderen({
        titel: t('Dit kerngegeven verwijderen?'),
        tekst: t('Dit kan niet ongedaan worden gemaakt.'),
    });

    if (!akkoord) {
        return;
    }

    router.delete(kerngegevens.destroy(item.id).url, { preserveScroll: true });
};
</script>

<template>
    <Head :title="$t('Kerngegevens')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <header class="max-w-xl space-y-1">
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ $t('Kerngegevens') }}
                </h1>
                <p class="text-sm text-pretty text-muted-foreground">
                    {{
                        $t(
                            'De praktische feiten over zakendoen met jou: wanneer je kunt, waar je werkt, hoe snel je reageert. Ze staan als een strook op je voorpagina.',
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

                <!-- Vanaf twee: met één valt er niets te slepen. -->
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
                    De knop verdwijnt bij acht in plaats van te blijven
                    staan en geweigerd te worden. Een knop die je mag
                    indrukken hoort iets te doen.
                -->
                <Button
                    v-if="!vol"
                    variant="aanmaken"
                    class="gap-2"
                    @click="nieuw"
                >
                    <Plus class="size-4" />
                    {{ $t('Nieuw kerngegeven') }}
                </Button>
            </div>
        </div>

        <!--
            Nog niets ingevuld. Dit is precies de toestand waarvoor de
            indelingspagina een uitroepteken toont: het onderdeel staat
            aan maar is leeg, dus het staat niet op de website.

            De acht soorten staan erbij met een voorbeeld. Wij verzinnen
            de soorten, jij vult de feiten -- en zonder die voorbeelden is
            "kerngegeven" een woord zonder betekenis.
        -->
        <div v-if="props.leeg" class="flex flex-col gap-4">
            <div class="brand-ervaring-leeg">
                <TriangleAlert class="size-5 shrink-0 text-warning" />
                <div class="space-y-1">
                    <p class="font-medium">
                        {{ $t('Je hebt nog geen kerngegevens.') }}
                    </p>
                    <p class="text-sm text-pretty text-muted-foreground">
                        {{
                            $t(
                                'Zolang dit leeg is laten we de hele strook weg van je website. Een kopje met niets eronder is slordiger dan geen kopje.',
                            )
                        }}
                    </p>
                </div>
            </div>

            <div>
                <p class="mb-3 text-sm text-pretty text-muted-foreground">
                    {{
                        $t(
                            'Dit zijn de soorten waaruit je kunt kiezen. De waarde ernaast is een voorbeeld -- jij vult in wat voor jou waar is.',
                        )
                    }}
                </p>

                <ul class="grid gap-2 tablet:grid-cols-2">
                    <li
                        v-for="soort in props.opties.soorten"
                        :key="soort.value"
                        class="flex items-start gap-3 rounded-lg border border-border p-3 text-sm"
                    >
                        <KerngegevenIcoon
                            :icoon="soort.value"
                            class="mt-0.5 size-4 shrink-0 text-brand-cyan"
                            aria-hidden="true"
                        />
                        <span class="min-w-0">
                            <span class="block font-medium">
                                {{ soort.label }}
                            </span>
                            <span
                                class="block text-pretty text-muted-foreground"
                            >
                                {{ soort.voorbeeld }}
                            </span>
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <template v-else>
            <p v-if="vol" class="text-sm text-pretty text-muted-foreground">
                {{
                    $t(
                        'Je hebt er :aantal, en dat is het maximum. Een strook met meer regels leest niemand meer; wil je iets anders kwijt, verwijder er dan eerst een.',
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
                                {{ $t('Kerngegeven') }}
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
                            <td
                                :data-label="$t('Kerngegeven')"
                                class="px-3 py-2"
                            >
                                <span
                                    class="flex items-center gap-2 font-medium"
                                >
                                    <KerngegevenIcoon
                                        :icoon="item.icon"
                                        class="size-4 shrink-0 text-brand-cyan"
                                        aria-hidden="true"
                                    />
                                    {{ item.label_nl }}
                                </span>
                                <!--
                                    `font-normal` is hier nodig: op een
                                    telefoon is de eerste cel de kop van
                                    het kaartje en staat die in het vet.
                                -->
                                <span
                                    class="mt-0.5 block text-xs font-normal text-muted-foreground"
                                >
                                    {{ item.value_nl }}
                                </span>
                            </td>

                            <td :data-label="$t('Engels')" class="px-3 py-2">
                                <!--
                                    Of het Engels er staat, en niet de
                                    tekst zelf: die staat in het venster.
                                    Wat je hier wil weten is of er nog
                                    werk ligt.
                                -->
                                <span
                                    v-if="item.label_en && item.value_en"
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
                        'Er staat op dit moment geen enkel kerngegeven op je website; alles staat uit.',
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

    <KerngegevenDialoog
        v-model:open="venster"
        :item="gekozen"
        :opties="props.opties"
        :kan-vertalen="props.kanVertalen"
    />

    <KoptekstDialoog
        v-model:open="kopVenster"
        :kop="props.kop"
        :actie="kerngegevens.kop().url"
        :titel="$t('De kop boven je kerngegevens')"
        :uitleg="
            $t(
                'Het opschrift, de titel en de zin eronder. Hou die laatste zin kort: de strook eronder is al kort en bondig, en een lange inleiding haalt dat onderuit.',
            )
        "
        :bevestiging="$t('De kop boven je kerngegevens aanpassen?')"
        sleutel="kerngegevens"
        :kan-vertalen="props.kanVertalen"
    />

    <KerngegevenVolgordeDialoog
        v-model:open="volgordeVenster"
        :items="props.items"
    />
</template>
