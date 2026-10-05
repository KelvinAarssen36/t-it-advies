<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { home } from '@/routes';

/**
 * De privacyverklaring.
 *
 * **De tekst staat hier en niet in de database.** Een privacyverklaring
 * beschrijft wat de software doet; zou de eigenaar hem zelf kunnen
 * aanpassen, dan kan hij een verklaring neerzetten die niet meer klopt --
 * en een verklaring die niet klopt is erger dan geen verklaring. Nu
 * verandert hij mee in dezelfde wijziging als de meting.
 *
 * **Elke bewering hier is nagelopen tegen de code, en dat is de reden dat
 * er dingen staan die een standaardverklaring weglaat.** Vier voorbeelden,
 * zodat de volgende lezer begrijpt waarom het zo omzichtig is opgeschreven:
 *
 * 1. Er stond "je bericht komt niet in een database op deze website
 *    terecht". Dat was onwaar: het bericht gaat via de queue, en die staat
 *    op de `database`-verbinding -- dus het staat er kort wél in. Nu staat
 *    de wachtrij er met zoveel woorden bij.
 * 2. Er stond "een technisch noodzakelijke cookie". Het zijn er twee: de
 *    sessiecookie en het CSRF-token.
 * 3. De alinea over Cloudflare staat er alleen als de spamcontrole echt
 *    aanstaat. Een derde partij noemen die er niet is, is net zo fout als
 *    er een verzwijgen.
 * 4. Het logbestand van de webserver ontbrak. Dat schrijven wij niet zelf
 *    -- het gebeurt een laag onder onze code, bij de hostingpartij -- maar
 *    het IP-adres van elke bezoeker staat er wel in. Een verklaring die
 *    alles beschrijft behalve dat ene is niet eerlijk. Er staat met opzet
 *    geen termijn bij, want die bepalen wij niet.
 *
 * **Bij elke wijziging aan wat er gemeten of bewaard wordt, hoort deze
 * pagina in dezelfde wijziging mee.** Dat is geen nette gewoonte maar de
 * kern: dit is de belofte aan de bezoeker, en de code eronder hoort hem na
 * te komen.
 *
 * **De module Contact is daar het grootste voorbeeld van**, en het is geen
 * correctie van een fout maar van een keuze. Drie alinea's hier zeiden dat
 * een bericht de website verliet zodra het verstuurd was, en dat was tot
 * dat moment waar. Sindsdien blijft een aanvraag een jaar in het portaal
 * staan, zodat de eigenaar kan terugzoeken wie hem wanneer benaderde:
 *
 * - "daarna is het weg uit de website" is vervangen door hoe lang het blijft
 *   staan en wie erbij kan;
 * - de bewaarlijst noemt die termijn, uit `config('site.contact.retention_days')`;
 * - er staat nu bij dat de bezoeker zelf een bevestiging krijgt, en dat zijn
 *   adres daardoor in het mailoverzicht als ontvanger staat.
 *
 * Zie docs/architecture/modules/contact.md.
 *
 * De bewaartermijnen en het adres komen van de server, uit de instellingen
 * die ze ook echt bepalen. Een getal dat hier met de hand staat, klopt tot
 * de dag dat iemand die instelling wijzigt.
 *
 * Zie docs/architecture/bezoekcijfers.md.
 */
const props = defineProps<{
    email: string;
    bewaartermijnBeveiliging: number;
    /** Hoe lang een bericht uit het contactformulier blijft staan. */
    bewaartermijnAanvragen: number;
    bewaartermijnMail: number;
    /** Of de spamcontrole van Cloudflare daadwerkelijk aanstaat. */
    spamcontrole: boolean;
    /** Hoe lang een sessie meegaat, in minuten. */
    sessieMinuten: number;
}>();
</script>

