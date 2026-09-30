<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowUpDown,
    Eye,
    EyeOff,
    Globe,
    Heading,
    LayoutGrid,
    Languages,
    Pencil,
    Plus,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import VerlaatPortaal from '@/components/VerlaatPortaal.vue';
import DienstDialoog from '@/components/website/DienstDialoog.vue';
import DienstenKopDialoog from '@/components/website/DienstenKopDialoog.vue';
import DienstIcoon from '@/components/site/DienstIcoon.vue';
import VolgordeDialoog from '@/components/website/VolgordeDialoog.vue';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { bevestigBewerken, bevestigVerwijderen } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import site from '@/routes/site';
import website from '@/routes/website';
import diensten from '@/routes/website/diensten';
import type {
    DienstRij,
    DienstenKopRij,
    DienstenOpties,
} from '@/types/diensten';

/**
 * De diensten op je website.
 *
 * **Geen zoekveld en geen paginering**, anders dan bij de tijdlijn. Een
 * loopbaan telt tientallen functies; diensten zijn er een handvol. Een
 * zoekveld boven vier regels is gereedschap dat in de weg staat.
 *
 * **Geen detailpagina.** Alles van een dienst past in één venster. Bij
 * een ervaring is die pagina er om na te kijken -- twee talen naast
 * elkaar, de datums, het logo -- en dat weegt hier niet op tegen een
 * klik extra.
 *
 * **Wél een eigen volgorde**, want welke dienst je het eerst noemt is
 * een redactionele keuze en geen datum. Die zet de klant met slepen, in
 * een eigen venster -- net als de indeling van de pagina.
 *
 * Zie docs/architecture/modules/diensten.md.
 */
const props = defineProps<{
    items: DienstRij[];
    leeg: boolean;
    kop: DienstenKopRij;
    opties: DienstenOpties;
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
            { title: 'Diensten' },
        ],
    },
});

const venster = ref(false);
const kopVenster = ref(false);
const volgordeVenster = ref(false);

/** De dienst die in het venster staat; null is een nieuwe. */
const gekozen = ref<DienstRij | null>(null);

const nieuw = (): void => {
    gekozen.value = null;
    venster.value = true;
};

const bewerk = (item: DienstRij): void => {
    gekozen.value = item;
    venster.value = true;
};

/** Hoeveel diensten er daadwerkelijk op de website staan. */
const online = computed(
    () => props.items.filter((item) => item.published).length,
);

/**
 * Hoeveel diensten er tegelijk op de website passen.
 *
 * Moet gelijk blijven aan `BREED` in DienstenSection.vue. Hier staat
 * het alleen om uit te leggen wat de bezoeker ziet; de echte indeling
 * gebeurt daar.
 */
const PER_PAGINA = 4;

/**
 * Staat er meer dan één pagina op de website?
 *
 * Geen waarschuwing maar een mededeling. Er is geen maximum -- de klant
 * loopt nergens tegen een muur op -- maar hij hoort wel te weten dat
 * zijn vijfde dienst achter een knopje staat en niet meteen in beeld.
 */
const bladert = computed(() => online.value > PER_PAGINA);

