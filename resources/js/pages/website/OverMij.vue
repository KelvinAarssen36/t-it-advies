<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowUpDown,
    ExternalLink,
    Globe,
    Heading,
    Image as Beeld,
    Pencil,
    Plus,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import SegmentToggle from '@/components/SegmentToggle.vue';
import VerlaatPortaal from '@/components/VerlaatPortaal.vue';
import KoptekstDialoog from '@/components/website/KoptekstDialoog.vue';
import OverMijBlokDialoog from '@/components/website/OverMijBlokDialoog.vue';
import OverMijFotoDialoog from '@/components/website/OverMijFotoDialoog.vue';
import OverMijPaginaDialoog from '@/components/website/OverMijPaginaDialoog.vue';
import OverMijPuntDialoog from '@/components/website/OverMijPuntDialoog.vue';
import OverMijVolgordeDialoog from '@/components/website/OverMijVolgordeDialoog.vue';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { bevestigBewerken, bevestigVerwijderen } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import site from '@/routes/site';
import website from '@/routes/website';
import overMij from '@/routes/website/over-mij';
import punten from '@/routes/website/over-mij/punten';
import type {
    OverMijInstelling,
    OverMijOpties,
    PuntRij,
} from '@/types/over-mij';
import type { KoptekstRij } from '@/types/secties';

/**
 * Het onderdeel "Over mij".
 *
 * **Dit scherm is een overzicht en geen formulier, en dat is een
 * correctie.** Het was eerst één lange invulpagina met alle velden open.
 * Daar zijn twee dingen mis mee, en de eigenaar wees ze alle twee aan:
 *
 * 1. **Je kon meteen in alles typen.** Overal elders in dit portaal gebeurt
 *    bewerken in een eigen venster, met een bevestiging erachter. Een
 *    pagina waarin je per ongeluk in een veld typt en wegklikt was de enige
 *    plek waar dat anders was.
 * 2. **Je zag niet waar iets terechtkwam.** Twee blokken onder elkaar lezen
 *    als één formulier. Nu staat er een schakelaar boven: je kijkt naar de
 *    ene versie óf naar de andere, nooit naar allebei tegelijk.
 *
 * **De foto staat er als beeld en niet als leeg uploadvak.** Standaard is
 * dat het portret uit de kop; dat is de stand waarin de eigenaar begint, en
 * een leeg vak zou suggereren dat er nog niets is.
 *
 * Zie docs/architecture/modules/over-mij.md.
 */
const props = defineProps<{
    instelling: OverMijInstelling;
    punten: PuntRij[];
    leeg: boolean;
    kop: KoptekstRij;
    opties: OverMijOpties;
    kanVertalen: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Website', href: website.index() },
            { title: 'Over mij' },
        ],
    },
});

/* --- Welke versie je bekijkt ------------------------------------------- */

/**
 * De schakelaar tussen de twee bestemmingen.
 *
 * Geen tabbladen maar de segmentknop die dit portaal al heeft: twee
 * mogelijkheden waarvan er altijd precies één geldt.
 */
const versie = ref<'website' | 'pagina'>('website');

const versies = computed(() => [
    { value: 'website', label: t('Op je website') },
    { value: 'pagina', label: t('Op je aparte pagina') },
]);

/* --- De vensters ------------------------------------------------------- */

const blokVenster = ref(false);
const paginaVenster = ref(false);

/**
 * De foto heeft een eigen venster, en dat is met reden.
 *
 * Hij staat op de voorpagina én op de aparte pagina, dus hij hoort bij geen
 * van de twee. Zat hij in het venster van één ervan, dan moet je onthouden
 * in welk venster dat was -- en dan kun je hem vanuit de andere versie niet
 * aanpassen terwijl je er wel naar kijkt. Nu staat hij ernaast: een eigen
 * knop in beide koppen, een eigen venster, een eigen eindpunt.
 */
const fotoVenster = ref(false);
const kopVenster = ref(false);
const puntVenster = ref(false);
const volgordeVenster = ref(false);

const gekozenPunt = ref<PuntRij | null>(null);

const nieuwPunt = (): void => {
    gekozenPunt.value = null;
    puntVenster.value = true;
};

