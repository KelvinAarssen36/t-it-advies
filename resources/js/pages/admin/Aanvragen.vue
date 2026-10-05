<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Building2,
    Check,
    Reply,
    ChevronDown,
    Inbox,
    Mail,
    MailCheck,
    Phone,
    Search,
    Star,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import BrandSelect from '@/components/BrandSelect.vue';
import LocaleFlag from '@/components/LocaleFlag.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { bevestigVerwijderen } from '@/lib/bevestiging';
import { toonToast } from '@/lib/flashToast';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/admin';
import mail from '@/routes/admin/mail';
import aanvragen from '@/routes/admin/submissions';
import type { AanvraagRij, OnderwerpFilter } from '@/types/contact';

type Paginator<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    total: number;
};

/**
 * Beheer → Aanvragen: wat er via het contactformulier binnenkwam.
 *
 * **Dit is geen mailprogramma.** De eigenaar antwoordt vanuit zijn eigen
 * postvak; dat was een uitdrukkelijke keuze. Dit scherm is er om bij te
 * houden wat er binnenkwam en wat hij er al mee heeft gedaan.
 *
 * **De twee bollen zet hij zelf.** Gelezen wordt niet vanzelf aangezet bij
 * het openklappen: een aanvraag die je kwijtraakt omdat je per ongeluk op
 * de verkeerde regel klikte, is erger dan een aanvraag die je twee keer
 * openmaakt. Beantwoord zet gelezen wél mee aan -- je kunt niet iets
 * beantwoorden dat je niet hebt gelezen, en anders telt het cijfer
 * bovenaan iets dat niet waar is.
 *
 * Zie docs/architecture/modules/contact.md.
 */
const props = defineProps<{
    aanvragen: Paginator<AanvraagRij>;
    zoek: string | null;
    stand: string | null;
    onderwerp: string | null;
    /** Alle onderwerpen om op te filteren, ook de offline. */
    onderwerpen: OnderwerpFilter[];
    cijfers: { totaal: number; ongelezen: number; onbeantwoord: number };
    bewaartermijn: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beheer', href: dashboard() },
            { title: 'Aanvragen' },
        ],
        breed: true,
    },
});

const zoekterm = ref(props.zoek ?? '');
const stand = ref(props.stand ?? '');
const onderwerp = ref(props.onderwerp ?? '');

const standen = [
    { value: '', label: t('Alle aanvragen') },
    { value: 'ongelezen', label: t('Alleen ongelezen') },
    { value: 'onbeantwoord', label: t('Alleen onbeantwoord') },
];

/**
 * De onderwerpen om op te filteren.
 *
 * **Met "zelf ingevuld" eronder, en dat is geen bijzaak.** Een bezoeker die
 * zijn eigen onderwerp typte hangt aan geen enkel onderwerp, dus zonder
 * deze keuze is die groep niet te bekijken -- en juist daar zit wat niet in
 * de lijst van de eigenaar past. Dat is precies waar je af en toe naar wil
 * kijken.
 *
 * Een `computed` en geen vaste lijst: `t()` eenmalig berekend blijft bij
 * een taalwissel in de oude taal staan. Zie docs/architecture/vertalingen.md.
 */
const onderwerpFilters = computed(() => [
    { value: '', label: t('Alle onderwerpen') },
    ...props.onderwerpen.map((rij) => ({
        value: String(rij.id),
        label: rij.naam,
    })),
    { value: 'zelf', label: t('Zelf ingevuld') },
]);

let wachten: ReturnType<typeof setTimeout> | undefined;

// Even wachten met zoeken, anders vuurt elke toetsaanslag een verzoek af.
watch([zoekterm, stand, onderwerp], () => {
    clearTimeout(wachten);

    wachten = setTimeout(() => {
        router.get(
            aanvragen.index().url,
            {
                zoek: zoekterm.value || undefined,
                stand: stand.value || undefined,
                onderwerp: onderwerp.value || undefined,
            },
            { preserveState: true, replace: true },
        );
    }, 300);
});