<template>
    <Head :title="$t('Privacyverklaring')" />

    <section class="mx-auto max-w-3xl px-6 py-16 sm:py-24">
        <!--
            De weg terug, bovenaan. De kop van de site laat op deze pagina
            geen onderdelen zien -- die ankers bestaan hier niet -- dus
            zonder deze link is de enige uitweg de terugknop van de
            browser.
        -->
        <Link :href="home()" class="brand-terug">
            <ArrowLeft class="size-4" />
            {{ $t('Terug naar de website') }}
        </Link>

        <h1
            class="mt-8 text-3xl font-semibold tracking-tight text-white sm:text-4xl"
        >
            {{ $t('Privacyverklaring') }}
        </h1>

        <p class="mt-4 text-pretty text-muted-foreground">
            {{
                $t(
                    'Deze verklaring beschrijft welke gegevens deze website verwerkt, waarom, en hoe lang ze bewaard blijven. Ze is zo kort gehouden als eerlijk kan.',
                )
            }}
        </p>

        <div class="brand-privacy">
            <h2>{{ $t('Wie verwerkt je gegevens') }}</h2>
            <p>
                <!--
                    De merknaam in dezelfde kleuren als in de kop van de
                    site: de "@T" in de gradient, de rest wit. Zo is het
                    ook hier herkenbaar als hetzelfde bedrijf.
                -->
                <span class="brand-naam">
                    <span class="brand-text-gradient">@T</span>
                    <span class="text-white"> IT Advies</span>
                </span>
                {{
                    $t(
                        'is verantwoordelijk voor de gegevens die via deze website worden verwerkt. Je bereikt ons op:',
                    )
                }}
                <a :href="`mailto:${props.email}`" class="brand-sitemail">
                    {{ props.email }}
                </a>
            </p>

            <h2>{{ $t('Bezoekcijfers') }}</h2>
            <p>
                {{
                    $t(
                        'We houden bij hoeveel mensen deze website bekijken, waar ze vandaan komen en of ze een telefoon of een computer gebruiken. Dat doen we om te weten of de site zijn werk doet -- niet om jou te volgen.',
                    )
                }}
            </p>
            <p>
                <strong>
                    {{
                        $t(
                            'Hiervoor gebruiken we geen cookies en slaan we niets op je apparaat op.',
                        )
                    }}
                </strong>
                {{
                    $t(
                        'Er staat daarom ook geen cookiemelding op deze site: voor het meten is er niets om toestemming voor te vragen.',
                    )
                }}
            </p>
            <p>
                {{
                    $t(
                        'Je IP-adres bewaren we niet. Om te kunnen zien of twee paginaweergaven van dezelfde bezoeker komen, rekenen we je IP-adres en je browserkenmerk met een geheime sleutel om naar een code. Die code is niet om te keren naar jouw adres.',
                    )
                }}
            </p>
            <p>
                {{
                    $t(
                        'Die sleutel geldt één dag en wordt uiterlijk de volgende nacht weggegooid, samen met de codes van die dag. Daarna is er ook geen manier meer om te controleren of een code bij een bepaald adres hoorde. Wat er dan nog van over is, is een getal: "op deze dag zoveel bezoekers".',
                    )
                }}
            </p>
            <p>
                {{
                    $t(
                        'Van de website waar je vandaan komt bewaren we alleen de domeinnaam, bijvoorbeeld linkedin.com. De rest van die adresregel gooien we weg voordat er iets wordt opgeslagen.',
                    )
                }}
            </p>

            <h2>{{ $t('Het contactformulier') }}</h2>
            <p>
                {{
                    $t(
                        'Wat je in het contactformulier invult -- je naam, je e-mailadres, je onderwerp en je bericht -- gebruiken we om je bericht te lezen en je te antwoorden. Het wordt per e-mail verstuurd naar:',
                    )
                }}
                <a :href="`mailto:${props.email}`" class="brand-sitemail">
                    {{ props.email }}
                </a>
            </p>
            <!--
                **Deze alinea zei eerder het tegenovergestelde**, en dat was
                waar tot de module Contact. Toen verliet een bericht de
                website zodra het verstuurd was; nu blijft het een jaar in
                het portaal staan, zodat de eigenaar kan terugzoeken wie hem
                wanneer benaderde.

                Zie docs/architecture/modules/contact.md en het commentaar
                bovenaan dit bestand: bij elke wijziging aan wat er wordt
                bewaard hoort deze pagina in dezelfde wijziging mee.
            -->
            <p>
                <strong>
                    {{
                        $t(
                            'Je bericht blijft in het beheergedeelte van deze website staan, :dagen dagen lang.',
                            { dagen: props.bewaartermijnAanvragen },
                        )
                    }}
                </strong>
                {{
                    $t(
                        'Dat is zodat we kunnen terugzoeken waar we het eerder over hadden. Daarna verdwijnt het vanzelf. Alleen de eigenaar van deze website kan erbij, en daarvoor moet hij inloggen met een wachtwoord en een code uit een app.',
                    )
                }}
            </p>
            <p>
                {{
                    $t(
                        'Daarnaast wordt het per e-mail naar hem verstuurd, en staat het dus ook in zijn mailbox, zoals gewone post. Lukt dat versturen niet, dan blijft die poging hoogstens veertien dagen bewaard zodat hij opnieuw geprobeerd kan worden.',
                    )
                }}
            </p>
            <p>
                {{
                    $t(
                        'Je krijgt zelf ook een bevestiging per mail. Daardoor staat je e-mailadres als ontvanger in het logboek hieronder.',
                    )
                }}
            </p>
            <p>
                {{
                    $t(
                        'Van de verzending houden we een logboek bij om te kunnen zien of een bericht is aangekomen. Daar staan het tijdstip, de ontvanger en de status in -- niet je bericht en niet je onderwerp. Dat logboek bewaren we :dagen dagen.',
                        { dagen: props.bewaartermijnMail },
                    )
                }}
            </p>
            <p v-if="props.spamcontrole">
                {{
                    $t(
                        "Om te voorkomen dat het formulier door geautomatiseerde programma's wordt misbruikt, draait er een controle van Cloudflare. Die ziet je verzoek, inclusief je IP-adres, en beoordeelt of het van een mens komt.",
                    )
                }}
            </p>

            <h2>{{ $t('Beveiliging') }}</h2>
            <p>
                {{
                    $t(
                        'Achter deze website zit een beheeromgeving. Inlogpogingen daarop worden vastgelegd, inclusief IP-adres, om misbruik te kunnen zien en tegen te houden. Ook een mislukte spamcontrole op het contactformulier komt in dat logboek terecht, met het IP-adres waar de poging vandaan kwam. Die gegevens bewaren we :dagen dagen.',
                        { dagen: props.bewaartermijnBeveiliging },
                    )
                }}
            </p>
            <p>
                {{
                    $t(
                        'Deze website gebruikt twee technisch noodzakelijke cookies: één voor je sessie, die onder andere je taalkeuze onthoudt, en één die het contactformulier beveiligt tegen misbruik van buitenaf. Zonder die twee werkt de site niet, en daarom hoeft daar geen toestemming voor gevraagd te worden.',
                    )
                }}
            </p>
            <p>
                {{
                    $t(
                        'Bij die sessie hoort een regel op onze server met je IP-adres en je browserkenmerk. Die hebben we nodig om de sessie aan jou te kunnen koppelen, en hij verloopt na :minuten minuten.',
                        { minuten: props.sessieMinuten },
                    )
                }}
            </p>
            <!--
                Het logbestand van de webserver.

                **Dit hoort hier te staan ook al schrijven wij het niet
                zelf.** Elke webserver legt van elk verzoek het IP-adres
                vast, dus het gebeurt wel degelijk bij een bezoek aan deze
                site -- het gebeurt alleen een laag onder onze code, bij de
                partij waar de server staat. Een verklaring die alles
                beschrijft behalve dat ene is niet eerlijk.

                Er staat met opzet géén termijn bij. Hoe lang die bestanden
                blijven staan bepaalt de hostingpartij en niet wij, en een
                getal verzinnen is erger dan het weglaten.
            -->
            <p>
                {{
                    $t(
                        'Daarnaast houdt de server waar deze website op draait, zoals elke webserver, een technisch logbestand bij van de verzoeken die binnenkomen. Daar staat je IP-adres in. Dat hoort bij het beheer en de beveiliging van de server; wij gebruiken het niet om bezoekers te volgen en koppelen het niet aan de cijfers hierboven. Hoe lang die bestanden bewaard blijven bepaalt de partij waar onze server staat.',
                    )
                }}
            </p>

            <h2>{{ $t('Met wie we gegevens delen') }}</h2>
            <p>
                <strong>
                    {{
                        $t(
                            'Bekijk je deze site alleen, dan gaat er niets naar een andere partij.',
                        )
                    }}
                </strong>
                {{
                    $t(
                        'Alle afbeeldingen, lettertypen en scripts komen van onze eigen server. Er zitten geen advertentienetwerken, sociale knoppen of trackers in deze pagina.',
                    )
                }}
            </p>
            <p>
                {{
                    $t(
                        'Wat er wel nodig is om de site te laten werken: de partij waar onze server staat, en de partij die onze e-mail verstuurt wanneer je het contactformulier gebruikt.',
                    )
                }}
                <template v-if="props.spamcontrole">
                    {{ $t('En Cloudflare voor de controle op dat formulier.') }}
                </template>
                {{
                    $t(
                        'Zij mogen die gegevens alleen voor dat doel gebruiken. We verkopen niets en we doen niet mee aan advertentienetwerken.',
                    )
                }}
            </p>

            <h2>{{ $t('Hoe lang we gegevens bewaren') }}</h2>
            <!--
                De bolletjes pulseren één voor één. De vertraging staat
                hier en niet in de CSS, want hij hangt af van de plek in de
                lijst -- en die weet het sjabloon.
            -->
            <ul>
                <li :style="{ '--stip-vertraging': '0s' }">
                    {{
                        $t(
                            'De codes waarmee bezoekers worden geteld: maximaal één dag.',
                        )
                    }}
                </li>
                <li :style="{ '--stip-vertraging': '0.35s' }">
                    {{
                        $t(
                            'De bezoekcijfers zelf: onbeperkt. Dat zijn aantallen per dag en die gaan over niemand in het bijzonder.',
                        )
                    }}
                </li>
                <li :style="{ '--stip-vertraging': '0.70s' }">
                    {{
                        $t(
                            'Je bericht via het contactformulier: :dagen dagen in het beheergedeelte van deze website, en daarnaast in onze mailbox zolang het nodig is om je vraag af te handelen.',
                            { dagen: props.bewaartermijnAanvragen },
                        )
                    }}
                </li>
                <li :style="{ '--stip-vertraging': '1.05s' }">
                    {{
                        $t('Het logboek van verstuurde mail: :dagen dagen.', {
                            dagen: props.bewaartermijnMail,
                        })
                    }}
                </li>
                <li :style="{ '--stip-vertraging': '1.40s' }">
                    {{
                        $t(
                            'De sessie die bij je bezoek hoort, met je IP-adres en browserkenmerk: :minuten minuten.',
                            { minuten: props.sessieMinuten },
                        )
                    }}
                </li>
                <li :style="{ '--stip-vertraging': '1.75s' }">
                    {{
                        $t('Het beveiligingslogboek: :dagen dagen.', {
                            dagen: props.bewaartermijnBeveiliging,
                        })
                    }}
                </li>
                <!--
                    De enige regel zonder getal, en dat is geen slordigheid:
                    dit bestand is niet van ons. Zie het commentaar bij de
                    alinea erover hierboven.
                -->
                <li :style="{ '--stip-vertraging': '2.10s' }">
                    {{
                        $t(
                            'Het technische logbestand van de webserver, met je IP-adres: dat bepaalt de partij waar onze server staat.',
                        )
                    }}
                </li>
            </ul>

            <h2>{{ $t('Jouw rechten') }}</h2>
            <p>
                {{
                    $t(
                        'Je mag ons vragen welke gegevens we van je hebben, ze laten verbeteren, en bezwaar maken tegen de verwerking. Verwijderen kan ook, behalve waar we iets moeten bewaren om misbruik tegen te gaan -- dan zeggen we dat, met de reden. Stuur een bericht naar:',
                    )
                }}
                <a :href="`mailto:${props.email}`" class="brand-sitemail">
                    {{ props.email }}
                </a>
                {{ $t('en we reageren binnen een maand.') }}
            </p>
            <p>
                {{
                    $t(
                        'Praktisch gezien gaat dat over je bericht via het contactformulier. Dat staat in het beheergedeelte van deze website en in onze mailbox; we kunnen het op allebei die plekken opzoeken, opsturen en verwijderen.',
                    )
                }}
            </p>
            <p>
                {{
                    $t(
                        'Kom je voor in het beveiligingslogboek, dan kunnen we je vertellen wat er staat -- geef ons dan het e-mailadres of IP-adres waar het om gaat, anders kunnen we niet zoeken. Verwijderen doen we daar niet: dat logboek bestaat om misbruik te kunnen zien en tegenhouden, en een logboek waar regels uit te halen zijn doet dat niet meer. Het verdwijnt vanzelf na de termijn hierboven.',
                    )
                }}
            </p>
            <p>
                {{
                    $t(
                        'Van de bezoekcijfers kunnen we je niets geven of verwijderen, en dat is geen onwil: er staat niets in waarmee we jou zouden kunnen terugvinden. Je bent niet weg te halen uit een getal.',
                    )
                }}
            </p>
            <p>
                {{
                    $t(
                        'Ben je het niet met ons eens, dan kun je een klacht indienen bij de Autoriteit Persoonsgegevens.',
                    )
                }}
            </p>
        </div>

        <!--
            En onderaan nog een keer de weg terug. Wie de hele verklaring
            heeft doorgelezen staat onder aan een lange pagina, en dan is
            een link bovenaan geen link.
        -->
        <Link :href="home()" class="brand-terug mt-12">
            <ArrowLeft class="size-4" />
            {{ $t('Terug naar de website') }}
        </Link>
    </section>
</template>