const bewerkPunt = (punt: PuntRij): void => {
    gekozenPunt.value = punt;
    puntVenster.value = true;
};

const vol = computed(() => props.punten.length >= props.opties.puntenMax);

const verwijderPunt = async (punt: PuntRij): Promise<void> => {
    const akkoord = await bevestigVerwijderen({
        titel: t('":naam" verwijderen?', { naam: punt.text_nl }),
        tekst: t('Dit kan niet ongedaan worden gemaakt.'),
    });

    if (!akkoord) {
        return;
    }

    router.delete(punten.destroy(punt.id).url, { preserveScroll: true });
};

/* --- Het schuifje van de aparte pagina --------------------------------- */

/**
 * Of de pagina echt te bezoeken is.
 *
 * Aan staan is niet hetzelfde als klaar zijn: zonder verhaal bestaat de
 * pagina niet en komt er ook geen knop op de voorpagina. Dezelfde vraag als
 * `AboutSetting::paginaStaatKlaar()` op de server; die is de bron, dit is
 * alleen om het hier te kunnen zeggen.
 */
const paginaKlaar = computed(
    () =>
        props.instelling.page_enabled &&
        (props.instelling.story_nl ?? '').trim() !== '',
);

const wisselPagina = async (): Promise<void> => {
    const aan = !props.instelling.page_enabled;

    const akkoord = await bevestigBewerken({
        titel: aan
            ? t('Je aparte pagina aanzetten?')
            : t('Je aparte pagina uitzetten?'),
        tekst: aan
            ? t(
                  'Er komt dan een knop onder je korte stuk die ernaartoe wijst -- zodra er een verhaal in staat.',
              )
            : t(
                  'Wat je hebt ingevuld blijft gewoon staan; bezoekers kunnen de pagina alleen niet meer bereiken.',
              ),
    });

    if (!akkoord) {
        return;
    }

    router.patch(
        overMij.paginaAan().url,
        { page_enabled: aan },
        { preserveScroll: true, preserveState: true },
    );
};

/* --- Wat er in het overzicht staat ------------------------------------- */

/** Het begin van een lange tekst, voor het overzicht. */
const begin = (tekst: string | null, lengte = 260): string => {
    const plat = (tekst ?? '').replace(/\s+/g, ' ').trim();

    if (plat === '') {
        return '';
    }

    return plat.length > lengte ? `${plat.slice(0, lengte)}…` : plat;
};

/**
 * Het adres van de aparte pagina.
 *
 * Als constante en niet als losse tekst in het sjabloon: een pad is geen
 * zin, dus het hoort niet door de vertaling -- en de controle op
 * onvertaalde teksten pakt elk stuk tekst in een sjabloon.
 */
const PAGINA_PAD = '/over-mij';

/** Hoeveel tekens er in de samenvatting staan. */
const samenvattingLengte = computed(
    () => (props.instelling.summary_nl ?? '').length,
);
</script>

