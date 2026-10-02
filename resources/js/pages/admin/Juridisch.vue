<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Lock,
    Scale,
    Search,
    ShieldCheck,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import CopyButton from '@/components/CopyButton.vue';
import LocaleFlag from '@/components/LocaleFlag.vue';
import VerlaatPortaal from '@/components/VerlaatPortaal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { bevestigVerwijderen } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes/admin';
import legal from '@/routes/admin/legal';
import { privacy } from '@/routes';

/**
 * Juridisch: een verzoek van een bezoeker afhandelen.
 *
 * **Eén scherm met één vraag erachter:** iemand mailt "welke gegevens
 * hebben jullie van mij", en wat doet de eigenaar dan? Die kennis zat
 * verspreid over drie schermen en een kaart in de handleiding, en dat is
 * precies het moment waarop iemand het verkeerd of te laat doet.
 *
 * Drie dingen staan erop:
 *
 * 1. **Zoeken.** Eén veld voor een e-mailadres of IP-adres, en eronder
 *    wat er gevonden is -- én welke plekken zijn nagekeken waar iemand
 *    niet te vinden kan zijn. Dat tweede is net zo belangrijk: "ik heb
 *    gezocht en er staat niets" is een antwoord dat je moet kunnen geven.
 * 2. **Een antwoord om te kopiëren.** De zin die hij kan plakken in zijn
 *    mail, met de bewaartermijnen er al in. Dit is waarom het scherm
 *    bestaat: een procedure die je moet onthouden gaat fout, een tekst
 *    die er al staat niet.
 * 3. **Wat waar staat en hoe lang.** Uit de instellingen die het ook echt
 *    bepalen, dus die lijst kan niet verouderen.
 *
 * **Er is met opzet geen knop om een regel uit het beveiligingslogboek te
 * verwijderen.** Zie LegalController voor waarom dat zo blijft; het staat
 * ook met zoveel woorden op het scherm, want een ontbrekende knop zonder
 * uitleg is hetzelfde raadsel als een knop die niets doet.
 *
 * Zie docs/security/verzoeken-van-bezoekers.md.
 */
const props = defineProps<{
    zoekterm: string | null;
    resultaten: Array<{
        id: number;
        wanneer: string | null;
        wat: string;
        uitkomst: string;
        email: string | null;
        ip: string | null;
    }>;
    plekken: Array<{
        sleutel: string;
        bewaartermijn: string;
        zoekbaar: boolean;
        wisbaar: boolean;
        aantal: number | null;
    }>;
    mislukteMail: number;
    /** De antwoordtekst per taalcode; zie Antwoordtekst.php. */
    antwoord: Record<string, string>;
    termijnen: {
        beveiliging: number;
        mail: number;
        sessie: number;
        mislukteMail: number;
    };
    email: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Beheer', href: dashboard() },
            { title: 'Juridisch' },
        ],
    },
});

const zoek = ref(props.zoekterm ?? '');

