<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    ArrowUpRight,
    Asterisk,
    CheckCheck,
    Eye,
    Globe,
    Hash,
    // Onze eigen Heading.vue heet ook zo; vandaar de andere naam hier.
    Heading as KopIcoon,
    Image,
    Languages,
    LayoutList,
    Lightbulb,
    Mail,
    Milestone,
    MousePointerClick,
    Pencil,
    Plus,
    Search,
    Share2,
    ShieldAlert,
    Sparkles,
    SunMoon,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import SegmentToggle from '@/components/SegmentToggle.vue';
import UitlegKaart from '@/components/settings/UitlegKaart.vue';
import VerlaatPortaal from '@/components/VerlaatPortaal.vue';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import { dashboard as adminDashboard } from '@/routes/admin';
import adminActivity from '@/routes/admin/activity';
import adminMail from '@/routes/admin/mail';
import adminSecurity from '@/routes/admin/security';
import adminUsers from '@/routes/admin/users';
import { show } from '@/routes/documentation';
import site from '@/routes/site';
import website from '@/routes/website';
import dienstenRoutes from '@/routes/website/diensten';
import kop from '@/routes/website/kop';
import ervaring from '@/routes/website/ervaring';

/**
 * De handleiding bij het portaal.
 *
 * **Dit is de enige plek waar de eigenaar zelf kan opzoeken hoe iets
 * werkt.** De documentatie in `docs/` is voor ons; die gaat over code,
 * keuzes en valkuilen. Deze pagina gaat over knoppen: wat er gebeurt als
 * je erop drukt, en wat de klant van hem daarna ziet.
 *
 * Drie onderdelen, en die volgen de zijbalk:
 *
 * 1. **Basis** -- de dingen die overal hetzelfde werken. De kleur van een
 *    knop, de sterretjes, de twee talen, online en offline. Wie dit ene
 *    onderdeel leest, kan met elk scherm overweg dat er later bij komt.
 * 2. **Website** -- de schermen waarmee hij zijn eigen site vult.
 * 3. **Administratie** -- de zakelijke kant; nu alleen de mail.
 * 4. **Beheer** -- de logboeken en de gebruikers.
 *
 * **Bij elk afgerond onderdeel hoort hier een kaart bij.** Een module die
 * de eigenaar niet kan vinden is geen module. Zie
 * docs/architecture/uitleg-voor-de-eigenaar.md voor hoe je dat doet en
 * waar de kaart dan heen gaat.
 *
 * De inhoud staat in code en niet in de database: dit beschrijft hoe het
 * portaal werkt, en dat verandert alleen als wij er iets aan bouwen.
 */
const onderdeel = ref<string>('basis');

const onderdelen = computed(() => [
    { value: 'basis', label: t('Basis') },
    { value: 'website', label: t('Website') },
    { value: 'administratie', label: t('Administratie') },
    { value: 'beheer', label: t('Beheer') },
]);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Documentatie', href: show() }],
        breed: true,
    },
});
</script>

