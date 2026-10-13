<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowUpDown,
    Award,
    Eye,
    EyeOff,
    GraduationCap,
    Globe,
    Heading,
    LayoutGrid,
    Pencil,
    Plus,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import VerlaatPortaal from '@/components/VerlaatPortaal.vue';
import CertificaatDialoog from '@/components/website/CertificaatDialoog.vue';
import CertificaatVolgordeDialoog from '@/components/website/CertificaatVolgordeDialoog.vue';
import KoptekstDialoog from '@/components/website/KoptekstDialoog.vue';
import OpleidingDialoog from '@/components/website/OpleidingDialoog.vue';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { bevestigBewerken, bevestigVerwijderen } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import site from '@/routes/site';
import website from '@/routes/website';
import certificaten from '@/routes/website/certificaten';
import type {
    CertificaatRij,
    CertificatenOpties,
    OpleidingRij,
} from '@/types/certificaten';
import type { KoptekstRij } from '@/types/secties';

/**
 * De certificaten op je website, met de opleidingen eronder.
 *
 * **Twee lijsten op één scherm**, want op de website zijn ze samen één
 * blok. Ze in twee schermen uit elkaar trekken zou betekenen dat de
 * eigenaar twee plekken moet onthouden voor iets dat hij als één ding
 * ziet.
 *
 * **Geen zoekveld en geen paginering**, net als bij de diensten: een
 * handvol certificaten is geen lijst om in te zoeken.
 *
 * **Wél een eigen volgorde voor de certificaten**, want welk certificaat
 * je vooraan zet is een redactionele keuze en geen datum. De opleidingen
 * ordenen zichzelf op periode; zie App\Models\Education.
 *
 * Zie docs/architecture/modules/certificaten.md.
 */
const props = defineProps<{
    items: CertificaatRij[];
    opleidingen: OpleidingRij[];
    leeg: boolean;
    kop: KoptekstRij;
    opties: CertificatenOpties;
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
            { title: 'Certificaten' },
        ],
    },
});

const venster = ref(false);
const opleidingVenster = ref(false);
const kopVenster = ref(false);
const volgordeVenster = ref(false);

/** Het certificaat dat in het venster staat; null is een nieuw. */
const gekozen = ref<CertificaatRij | null>(null);
const gekozenOpleiding = ref<OpleidingRij | null>(null);

const nieuw = (): void => {
    gekozen.value = null;
    venster.value = true;
};

const bewerk = (item: CertificaatRij): void => {
    gekozen.value = item;
    venster.value = true;
};

const nieuweOpleiding = (): void => {
    gekozenOpleiding.value = null;
    opleidingVenster.value = true;
};

const bewerkOpleiding = (item: OpleidingRij): void => {
    gekozenOpleiding.value = item;
    opleidingVenster.value = true;
};

/** Hoeveel certificaten er daadwerkelijk op de website staan. */
const online = computed(
    () => props.items.filter((item) => item.published).length,
);

/**
 * Hoeveel certificaten er tegelijk op de website passen.
 *
 * Moet gelijk blijven aan `BREED` in CertificatenSection.vue. Hier staat
 * het alleen om uit te leggen wat de bezoeker ziet; de echte indeling
 * gebeurt daar.
 */
const PER_PAGINA = 8;

/**
 * Staat er meer dan één pagina op de website?
 *
 * Geen waarschuwing maar een mededeling. Er is geen maximum -- de klant
 * loopt nergens tegen een muur op -- maar hij hoort wel te weten dat het
 * negende certificaat achter een knopje staat en niet meteen in beeld.
 */
const bladert = computed(() => online.value > PER_PAGINA);

/** Hoeveel er verlopen zijn; dat is iets om zelf te bekijken. */
const verlopen = computed(
    () => props.items.filter((item) => item.verlopen).length,
);

const wisselOnline = async (item: CertificaatRij): Promise<void> => {
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
        certificaten.online(item.id).url,
        { published: aan },
        { preserveScroll: true, preserveState: true },
    );
};

