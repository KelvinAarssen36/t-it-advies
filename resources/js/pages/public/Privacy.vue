<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import SiteKruimels from '@/components/site/SiteKruimels.vue';
import SiteTerug from '@/components/site/SiteTerug.vue';
import { scrollNaar } from '@/lib/motion';

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

/**
 * De inhoudsopgave: de zeven kopjes van de verklaring, in dezelfde volgorde.
 *
 * **Met de hand en niet uit de DOM gelezen.** De kopjes staan hier vast in
 * het sjabloon, dus de lijst ook; uit de DOM lezen zou betekenen dat de
 * inhoudsopgave pas na het tekenen bestaat, en dan verspringt de pagina.
 * Komt er een kopje bij, dan hoort het hier ook bij -- het `id` is de
 * schakel.
 */
const inhoud = [
    { id: 'wie', titel: 'Wie verwerkt je gegevens' },
    { id: 'bezoekcijfers', titel: 'Bezoekcijfers' },
    { id: 'contactformulier', titel: 'Het contactformulier' },
    { id: 'beveiliging', titel: 'Beveiliging' },
    { id: 'delen', titel: 'Met wie we gegevens delen' },
    { id: 'bewaren', titel: 'Hoe lang we gegevens bewaren' },
    { id: 'rechten', titel: 'Jouw rechten' },
];

/** Het kopje waar je nu leest; dat licht op in de inhoudsopgave. */
const hier = ref<string | null>(null);

/**
 * Een klik in de inhoudsopgave scrollt via Lenis, net als het menu in de
 * kop, en zet het anker in het adres met `replaceState`: zo kun je naar
 * "Jouw rechten" linken, zonder dat de terugknop zich vult met ankers.
 * Een ctrl- of middelklik blijft een nieuw tabblad.
 */
const gaNaar = (id: string, gebeurtenis: MouseEvent): void => {
    if (gebeurtenis.metaKey || gebeurtenis.ctrlKey || gebeurtenis.shiftKey) {
        return;
    }

    if (!scrollNaar(id)) {
        return;
    }

    gebeurtenis.preventDefault();
    window.history.replaceState(window.history.state, '', `#${id}`);
    hier.value = id;
};

/*
 * Welk kopje er actief is: het laatste dat boven het eerste derde van het
 * scherm is gekomen. Een IntersectionObserver en geen scrollluisteraar,
 * want die rekent bij elke pixel; dit meldt alleen als een kopje die lijn
 * passeert. De bovenste marge haalt de zwevende kop eraf.
 */
let kijker: IntersectionObserver | undefined;

onMounted(() => {
    const koppen = [
        ...document.querySelectorAll<HTMLElement>('[data-privacy-kop]'),
    ];

    if (koppen.length === 0 || typeof IntersectionObserver === 'undefined') {
        return;
    }

    kijker = new IntersectionObserver(
        () => {
            const lijn = window.innerHeight / 3;
            const voorbij = koppen.filter(
                (kop) => kop.getBoundingClientRect().top <= lijn,
            );

            hier.value = voorbij.at(-1)?.id ?? null;
        },
        { rootMargin: '-72px 0px -66% 0px', threshold: [0, 1] },
    );

    koppen.forEach((kop) => kijker?.observe(kop));
});

onBeforeUnmount(() => kijker?.disconnect());
</script>

<template>
    <Head :title="$t('Privacyverklaring')" />

    <section class="brand-site-raam brand-site-privacy">
        <!--
            Waar je bent. Waar je heen kunt staat in de kop: die toont op
            deze pagina gewoon het menu van de voorpagina. Hier stond een
            teruglink die hetzelfde deed als die kop, en onderaan nog een
            keer dezelfde.
        -->
        <SiteKruimels :titel="'Privacyverklaring'" />

        <h1 class="brand-site-subpagina-titel">
            {{ $t('Privacyverklaring') }}
        </h1>

        <p class="brand-site-privacy-inleiding">
            {{
                $t(
                    'Deze verklaring beschrijft welke gegevens deze website verwerkt, waarom, en hoe lang ze bewaard blijven. Ze is zo kort gehouden als eerlijk kan.',
                )
            }}
        </p>

        <!--
            Op een breed scherm staat de inhoudsopgave naast de tekst en
            blijft hij staan terwijl je leest; op een telefoon staat hij
            boven de tekst als een korte lijst. Een lange juridische tekst
            zonder houvast leest als een muur; zo zie je waar je bent en
            spring je naar "Jouw rechten" zonder te zoeken.
        -->
        <div class="brand-site-privacy-raster">
            <nav
                class="brand-site-privacy-inhoud"
                :aria-label="$t('Op deze pagina')"
            >
                <p class="brand-site-privacy-inhoud-kop">
                    {{ $t('Op deze pagina') }}
                </p>
                <ol>
                    <li v-for="kop in inhoud" :key="kop.id">
                        <a
                            :href="`#${kop.id}`"
                            :aria-current="
                                hier === kop.id ? 'location' : undefined
                            "
                            @click="gaNaar(kop.id, $event)"
                        >
                            {{ $t(kop.titel) }}
                        </a>
                    </li>
                </ol>
            </nav>

            <div class="brand-privacy">
                <h2 id="wie" data-privacy-kop>
                    {{ $t('Wie verwerkt je gegevens') }}
                </h2>
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

                <h2 id="bezoekcijfers" data-privacy-kop>
                    {{ $t('Bezoekcijfers') }}
                </h2>
                <p>
                    {{
                        $t(
                            'We houden bij hoeveel mensen deze website bekijken, welke pagina ze opvragen, waar ze vandaan komen en of ze een telefoon of een computer gebruiken. Dat doen we om te weten of de site zijn werk doet -- niet om jou te volgen.',
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

                <h2 id="contactformulier" data-privacy-kop>
                    {{ $t('Het contactformulier') }}
                </h2>
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

                <h2 id="beveiliging" data-privacy-kop>
                    {{ $t('Beveiliging') }}
                </h2>
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

                <h2 id="delen" data-privacy-kop>
                    {{ $t('Met wie we gegevens delen') }}
                </h2>
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
                        {{
                            $t(
                                'En Cloudflare voor de controle op dat formulier.',
                            )
                        }}
                    </template>
                    {{
                        $t(
                            'Zij mogen die gegevens alleen voor dat doel gebruiken. We verkopen niets en we doen niet mee aan advertentienetwerken.',
                        )
                    }}
                </p>

                <h2 id="bewaren" data-privacy-kop>
                    {{ $t('Hoe lang we gegevens bewaren') }}
                </h2>
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
                            $t(
                                'Het logboek van verstuurde mail: :dagen dagen.',
                                {
                                    dagen: props.bewaartermijnMail,
                                },
                            )
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

                <h2 id="rechten" data-privacy-kop>{{ $t('Jouw rechten') }}</h2>
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

            <!-- Het einde van de pagina: je bent klaar met lezen. -->
        </div>

        <SiteTerug />
    </section>
</template>
