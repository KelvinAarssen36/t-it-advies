<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowUpDown,
    Eye,
    EyeOff,
    Globe,
    Heading,
    Info,
    Languages,
    Layout,
    Mail,
    Pencil,
    Plus,
    Star,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import VerlaatPortaal from '@/components/VerlaatPortaal.vue';
import ContactMailDialoog from '@/components/website/ContactMailDialoog.vue';
import ContactOnderwerpDialoog from '@/components/website/ContactOnderwerpDialoog.vue';
import ContactVeldenVak from '@/components/website/ContactVeldenVak.vue';
import ContactVolgordeDialoog from '@/components/website/ContactVolgordeDialoog.vue';
import ContactWeergaveDialoog from '@/components/website/ContactWeergaveDialoog.vue';
import KoptekstDialoog from '@/components/website/KoptekstDialoog.vue';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { bevestigBewerken, bevestigVerwijderen } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import site from '@/routes/site';
import website from '@/routes/website';
import contact from '@/routes/website/contact';
import type {
    ContactInstellingen,
    ContactOpties,
    OnderwerpRij,
    VeldRij,
} from '@/types/contact';
import type { KoptekstRij } from '@/types/secties';

/**
 * Website → Contact: het contactformulier beheren.
 *
 * **Dit scherm beheert het formulier, niet de berichten.** Die staan
 * onder Beheer → Aanvragen -- daar sla je na, hier maak je.
 *
 * Drie blokken onder elkaar, in de volgorde waarin je eraan denkt:
 *
 * 1. **De onderwerpen** waaruit een bezoeker kan kiezen. Een gewone lijst
 *    zoals bij de andere modules: aanmaken, slepen, aan en uit.
 * 2. **De velden** van het formulier. Geen lijst die je aanmaakt maar een
 *    vaste set met per veld een stand; zie ContactVeldenVak.
 * 3. **De bevestigingsmail** en de weergave, allebei achter een knop in de
 *    kop -- dat zijn instellingen en geen inhoud.
 *
 * Zie docs/architecture/modules/contact.md.
 */
const props = defineProps<{
    onderwerpen: OnderwerpRij[];
    velden: VeldRij[];
    leeg: boolean;
    kop: KoptekstRij;
    instellingen: ContactInstellingen;
    opties: ContactOpties;
    kanVertalen: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Website', href: '/website' },
            { title: 'Contact' },
        ],
    },
});

const venster = ref(false);
const kopVenster = ref(false);
const volgordeVenster = ref(false);
const weergaveVenster = ref(false);
const mailVenster = ref(false);

const gekozen = ref<OnderwerpRij | null>(null);

const nieuw = (): void => {
    gekozen.value = null;
    venster.value = true;
};

const bewerk = (item: OnderwerpRij): void => {
    gekozen.value = item;
    venster.value = true;
};

/** Hoeveel onderwerpen de bezoeker op dit moment kan kiezen. */
const online = computed(
    () => props.onderwerpen.filter((item) => item.published).length,
);

/** De huidige weergave in woorden, voor de knop in de kop. */
const weergaveLabel = computed(
    () =>
        props.opties.weergave.find(
            (optie) => optie.value === props.instellingen.weergave,
        )?.label ?? '',
);

const wisselOnline = async (item: OnderwerpRij): Promise<void> => {
    const aan = !item.published;

    const akkoord = await bevestigBewerken({
        titel: aan
            ? t('":naam" op je website zetten?', { naam: item.label_nl })
            : t('":naam" van je website halen?', { naam: item.label_nl }),
        tekst: aan
            ? undefined
            : t(
                  'Het blijft hier gewoon staan; bezoekers kunnen het alleen niet meer kiezen.',
              ),
    });

    if (!akkoord) {
        return;
    }

    router.patch(
        contact.online(item.id).url,
        { published: aan },
        { preserveScroll: true, preserveState: true },
    );
};

const verwijder = async (item: OnderwerpRij): Promise<void> => {
    const akkoord = await bevestigVerwijderen({
        titel: t('":naam" verwijderen?', { naam: item.label_nl }),

        /*
         * Hangen er aanvragen aan, dan zeggen we dat erbij. Die blijven
         * leesbaar -- ze bewaren de onderwerptekst zelf -- maar je wilt
         * weten dat je iets weghaalt waar geschiedenis aan hangt.
         */
        tekst:
            item.aanvragen > 0
                ? t(
                      'Er horen :aantal aanvragen bij dit onderwerp. Die blijven staan en blijven leesbaar; alleen de koppeling verdwijnt.',
                      { aantal: item.aanvragen },
                  )
                : t('Dit kan niet ongedaan worden gemaakt.'),
    });

    if (!akkoord) {
        return;
    }

    router.delete(contact.destroy(item.id).url, { preserveScroll: true });
};
</script>

