<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Cookie,
    FileClock,
    Fingerprint,
    KeyRound,
    Lock,
    Mail,
    ShieldAlert,
    ShieldCheck,
    Smartphone,
} from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import VerlaatPortaal from '@/components/VerlaatPortaal.vue';
import { t } from '@/lib/i18n';
import { edit as editSecurity } from '@/routes/security';
import { privacy } from '@/routes';

/**
 * Hoe het allemaal beveiligd is.
 *
 * **Een scherm zonder knoppen, en dat is de bedoeling.** De eigenaar vroeg
 * om een pagina "die niet echt iets doet maar waarin gewoon simpel staat
 * hoe het allemaal beveiligd is".
 *
 * **Het is geen controlelijst, en dat is een correctie.** Eerst stond hier
 * bij elk punt een vinkje of een kruisje, met de echte stand erachter.
 * Technisch klopte dat, maar het leest als een keuring: acht regels waarvan
 * er een paar rood staan, over dingen waar hij niets aan kan doen of die
 * alleen lokaal uitstaan. De eigenaar zei het zo: _"eigenlijk wilde ik dat
 * je dat gewoon simpel gebruikte, dus wat we allemaal aan veiligheid
 * gebruiken, om de klant tot rust te stellen -- niet per se een echte
 * check."_
 *
 * Dus: rustige uitleg van wat er altijd gebeurt, en geen stoplichten.
 *
 * **Rustig betekent niet onwaar.** Waar iets afhangt van een instelling --
 * de spamcontrole van Cloudflare, het adres voor meldingen -- staat er de
 * zin die bij die situatie hoort, net als op de privacyverklaring. Er staat
 * dus nooit dat iets beschermt wat niet aanstaat; het staat er alleen niet
 * als een rood kruis bij.
 *
 * Wat hij **zelf** instelt staat apart onderaan, als uitnodiging en niet
 * als gebrek: tweestapsverificatie en passkeys. Die horen bij het scherm
 * **Beveiliging**, en dit scherm verwijst daarheen.
 *
 * Zie docs/security/overzicht-voor-de-eigenaar.md.
 */
const props = defineProps<{
    dagen: number;
    cijfers: {
        mislukteLogins: number;
        geblokkeerd: number;
        mailVerstuurd: number;
        mailProblemen: number;
    };
    beschermingen: {
        tweestaps: boolean;
        tweestapsSinds: string | null;
        passkeys: number;
        /** Of de Turnstile-sleutel is ingevuld; zonder draait die laag niet. */
        spamcontrole: boolean;
        /** Of er een adres is om meldingen naartoe te sturen. */
        alarmering: boolean;
    };
    termijnen: {
        beveiliging: number;
        activiteit: number;
        mail: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Veiligheid' }],
        breed: true,
    },
});

/**
 * De cijfers over de afgelopen periode.
 *
 * Ze staan er niet om indruk te maken maar om één vraag te beantwoorden:
 * gebeurt er iets waar ik iets mee moet? Daarom is nul hier een goed
 * getal, en zegt de regel eronder dat ook.
 */