const wisselOnline = async (item: DienstRij): Promise<void> => {
    const aan = !item.published;

    const akkoord = await bevestigBewerken({
        titel: aan
            ? t('":naam" op je website zetten?', { naam: item.title_nl })
            : t('":naam" van je website halen?', { naam: item.title_nl }),
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
        diensten.online(item.id).url,
        { published: aan },
        { preserveScroll: true, preserveState: true },
    );
};

const verwijder = async (item: DienstRij): Promise<void> => {
    const akkoord = await bevestigVerwijderen({
        titel: t('":naam" verwijderen?', { naam: item.title_nl }),
        tekst: t(
            'Dit kan niet ongedaan worden gemaakt. De expertisepunten gaan mee.',
        ),
    });

    if (!akkoord) {
        return;
    }

    router.delete(diensten.destroy(item.id).url, { preserveScroll: true });
};
</script>

<template>
    <Head :title="$t('Diensten')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <header class="max-w-xl space-y-1">
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ $t('Diensten') }}
                </h1>
                <p class="text-sm text-pretty text-muted-foreground">
                    {{
                        $t(
                            'Wat je aanbiedt, elk met een korte tekst en de expertise die eronder valt. Ze staan op je website in de volgorde die je hier instelt.',
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

                <Button
                    v-if="props.items.length > 1"
                    variant="outline"
                    class="gap-2"
                    @click="volgordeVenster = true"
                >
                    <ArrowUpDown class="size-4" />
                    {{ $t('Volgorde') }}
                </Button>

                <Button variant="aanmaken" class="gap-2" @click="nieuw">
                    <Plus class="size-4" />
                    {{ $t('Nieuwe dienst') }}
                </Button>
            </div>
        </div>

        <!--
            Nog niets ingevuld. Dit is precies de toestand waarvoor de
            indelingspagina een uitroepteken toont: het onderdeel staat
            aan maar is leeg, dus het staat niet op de website. Die
            uitleg hoort hier ook te staan, want dit is waar je hem
            oplost.
        -->
        <div v-if="props.leeg" class="brand-ervaring-leeg">
            <TriangleAlert class="size-5 shrink-0 text-warning" />
            <div class="space-y-1">
                <p class="font-medium">
                    {{ $t('Je hebt nog geen diensten.') }}
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
                v-if="bladert"
                class="flex items-center gap-2 text-sm text-muted-foreground"
            >
                <LayoutGrid class="size-4 shrink-0" />
                <span>
                    {{
                        $t(
                            'Je hebt :aantal diensten online. Er staan er vier tegelijk op je website -- op een telefoon drie -- en je bezoeker bladert met de knopjes eronder naar de rest.',
                            { aantal: online },
                        )
                    }}
                </span>
            </p>

            <div
                class="brand-tabelvak brand-scrollbar overflow-x-auto rounded-xl border"
            >
                <table class="brand-tabel-kaarten w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="px-3 py-2 font-medium">
                                {{ $t('Dienst') }}
                            </th>
                            <th class="px-3 py-2 font-medium">
                                {{ $t('Expertise') }}
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
                            <td :data-label="$t('Dienst')" class="px-3 py-2">
                                <span class="flex items-start gap-2.5">
                                    <span
                                        class="brand-dienst-merk"
                                        aria-hidden="true"
                                    >
                                        <DienstIcoon
                                            :icoon="item.icon"
                                            class="size-4"
                                        />
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block font-medium">
                                            {{ item.title_nl }}
                                        </span>
                                        <!--
                                            `font-normal` is hier nodig:
                                            op een telefoon is de eerste
                                            cel de kop van het kaartje en
                                            staat die in het vet. Zonder
                                            dit erft de omschrijving dat
                                            en leest de hele kaart als
                                            één zware regel.
                                        -->
                                        <span
                                            class="block text-xs font-normal text-muted-foreground"
                                        >
                                            {{ item.summary_nl }}
                                        </span>
                                    </span>
                                </span>
                            </td>

                            <td :data-label="$t('Expertise')" class="px-3 py-2">
                                <span
                                    v-if="item.punten.length === 0"
                                    class="text-muted-foreground"
                                >
                                    {{ $t('geen') }}
                                </span>
                                <span v-else>
                                    {{
                                        $t(':aantal punten', {
                                            aantal: item.punten.length,
                                        })
                                    }}
                                </span>
                            </td>

                            <td :data-label="$t('Engels')" class="px-3 py-2">
                                <span
                                    v-if="item.vertaald"
                                    class="text-muted-foreground"
                                >
                                    {{ $t('compleet') }}
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 text-warning"
                                >
                                    <Languages class="size-3.5 shrink-0" />
                                    {{ $t('niet compleet') }}
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

    <DienstDialoog
        v-model:open="venster"
        :item="gekozen"
        :opties="props.opties"
        :kan-vertalen="props.kanVertalen"
    />

    <DienstenKopDialoog
        v-model:open="kopVenster"
        :kop="props.kop"
        :kan-vertalen="props.kanVertalen"
    />

    <VolgordeDialoog v-model:open="volgordeVenster" :items="props.items" />
</template>