<template>
    <Head :title="$t('Contact')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <header class="max-w-xl space-y-1">
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ $t('Contact') }}
                </h1>
                <p class="text-sm text-pretty text-muted-foreground">
                    {{
                        $t(
                            'Je contactformulier: waar bezoekers uit kunnen kiezen, wat ze moeten invullen en wat ze terugkrijgen.',
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
                    variant="outline"
                    class="gap-2"
                    @click="weergaveVenster = true"
                >
                    <Layout class="size-4" />
                    {{ $t('Weergave') }}
                </Button>

                <Button
                    variant="outline"
                    class="gap-2"
                    @click="mailVenster = true"
                >
                    <Mail class="size-4" />
                    {{ $t('Bevestigingsmail') }}
                </Button>

                <Button variant="aanmaken" class="gap-2" @click="nieuw">
                    <Plus class="size-4" />
                    {{ $t('Nieuw onderwerp') }}
                </Button>
            </div>
        </div>

        <!--
            Hoe het contact nu op de site staat. Eén regel, want het is de
            keuze die het meest bepaalt wat een bezoeker ziet en hij zit
            achter een knop.
        -->
        <p
            class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
        >
            <Layout class="size-4 shrink-0" />
            <span>{{ weergaveLabel }}</span>
        </p>

        <!-- 1. De onderwerpen -->
        <section class="space-y-3">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <div class="space-y-1">
                    <h2 class="brand-bezoeklijst-kop">
                        {{ $t('De onderwerpen') }}
                    </h2>
                    <p
                        class="max-w-prose text-sm text-pretty text-muted-foreground"
                    >
                        {{
                            $t(
                                'Waar een bezoeker uit kan kiezen bij "Onderwerp". Hoe duidelijker de onderwerpen, hoe beter je vooraf weet waar een bericht over gaat.',
                            )
                        }}
                    </p>
                </div>

                <Button
                    v-if="props.onderwerpen.length > 1"
                    variant="outline"
                    size="sm"
                    class="gap-2"
                    @click="volgordeVenster = true"
                >
                    <ArrowUpDown class="size-4" />
                    {{ $t('Volgorde') }}
                </Button>
            </div>

            <!--
                Nog geen onderwerpen. **Dit is geen waarschuwing**, anders
                dan bij de andere modules: zonder onderwerpen is het
                onderwerpveld gewoon een tekstvak, en dat werkt prima. Het
                blok verdwijnt dus niet van de website.
            -->
            <div v-if="props.leeg" class="brand-bezoekuitleg">
                <Info class="size-5 shrink-0" aria-hidden="true" />
                <div class="space-y-1">
                    <p class="font-medium">
                        {{ $t('Je hebt nog geen onderwerpen.') }}
                    </p>
                    <p class="text-sm text-pretty text-muted-foreground">
                        {{
                            $t(
                                'Dat mag: dan typt je bezoeker zelf een onderwerp, zoals nu. Zet je ze er wel in, dan krijgt hij een keuzelijst en weet je vooraf waar een bericht over gaat.',
                            )
                        }}
                    </p>
                </div>
            </div>

            <template v-else>
                <div
                    class="brand-tabelvak brand-schuif-x brand-scrollbar rounded-xl border"
                >
                    <table class="brand-tabel-kaarten w-full text-sm">
                        <thead class="bg-muted/50 text-left">
                            <tr>
                                <th class="px-3 py-2 font-medium">
                                    {{ $t('Onderwerp') }}
                                </th>
                                <th class="px-3 py-2 font-medium">
                                    {{ $t('Engels') }}
                                </th>
                                <th class="px-3 py-2 font-medium">
                                    {{ $t('Aanvragen') }}
                                </th>
                                <th class="px-3 py-2 font-medium">
                                    {{ $t('Online') }}
                                </th>
                                <th class="px-3 py-2">
                                    <span class="sr-only">
                                        {{ $t('Acties') }}
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in props.onderwerpen"
                                :key="item.id"
                                class="border-t"
                            >
                                <td
                                    :data-label="$t('Onderwerp')"
                                    class="px-3 py-2 font-medium"
                                >
                                    <span
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        {{ item.label_nl }}

                                        <!--
                                            Uitgelicht. Een merkje en geen
                                            eigen kolom: het is één vinkje
                                            dat zelden aanstaat, en een
                                            kolom die meestal leeg is maakt
                                            de tabel breder zonder iets te
                                            zeggen.
                                        -->
                                        <span
                                            v-if="item.featured"
                                            class="brand-uitgelicht"
                                        >
                                            <Star
                                                class="size-3"
                                                aria-hidden="true"
                                            />
                                            {{ $t('uitgelicht') }}
                                        </span>
                                    </span>
                                </td>

                                <!--
                                    Dezelfde drie standen als bij de
                                    diensten: compleet, automatisch
                                    vertaald en nog niet gedaan. Dat
                                    laatste staat in de warme kleur, want
                                    dan ziet een Engelse bezoeker Nederlands.
                                -->
                                <td
                                    :data-label="$t('Engels')"
                                    class="px-3 py-2"
                                >
                                    <span
                                        v-if="!item.label_en"
                                        class="inline-flex items-center gap-1 text-warning"
                                    >
                                        <Languages class="size-3.5 shrink-0" />
                                        {{ $t('niet compleet') }}
                                    </span>
                                    <span
                                        v-else-if="item.automatischVertaald"
                                        class="inline-flex items-center gap-1 text-muted-foreground"
                                    >
                                        <Languages class="size-3.5 shrink-0" />
                                        {{ $t('automatisch') }}
                                    </span>
                                    <span v-else class="text-muted-foreground">
                                        {{ $t('compleet') }}
                                    </span>
                                </td>

                                <td
                                    :data-label="$t('Aanvragen')"
                                    class="px-3 py-2 text-muted-foreground tabular-nums"
                                >
                                    {{ item.aanvragen }}
                                </td>

                                <td
                                    :data-label="$t('Online')"
                                    class="px-3 py-2"
                                    @click.stop
                                >
                                    <span class="flex items-center gap-2">
                                        <Switch
                                            :model-value="item.published"
                                            @update:model-value="
                                                wisselOnline(item)
                                            "
                                        />
                                        <Eye
                                            v-if="item.published"
                                            class="size-4 text-muted-foreground"
                                        />
                                        <EyeOff
                                            v-else
                                            class="size-4 text-muted-foreground"
                                        />
                                    </span>
                                </td>

                                <td class="px-3 py-2">
                                    <span class="flex justify-end gap-1">
                                        <Button
                                            variant="bewerken-zacht"
                                            size="icon-sm"
                                            @click="bewerk(item)"
                                        >
                                            <Pencil class="size-4" />
                                            <span class="sr-only">
                                                {{ $t('Bewerken') }}
                                            </span>
                                        </Button>
                                        <Button
                                            variant="verwijderen-zacht"
                                            size="icon-sm"
                                            @click="verwijder(item)"
                                        >
                                            <Trash2 class="size-4" />
                                            <span class="sr-only">
                                                {{ $t('Verwijderen') }}
                                            </span>
                                        </Button>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p
                    v-if="online === 0"
                    class="flex items-start gap-2 text-sm text-muted-foreground"
                >
                    <Info class="mt-0.5 size-4 shrink-0" />
                    <span>
                        {{
                            $t(
                                'Alles staat uit, dus je bezoeker typt zelf een onderwerp. Zet er minstens één online om hem te laten kiezen.',
                            )
                        }}
                    </span>
                </p>
            </template>
        </section>

        <!-- 2. De velden -->
        <ContactVeldenVak :velden="props.velden" :opties="props.opties" />

        <p class="text-sm text-muted-foreground">
            <Link
                :href="website.index()"
                class="underline-offset-4 hover:underline"
            >
                {{ $t('Terug naar de indeling van je website') }}
            </Link>
        </p>
    </div>

    <ContactOnderwerpDialoog
        v-model:open="venster"
        :item="gekozen"
        :kan-vertalen="props.kanVertalen"
    />

    <KoptekstDialoog
        v-model:open="kopVenster"
        :kop="props.kop"
        :actie="contact.kop().url"
        :titel="$t('De kop boven je contactformulier')"
        :uitleg="
            $t(
                'Het opschrift, de titel en de zin eronder. Op je website staat dit boven het formulier.',
            )
        "
        :bevestiging="$t('De kop boven je contactformulier aanpassen?')"
        sleutel="contact"
        :kan-vertalen="props.kanVertalen"
    />

    <ContactVolgordeDialoog
        v-model:open="volgordeVenster"
        :items="props.onderwerpen"
    />

    <ContactWeergaveDialoog
        v-model:open="weergaveVenster"
        :weergave="props.instellingen.weergave"
        :opties="props.opties"
    />

    <ContactMailDialoog
        v-model:open="mailVenster"
        :instellingen="props.instellingen"
        :kan-vertalen="props.kanVertalen"
    />
</template>