<template>
    <Head :title="$t('Over mij')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <header class="max-w-xl space-y-1">
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ $t('Over mij') }}
                </h1>
                <p class="text-sm text-pretty text-muted-foreground">
                    {{
                        $t(
                            'Een kort stuk over jezelf op je voorpagina, en als je wilt een uitgebreidere pagina erachter. Met de knoppen hieronder kies je welke van de twee je bekijkt.',
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
            </div>
        </div>

        <!--
            Nog niets ingevuld. Dit is precies de toestand waarvoor de
            indelingspagina een uitroepteken toont: het onderdeel staat aan
            maar is leeg, dus het staat niet op de website.
        -->
        <div v-if="props.leeg" class="brand-ervaring-leeg">
            <TriangleAlert class="size-5 shrink-0 text-warning" />
            <div class="space-y-1">
                <p class="font-medium">
                    {{ $t('Je hebt nog geen "Over mij" geschreven.') }}
                </p>
                <p class="text-sm text-pretty text-muted-foreground">
                    {{
                        $t(
                            'Zolang de samenvatting leeg is laten we het hele blok weg van je website. Een kopje met niets eronder is slordiger dan geen kopje.',
                        )
                    }}
                </p>
            </div>
        </div>

        <SegmentToggle
            v-model="versie"
            :options="versies"
            :groep-label="$t('Welke versie je bekijkt')"
        />

        <!-- ===== Op je website ======================================== -->

        <section v-if="versie === 'website'" class="brand-overmij-blok">
            <header class="brand-overmij-blokkop">
                <div class="min-w-0 flex-1">
                    <h2 class="brand-overmij-bloktitel">
                        {{ $t('Op je website') }}
                    </h2>
                    <p class="brand-overmij-blokuitleg">
                        {{
                            $t(
                                'Dit staat altijd op je voorpagina, met je foto ernaast. Hou het kort -- het is een kennismaking en niet je hele verhaal.',
                            )
                        }}
                    </p>
                </div>

                <!--
                    Dezelfde twee knoppen als bij de aparte pagina, en de
                    fotoknop staat er bij allebei: één foto, dus hij hoort
                    bij geen van de twee versies en moet vanuit allebei te
                    bereiken zijn.
                -->
                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        class="gap-2"
                        @click="fotoVenster = true"
                    >
                        <Beeld class="size-4" />
                        {{ $t('Foto aanpassen') }}
                    </Button>

                    <Button
                        variant="bewerken"
                        size="sm"
                        class="gap-2"
                        @click="blokVenster = true"
                    >
                        <Pencil class="size-4" />
                        {{ $t('Bewerken') }}
                    </Button>
                </div>
            </header>

            <div class="brand-overmij-voorbeeld">
                <!--
                    De foto zoals hij op je site staat. Staat er geen eigen
                    foto, dan is dit het portret uit je kop -- met opzet een
                    beeld en geen leeg vak, want er ís iets.
                -->
                <figure class="brand-overmij-voorbeeldfoto">
                    <img
                        :src="props.instelling.foto.src"
                        :srcset="props.instelling.foto.srcset ?? undefined"
                        sizes="7rem"
                        :alt="$t('Je foto zoals hij op je website staat')"
                        width="640"
                        height="640"
                    />
                    <figcaption>
                        {{
                            props.instelling.foto.eigen
                                ? $t('Je eigen foto')
                                : $t('Het portret uit je kop')
                        }}
                    </figcaption>
                </figure>

                <div class="min-w-0 flex-1 space-y-3">
                    <p
                        v-if="props.instelling.summary_nl"
                        class="brand-overmij-voorbeeldtekst"
                    >
                        {{ props.instelling.summary_nl }}
                    </p>
                    <p v-else class="text-sm text-muted-foreground italic">
                        {{ $t('Nog geen samenvatting.') }}
                    </p>

                    <dl class="brand-overmij-feiten">
                        <div>
                            <dt>{{ $t('Lengte') }}</dt>
                            <dd>
                                {{
                                    $t(':aantal van :maximum tekens', {
                                        aantal: samenvattingLengte,
                                        maximum: props.opties.samenvattingMax,
                                    })
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt>{{ $t('Engels') }}</dt>
                            <dd>
                                <span v-if="props.instelling.summary_en">
                                    {{
                                        props.instelling.automatisch_vertaald
                                            ? $t('Automatisch vertaald')
                                            : $t('Ingevuld')
                                    }}
                                </span>
                                <span v-else class="text-warning">
                                    {{
                                        $t(
                                            'Leeg -- blok valt weg op je Engelse site',
                                        )
                                    }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

        <!-- ===== Op je aparte pagina ================================== -->

        <section v-else class="brand-overmij-blok">
            <header class="brand-overmij-blokkop">
                <div class="min-w-0 flex-1">
                    <h2 class="brand-overmij-bloktitel">
                        {{ $t('Op je aparte pagina') }}
                    </h2>
                    <p class="brand-overmij-blokuitleg">
                        {{
                            $t(
                                'Dit komt alleen op /over-mij te staan, achter een knop onder je korte stuk. Hier is ruimte voor je hele verhaal.',
                            )
                        }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <label class="brand-overmij-schakelaar">
                        <Switch
                            :model-value="props.instelling.page_enabled"
                            :aria-label="$t('Pagina aan')"
                            @update:model-value="wisselPagina"
                        />
                        <span class="text-sm font-medium">
                            {{ $t('Pagina aan') }}
                        </span>
                    </label>

                    <!--
                        De foto staat ook hier, want hij staat op allebei de
                        versies. De knop staat bij de andere knoppen en niet
                        onder de foto zelf: die kolom is net zo breed als het
                        portret, en een knop met tekst past daar niet in
                        zonder over de feiten ernaast te vallen.
                    -->
                    <Button
                        variant="outline"
                        size="sm"
                        class="gap-2"
                        @click="fotoVenster = true"
                    >
                        <Beeld class="size-4" />
                        {{ $t('Foto aanpassen') }}
                    </Button>

                    <Button
                        variant="bewerken"
                        size="sm"
                        class="gap-2"
                        @click="paginaVenster = true"
                    >
                        <Pencil class="size-4" />
                        {{ $t('Bewerken') }}
                    </Button>
                </div>
            </header>

            <!--
                Aan staan is niet hetzelfde als klaar zijn, en dat zegt het
                scherm met zoveel woorden. Zonder verhaal bestaat de pagina
                niet en komt er ook geen knop op je voorpagina.
            -->
            <p
                v-if="props.instelling.page_enabled && !paginaKlaar"
                class="brand-overmij-uitmelding"
            >
                {{
                    $t(
                        'De pagina staat aan maar is nog leeg, dus hij is nog niet te bezoeken en er staat nog geen knop op je voorpagina. Zet er een verhaal in en hij staat er.',
                    )
                }}
            </p>

            <p
                v-else-if="!props.instelling.page_enabled"
                class="brand-overmij-uitmelding"
            >
                {{
                    $t(
                        'De pagina staat uit. Je kunt alles hieronder gewoon invullen en bewaren; het komt pas op je website als je hem aanzet.',
                    )
                }}
            </p>

            <div class="brand-overmij-voorbeeld">
                <!--
                    Dezelfde foto als op de voorpagina, en dat staat er met
                    zoveel woorden: je ziet hem op /over-mij terug, dus
                    "waar pas ik dit aan" is een terechte vraag. De knop
                    brengt je naar de versie waar hij verandert.
                -->
                <figure class="brand-overmij-voorbeeldfoto">
                    <img
                        :src="props.instelling.foto.src"
                        :srcset="props.instelling.foto.srcset ?? undefined"
                        sizes="7rem"
                        :alt="$t('Je foto zoals hij op je aparte pagina staat')"
                        width="640"
                        height="640"
                    />
                    <figcaption>
                        {{ $t('Dezelfde foto als op je voorpagina') }}
                    </figcaption>
                </figure>

                <div class="min-w-0 flex-1 space-y-3">
                    <p class="brand-overmij-voorbeeldtitel">
                        {{
                            props.instelling.page_title_nl ||
                            $t(
                                'Geen eigen titel -- de pagina gebruikt die van je blok',
                            )
                        }}
                    </p>

                    <p
                        v-if="props.instelling.page_intro_nl"
                        class="brand-overmij-voorbeeldtekst"
                    >
                        {{ props.instelling.page_intro_nl }}
                    </p>

                    <p
                        v-if="props.instelling.story_nl"
                        class="brand-overmij-voorbeeldtekst"
                    >
                        {{ begin(props.instelling.story_nl) }}
                    </p>
                    <p v-else class="text-sm text-warning">
                        {{ $t('Nog geen verhaal.') }}
                    </p>

                    <dl class="brand-overmij-feiten">
                        <div>
                            <dt>{{ $t('Engels') }}</dt>
                            <dd>
                                <span v-if="props.instelling.story_en">
                                    {{ $t('Ingevuld') }}
                                </span>
                                <span v-else class="text-warning">
                                    {{
                                        $t(
                                            'Leeg -- pagina valt weg op je Engelse site',
                                        )
                                    }}
                                </span>
                            </dd>
                        </div>
                        <div v-if="paginaKlaar">
                            <dt>{{ $t('Te bezoeken op') }}</dt>
                            <dd>
                                <a
                                    :href="PAGINA_PAD"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center gap-1 underline-offset-4 hover:underline"
                                >
                                    {{ PAGINA_PAD }}
                                    <ExternalLink class="size-3.5" />
                                </a>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- De punten horen bij deze pagina en staan daarom hier. -->
            <div class="brand-overmij-punten-vak">
                <div class="brand-overmij-blokkop">
                    <div class="min-w-0 flex-1">
                        <h3 class="brand-overmij-bloktitel">
                            {{ $t('Punten onder je verhaal') }}
                        </h3>
                        <p class="brand-overmij-blokuitleg">
                            {{
                                $t(
                                    'Korte regels die zeggen waar je voor staat -- hoogstens :aantal.',
                                    { aantal: props.opties.puntenMax },
                                )
                            }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            v-if="props.punten.length > 1"
                            variant="outline"
                            size="sm"
                            class="gap-2"
                            @click="volgordeVenster = true"
                        >
                            <ArrowUpDown class="size-4" />
                            {{ $t('Volgorde') }}
                        </Button>

                        <Button
                            variant="aanmaken"
                            size="sm"
                            class="gap-2"
                            :disabled="vol"
                            @click="nieuwPunt"
                        >
                            <Plus class="size-4" />
                            {{ $t('Nieuw punt') }}
                        </Button>
                    </div>
                </div>

                <p v-if="vol" class="text-sm text-muted-foreground">
                    {{
                        $t(
                            'Je hebt het maximum van :aantal punten. Haal er een weg om er een nieuw bij te zetten.',
                            { aantal: props.opties.puntenMax },
                        )
                    }}
                </p>

                <p
                    v-else-if="props.punten.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{
                        $t(
                            'Nog geen punten. Zonder punten staat er op je pagina alleen je verhaal, en dat mag ook.',
                        )
                    }}
                </p>

                <ul v-else class="brand-overmij-punten">
                    <li
                        v-for="punt in props.punten"
                        :key="punt.id"
                        class="brand-overmij-punt"
                    >
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium">
                                {{ punt.text_nl }}
                            </span>
                            <span
                                class="block truncate text-xs text-muted-foreground"
                            >
                                <template v-if="punt.text_en">
                                    {{ punt.text_en }}
                                </template>
                                <template v-else>
                                    {{
                                        $t(
                                            'Geen Engels -- valt weg op je Engelse pagina',
                                        )
                                    }}
                                </template>
                            </span>
                        </span>

                        <span class="flex items-center gap-1">
                            <Button
                                variant="bewerken-zacht"
                                size="icon-sm"
                                :aria-label="$t('Bewerken')"
                                @click="bewerkPunt(punt)"
                            >
                                <Pencil class="size-4" />
                            </Button>
                            <Button
                                variant="verwijderen-zacht"
                                size="icon-sm"
                                :aria-label="$t('Verwijderen')"
                                @click="verwijderPunt(punt)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </span>
                    </li>
                </ul>
            </div>
        </section>

        <p class="text-sm text-muted-foreground">
            <Link
                :href="website.index()"
                class="underline-offset-4 hover:underline"
            >
                {{ $t('Terug naar de indeling van je website') }}
            </Link>
        </p>
    </div>

    <OverMijBlokDialoog
        v-model:open="blokVenster"
        :instelling="props.instelling"
        :opties="props.opties"
        :kan-vertalen="props.kanVertalen"
    />

    <OverMijFotoDialoog
        v-model:open="fotoVenster"
        :instelling="props.instelling"
    />

    <OverMijPaginaDialoog
        v-model:open="paginaVenster"
        :instelling="props.instelling"
        :opties="props.opties"
        :kan-vertalen="props.kanVertalen"
    />

    <OverMijPuntDialoog
        v-model:open="puntVenster"
        :item="gekozenPunt"
        :kan-vertalen="props.kanVertalen"
    />

    <OverMijVolgordeDialoog
        v-model:open="volgordeVenster"
        :items="props.punten"
    />

    <KoptekstDialoog
        v-model:open="kopVenster"
        :kop="props.kop"
        :actie="overMij.kop().url"
        :titel="$t('De kop boven je Over mij-blok')"
        :uitleg="
            $t(
                'Het opschrift, de titel en de zin eronder. Dit staat boven het korte stuk op je voorpagina; de aparte pagina heeft zijn eigen titel.',
            )
        "
        :bevestiging="$t('De kop boven je Over mij-blok aanpassen?')"
        sleutel="over-mij"
        :kan-vertalen="props.kanVertalen"
    />
</template>
