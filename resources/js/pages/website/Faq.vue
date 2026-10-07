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
import FaqDialoog from '@/components/website/FaqDialoog.vue';
import FaqVolgordeDialoog from '@/components/website/FaqVolgordeDialoog.vue';
import KoptekstDialoog from '@/components/website/KoptekstDialoog.vue';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { bevestigBewerken, bevestigVerwijderen } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import site from '@/routes/site';
import website from '@/routes/website';
import faq from '@/routes/website/faq';
import type { FaqOpties, VraagRij } from '@/types/faq';
import type { KoptekstRij } from '@/types/secties';

/**
 * De veelgestelde vragen op je website.
 *
 * De eenvoudigste module van allemaal: één lijst, geen groepen, geen
 * bijlagen. Daarom staat hier ook bijna niets dat uitleg nodig heeft --
 * het is het vaste patroon van dit project, en dat is de bedoeling.
 *
 * **Eén ding wijkt af: de tabel toont de vraag én het begin van het
 * antwoord.** Bij een statistiek is de naam genoeg om een regel terug te
 * vinden, maar twee vragen beginnen vaak met dezelfde woorden ("wat kost
 * ...", "wat doe je als ..."). Zonder dat stukje antwoord zit je te
 * klikken om te zien welke je te pakken hebt.
 *
 * Zie docs/architecture/modules/faq.md.
 */
const props = defineProps<{
    items: VraagRij[];
    leeg: boolean;
    kop: KoptekstRij;
    opties: FaqOpties;
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
            { title: 'Vragen' },
        ],
    },
});

const venster = ref(false);
const kopVenster = ref(false);
const volgordeVenster = ref(false);

/** De vraag die in het venster staat; null is een nieuwe. */
const gekozen = ref<VraagRij | null>(null);

const nieuw = (): void => {
    gekozen.value = null;
    venster.value = true;
};

const bewerk = (item: VraagRij): void => {
    gekozen.value = item;
    venster.value = true;
};

/** Hoeveel er daadwerkelijk op de website staan. */
const online = computed(
    () => props.items.filter((item) => item.published).length,
);

/**
 * Of de uitleg over het bladeren zin heeft.
 *
 * Het blok op de website bladert per zes. Dat is pas iets om te weten
 * zodra er meer dan zes staan; daarvoor is het ruis.
 */
const bladert = computed(() => online.value > 6);

/**
 * Het begin van het antwoord, voor de tabel.
 *
 * Witregels worden spaties: in een cel van één regel hoort geen alinea.
 */
const begin = (tekst: string): string => {
    const plat = tekst.replace(/\s+/g, ' ').trim();

    return plat.length > 90 ? `${plat.slice(0, 90)}…` : plat;
};

const wisselOnline = async (item: VraagRij): Promise<void> => {
    const aan = !item.published;

    const akkoord = await bevestigBewerken({
        titel: aan
            ? t('Deze vraag op je website zetten?')
            : t('Deze vraag van je website halen?'),
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
        faq.online(item.id).url,
        { published: aan },
        { preserveScroll: true, preserveState: true },
    );
};

const verwijder = async (item: VraagRij): Promise<void> => {
    const akkoord = await bevestigVerwijderen({
        titel: t('Deze vraag verwijderen?'),
        tekst: t('Dit kan niet ongedaan worden gemaakt.'),
    });

    if (!akkoord) {
        return;
    }

    router.delete(faq.destroy(item.id).url, { preserveScroll: true });
};
</script>

<template>
    <Head :title="$t('Vragen')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <header class="max-w-xl space-y-1">
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ $t('Vragen') }}
                </h1>
                <p class="text-sm text-pretty text-muted-foreground">
                    {{
                        $t(
                            'De vragen die je vaker krijgt, met je antwoord eronder. Een bezoeker klikt er een open en de rest blijft dicht.',
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

                <!-- Vanaf twee vragen: met één valt er niets te slepen. -->
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
                    {{ $t('Nieuwe vraag') }}
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
                    {{ $t('Je hebt nog geen vragen.') }}
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
            <p v-if="bladert" class="text-sm text-pretty text-muted-foreground">
                {{
                    $t(
                        'Op je website staan er zes per keer, met knopjes eronder om door te bladeren. Zo blijft het blok even hoog, hoeveel vragen je ook toevoegt. De vragen die je bovenaan sleept staan dus op de eerste bladzijde.',
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
                                {{ $t('Vraag') }}
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
                            <td :data-label="$t('Vraag')" class="px-3 py-2">
                                <span class="block font-medium">
                                    {{ item.question_nl }}
                                </span>
                                <!--
                                    `font-normal` is hier nodig: op een
                                    telefoon is de eerste cel de kop van
                                    het kaartje en staat die in het vet.
                                -->
                                <span
                                    class="block text-xs font-normal text-muted-foreground"
                                >
                                    {{ begin(item.answer_nl) }}
                                </span>
                            </td>

                            <td :data-label="$t('Engels')" class="px-3 py-2">
                                <!--
                                    Of het Engels er staat, en niet de tekst
                                    zelf: die staat in het venster. Wat je
                                    hier wil weten is of er nog werk ligt.
                                -->
                                <span
                                    v-if="item.question_en && item.answer_en"
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
                        'Er staat op dit moment geen enkele vraag op je website; alles staat uit.',
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

    <FaqDialoog
        v-model:open="venster"
        :item="gekozen"
        :opties="props.opties"
        :kan-vertalen="props.kanVertalen"
    />

    <KoptekstDialoog
        v-model:open="kopVenster"
        :kop="props.kop"
        :actie="faq.kop().url"
        :titel="$t('De kop boven je vragen')"
        :uitleg="
            $t(
                'Het opschrift, de titel en de zin eronder. Zet in die laatste zin wat een bezoeker moet doen als zijn vraag er niet bij staat -- anders is een vragenlijst een doodlopende weg voor precies de vraag die jij nog niet had bedacht.',
            )
        "
        :bevestiging="$t('De kop boven je vragen aanpassen?')"
        sleutel="faq"
        :kan-vertalen="props.kanVertalen"
    />

    <FaqVolgordeDialoog v-model:open="volgordeVenster" :items="props.items" />
</template>