/** Welke regel open staat. Eén tegelijk; dit is een postbus, geen lijst. */
const open = ref<number | null>(null);

const klap = (id: number): void => {
    open.value = open.value === id ? null : id;
};

/**
 * Een vinkje omzetten, met een melding erbij.
 *
 * **De melding komt van de browser en niet van de server.** Elders in dit
 * portaal zet een controller een toast in de sessie, maar dat werkt hier
 * slecht: dit zijn twee vinkjes die je achter elkaar aantikt, en dan
 * stapelen de meldingen op voor iets dat je al ziet gebeuren. Hier is de
 * melding een bevestiging dat het is opgeslagen -- want dat is het enige
 * dat je niet ziet.
 *
 * Vandaar `onSuccess`: pas melden als de server het heeft bewaard. Een
 * melding vóór het verzoek zou liegen zodra het misgaat.
 */
const zet = (
    aanvraag: AanvraagRij,
    wat: 'gelezen' | 'beantwoord',
    aan: boolean,
): void => {
    router.patch(
        aanvragen.stand(aanvraag.id).url,
        { wat, aan },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => meld(aanvraag.naam, wat, aan),
        },
    );
};

/**
 * De tekst bij die melding.
 *
 * Met de naam erin, want je tikt ze aan in een lijst: zonder naam weet je
 * bij de derde melding niet meer welke regel je net hebt geraakt.
 *
 * **Beantwoord aanzetten zet gelezen mee aan**, dus dan zegt de melding dat
 * ook. Anders lijkt het of er maar één van de twee is opgeslagen terwijl je
 * twee vinkjes ziet verspringen.
 */
const meld = (
    naam: string,
    wat: 'gelezen' | 'beantwoord',
    aan: boolean,
): void => {
    if (wat === 'gelezen') {
        toonToast(
            aan
                ? t('De aanvraag van :naam staat op gelezen.', { naam })
                : t('De aanvraag van :naam staat weer op ongelezen.', { naam }),
        );

        return;
    }

    toonToast(
        aan
            ? t(
                  'De aanvraag van :naam staat op beantwoord, en dus ook op gelezen.',
                  { naam },
              )
            : t('De aanvraag van :naam staat weer op onbeantwoord.', { naam }),
    );
};