<template>
    <Head :title="$t('Documentatie')" />

    <h1 class="sr-only">{{ $t('Documentatie') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="$t('Documentatie')"
            :description="
                $t(
                    'Hoe je portaal werkt, in gewone woorden. Zoek het onderwerp op dat je nodig hebt.',
                )
            "
        />

        <SegmentToggle
            v-model="onderdeel"
            :options="onderdelen"
            :groep-label="$t('Kies een onderdeel van de handleiding')"
        />

        <!--
            `relative`, want het vertrekkende onderdeel staat tijdens de
            overgang even absoluut. Zonder deze regel valt de pagina een
            fractie van een seconde in elkaar.
        -->
        <div class="relative">
            <Transition name="brand-uitleg-wissel" mode="out-in">
                <!-- ------------------------- Basis ------------------------- -->
                <div v-if="onderdeel === 'basis'" key="basis" class="space-y-3">
                    <p class="text-sm text-pretty text-muted-foreground">
                        {{
                            $t(
                                'Deze dingen werken overal in het portaal hetzelfde. Ken je ze eenmaal, dan kun je met elk scherm overweg -- ook met de schermen die er later bij komen.',
                            )
                        }}
                    </p>

                    <UitlegKaart
                        :titel="$t('De kleur van een knop zegt wat hij doet')"
                        :icoon="MousePointerClick"
                    >
                        <p>
                            {{
                                $t(
                                    'Er zijn drie dingen die je met een item kunt doen, en ze hebben elk hun eigen kleur. Je hoeft dus niet te lezen om te zien waar je op drukt.',
                                )
                            }}
                        </p>
                        <ul class="space-y-1">
                            <li>
                                <strong class="text-foreground">{{
                                    $t('Blauw')
                                }}</strong>
                                {{
                                    $t(
                                        '-- er komt iets bij. Dit is de kleur van je huisstijl, en op elk scherm de knop waar je waarschijnlijk voor kwam.',
                                    )
                                }}
                            </li>
                            <li>
                                <strong class="text-foreground">{{
                                    $t('Oker')
                                }}</strong>
                                {{
                                    $t(
                                        '-- je past iets aan dat er al staat. Een eigen kleur, want aanpassen is iets anders dan toevoegen: wat je hier wijzigt staat meteen op je website.',
                                    )
                                }}
                            </li>
                            <li>
                                <strong class="text-foreground">{{
                                    $t('Rood')
                                }}</strong>
                                {{
                                    $t(
                                        '-- er gaat iets weg. Alleen deze kleur is onomkeerbaar.',
                                    )
                                }}
                            </li>
                        </ul>
                        <p>
                            {{
                                $t(
                                    'Een grijze knop verandert niets aan je website. Annuleren, zoeken, een venster openen: dat zijn grijze knoppen.',
                                )
                            }}
                        </p>

                        <template #voorbeeld>
                            <Button
                                variant="aanmaken"
                                size="sm"
                                tabindex="-1"
                                aria-hidden="true"
                            >
                                <Plus class="size-4" />
                                {{ $t('Toevoegen') }}
                            </Button>
                            <Button
                                variant="bewerken"
                                size="sm"
                                tabindex="-1"
                                aria-hidden="true"
                            >
                                <Pencil class="size-4" />
                                {{ $t('Bewerken') }}
                            </Button>
                            <Button
                                variant="verwijderen"
                                size="sm"
                                tabindex="-1"
                                aria-hidden="true"
                            >
                                <Trash2 class="size-4" />
                                {{ $t('Verwijderen') }}
                            </Button>
                        </template>
                    </UitlegKaart>

                    <UitlegKaart
                        :titel="$t('In een lijst zijn diezelfde knoppen zacht')"
                        :icoon="Pencil"
                    >
                        <p>
                            {{
                                $t(
                                    'Staat er een rij van vijftien regels op je scherm, dan zou vijftien keer een gevulde knop eruitzien als een waarschuwing. Achter elke regel staan daarom alleen de tekentjes, in dezelfde kleuren: het potlood past aan, de prullenbak haalt weg.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Wijs er eentje aan en er komt een gekleurde schijf onder. Dan weet je zeker welke van de twee je te pakken hebt.',
                                )
                            }}
                        </p>

                        <template #voorbeeld>
                            <Button
                                variant="bewerken-zacht"
                                size="icon-sm"
                                :aria-label="$t('Bewerken')"
                                tabindex="-1"
                            >
                                <Pencil class="size-4" />
                            </Button>
                            <Button
                                variant="verwijderen-zacht"
                                size="icon-sm"
                                :aria-label="$t('Verwijderen')"
                                tabindex="-1"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                            <span class="text-xs text-muted-foreground">
                                {{
                                    $t(
                                        'Zo staan ze achter elke regel in een tabel.',
                                    )
                                }}
                            </span>
                        </template>
                    </UitlegKaart>

                    <UitlegKaart
                        :titel="
                            $t('Voordat er iets live gaat, vragen we het na')
                        "
                        :icoon="CheckCheck"
                    >
                        <p>
                            {{
                                $t(
                                    'Er zit geen concept tussen: wat je opslaat staat op datzelfde moment op je website. Daarom komt er eerst een venster in beeld.',
                                )
                            }}
                        </p>
                        <ul class="space-y-1">
                            <li>
                                {{
                                    $t(
                                        'Iets nieuws toevoegen: één vraag. Er stond nog niets, dus er kan niets stuk.',
                                    )
                                }}
                            </li>
                            <li>
                                {{
                                    $t(
                                        'Iets aanpassen: twee vragen. De tweede zegt er letterlijk bij dat het direct live staat. Dat is bewust, want hier overschrijf je iets dat je bezoekers nu al zien.',
                                    )
                                }}
                            </li>
                            <li>
                                {{
                                    $t(
                                        'Iets verwijderen: één vraag. Je klikte op een prullenbak, dus wat er gaat gebeuren is al duidelijk.',
                                    )
                                }}
                            </li>
                        </ul>
                        <p>
                            {{
                                $t(
                                    'Het venster heeft dezelfde kleur als de knop waarop je drukte. Twijfel je halverwege, dan kun je altijd op Annuleren drukken; er is dan nog niets gebeurd.',
                                )
                            }}
                        </p>
                    </UitlegKaart>

                    <UitlegKaart
                        :titel="
                            $t('Een sterretje bij een veld: dit moet ingevuld')
                        "
                        :icoon="Asterisk"
                    >
                        <p>
                            {{
                                $t(
                                    'Staat er een rood sterretje achter de naam van een veld, dan kun je niet opslaan zolang het leeg is. Velden zonder sterretje mag je gerust overslaan.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Ben je er toch eentje vergeten, dan blijft je venster gewoon openstaan met alles wat je al had ingevuld. Je raakt niets kwijt.',
                                )
                            }}
                        </p>
                    </UitlegKaart>

                    <UitlegKaart
                        :titel="$t('Een sterretje in het menu: de hoofdpagina')"
                        :icoon="LayoutList"
                    >
                        <p>
                            {{
                                $t(
                                    'In de zijbalk staat achter Indeling en achter Overzicht een klein sterretje in de merkkleur. Dat betekent iets anders dan het rode sterretje bij een veld: dit is de hoofdpagina van zijn groep, de plek om te beginnen als je niet precies weet waar je moet zijn.',
                                )
                            }}
                        </p>
                    </UitlegKaart>

                    <UitlegKaart
                        :titel="$t('Een pijltje: je gaat het portaal uit')"
                        :icoon="ArrowUpRight"
                    >
                        <p>
                            {{
                                $t(
                                    'Achter een link met dit pijltje ligt je eigen website en niet een beheerscherm. Je zijbalk is daar weg. Met de terugknop van je browser sta je weer hier.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Het opent geen nieuw tabblad. Dat is met opzet: anders had je na drie keer kijken vier tabbladen open staan.',
                                )
                            }}
                        </p>

                        <template #voorbeeld>
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="site.enter()">
                                    <Globe class="size-4" />
                                    {{ $t('Bekijk de website') }}
                                    <VerlaatPortaal />
                                </Link>
                            </Button>
                        </template>
                    </UitlegKaart>

                    <UitlegKaart
                        :titel="
                            $t('Alles staat er twee keer: Nederlands en Engels')
                        "
                        :icoon="Languages"
                    >
                        <p>
                            {{
                                $t(
                                    'Je website is tweetalig, dus elk tekstveld heeft een Nederlandse en een Engelse variant. Bij elk veld staat het vlaggetje van de taal waar je in typt.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Vul eerst het Nederlands in en druk daarna op Vertaal automatisch: dan vult het Engels zich vanzelf. Bij een lange tekst duurt dat even en zie je hoe ver hij is.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Een automatische vertaling krijgt een merkje, zodat je later ziet welke teksten nog nooit door iemand zijn nagelezen. Zodra je het Engels zelf aanpast is dat merkje weg. Staat er al Engels, dan vragen we eerst of het echt overschreven mag worden.',
                                )
                            }}
                        </p>
                    </UitlegKaart>

                    <UitlegKaart
                        :titel="$t('Online en offline, per onderdeel')"
                        :icoon="Eye"
                    >
                        <p>
                            {{
                                $t(
                                    'Bijna alles wat je aanmaakt heeft een schuifje voor online. Staat het uit, dan bewaren wij het wel maar zien je bezoekers het niet. Handig om iets alvast klaar te zetten en later pas te tonen.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Bij het toevoegen vragen we meteen of het online mag. Later omzetten kan altijd, met het schuifje in de lijst of op de pagina van het item zelf.',
                                )
                            }}
                        </p>
                    </UitlegKaart>

                    <UitlegKaart
                        :titel="$t('Zoeken en bladeren')"
                        :icoon="Search"
                    >
                        <p>
                            {{
                                $t(
                                    'Boven elke lijst staat een zoekveld. Je hoeft niet op Enter te drukken; de lijst loopt mee terwijl je typt, en je zoekopdracht blijft in de adresbalk staan zodat je hem kunt bewaren of delen.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    "Wordt een lijst lang, dan komen er onderaan genummerde pagina's. Klik je op een regel, dan open je de pagina van dat item.",
                                )
                            }}
                        </p>
                    </UitlegKaart>

                    <UitlegKaart
                        :titel="$t('Je website beweegt mee')"
                        :icoon="Sparkles"
                    >
                        <p>
                            {{
                                $t(
                                    'Op je publieke website komt tekst op terwijl je scrolt, tekent er een lijn mee met de stappen van je werkwijze, en verschuift de gloed op de achtergrond licht met de muis. Bij binnenkomst is er kort een merkintro; die zie je één keer per bezoek en niet bij elke pagina opnieuw.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Het is versiering en nooit een voorwaarde. Alles staat er ook als er niets beweegt, en de pagina houdt je nergens tegen -- je kunt altijd gewoon doorscrollen.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Heeft een bezoeker in zijn systeeminstellingen aangegeven minder beweging te willen, dan zetten we het vanzelf uit en ziet hij dezelfde site zonder animaties. Dat is geen uitzondering die wij per geval regelen; het gaat overal automatisch.',
                                )
                            }}
                        </p>
                    </UitlegKaart>

                    <UitlegKaart
                        :titel="$t('Licht of donker')"
                        :icoon="SunMoon"
                    >
                        <p>
                            {{
                                $t(
                                    'Onder Weergave kies je of het portaal licht of donker staat. Dat geldt alleen voor jou en alleen voor dit beheergedeelte; je website blijft altijd in de huisstijl.',
                                )
                            }}
                        </p>
                    </UitlegKaart>
                </div>

                <!-- ------------------------ Website ------------------------ -->
                <div
                    v-else-if="onderdeel === 'website'"
                    key="website"
                    class="space-y-3"
                >
                    <p class="text-sm text-pretty text-muted-foreground">
                        {{
                            $t(
                                'Dit zijn de schermen waarmee je je eigen website vult. Alles wat je hier opslaat staat meteen online.',
                            )
                        }}
                    </p>

                    <UitlegKaart :titel="$t('Indeling')" :icoon="LayoutList">
                        <p>
                            {{
                                $t(
                                    'De kaart van je website: alle onderdelen onder elkaar, in de volgorde waarin een bezoeker ze tegenkomt. Hier begin je als je niet precies weet waar iets staat -- vanaf elk onderdeel klik je door naar het scherm waar je het beheert.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Sleep een onderdeel naar boven of beneden om de volgorde te wijzigen; met het toetsenbord kan het ook, met de pijltjestoetsen. Met het schuifje zet je een heel onderdeel uit: het blijft bestaan, maar bezoekers zien het niet meer.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Staat er een oranje driehoekje bij een onderdeel, dan staat het aan maar is het leeg. Wij laten het dan niet zien, want een lege kop op je website is slordiger dan geen kop. Vul het en het verschijnt vanzelf.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'De kop en de voettekst staan vast op hun plek. Die horen altijd boven- en onderaan, dus daar valt niets te slepen.',
                                )
                            }}
                        </p>

                        <template #voorbeeld>
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="website.index()">
                                    <LayoutList class="size-4" />
                                    {{ $t('Open de indeling') }}
                                </Link>
                            </Button>
                        </template>
                    </UitlegKaart>

                    <UitlegKaart :titel="$t('LinkedIn')" :icoon="Share2">
                        <p>
                            {{
                                $t(
                                    'Onderaan je website staat een blok dat bezoekers uitnodigt je op LinkedIn te volgen, met een knop naar je profiel. Dat is de plek waar iemand die nog geen bericht wil sturen je toch kan blijven volgen.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Helemaal bovenaan staat hetzelfde logo nog een keer, klein, naast de twee knoppen in de kop. Dat is voor de bezoeker die meteen weet wat hij zoekt en niet eerst naar beneden wil.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Hier valt niets in te vullen: je LinkedIn-adres ligt vast. Dat is met opzet -- een tikfout in die link is een knop op je voorpagina die nergens heen gaat. Wil je hem ooit wijzigen, laat het ons dan weten.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Wat je er wél mee kunt: hem verslepen naar een andere plek op je pagina, of helemaal uitzetten. Dat doe je op het scherm Indeling, net als bij de andere onderdelen.',
                                )
                            }}
                        </p>

                        <template #voorbeeld>
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="website.index()">
                                    <LayoutList class="size-4" />
                                    {{ $t('Open de indeling') }}
                                </Link>
                            </Button>
                        </template>
                    </UitlegKaart>

                    <UitlegKaart :titel="$t('Kop')" :icoon="KopIcoon">
                        <p>
                            {{
                                $t(
                                    'De drie teksten bovenaan je website: het kleine opschrift in hoofdletters, de grote titel eronder, en de zin daar weer onder. Dit is het eerste dat een bezoeker leest, dus het is ook het eerste dat je wilt kloppen.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Je ziet de twee talen naast elkaar, zoals ze op je site komen te staan. Staat er rechts iets schuingedrukt, dan is dat het Nederlands: dat betekent dat er nog geen Engelse tekst is en dat je Engelse bezoeker dus het Nederlands ziet.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Het opschrift staat op een telefoon ergens anders: daar hoort het bij je naam op het kaartje met je foto. Je past het maar op één plek aan; wij zetten het op allebei de plekken goed neer.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'De zin eronder mag leeg blijven. Dan staat er gewoon niets, en schuiven de knoppen netjes omhoog.',
                                )
                            }}
                        </p>

                        <template #voorbeeld>
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="kop.index()">
                                    <KopIcoon class="size-4" />
                                    {{ $t('Open de kop') }}
                                </Link>
                            </Button>
                        </template>
                    </UitlegKaart>

                    <UitlegKaart :titel="$t('Diensten')" :icoon="Lightbulb">
                        <p>
                            {{
                                $t(
                                    'Wat je aanbiedt, als kaarten op je website. Elke dienst krijgt een pictogram, een titel, een korte tekst en een lijstje met de expertise die eronder valt.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Er is geen maximum: zet er zoveel neer als je wilt. Op je website staan er vier tegelijk, en op een telefoon drie -- daarna bladert je bezoeker met de knopjes eronder naar de volgende. Zo wordt het blok nooit zo lang dat de rest van je pagina eronder verdwijnt.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'De uitgebreide tekst mag je leeg laten. Vul je hem in, dan kan een bezoeker op de kaart klikken en opent er een venster met dat verhaal. Laat je hem leeg, dan is de kaart gewoon een kaart.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Met de knop Volgorde sleep je ze in de volgorde die je wilt. Met het schuifje haal je er eentje tijdelijk vanaf zonder hem kwijt te raken. Staat álles uit, dan verdwijnt het hele blok van je website.',
                                )
                            }}
                        </p>

                        <template #voorbeeld>
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="dienstenRoutes.index()">
                                    <Lightbulb class="size-4" />
                                    {{ $t('Open de diensten') }}
                                </Link>
                            </Button>
                        </template>
                    </UitlegKaart>

                    <UitlegKaart :titel="$t('Ervaring')" :icoon="Milestone">
                        <p>
                            {{
                                $t(
                                    'Je loopbaan als tijdlijn. Eén regel per functie, met de organisatie, de plaats en de periode. Wat nu loopt staat bovenaan; de rest staat op datum, van nieuw naar oud.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Een periode vul je in maanden in, niet in dagen -- niemand weet nog op welke dag hij ergens begon. Loopt de functie nog, dan laat je het einde leeg en zet je website er "tot vandaag" bij.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Bij elke ervaring hoort een beeldje: het logo van de organisatie of, als je dat niet hebt, een pictogram uit de lijst. Het is het een of het ander, en het staat op dezelfde plek.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Klik op een regel en je krijgt de pagina van die ene ervaring, met beide talen naast elkaar en knoppen om naar de oudere of nieuwere ervaring te springen.',
                                )
                            }}
                        </p>

                        <template #voorbeeld>
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="ervaring.index()">
                                    <Milestone class="size-4" />
                                    {{ $t('Open Ervaring') }}
                                </Link>
                            </Button>
                        </template>
                    </UitlegKaart>

                    <UitlegKaart
                        :titel="$t('Een logo bijsnijden')"
                        :icoon="Image"
                        :onder="$t('Ervaring')"
                    >
                        <p>
                            {{
                                $t(
                                    "Logo's komen in alle vormen en maten, en op je website staan ze allemaal in hetzelfde vierkantje. Daarom kun je na het kiezen zelf in- en uitzoomen en het beeld verschuiven tot het goed staat.",
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Wat je in het vierkantje ziet, is precies wat er op je website komt. Wij maken er daarna één nette afbeelding van, zodat je pagina niet trager wordt van een bestand van vijf megabyte.',
                                )
                            }}
                        </p>
                    </UitlegKaart>

                    <UitlegKaart
                        :titel="$t('Je hoeft niet op de grootte te letten')"
                        :icoon="Sparkles"
                        :onder="$t('Ervaring')"
                    >
                        <p>
                            {{
                                $t(
                                    'Kies gerust een foto rechtstreeks van je telefoon. Is het bestand te groot, dan verkleinen we het meteen in je browser -- nog voordat het verstuurd wordt. Je ziet dan één regel staan waarin we vertellen hoeveel kleiner het is geworden.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Aan je website is daar niets van te zien: het beeldmerk wordt daar toch klein getoond. Het scheelt wel wachttijd, voor jou bij het uploaden en voor je bezoekers bij het laden.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Alleen een beeld dat echt te klein is -- kleiner dan 48 bij 48 pixels -- kunnen we niet redden. Dat zeggen we meteen bij het kiezen, dus je komt er niet pas achter als je op Opslaan drukt.',
                                )
                            }}
                        </p>
                    </UitlegKaart>

                    <UitlegKaart
                        :titel="$t('De kop en de cijfers boven je tijdlijn')"
                        :icoon="Hash"
                        :onder="$t('Ervaring')"
                    >
                        <p>
                            {{
                                $t(
                                    'Achter de knop "Kop en cijfers" zit alles wat bóven je tijdlijn staat: de titel, de zin eronder en de getallen. Dat zit in één venster omdat het op je website ook één blok is -- je slaat het dus in één keer op.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'De titel en de zin vul je in het Nederlands en het Engels in, net als bij een ervaring, en ook hier kun je het Engels automatisch laten vertalen.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'De getallen eronder bepaal je zelf: je zet er maximaal vier neer en je haalt weg wat je niet wilt. Per getal kies je wat er gebeurt. Op "Automatisch" tellen wij het uit je tijdlijn -- jaren ervaring, functies of organisaties -- en dan klopt het vanzelf zodra er een ervaring bij komt of er een jaar voorbijgaat.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Op "Eigen getal" vul je het zelf in. Dat is ook hoe je aan een cijfer komt dat niets met je tijdlijn te maken heeft, zoals het aantal certificeringen. Wijkt zo\'n ingevuld getal later af van wat wij zouden tellen, dan zeggen we dat erbij -- anders staat er een verouderd getal op je voorpagina waar niemand meer naar kijkt.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Op "Niet tonen" verdwijnt het getal van je website, maar blijft het hier gewoon staan. Zo kun je het later weer aanzetten zonder alles opnieuw in te vullen.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Het woord onder een getal mag je overschrijven -- "opdrachten" in plaats van "functies", bijvoorbeeld. Laat je het leeg, dan gebruiken we ons eigen woord, en dat is meteen vertaald: je Engelse bezoeker ziet dan vanzelf het Engelse woord.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Vul je er zelf een woord in, dan verschijnt er een klein knopje "Vertaal" naast het Engelse veld. Eén klik en het staat er; je kunt het daarna nog gewoon aanpassen.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Boven in elk kaartje staat een voorbeeld van het tegeltje zoals het op je website komt te staan. Kies je iets anders, dan verandert dat voorbeeld meteen mee -- je hoeft dus niet te gokken wat er gebeurt.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Een getal weghalen vraagt eerst om een bevestiging, net als overal. Wil je het eigenlijk alleen even niet laten zien, kies dan "Niet tonen": dan blijft alles bewaard.',
                                )
                            }}
                        </p>
                    </UitlegKaart>

                    <UitlegKaart
                        :titel="$t('Bekijk de website')"
                        :icoon="Globe"
                    >
                        <p>
                            {{
                                $t(
                                    'De onderste regel in de groep Website brengt je naar je eigen site, precies zoals een bezoeker hem ziet. Controleer daar even wat je zojuist hebt gewijzigd; het staat er al.',
                                )
                            }}
                        </p>
                    </UitlegKaart>
                </div>

                <!-- ---------------------- Administratie --------------------- -->
                <div
                    v-else-if="onderdeel === 'administratie'"
                    key="administratie"
                    class="space-y-3"
                >
                    <p class="text-sm text-pretty text-muted-foreground">
                        {{
                            $t(
                                'De zakelijke kant: wat er de deur uit gaat. Voorlopig is dat alleen je mail; hier komt later meer bij.',
                            )
                        }}
                    </p>

                    <UitlegKaart :titel="$t('Mail')" :icoon="Mail">
                        <p>
                            {{
                                $t(
                                    'Elke mail die het portaal verstuurt komt hier te staan, met onderwerp, ontvanger en status. Zegt iemand dat hij een bericht niet ontvangen heeft, dan zie je hier of het weg is en of het is aangekomen.',
                                )
                            }}
                        </p>

                        <template #voorbeeld>
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="adminMail.index()">
                                    <Mail class="size-4" />
                                    {{ $t('Open Mail') }}
                                </Link>
                            </Button>
                        </template>
                    </UitlegKaart>
                </div>

                <!-- ------------------------- Beheer ------------------------ -->
                <div v-else key="beheer" class="space-y-3">
                    <p class="text-sm text-pretty text-muted-foreground">
                        {{
                            $t(
                                'Dit gedeelte verandert niets aan je website. Het laat zien wat er gebeurd is: wie er wat wijzigde, wie er probeerde in te loggen en welke mail eruit ging.',
                            )
                        }}
                    </p>

                    <UitlegKaart :titel="$t('Overzicht')" :icoon="Activity">
                        <p>
                            {{
                                $t(
                                    'De samenvatting van het laatste etmaal: mislukte inlogpogingen, het aantal gebeurtenissen, verstuurde mail en eventuele mailproblemen. Staan die getallen op nul, dan hoef je niet verder te kijken.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Daaronder staan de laatste mislukte pogingen, met daarachter de knoppen naar de volledige logboeken.',
                                )
                            }}
                        </p>

                        <template #voorbeeld>
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="adminDashboard()">
                                    <Activity class="size-4" />
                                    {{ $t('Open het overzicht') }}
                                </Link>
                            </Button>
                        </template>
                    </UitlegKaart>

                    <UitlegKaart :titel="$t('Activiteit')" :icoon="Activity">
                        <p>
                            {{
                                $t(
                                    'Wat er aan de inhoud van je website is veranderd, door wie en wanneer. Je kunt filteren op onderdeel en op handeling, dus "laat zien wat er met de tijdlijn is gebeurd" is één klik.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Dit is de plek om te kijken als je je afvraagt waarom er iets anders op je site staat dan je verwachtte.',
                                )
                            }}
                        </p>

                        <template #voorbeeld>
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="adminActivity.index()">
                                    <Activity class="size-4" />
                                    {{ $t('Open Activiteit') }}
                                </Link>
                            </Button>
                        </template>
                    </UitlegKaart>

                    <UitlegKaart
                        :titel="$t('Beveiliging')"
                        :icoon="ShieldAlert"
                    >
                        <p>
                            {{
                                $t(
                                    'Alles rond inloggen: geslaagde en mislukte pogingen, tweestapsverificatie en wachtwoordwijzigingen, met het IP-adres erbij. Filteren kan op soort gebeurtenis en op afloop.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Wachtwoorden, codes en recovery codes komen hier nooit in te staan. Ook niet afgekort.',
                                )
                            }}
                        </p>

                        <template #voorbeeld>
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="adminSecurity.index()">
                                    <ShieldAlert class="size-4" />
                                    {{ $t('Open Beveiliging') }}
                                </Link>
                            </Button>
                        </template>
                    </UitlegKaart>

                    <UitlegKaart :titel="$t('Gebruikers')" :icoon="Users">
                        <p>
                            {{
                                $t(
                                    'De accounts die op dit portaal kunnen inloggen, met hun rol en of tweestapsverificatie aanstaat. In de praktijk is dat er één: dat van jou.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                $t(
                                    'Je eigen account kun je hier niet aanpassen of verwijderen. Dat is met opzet: anders kun je jezelf buitensluiten en is er niemand meer die naar binnen kan. Je eigen gegevens wijzig je onder Profiel en Beveiliging.',
                                )
                            }}
                        </p>

                        <template #voorbeeld>
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="adminUsers.index()">
                                    <Users class="size-4" />
                                    {{ $t('Open Gebruikers') }}
                                </Link>
                            </Button>
                        </template>
                    </UitlegKaart>
                </div>
            </Transition>
        </div>

        <p class="text-sm text-pretty text-muted-foreground">
            {{
                $t(
                    'Staat er iets niet bij, of werkt iets anders dan hier beschreven? Laat het weten -- dan klopt onze uitleg niet, en dat is aan ons om te herstellen.',
                )
            }}
        </p>
    </div>
</template>