const zoeken = (): void => {
    router.get(
        legal.index().url,
        { zoek: zoek.value.trim() || undefined },
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

const teKort = computed(
    () => zoek.value.trim().length > 0 && zoek.value.trim().length < 3,
);

/**
 * De opschriften van de plekken.
 *
 * De server stuurt sleutels en geen teksten, want het portaal is
 * tweetalig. Per plek staat er ook wát er dan precies staat -- dat is wat
 * de eigenaar moet weten om een antwoord te kunnen geven.
 */
const PLEKKEN: Record<string, { naam: () => string; wat: () => string }> = {
    beveiliging: {
        naam: () => t('Beveiligingslogboek'),
        wat: () =>
            t(
                'IP-adres en browserkenmerk bij een inlogpoging of een geblokkeerd formulier. Hier is iemand terug te vinden.',
            ),
    },
    mail: {
        naam: () => t('Mailoverzicht'),
        wat: () =>
            t(
                'Alleen jouw eigen adres als ontvanger, plus tijdstip en status. Een bezoeker staat hier niet in.',
            ),
    },
    'mislukte-mail': {
        naam: () => t('Mislukte mailpogingen'),
        wat: () =>
            t(
                'Een bericht dat niet verstuurd kon worden, met naam, adres en inhoud. De enige plek waar een bericht van een bezoeker in de database blijft liggen.',
            ),
    },
    sessies: {
        naam: () => t('Sessies'),
        wat: () =>
            t(
                'IP-adres en browserkenmerk van wie de site nu open heeft. Verloopt vanzelf en is niet op naam te zoeken.',
            ),
    },
    bezoekcodes: {
        naam: () => t('Bezoekerscodes'),
        wat: () =>
            t(
                'Onomkeerbare codes om bezoekers per dag te tellen. Niet naar een persoon te herleiden, ook niet door ons.',
            ),
    },
    bezoekcijfers: {
        naam: () => t('Bezoekcijfers'),
        wat: () =>
            t(
                'Aantallen per dag. Hier staat niets dat naar een persoon wijst.',
            ),
    },
    activiteit: {
        naam: () => t('Activiteitenlogboek'),
        wat: () =>
            t(
                'Wat jij in het portaal hebt gewijzigd, met jouw IP-adres. Gaat niet over bezoekers.',
            ),
    },
};

const naam = (sleutel: string): string => PLEKKEN[sleutel]?.naam() ?? sleutel;

const wat = (sleutel: string): string => PLEKKEN[sleutel]?.wat() ?? '';

/* --- Het antwoord dat hij kan kopiëren -------------------------------- */

/** De talen die de server heeft klaargezet, in de volgorde van de server. */
const talen = Object.keys(props.antwoord);

/**
 * De taal waarin hij antwoordt.
 *
 * **Dit is niet de taal van het portaal, en dat is het hele punt.** De
 * eigenaar werkt in het Nederlands, maar de vraag kan in het Engels
 * binnenkomen; dan hoort het antwoord Engels te zijn zonder dat hij zijn
 * hele beheeromgeving omzet om één mail te kunnen sturen.
 *
 * Hij begint wél op de taal van het portaal, want dat is de meest
 * waarschijnlijke -- en valt terug op de eerste taal die er is, zodat er
 * nooit een leeg vak staat als er ooit een taal bij of af gaat.
 */
const antwoordtaal = ref(
    talen.includes(usePage().props.locale)
        ? usePage().props.locale
        : (talen[0] ?? 'nl'),
);

/**
 * De tekst zelf, uit wat de server heeft klaargezet.
 *
 * Hij werd hier eerst in elkaar gezet met `t()`, en liep daarmee mee met
 * de taal van het portaal. Nu bouwt de server hem in allebei de talen en
 * kiest dit scherm er een; zie `app/Support/Juridisch/Antwoordtekst.php`.
 * De bewaartermijnen zitten er al in verwerkt, dus de tekst kan niet
 * verouderen.
 */
const antwoord = computed(
    () =>
        props.antwoord[antwoordtaal.value] ??
        Object.values(props.antwoord)[0] ??
        '',
);

/**
 * De naam van een taal, zoals die ook in het accountmenu staat.
 *
 * Uit de gedeelde props en niet uit een eigen lijstje hier: er is één
 * plek waar taalcodes aan namen hangen, en dat is de server.
 */
const taalnaam = (taal: string): string =>
    usePage().props.locales?.[taal] ?? taal;

/* --- Mislukte mailpogingen -------------------------------------------- */

const verwijderMislukteMail = async (): Promise<void> => {
    const akkoord = await bevestigVerwijderen({
        titel: t('De mislukte mailpogingen weggooien?'),
        tekst: t(
            'Deze berichten zijn nooit aangekomen en komen daarna ook nooit meer aan. Doe dit als iemand om verwijdering vraagt, en niet als een bericht alleen is blijven steken omdat de mail even niet werkte.',
        ),
    });

    if (!akkoord) {
        return;
    }

    router.delete(legal.failedMail.destroy().url, { preserveScroll: true });
};
</script>

<template>
    <Head :title="$t('Juridisch')" />

    <div class="flex flex-col gap-6 p-4">
        <header class="max-w-2xl space-y-1">
            <h1 class="text-xl font-semibold tracking-tight">
                {{ $t('Juridisch') }}
            </h1>
            <p class="text-sm text-pretty text-muted-foreground">
                {{
                    $t(
                        'Vraagt iemand welke gegevens je van hem hebt, of om die te verwijderen? Dan regel je dat hier. Je hebt een maand om te antwoorden.',
                    )
                }}
            </p>
        </header>

        <!-- 1. Zoeken -->
        <div class="rounded-xl border p-4">
            <p class="brand-bezoeklijst-kop">
                {{ $t('Zoek een persoon') }}
            </p>

            <form class="flex flex-wrap gap-2" @submit.prevent="zoeken">
                <Input
                    v-model="zoek"
                    class="min-w-0 flex-1"
                    :placeholder="$t('E-mailadres of IP-adres')"
                    :aria-label="$t('E-mailadres of IP-adres')"
                />
                <Button variant="outline" class="gap-2" type="submit">
                    <Search class="size-4" />
                    {{ $t('Zoeken') }}
                </Button>
            </form>

            <p v-if="teKort" class="mt-2 text-sm text-muted-foreground">
                {{
                    $t(
                        'Typ minstens drie tekens. Met minder krijg je half het logboek terug.',
                    )
                }}
            </p>

            <!--
                Wel gezocht, niets gevonden. Dat is een uitkomst, maar het
                is nog geen antwoord.

                **Hier stond eerst meteen "je kunt antwoorden dat je niets
                van hem hebt".** Dat is één stap te vroeg: het
                contactformulier komt in de mailbox terecht en niet in deze
                database, dus wie daar ooit een bericht liet staan is hier
                onvindbaar terwijl er wél iets van hem is. De conclusie
                komt daarom nu pas ná die stap, en niet ernaast.
            -->
            <div
                v-else-if="props.zoekterm && props.resultaten.length === 0"
                class="brand-bezoekuitleg mt-4"
            >
                <ShieldCheck class="size-5 shrink-0 text-success" />
                <div class="space-y-2 text-sm text-pretty">
                    <p>
                        {{
                            $t(
                                'Niets gevonden in je beveiligingslogboek. Dat is de enige plek in je website waar een bezoeker terug te vinden is.',
                            )
                        }}
                    </p>
                    <p>
                        <strong>
                            {{ $t('Kijk nu ook in je mailbox op dit adres.') }}
                        </strong>
                        {{
                            $t(
                                'Berichten uit het contactformulier komen daar binnen en staan nergens anders. Vind je daar iets, dan is dát wat hij bedoelt, en verwijder je het in je mailbox.',
                            )
                        }}
                    </p>
                    <p>
                        {{
                            $t(
                                'Staat daar ook niets, dan kun je antwoorden dat je niets van hem hebt.',
                            )
                        }}
                    </p>
                </div>
            </div>

            <div v-else-if="props.resultaten.length > 0" class="mt-4">
                <div
                    class="brand-tabelvak brand-schuif-x brand-scrollbar rounded-xl border"
                >
                    <table class="brand-tabel-kaarten w-full text-sm">
                        <thead class="bg-muted/50 text-left">
                            <tr>
                                <th class="px-3 py-2 font-medium">
                                    {{ $t('Wanneer') }}
                                </th>
                                <th class="px-3 py-2 font-medium">
                                    {{ $t('Wat') }}
                                </th>
                                <th class="px-3 py-2 font-medium">
                                    {{ $t('E-mail') }}
                                </th>
                                <th class="px-3 py-2 font-medium">
                                    {{ $t('IP') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="regel in props.resultaten"
                                :key="regel.id"
                                class="border-t"
                            >
                                <td
                                    :data-label="$t('Wanneer')"
                                    class="px-3 py-2 tabular-nums"
                                >
                                    {{ regel.wanneer }}
                                </td>
                                <td :data-label="$t('Wat')" class="px-3 py-2">
                                    {{ regel.wat }}
                                    <span class="text-muted-foreground">
                                        ({{ regel.uitkomst }})
                                    </span>
                                </td>
                                <td
                                    :data-label="$t('E-mail')"
                                    class="px-3 py-2"
                                >
                                    {{ regel.email ?? '—' }}
                                </td>
                                <td
                                    :data-label="$t('IP')"
                                    class="px-3 py-2 font-mono text-xs"
                                >
                                    {{ regel.ip ?? '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p
                    class="mt-3 flex items-start gap-2 text-sm text-muted-foreground"
                >
                    <Lock class="mt-0.5 size-4 shrink-0" />
                    <span>
                        {{
                            $t(
                                'Deze regels kun je niet verwijderen, en dat is met opzet: een logboek waar regels uit te halen zijn kan geen misbruik meer aantonen. Dat mag je weigeren -- zeg wél waarom, en dat ze na :dagen dagen vanzelf verdwijnen.',
                                { dagen: props.termijnen.beveiliging },
                            )
                        }}
                    </span>
                </p>
            </div>
        </div>

        <!-- 2. Het antwoord -->
        <div class="rounded-xl border p-4">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <p class="brand-bezoeklijst-kop">
                    {{ $t('Je antwoord') }}
                </p>
                <!--
                    De gedeelde kopieerknop en geen eigen knop hiernaast.
                    Dit stond er eerst wél met de hand in, en op een
                    `.test`-adres gebeurde er dan niets: `navigator.clipboard`
                    weigert buiten https. Geen tekst op het klembord, en
                    ook geen vinkje om dat aan te wijzen. CopyButton heeft
                    de terugval en de vinkje-animatie allebei al.
                -->
                <div class="flex flex-wrap items-center gap-2">
                    <!--
                        De taal van het antwoord, los van die van het
                        portaal. Twee knopjes en geen keuzelijst: het zijn
                        er twee, en je moet in één oogopslag zien welke
                        aanstaat.
                    -->
                    <div class="brand-antwoordtaal" role="group">
                        <button
                            v-for="taal in talen"
                            :key="taal"
                            type="button"
                            class="brand-antwoordtaal-knop"
                            :data-actief="
                                taal === antwoordtaal ? '' : undefined
                            "
                            :aria-pressed="taal === antwoordtaal"
                            @click="antwoordtaal = taal"
                        >
                            <LocaleFlag :locale="taal" size="sm" />
                            <span>{{ taalnaam(taal) }}</span>
                        </button>
                    </div>

                    <CopyButton
                        :value="antwoord"
                        :label="$t('Kopieer de tekst')"
                        :copied-label="$t('Gekopieerd')"
                        show-label
                    />
                </div>
            </div>

            <p class="mb-3 text-sm text-pretty text-muted-foreground">
                {{
                    $t(
                        'Deze tekst verandert mee met wat je hierboven vindt. Lees hem na, pas aan wat je wilt, en stuur hem als antwoord.',
                    )
                }}
                <strong>
                    {{
                        $t(
                            'Schreef je bezoeker in het Engels, zet de tekst dan met de vlaggetjes op Engels -- dat verandert niets aan de taal van je portaal.',
                        )
                    }}
                </strong>
            </p>

            <pre class="brand-juridisch-antwoord">{{ antwoord }}</pre>
        </div>

        <!-- 3. Wat waar staat -->
        <div class="rounded-xl border p-4">
            <p class="brand-bezoeklijst-kop">
                {{ $t('Wat er waar staat, en hoe lang') }}
            </p>

            <p class="mb-4 text-sm text-pretty text-muted-foreground">
                {{
                    $t(
                        'Deze lijst komt uit de instellingen die het ook echt bepalen, dus hij kan niet verouderen. Verandert een termijn, dan verandert deze regel mee.',
                    )
                }}
            </p>

            <ul class="space-y-3">
                <li
                    v-for="plek in props.plekken"
                    :key="plek.sleutel"
                    class="rounded-lg border p-3"
                >
                    <div
                        class="flex flex-wrap items-baseline justify-between gap-2"
                    >
                        <span class="font-medium">
                            {{ naam(plek.sleutel) }}
                            <span
                                v-if="plek.aantal !== null"
                                class="text-muted-foreground"
                            >
                                ({{ plek.aantal }})
                            </span>
                        </span>
                        <span class="text-xs text-muted-foreground">
                            {{ plek.bewaartermijn }}
                        </span>
                    </div>

                    <p class="mt-1 text-sm text-pretty text-muted-foreground">
                        {{ wat(plek.sleutel) }}
                    </p>

                    <!--
                        Alleen bij de mislukte mail valt er iets weg te
                        gooien, en alleen als er ook echt iets staat. Een
                        knop die "0 berichten" verwijdert is een knop die
                        niets doet.
                    -->
                    <Button
                        v-if="plek.wisbaar && (plek.aantal ?? 0) > 0"
                        variant="verwijderen"
                        size="sm"
                        class="mt-3 gap-2"
                        @click="verwijderMislukteMail"
                    >
                        <Trash2 class="size-4" />
                        {{ $t('Weggooien') }}
                    </Button>
                </li>
            </ul>
        </div>

        <!-- 4. De verklaring -->
        <div class="brand-bezoekuitleg">
            <Scale class="size-5 shrink-0 text-success" />
            <div class="space-y-2">
                <p class="text-sm text-pretty">
                    {{
                        $t(
                            'Wat hier staat hoort te kloppen met de privacyverklaring op je website. Die twee horen altijd hetzelfde te zeggen; verandert er iets aan wat we bewaren, dan verandert die pagina mee.',
                        )
                    }}
                </p>
                <p class="text-sm">
                    <Link
                        :href="privacy()"
                        class="underline-offset-4 hover:underline"
                    >
                        {{ $t('Bekijk de privacyverklaring op je website') }}
                    </Link>
                    <VerlaatPortaal />
                </p>
            </div>
        </div>

        <div class="brand-ervaring-leeg">
            <TriangleAlert class="size-5 shrink-0 text-warning" />
            <div class="space-y-1">
                <p class="font-medium">
                    {{ $t('Nog te doen vóór je website live gaat') }}
                </p>
                <p class="text-sm text-pretty text-muted-foreground">
                    {{
                        $t(
                            'Laat de privacyverklaring één keer nalezen door iemand met juridische kennis. De tekst beschrijft precies wat de software doet, maar dat is iets anders dan juridisch advies. Vragen daarover kun je stellen via :email.',
                            { email: props.email },
                        )
                    }}
                </p>
            </div>
        </div>
    </div>
</template>