const verwijder = async (aanvraag: AanvraagRij): Promise<void> => {
    const akkoord = await bevestigVerwijderen({
        titel: t('De aanvraag van :naam verwijderen?', { naam: aanvraag.naam }),
        tekst: t(
            'Het bericht is daarna weg uit je portaal. In je mailbox staat hij nog; die moet je daar apart weghalen.',
        ),
    });

    if (!akkoord) {
        return;
    }

    router.delete(aanvragen.destroy(aanvraag.id).url, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="$t('Aanvragen')" />

    <div class="flex flex-col gap-6 p-4">
        <header class="max-w-2xl space-y-1">
            <h1 class="text-xl font-semibold tracking-tight">
                {{ $t('Aanvragen') }}
            </h1>
            <p class="text-sm text-pretty text-muted-foreground">
                {{
                    $t(
                        'Alles wat er via je contactformulier binnenkwam. Antwoorden doe je vanuit je eigen mail; hier houd je bij wat je al hebt gedaan.',
                    )
                }}
            </p>
            <!--
                Waar de bezorgstatus staat. **Dit scherm toont hem met
                opzet niet**: een aanvraag staat hier zodra hij is
                opgeslagen, en dat gebeurt vóór de mails de wachtrij in
                gaan. Een vinkje "verstuurd" naast elke regel zou dus twee
                dingen door elkaar halen die los van elkaar mis kunnen
                gaan. Eén regel die zegt waar je dan wél moet kijken is
                eerlijker dan een kolom die maar half klopt.
            -->
            <p class="text-sm text-pretty text-muted-foreground">
                {{
                    $t(
                        'Een aanvraag staat hier al zodra hij is opgeslagen. Of de mails erover zijn aangekomen, zie je in het mailoverzicht.',
                    )
                }}
                <Link
                    :href="mail.index().url"
                    class="underline-offset-4 hover:underline"
                >
                    {{ $t('Open Mail') }}
                </Link>
            </p>
        </header>

        <!--
            Drie cijfers. Die van ongelezen en onbeantwoord staan er niet om
            indruk te maken maar omdat dat de twee vragen zijn waarmee je dit
            scherm opent.
        -->
        <div class="grid gap-3 sm:grid-cols-3">
            <div class="brand-veiligheidcijfer">
                <p class="brand-bezoekcijfer-kop">
                    <Inbox class="size-4" />
                    {{ $t('Aanvragen') }}
                </p>
                <p class="brand-bezoekcijfer-getal">
                    {{ props.cijfers.totaal.toLocaleString('nl-NL') }}
                </p>
            </div>
            <div class="brand-veiligheidcijfer">
                <p class="brand-bezoekcijfer-kop">
                    <Mail class="size-4" />
                    {{ $t('Nog niet gelezen') }}
                </p>
                <p class="brand-bezoekcijfer-getal">
                    {{ props.cijfers.ongelezen.toLocaleString('nl-NL') }}
                </p>
            </div>
            <div class="brand-veiligheidcijfer">
                <p class="brand-bezoekcijfer-kop">
                    <MailCheck class="size-4" />
                    {{ $t('Nog niet beantwoord') }}
                </p>
                <p class="brand-bezoekcijfer-getal">
                    {{ props.cijfers.onbeantwoord.toLocaleString('nl-NL') }}
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="relative min-w-0 flex-1 sm:max-w-sm">
                <Search
                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    v-model="zoekterm"
                    class="pl-9"
                    :placeholder="$t('Zoek op naam, adres of bericht')"
                    :aria-label="$t('Zoek op naam, adres of bericht')"
                />
            </div>

            <BrandSelect
                v-model="stand"
                :options="standen"
                :aria-label="$t('Welke aanvragen je ziet')"
                class="sm:w-52"
            />

            <!--
                Filteren op onderwerp. Staat naast het standfilter en niet
                erin: "alleen ongelezen" en "alleen offertes" zijn twee
                vragen die je ook samen kunt stellen.
            -->
            <BrandSelect
                v-if="props.onderwerpen.length > 0"
                v-model="onderwerp"
                :options="onderwerpFilters"
                :aria-label="$t('Filteren op onderwerp')"
                class="sm:w-52"
            />
        </div>

        <!-- Nog niets binnengekomen, of niets gevonden. -->
        <div
            v-if="props.aanvragen.data.length === 0"
            class="brand-bezoekuitleg"
        >
            <Inbox class="size-5 shrink-0" aria-hidden="true" />
            <div class="space-y-1">
                <p class="font-medium">
                    <template v-if="zoekterm || stand || onderwerp">
                        {{ $t('Niets gevonden.') }}
                    </template>
                    <template v-else>
                        {{ $t('Er zijn nog geen aanvragen.') }}
                    </template>
                </p>
                <p class="text-sm text-pretty text-muted-foreground">
                    <template v-if="zoekterm || stand || onderwerp">
                        {{
                            $t(
                                'Probeer een kortere zoekterm, of zet het filter op alle aanvragen.',
                            )
                        }}
                    </template>
                    <template v-else>
                        {{
                            $t(
                                'Zodra iemand je contactformulier invult, staat hij hier. Je krijgt hem ook per mail.',
                            )
                        }}
                    </template>
                </p>
            </div>
        </div>

        <ul v-else class="space-y-2">
            <li
                v-for="aanvraag in props.aanvragen.data"
                :key="aanvraag.id"
                class="brand-aanvraag"
                :data-ongelezen="aanvraag.gelezen ? undefined : ''"
                :data-uitgelicht="aanvraag.onderwerpUitgelicht ? '' : undefined"
            >
                <div class="brand-aanvraag-kop">
                    <!--
                        De twee vinkjes, vóór de regel en buiten de knop die
                        openklapt.

                        **Buiten die knop en niet erin**: een knop in een
                        knop mag niet van HTML en werkt in de praktijk ook
                        niet -- de klik komt bij de buitenste terecht en dan
                        klapt de rij open in plaats van dat je iets
                        aanvinkt. Vandaar een rij met drie delen: de
                        vinkjes, de samenvatting die openklapt, en de tijd.

                        Ze stonden eerst ín het uitgeklapte vlak. Dan moet
                        je eerst ergens op klikken voordat je kunt
                        afvinken, en dat is precies één handeling te veel
                        voor het enige dat je op dit scherm doet.

                        Twee verschillende tekens en niet twee keer een
                        vinkje: zo zie je in één oogopslag welk van de twee
                        aanstaat zonder de kleur te hoeven lezen.
                    -->
                    <span class="brand-aanvraag-vinkjes">
                        <button
                            type="button"
                            class="brand-aanvraag-vink"
                            :data-aan="aanvraag.gelezen ? '' : undefined"
                            :aria-pressed="aanvraag.gelezen"
                            :aria-label="$t('Gelezen')"
                            :title="$t('Gelezen')"
                            @click="zet(aanvraag, 'gelezen', !aanvraag.gelezen)"
                        >
                            <Check class="size-3.5" aria-hidden="true" />
                        </button>

                        <button
                            type="button"
                            class="brand-aanvraag-vink is-groen"
                            :data-aan="aanvraag.beantwoord ? '' : undefined"
                            :aria-pressed="aanvraag.beantwoord"
                            :aria-label="$t('Beantwoord')"
                            :title="$t('Beantwoord')"
                            @click="
                                zet(
                                    aanvraag,
                                    'beantwoord',
                                    !aanvraag.beantwoord,
                                )
                            "
                        >
                            <Reply class="size-3.5" aria-hidden="true" />
                        </button>
                    </span>

                    <!-- De samenvatting; klikken klapt hem open. -->
                    <button
                        type="button"
                        class="brand-aanvraag-samenvatting"
                        :aria-expanded="open === aanvraag.id"
                        @click="klap(aanvraag.id)"
                    >
                        <span class="min-w-0 flex-1 space-y-0.5 text-left">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="font-medium">{{
                                    aanvraag.naam
                                }}</span>
                                <LocaleFlag :locale="aanvraag.taal" size="sm" />
                                <span
                                    v-if="!aanvraag.gelezen"
                                    class="brand-aanvraag-nieuw"
                                >
                                    {{ $t('nieuw') }}
                                </span>
                            </span>
                            <!--
                                Het onderwerp, met een sterretje als de
                                eigenaar het heeft uitgelicht.

                                **Een sterretje én een gekleurde regel.**
                                Alleen een tint is te zwak om op te vallen
                                tussen twintig regels, en alleen een
                                sterretje zegt niet wat het betekent. Samen
                                zie je in één blik welke aanvragen voorgaan.
                            -->
                            <span
                                class="flex min-w-0 items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <Star
                                    v-if="aanvraag.onderwerpUitgelicht"
                                    class="brand-aanvraag-ster size-3 shrink-0"
                                    aria-hidden="true"
                                />
                                <span class="truncate">
                                    {{ aanvraag.onderwerp }}
                                </span>
                            </span>
                        </span>

                        <span
                            class="shrink-0 text-xs text-muted-foreground tabular-nums"
                        >
                            {{ aanvraag.wanneer }}
                        </span>

                        <ChevronDown
                            class="brand-aanvraag-pijl size-4 shrink-0 text-muted-foreground"
                            :data-open="open === aanvraag.id ? '' : undefined"
                            aria-hidden="true"
                        />
                    </button>
                </div>

                <div v-if="open === aanvraag.id" class="brand-aanvraag-inhoud">
                    <!-- Het bericht zelf, bovenaan: dat is waarvoor je klikt. -->
                    <p class="brand-aanvraag-bericht">{{ aanvraag.bericht }}</p>

                    <dl class="brand-aanvraag-gegevens">
                        <div>
                            <dt>{{ $t('E-mailadres') }}</dt>
                            <dd>
                                <a
                                    :href="`mailto:${aanvraag.email}`"
                                    class="underline-offset-4 hover:underline"
                                >
                                    {{ aanvraag.email }}
                                </a>
                            </dd>
                        </div>

                        <div v-if="aanvraag.bedrijf">
                            <dt>
                                <Building2
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                                {{ $t('Bedrijfsnaam') }}
                            </dt>
                            <dd>{{ aanvraag.bedrijf }}</dd>
                        </div>

                        <div v-if="aanvraag.telefoon">
                            <dt>
                                <Phone class="size-3.5" aria-hidden="true" />
                                {{ $t('Telefoonnummer') }}
                            </dt>
                            <dd>
                                <a
                                    :href="`tel:${aanvraag.telefoon}`"
                                    class="underline-offset-4 hover:underline"
                                >
                                    {{ aanvraag.telefoon }}
                                </a>
                            </dd>
                        </div>

                        <div>
                            <dt>{{ $t('Onderwerp') }}</dt>
                            <dd>
                                {{ aanvraag.onderwerp }}
                                <!--
                                    Het verschil tussen "zelf getypt" en
                                    "koos iets dat inmiddels weg is" is een
                                    ander verhaal; zie
                                    ContactSubmission::onderwerpVerdwenen().
                                -->
                                <span
                                    v-if="aanvraag.onderwerpVerdwenen"
                                    class="text-muted-foreground"
                                >
                                    {{
                                        $t('(dit onderwerp bestaat niet meer)')
                                    }}
                                </span>
                                <span
                                    v-else-if="aanvraag.onderwerpZelf"
                                    class="text-muted-foreground"
                                >
                                    {{ $t('(zelf ingevuld)') }}
                                </span>
                            </dd>
                        </div>
                    </dl>

                    <!--
                        **Hier stonden de twee bollen, en die zijn naar de
                        regel verhuisd.** Afvinken is het enige dat je op dit
                        scherm doet, en dan hoort het niet achter een klik te
                        zitten. Ze blijven hier niet óók staan: twee plekken
                        voor dezelfde schakelaar is twee plekken om aan te
                        passen, en de regel staat toch vlak boven dit vlak.
                    -->
                    <div class="brand-aanvraag-acties">
                        <span class="flex-1"></span>

                        <Button
                            variant="verwijderen-zacht"
                            size="icon-sm"
                            @click="verwijder(aanvraag)"
                        >
                            <Trash2 class="size-4" />
                            <span class="sr-only">{{ $t('Verwijderen') }}</span>
                        </Button>
                    </div>
                </div>
            </li>
        </ul>

        <nav
            v-if="props.aanvragen.links.length > 3"
            class="flex flex-wrap gap-1"
        >
            <template v-for="link in props.aanvragen.links" :key="link.label">
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

        <p class="flex items-start gap-2 text-sm text-muted-foreground">
            <X class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <span>
                {{
                    $t(
                        'Een aanvraag verdwijnt na :dagen dagen vanzelf. Dat staat ook in je privacyverklaring, dus het is een belofte aan je bezoeker -- verander je het, dan verandert die tekst mee.',
                        { dagen: props.bewaartermijn },
                    )
                }}
            </span>
        </p>
    </div>
</template>
