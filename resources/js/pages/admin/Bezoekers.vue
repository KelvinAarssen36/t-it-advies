<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowDownRight,
    ArrowUpRight,
    Eye,
    MailOpen,
    ShieldCheck,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import BezoekGrafiek from '@/components/admin/BezoekGrafiek.vue';
import SegmentToggle from '@/components/SegmentToggle.vue';
import VerlaatPortaal from '@/components/VerlaatPortaal.vue';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/admin';
import visitors from '@/routes/admin/visitors';
import { privacy } from '@/routes';
import type { BezoekCijfers, BezoekRegel } from '@/types/bezoek';

/**
 * Wat de website doet: hoeveel bezoek er is en waar het vandaan komt.
 *
 * **Het enige scherm in het beheergedeelte dat over de website gaat** en
 * niet over het portaal. Het staat hier omdat je het nakíjkt, net als de
 * logboeken ernaast -- de schermen onder Website zijn er om iets te
 * wijzigen.
 *
 * **Dit scherm rekent niets uit.** De totalen, het verschil met de vorige
 * periode en de uitsplitsingen komen kant-en-klaar van de server; zie
 * `Bezoekcijfers`. Dat is met opzet: welke periode "de vorige" is en wat
 * er moet gebeuren als die nul was, zijn inhoudelijke beslissingen en geen
 * opmaak.
 *
 * **De uitleg onderaan is geen bijzaak.** Een cijfer dat je niet begrijpt
 * is een cijfer waar je verkeerde conclusies uit trekt, en bij bezoekcijfers
 * is dat bijna gegarandeerd: "bezoekers" betekent hier iets anders dan bij
 * Google Analytics. Dat staat er dus bij, in gewone taal, met de link naar
 * de privacyverklaring ernaast.
 *
 * Zie docs/architecture/bezoekcijfers.md.
 */
const props = defineProps<{
    cijfers: BezoekCijfers;
    perioden: number[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beheer', href: dashboard() },
            { title: 'Bezoekers' },
        ],
    },
});