const cijfers = computed(() => [
    {
        sleutel: 'logins',
        icoon: Lock,
        label: t('Mislukte inlogpogingen'),
        waarde: props.cijfers.mislukteLogins,
        goed: t('Niemand heeft geprobeerd binnen te komen.'),
        uitleg: t(
            'Pogingen met een verkeerd wachtwoord of een verkeerde code. Een paar is normaal -- dat ben je zelf, of een bot die alles probeert. Bij een plotselinge piek krijg je automatisch een mail.',
        ),
    },
    {
        sleutel: 'geblokkeerd',
        icoon: ShieldCheck,
        label: t('Tegengehouden pogingen'),
        waarde: props.cijfers.geblokkeerd,
        goed: t('Er is niets tegengehouden; er was niets om tegen te houden.'),
        uitleg: t(
            'Spam op je contactformulier, te veel verzoeken achter elkaar, of een inlogpoging die tijdelijk op slot is gezet. Dit getal is goed nieuws: het is wat er níet door is gekomen.',
        ),
    },
    {
        sleutel: 'mail',
        icoon: Mail,
        label: t('Verstuurde mail'),
        waarde: props.cijfers.mailVerstuurd,
        goed: t('Er is geen mail verstuurd in deze periode.'),
        uitleg: t(
            'Alles wat de website heeft verstuurd: berichten uit je contactformulier en meldingen aan jou.',
        ),
    },
    {
        sleutel: 'mailproblemen',
        icoon: ShieldAlert,
        label: t('Mail die niet aankwam'),
        waarde: props.cijfers.mailProblemen,
        goed: t('Alle mail is aangekomen.'),
        uitleg: t(
            'Mail die is geweigerd of niet bezorgd kon worden. Staat hier een getal, kijk dan in het mailoverzicht welk bericht het was -- misschien is er een adres verkeerd getypt.',
        ),
    },
]);

/**
 * Wat er altijd gebeurt, zonder dat hij er iets voor hoeft te doen.
 *
 * Geen vinkjes en geen kruisjes: dit is uitleg en geen keuring. Waar een
 * punt afhangt van een instelling staat de zin die bij die situatie hoort,
 * zodat het rustig blijft zonder onwaar te worden.
 */
const bescherming = computed(() => [
    {
        sleutel: 'inloggen',
        icoon: KeyRound,
        label: t('Alleen jij komt erin'),
        tekst: t(
            'Er is één account op deze website en dat is het jouwe. Je kunt je nergens aanmelden -- er staat geen inlogknop op je website en er is geen registratieformulier. Na een aantal mislukte pogingen gaat het inloggen tijdelijk op slot.',
        ),
    },
    {
        sleutel: 'https',
        icoon: Lock,
        label: t('Versleutelde verbinding'),
        tekst: t(
            'Op je website is alles tussen je bezoeker en de server versleuteld. Daarbovenop staan instellingen die de browser vertellen wat hij niet mag: geen scripts van buitenaf, en je site mag niet in de pagina van iemand anders worden ingebouwd.',
        ),
    },
    {
        sleutel: 'formulier',
        icoon: ShieldCheck,
        label: t('Je contactformulier'),
        tekst: props.beschermingen.spamcontrole
            ? t(
                  'Drie lagen houden spam tegen: een grens op het aantal berichten achter elkaar, een verborgen veld waar alleen een bot in typt, en een controle van Cloudflare die kijkt of je bezoeker een mens is.',
              )
            : t(
                  'Twee lagen houden spam tegen: een grens op het aantal berichten achter elkaar, en een verborgen veld waar alleen een bot in typt. Er kan een controle van Cloudflare bij; die staat nu niet aan.',
              ),
    },
    {
        sleutel: 'mail',
        icoon: Mail,
        label: t('Je mail'),
        tekst: t(
            'Mail gaat via een professionele verzenddienst en niet rechtstreeks van de server. Dat is wat ervoor zorgt dat een bericht niet in de spammap van de ontvanger belandt. Van elk bericht houden we bij of het is aangekomen.',
        ),
    },
    {
        sleutel: 'melding',
        icoon: ShieldAlert,
        label: t('Er wordt meegekeken'),
        tekst: props.beschermingen.alarmering
            ? t(
                  'Elk uur wordt er gekeken of er iets vreemds gebeurt: een piek in mislukte inlogpogingen, mail die niet aankomt, of een website die er even uit ligt. Gebeurt dat, dan krijg je automatisch bericht.',
              )
            : t(
                  'Elk uur wordt er gekeken of er iets vreemds gebeurt: een piek in mislukte inlogpogingen, mail die niet aankomt, of een website die er even uit ligt. Alles wordt vastgelegd; er is nog geen adres ingesteld om bericht naartoe te sturen.',
              ),
    },
    {
        sleutel: 'cookies',
        icoon: Cookie,
        label: t('Niets dat je bezoekers volgt'),
        tekst: t(
            'Je website meet bezoekers zonder cookies en zonder IP-adressen te bewaren. Daarom staat er geen cookiemelding op je site. Er zitten ook geen advertentienetwerken of sociale knoppen in je pagina.',
        ),
    },
    {
        sleutel: 'logboeken',
        icoon: FileClock,
        label: t('Logboeken met een einddatum'),
        tekst: t(
            'Wat er gebeurt wordt vastgelegd, maar niet voor altijd: het beveiligingslogboek :beveiliging dagen, je eigen wijzigingen :activiteit dagen, en de verstuurde mail :mail dagen. Daarna ruimt het zichzelf op.',
            {
                beveiliging: props.termijnen.beveiliging,
                activiteit: props.termijnen.activiteit,
                mail: props.termijnen.mail,
            },
        ),
    },
]);