const verwijder = async (item: CertificaatRij): Promise<void> => {
    const akkoord = await bevestigVerwijderen({
        titel: t('":naam" verwijderen?', { naam: item.title_nl }),
        tekst: t('Dit kan niet ongedaan worden gemaakt. Het logo gaat mee.'),
    });

    if (!akkoord) {
        return;
    }

    router.delete(certificaten.destroy(item.id).url, { preserveScroll: true });
};

const wisselOpleidingOnline = async (item: OpleidingRij): Promise<void> => {
    const aan = !item.published;

    const akkoord = await bevestigBewerken({
        titel: aan
            ? t('":naam" op je website zetten?', { naam: item.title_nl })
            : t('":naam" van je website halen?', { naam: item.title_nl }),
    });

    if (!akkoord) {
        return;
    }

    router.patch(
        certificaten.opleidingen.online(item.id).url,
        { published: aan },
        { preserveScroll: true, preserveState: true },
    );
};

const verwijderOpleiding = async (item: OpleidingRij): Promise<void> => {
    const akkoord = await bevestigVerwijderen({
        titel: t('":naam" verwijderen?', { naam: item.title_nl }),
        tekst: t('Dit kan niet ongedaan worden gemaakt.'),
    });

    if (!akkoord) {
        return;
    }

    router.delete(certificaten.opleidingen.destroy(item.id).url, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="$t('Certificaten')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <header class="max-w-xl space-y-1">
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ $t('Certificaten') }}
                </h1>
                <p class="text-sm text-pretty text-muted-foreground">
                    {{
                        $t(
                            'Wat je hebt gehaald en bij wie. Op je website staan ze als tegels met het logo van de uitgever, in de volgorde die je hier instelt.',
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
                    {{ $t('Nieuw certificaat') }}
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
                    {{ $t('Je hebt nog geen certificaten.') }}
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

        <template v-else-if="props.items.length > 0">
            <p
                v-if="bladert"
                class="flex items-center gap-2 text-sm text-muted-foreground"
            >
                <LayoutGrid class="size-4 shrink-0" />
                <span>
                    {{
                        $t(
                            'Je hebt :aantal certificaten online. Er staan er acht tegelijk op je website -- op een telefoon vier -- en je bezoeker bladert met de knopjes eronder naar de rest.',
                            { aantal: online },
                        )
                    }}
                </span>
            </p>

            <p
                v-if="verlopen > 0"
                class="flex items-center gap-2 text-sm text-muted-foreground"
            >
                <TriangleAlert class="size-4 shrink-0 text-warning" />
                <span>
                    {{
                        $t(
                            'Van :aantal certificaten is de geldigheid verlopen. Ze blijven gewoon op je website staan met een klein label erbij -- behaald is behaald. Wil je dat niet, haal ze dan weg of zet ze offline.',
                            { aantal: verlopen },
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
                                {{ $t('Certificaat') }}
                            </th>
                            <th class="px-3 py-2 font-medium">
                                {{ $t('Behaald') }}
                            </th>
                            <th class="px-3 py-2 font-medium">
                                {{ $t('Geldig tot') }}
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
                                :data-label="$t('Certificaat')"
                                class="px-3 py-2"
                            >
                                <span class="flex items-start gap-2.5">
                                    <span class="brand-logo-rondje is-klein">
                                        <img
                                            v-if="item.logo"
                                            :src="item.logo"
                                            alt=""
                                        />
                                        <Award v-else class="size-3.5" />
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
                                            dit erft de uitgever dat en
                                            leest de hele kaart als één
                                            zware regel.
                                        -->
                                        <span
                                            class="block text-xs font-normal text-muted-foreground"
                                        >
                                            {{ item.issuer }}
                                        </span>
                                    </span>
                                </span>
                            </td>

                            <td :data-label="$t('Behaald')" class="px-3 py-2">
                                {{ item.behaald }}
                            </td>

                            <td
                                :data-label="$t('Geldig tot')"
                                class="px-3 py-2"
                            >
                                <span
                                    v-if="!item.geldig_tot"
                                    class="text-muted-foreground"
                                >
                                    {{ $t('verloopt niet') }}
                                </span>
                                <span
                                    v-else-if="item.verlopen"
                                    class="inline-flex items-center gap-1 text-warning"
                                >
                                    <TriangleAlert class="size-3.5 shrink-0" />
                                    {{ item.geldig_tot }}
                                </span>
                                <span v-else>{{ item.geldig_tot }}</span>
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

        <!--
            De opleidingen. Een tweede, kleiner blok en geen tweede
            scherm: op de website staan ze in hetzelfde onderdeel, onder
            het raster met certificaten.
        -->
        <div class="flex flex-col gap-3 border-t border-border pt-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="max-w-xl space-y-1">
                    <h2
                        class="flex items-center gap-2 text-base font-semibold tracking-tight"
                    >
                        <GraduationCap class="size-4 text-muted-foreground" />
                        {{ $t('Opleiding') }}
                    </h2>
                    <p class="text-sm text-pretty text-muted-foreground">
                        {{
                            $t(
                                'Optioneel. Zet je hier iets neer, dan komt er onder je certificaten een kort lijstje te staan. Laat je het leeg, dan is er niets te zien.',
                            )
                        }}
                    </p>
                </div>

                <Button
                    variant="aanmaken"
                    size="sm"
                    class="gap-2"
                    @click="nieuweOpleiding"
                >
                    <Plus class="size-4" />
                    {{ $t('Nieuwe opleiding') }}
                </Button>
            </div>

            <div
                v-if="props.opleidingen.length > 0"
                class="brand-tabelvak brand-schuif-x brand-scrollbar rounded-xl border"
            >
                <table class="brand-tabel-kaarten w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="px-3 py-2 font-medium">
                                {{ $t('Opleiding') }}
                            </th>
                            <th class="px-3 py-2 font-medium">
                                {{ $t('Periode') }}
                            </th>
                            <th class="px-3 py-2 font-medium">
                                {{ $t('Op de website') }}
                            </th>
                            <th class="px-3 py-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in props.opleidingen"
                            :key="item.id"
                            class="border-t"
                            :data-offline="item.published ? undefined : ''"
                        >
                            <td :data-label="$t('Opleiding')" class="px-3 py-2">
                                <span class="block font-medium">
                                    {{ item.title_nl }}
                                </span>
                                <span
                                    class="block text-xs font-normal text-muted-foreground"
                                >
                                    {{ item.institution }}
                                    <template v-if="item.level_nl">
                                        <span aria-hidden="true">·</span>
                                        {{ item.level_nl }}
                                    </template>
                                </span>
                            </td>

                            <td :data-label="$t('Periode')" class="px-3 py-2">
                                {{ item.periode }}
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
                                        @update:model-value="
                                            wisselOpleidingOnline(item)
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
                                        @click="bewerkOpleiding(item)"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        variant="verwijderen-zacht"
                                        size="icon-sm"
                                        :aria-label="$t('Verwijderen')"
                                        @click="verwijderOpleiding(item)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="text-sm text-muted-foreground">
            <Link
                :href="website.index()"
                class="underline-offset-4 hover:underline"
            >
                {{ $t('Terug naar de indeling van je website') }}
            </Link>
        </p>
    </div>

    <CertificaatDialoog
        v-model:open="venster"
        :item="gekozen"
        :opties="props.opties"
        :kan-vertalen="props.kanVertalen"
    />

    <OpleidingDialoog
        v-model:open="opleidingVenster"
        :item="gekozenOpleiding"
        :opties="props.opties"
        :kan-vertalen="props.kanVertalen"
    />

    <KoptekstDialoog
        v-model:open="kopVenster"
        :kop="props.kop"
        :actie="certificaten.kop().url"
        :titel="$t('De kop boven je certificaten')"
        :uitleg="
            $t(
                'Het opschrift, de titel en de zin eronder. Op je website staat dit boven de tegels.',
            )
        "
        :bevestiging="$t('De kop boven je certificaten aanpassen?')"
        sleutel="certificaten"
        :kan-vertalen="props.kanVertalen"
    />

    <CertificaatVolgordeDialoog
        v-model:open="volgordeVenster"
        :items="props.items"
    />
</template>