const periode = computed({
    get: () => String(props.cijfers.dagen),
    set: (waarde: string) => {
        router.get(
            visitors.index().url,
            { dagen: waarde },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    },
});

const periodeKeuzes = computed(() =>
    props.perioden.map((dagen) => ({
        value: String(dagen),
        label: t(':aantal dagen', { aantal: dagen }),
    })),
);

/** Of er al iets gemeten is. Zo niet, dan is een grafiek misleidend. */
const leeg = computed(() => props.cijfers.eerste === null);

/**
 * De opschriften van de uitsplitsingen.
 *
 * De server stuurt sleutels (`linkedin`, `mobiel`, `nl`) en geen teksten,
 * want het portaal is tweetalig. Staat een sleutel niet in deze lijst, dan
 * is het de host van een verwijzer die we niet kennen -- en die zetten we
 * neer zoals hij is.
 */
const OPSCHRIFTEN: Record<string, () => string> = {
    direct: () => t('Rechtstreeks'),
    overig: () => t('Overig'),
    linkedin: () => 'LinkedIn',
    google: () => 'Google',
    bing: () => 'Bing',
    duckduckgo: () => 'DuckDuckGo',
    yahoo: () => 'Yahoo',
    facebook: () => 'Facebook',
    instagram: () => 'Instagram',
    youtube: () => 'YouTube',
    github: () => 'GitHub',
    mobiel: () => t('Mobiel'),
    tablet: () => t('Tablet'),
    desktop: () => t('Desktop'),
    nl: () => t('Nederlands'),
    en: () => t('Engels'),
};

const opschrift = (sleutel: string): string =>
    OPSCHRIFTEN[sleutel]?.() ?? sleutel;

/** Het aandeel van een regel binnen zijn eigen lijst, in procenten. */
const aandeel = (regels: BezoekRegel[], regel: BezoekRegel): number => {
    const totaal = regels.reduce((som, rij) => som + rij.aantal, 0);

    return totaal === 0 ? 0 : Math.round((regel.aantal / totaal) * 100);
};

const uitsplitsingen = computed(() => [
    {
        sleutel: 'verwijzer',
        titel: t('Waar ze vandaan komen'),
        leeg: t('Nog geen verwijzingen gemeten.'),
    },
    {
        sleutel: 'apparaat',
        titel: t('Apparaat'),
        leeg: t('Nog geen apparaten gemeten.'),
    },
    {
        sleutel: 'taal',
        titel: t('Taal'),
        leeg: t('Nog geen talen gemeten.'),
    },
]);

const alsDatum = (iso: string): string =>
    new Intl.DateTimeFormat('nl-NL', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date(`${iso}T12:00:00`));
</script>

<template>
    <Head :title="$t('Bezoekers')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <header class="max-w-xl space-y-1">
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ $t('Bezoekers') }}
                </h1>
                <p class="text-sm text-pretty text-muted-foreground">
                    {{
                        $t(
                            'Hoeveel mensen je website bekijken, waar ze vandaan komen en waarmee ze kijken.',
                        )
                    }}
                </p>
            </header>

            <SegmentToggle
                v-model="periode"
                :options="periodeKeuzes"
                :groep-label="$t('De periode')"
            />
        </div>

        <!--
            Nog niets gemeten. Een grafiek met nullen zou hier zeggen "er
            komt niemand", en dat is iets anders dan "we zijn pas net
            begonnen met kijken".
        -->
        <div v-if="leeg" class="brand-ervaring-leeg">
            <Eye class="size-5 shrink-0 text-muted-foreground" />
            <div class="space-y-1">
                <p class="font-medium">
                    {{ $t('Er is nog geen bezoek gemeten.') }}
                </p>
                <p class="text-sm text-pretty text-muted-foreground">
                    {{
                        $t(
                            'Zodra de eerste bezoeker op je website komt, staat hij hier. Het tellen begint vanaf het moment dat dit onderdeel live staat; over de tijd daarvoor weten we niets.',
                        )
                    }}
                </p>
            </div>
        </div>

        <template v-else>
            <!-- De drie hoofdcijfers. -->
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="brand-bezoekcijfer">
                    <p class="brand-bezoekcijfer-kop">
                        <Users class="size-4" />
                        {{ $t('Bezoekers') }}
                    </p>
                    <p class="brand-bezoekcijfer-getal">
                        {{
                            props.cijfers.totalen.bezoekers.toLocaleString(
                                'nl-NL',
                            )
                        }}
                    </p>
                    <p
                        v-if="props.cijfers.verschil.bezoekers !== null"
                        class="brand-bezoekcijfer-verschil"
                        :data-omlaag="
                            props.cijfers.verschil.bezoekers < 0
                                ? ''
                                : undefined
                        "
                    >
                        <ArrowUpRight
                            v-if="props.cijfers.verschil.bezoekers >= 0"
                            class="size-3.5"
                        />
                        <ArrowDownRight v-else class="size-3.5" />
                        {{
                            $t(':procent% t.o.v. de vorige periode', {
                                procent: Math.abs(
                                    props.cijfers.verschil.bezoekers,
                                ),
                            })
                        }}
                    </p>
                </div>

                <div class="brand-bezoekcijfer">
                    <p class="brand-bezoekcijfer-kop">
                        <Eye class="size-4" />
                        {{ $t('Paginaweergaven') }}
                    </p>
                    <p class="brand-bezoekcijfer-getal">
                        {{
                            props.cijfers.totalen.weergaven.toLocaleString(
                                'nl-NL',
                            )
                        }}
                    </p>
                    <p
                        v-if="props.cijfers.verschil.weergaven !== null"
                        class="brand-bezoekcijfer-verschil"
                        :data-omlaag="
                            props.cijfers.verschil.weergaven < 0
                                ? ''
                                : undefined
                        "
                    >
                        <ArrowUpRight
                            v-if="props.cijfers.verschil.weergaven >= 0"
                            class="size-3.5"
                        />
                        <ArrowDownRight v-else class="size-3.5" />
                        {{
                            $t(':procent% t.o.v. de vorige periode', {
                                procent: Math.abs(
                                    props.cijfers.verschil.weergaven,
                                ),
                            })
                        }}
                    </p>
                </div>

                <!--
                    Het enige cijfer dat zegt of de site zijn wérk doet in
                    plaats van alleen bekeken te worden.
                -->
                <div class="brand-bezoekcijfer">
                    <p class="brand-bezoekcijfer-kop">
                        <MailOpen class="size-4" />
                        {{ $t('Berichten via het formulier') }}
                    </p>
                    <p class="brand-bezoekcijfer-getal">
                        {{
                            props.cijfers.totalen.berichten.toLocaleString(
                                'nl-NL',
                            )
                        }}
                    </p>
                    <p
                        v-if="props.cijfers.verschil.berichten !== null"
                        class="brand-bezoekcijfer-verschil"
                        :data-omlaag="
                            props.cijfers.verschil.berichten < 0
                                ? ''
                                : undefined
                        "
                    >
                        <ArrowUpRight
                            v-if="props.cijfers.verschil.berichten >= 0"
                            class="size-3.5"
                        />
                        <ArrowDownRight v-else class="size-3.5" />
                        {{
                            $t(':procent% t.o.v. de vorige periode', {
                                procent: Math.abs(
                                    props.cijfers.verschil.berichten,
                                ),
                            })
                        }}
                    </p>
                </div>
            </div>

            <div class="rounded-xl border p-4">
                <BezoekGrafiek :reeks="props.cijfers.reeks" />
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <div
                    v-for="vak in uitsplitsingen"
                    :key="vak.sleutel"
                    class="rounded-xl border p-4"
                >
                    <p class="brand-bezoeklijst-kop">{{ vak.titel }}</p>

                    <p
                        v-if="
                            (props.cijfers.dimensies[vak.sleutel] ?? [])
                                .length === 0
                        "
                        class="text-sm text-muted-foreground"
                    >
                        {{ vak.leeg }}
                    </p>

                    <ul v-else class="space-y-2.5">
                        <li
                            v-for="regel in props.cijfers.dimensies[
                                vak.sleutel
                            ]"
                            :key="regel.naam"
                            class="space-y-1"
                        >
                            <span
                                class="flex items-baseline justify-between gap-2"
                            >
                                <span class="min-w-0 truncate text-sm">
                                    {{ opschrift(regel.naam) }}
                                </span>
                                <span
                                    class="shrink-0 text-xs text-muted-foreground tabular-nums"
                                >
                                    {{ regel.aantal.toLocaleString('nl-NL') }}
                                    ·
                                    {{
                                        aandeel(
                                            props.cijfers.dimensies[
                                                vak.sleutel
                                            ],
                                            regel,
                                        )
                                    }}%
                                </span>
                            </span>

                            <span
                                class="brand-bezoekbalk"
                                aria-hidden="true"
                                :style="{
                                    '--deel': `${aandeel(
                                        props.cijfers.dimensies[vak.sleutel],
                                        regel,
                                    )}%`,
                                }"
                            />
                        </li>
                    </ul>
                </div>
            </div>
        </template>

        <!--
            Wat de cijfers betekenen. Dit hoort hier en niet in de
            handleiding: een cijfer dat je niet begrijpt, lees je verkeerd
            op het moment dat je ernaar kijkt.
        -->
        <div class="brand-bezoekuitleg">
            <ShieldCheck class="size-5 shrink-0 text-success" />
            <div class="space-y-2">
                <p class="text-sm text-pretty">
                    {{
                        $t(
                            'Een bezoeker wordt één keer per dag geteld. Komt iemand drie dagen achter elkaar, dan zijn dat over die week drie bezoekers -- we kunnen niet zien dat het dezelfde persoon was, en dat is precies de bedoeling.',
                        )
                    }}
                </p>
                <p class="text-sm text-pretty text-muted-foreground">
                    {{
                        $t(
                            'Hiervoor worden geen cookies gebruikt en er wordt niets op het apparaat van je bezoeker opgeslagen. IP-adressen bewaren we niet; ze gaan door een onomkeerbare berekening met een sleutel die elke nacht wordt weggegooid. Daarom hoeft er geen cookiemelding op je website te staan.',
                        )
                    }}
                </p>
                <p
                    v-if="props.cijfers.eerste"
                    class="text-xs text-muted-foreground"
                >
                    {{
                        $t('We meten sinds :datum.', {
                            datum: alsDatum(props.cijfers.eerste),
                        })
                    }}
                </p>
            </div>
        </div>

        <p class="text-sm text-muted-foreground">
            <Link :href="privacy()" class="underline-offset-4 hover:underline">
                {{ $t('Bekijk de privacyverklaring op je website') }}
            </Link>
            <VerlaatPortaal />
        </p>
    </div>
</template>