/**
 * De twee dingen die hij zélf aanzet.
 *
 * **Die stonden tussen de rest met een kruisje erbij, en dat was de
 * verkeerde toon.** Tweestapsverificatie die uitstaat is geen defect maar
 * een knop die hij nog niet heeft ingedrukt. Hier staan ze dus apart, met
 * de stand als mededeling en een weg ernaartoe.
 */
const eigenKeuze = computed(() => [
    {
        sleutel: 'tweestaps',
        icoon: Smartphone,
        label: t('Tweestapsverificatie'),
        aan: props.beschermingen.tweestaps,
        tekst: props.beschermingen.tweestaps
            ? props.beschermingen.tweestapsSinds
                ? t(
                      'Staat aan sinds :datum. Inloggen vraagt naast je wachtwoord om een code uit je app, dus iemand met alleen je wachtwoord komt er niet in.',
                      { datum: props.beschermingen.tweestapsSinds },
                  )
                : t(
                      'Staat aan. Inloggen vraagt naast je wachtwoord om een code uit je app, dus iemand met alleen je wachtwoord komt er niet in.',
                  )
            : t(
                  'Hiermee vraagt inloggen naast je wachtwoord om een code uit een app op je telefoon. Dit is de sterkste bescherming die je zelf kunt aanzetten.',
              ),
    },
    {
        sleutel: 'passkeys',
        icoon: Fingerprint,
        label: t('Passkeys'),
        aan: props.beschermingen.passkeys > 0,
        tekst:
            props.beschermingen.passkeys > 0
                ? t(
                      ':aantal ingesteld. Daarmee log je in met je vingerafdruk of je gezicht, zonder wachtwoord -- en een passkey is niet na te maken door een nepsite.',
                      { aantal: props.beschermingen.passkeys },
                  )
                : t(
                      'Hiermee log je in met je vingerafdruk of je gezicht in plaats van met een wachtwoord. Het werkt op je telefoon en op je laptop, en een passkey is niet na te maken door een nepsite.',
                  ),
    },
]);
</script>

<template>
    <Head :title="$t('Veiligheid')" />

    <h1 class="sr-only">{{ $t('Veiligheid') }}</h1>

    <div class="space-y-8">
        <Heading
            variant="small"
            :title="$t('Veiligheid')"
            :description="
                $t(
                    'Wat er gebeurt om je website en dit portaal te beschermen. Je hoeft hier niets in te stellen -- het staat er zodat je weet waar je aan toe bent.',
                )
            "
        />

        <!-- De cijfers -->
        <section class="space-y-3">
            <h2 class="text-sm font-medium">
                {{ $t('De afgelopen :aantal dagen', { aantal: props.dagen }) }}
            </h2>

            <div class="grid gap-3 sm:grid-cols-2">
                <div
                    v-for="cijfer in cijfers"
                    :key="cijfer.sleutel"
                    class="brand-veiligheidcijfer"
                >
                    <p class="brand-bezoekcijfer-kop">
                        <component :is="cijfer.icoon" class="size-4" />
                        {{ cijfer.label }}
                    </p>
                    <p class="brand-bezoekcijfer-getal">
                        {{ cijfer.waarde.toLocaleString('nl-NL') }}
                    </p>
                    <p class="text-sm text-pretty text-muted-foreground">
                        {{ cijfer.waarde === 0 ? cijfer.goed : cijfer.uitleg }}
                    </p>
                </div>
            </div>
        </section>

        <!--
            Wat er altijd gebeurt.

            Geen vinkjes en geen kruisjes: het tekentje links is het
            onderwerp en geen oordeel. Een rij stoplichten leest als een
            keuring, en dit is uitleg.
        -->
        <section class="space-y-3">
            <h2 class="text-sm font-medium">
                {{ $t('Wat er voor je geregeld is') }}
            </h2>

            <ul class="grid gap-2 lg:grid-cols-2">
                <li
                    v-for="punt in bescherming"
                    :key="punt.sleutel"
                    class="brand-veiligheidpunt"
                >
                    <span class="brand-veiligheidpunt-teken" aria-hidden="true">
                        <component :is="punt.icoon" class="size-4" />
                    </span>

                    <div class="min-w-0 space-y-1">
                        <p class="font-medium">{{ punt.label }}</p>
                        <p class="text-sm text-pretty text-muted-foreground">
                            {{ punt.tekst }}
                        </p>
                    </div>
                </li>
            </ul>
        </section>

        <!--
            Wat hij zelf aanzet.

            Apart, en met een rustig woordje erbij of het aanstaat. Niet
            tussen de rest met een kruisje: iets dat je nog kunt doen is
            geen defect.
        -->
        <section class="space-y-3">
            <h2 class="text-sm font-medium">
                {{ $t('Wat je zelf kunt aanzetten') }}
            </h2>

            <ul class="grid gap-2 lg:grid-cols-2">
                <li
                    v-for="punt in eigenKeuze"
                    :key="punt.sleutel"
                    class="brand-veiligheidpunt"
                >
                    <span class="brand-veiligheidpunt-teken" aria-hidden="true">
                        <component :is="punt.icoon" class="size-4" />
                    </span>

                    <div class="min-w-0 space-y-1">
                        <p class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ punt.label }}</span>
                            <span
                                class="brand-veiligheidstand"
                                :data-aan="punt.aan ? '' : undefined"
                            >
                                {{ punt.aan ? $t('aan') : $t('nog niet aan') }}
                            </span>
                        </p>
                        <p class="text-sm text-pretty text-muted-foreground">
                            {{ punt.tekst }}
                        </p>
                    </div>
                </li>
            </ul>

            <p class="text-sm text-pretty text-muted-foreground">
                {{
                    $t(
                        'Allebei stel je ze in onder Beveiliging, en je kunt ze ook naast elkaar gebruiken.',
                    )
                }}
            </p>
        </section>

        <!-- Waar hij wél iets kan doen -->
        <section class="space-y-2">
            <h2 class="text-sm font-medium">
                {{ $t('Waar je wat kunt doen') }}
            </h2>

            <p class="text-sm text-pretty text-muted-foreground">
                {{
                    $t(
                        'Je wachtwoord, je authenticator-app en je passkeys stel je in onder Beveiliging. Wat er precies van je bezoekers wordt bewaard, staat in de privacyverklaring op je website.',
                    )
                }}
            </p>

            <p class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                <Link
                    :href="editSecurity()"
                    class="underline-offset-4 hover:underline"
                >
                    {{ $t('Naar Beveiliging') }}
                </Link>
                <span class="inline-flex items-center">
                    <Link
                        :href="privacy()"
                        class="underline-offset-4 hover:underline"
                    >
                        {{ $t('Naar de privacyverklaring') }}
                    </Link>
                    <VerlaatPortaal />
                </span>
            </p>
        </section>
    </div>
</template>
