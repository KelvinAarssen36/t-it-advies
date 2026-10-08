<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    ArrowUpRight,
    Asterisk,
    Award,
    ChartNoAxesColumn,
    CircleQuestionMark,
    UserRound,
    CheckCheck,
    Clock,
    Eye,
    Fingerprint,
    FolderKanban,
    Globe,
    Hash,
    // Onze eigen Heading.vue heet ook zo; vandaar de andere naam hier.
    Heading as KopIcoon,
    Image,
    Inbox,
    Languages,
    LayoutList,
    Lightbulb,
    Mail,
    MessageSquare,
    Milestone,
    MousePointerClick,
    Pencil,
    Scale,
    Plus,
    Route,
    Search,
    Share2,
    ShieldAlert,
    ShieldCheck,
    Sparkles,
    SunMoon,
    UserCog,
    X,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    aantalTreffers,
    leegZoekterm,
    zoekt,
    zoekterm,
} from '@/lib/handleiding';
import Heading from '@/components/Heading.vue';
import SegmentToggle from '@/components/SegmentToggle.vue';
import UitlegKaart from '@/components/settings/UitlegKaart.vue';
import VerlaatPortaal from '@/components/VerlaatPortaal.vue';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import { dashboard as adminDashboard } from '@/routes/admin';
import adminAanvragen from '@/routes/admin/submissions';
import adminActivity from '@/routes/admin/activity';
import adminLegal from '@/routes/admin/legal';
import adminMail from '@/routes/admin/mail';
import adminSecurity from '@/routes/admin/security';
import adminVisitors from '@/routes/admin/visitors';
import adminUsers from '@/routes/admin/users';
import dashboardSettings from '@/routes/dashboard-settings';
import { show } from '@/routes/documentation';
import { show as showSafety } from '@/routes/safety';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import site from '@/routes/site';
import website from '@/routes/website';
import certificatenRoutes from '@/routes/website/certificaten';
import contactRoutes from '@/routes/website/contact';
import dienstenRoutes from '@/routes/website/diensten';
import faqRoutes from '@/routes/website/faq';
import overMijRoutes from '@/routes/website/over-mij';
import projectenRoutes from '@/routes/website/projecten';
import werkwijzeRoutes from '@/routes/website/werkwijze';
import statistiekenRoutes from '@/routes/website/statistieken';
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

        <!--
            Het zoekveld staat bóven de onderdelenkiezer, want zoeken gaat
            dwars door alle onderdelen heen. Stond het eronder, dan leest het
            als "zoek binnen dit onderdeel", en dat is het niet.
        -->
        <div class="brand-uitleg-zoek">
            <Search class="brand-uitleg-zoek-teken size-4" aria-hidden="true" />
            <input
                v-model="zoekterm"
                type="search"
                class="brand-uitleg-zoek-veld"
                :placeholder="$t('Zoek in de handleiding')"
                :aria-label="$t('Zoek in de handleiding')"
                @keydown.escape="leegZoekterm"
            />
            <button
                v-if="zoekterm.length > 0"
                type="button"
                class="brand-uitleg-zoek-leeg"
                :aria-label="$t('Zoekterm wissen')"
                @click="leegZoekterm"
            >
                <X class="size-4" />
            </button>
        </div>

        <!--
            Hoeveel er gevonden is. Dit staat er alleen tijdens het zoeken:
            buiten een zoekopdracht is "29 onderwerpen" geen informatie maar
            ruis.
        -->
        <p
            v-if="zoekt"
            aria-live="polite"
            class="text-sm text-muted-foreground"
        >
            <template v-if="aantalTreffers === 1">
                {{ $t('1 onderwerp gevonden') }}
            </template>
            <template v-else>
                {{
                    $t(':aantal onderwerpen gevonden', {
                        aantal: aantalTreffers,
                    })
                }}
            </template>
        </p>

        <SegmentToggle
            v-show="!zoekt"
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
                <!--
                    Eén omhulsel met een wisselende sleutel, en de drie
                    onderdelen daarbinnen.

                    **Tijdens het zoeken staan ze alle drie open**, want
                    anders doorzoek je alleen het onderdeel dat toevallig
                    aanstaat -- en dan moet je weten waar iets staat om het
                    te kunnen vinden, wat precies de vraag is die je stelde.
                    De sleutel wordt dan `zoeken`, zodat de overgang één keer
                    speelt in plaats van bij elke toetsaanslag.
                -->
                <div :key="zoekt ? 'zoeken' : onderdeel" class="space-y-3">
                    <!-- ----------------------- Basis ----------------------- -->
                    <div
                        v-if="zoekt || onderdeel === 'basis'"
                        class="space-y-3"
                    >
                        <p
                            v-if="!zoekt"
                            class="text-sm text-pretty text-muted-foreground"
                        >
                            {{
                                $t(
                                    'Deze dingen werken overal in het portaal hetzelfde. Ken je ze eenmaal, dan kun je met elk scherm overweg -- ook met de schermen die er later bij komen.',
                                )
                            }}
                        </p>

                        <UitlegKaart
                            :titel="
                                $t('De kleur van een knop zegt wat hij doet')
                            "
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
                            :titel="
                                $t('In een lijst zijn diezelfde knoppen zacht')
                            "
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
                                $t(
                                    'Voordat er iets live gaat, vragen we het na',
                                )
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
                                $t(
                                    'Een sterretje bij een veld: dit moet ingevuld',
                                )
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
                            :titel="
                                $t('Een sterretje in het menu: de hoofdpagina')
                            "
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
                                $t(
                                    'Alles staat er twee keer: Nederlands en Engels',
                                )
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
                            <p>
                                {{
                                    $t(
                                        'Deze handleiding heeft er zelf ook een, bovenaan. Die zoekt in alle onderwerpen tegelijk -- ook in de onderdelen die je nu niet open hebt staan -- en kijkt naar de hele tekst van een kaart en niet alleen naar de titel. Weet je dus nog net één woord, dan is dat genoeg.',
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
                            <p>
                                {{
                                    $t(
                                        'Op datzelfde scherm staan twee voorbeelden van je mail. Het eerste is de bevestiging die een bezoeker krijgt, met jouw eigen tekst erin -- dat is niet een plaatje maar de echte mail. Het tweede toont de bouwstenen: een knop, een uitgelicht vak en een tabel. Die komen voor in de meldingen die jij krijgt, niet in de bevestiging, en daarom staan ze apart.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Daar kies je ook hoe je mail eruitziet. "Licht" is een witte mail met je merkkleur als accent; "Huisstijl" is donkerblauw, zoals je website. Dat tweede is mooier, maar in een enkel ouder mailprogramma kan de donkere achtergrond wegvallen -- daarom staat licht standaard aan. Je ziet het verschil meteen in het voorbeeld eronder, en je kunt altijd terug.',
                                    )
                                }}
                            </p>
                        </UitlegKaart>

                        <UitlegKaart
                            :titel="$t('De klok op je dashboard')"
                            :icoon="Clock"
                        >
                            <p>
                                {{
                                    $t(
                                        'Op je beginscherm staat midden bovenin een klok met de datum eronder. Op een telefoon staat hij bovenaan als een dunne strook, zodat hij de rest niet wegduwt.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Links ernaast staat wat je website doet, met boven elk cijfer waar het over gaat. Onder "Totaal sinds de start" staat hoe vaak je website is bekeken sinds de allereerste dag; dat getal loopt nooit terug, want die cijfers gooien we niet weg. Onder "Vandaag" staat hoeveel bezoekers er vandaag langskwamen en hoeveel pagina\'s zij samen hebben bekeken. De staafjes onderin zijn de laatste twee weken, met vandaag als laatste. Klik op dat vlak en je komt in het volledige overzicht.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Onder Instellingen → Dashboard kies je in welke tijdzone hij loopt. Nederland staat er standaard op, en er zijn er nog vier om uit te kiezen. Zomer- en wintertijd gaan automatisch mee, dus daar hoef je nooit iets aan te doen. Onder de keuze loopt dezelfde klok mee, zodat je ziet wat je kiest voordat je opslaat.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Dat gedeelte is er voor de instellingen van je dashboard. Nu staat er alleen de klok; komen er later meer dingen die je zelf op je beginscherm wilt zetten, dan komen die daar ook te staan.',
                                    )
                                }}
                            </p>

                            <template #voorbeeld>
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="dashboardSettings.edit()">
                                        <Clock class="size-4" />
                                        {{
                                            $t('Open de dashboardinstellingen')
                                        }}
                                    </Link>
                                </Button>
                            </template>
                        </UitlegKaart>

                        <UitlegKaart
                            :titel="$t('Hoe je site beveiligd is')"
                            :icoon="ShieldCheck"
                        >
                            <p>
                                {{
                                    $t(
                                        'Onder Instellingen → Veiligheid staat in gewone taal wat er allemaal gebeurt om je website en dit portaal te beschermen. Je hoeft daar niets in te stellen; het staat er zodat je weet waar je aan toe bent.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Je ziet er een paar echte cijfers over de afgelopen maand -- mislukte inlogpogingen, wat er is tegengehouden, en of je mail aankomt -- en daaronder in gewone taal wat er voor je geregeld is. Onderaan staan de twee dingen die je zelf kunt aanzetten: een code uit een app bij het inloggen, en inloggen met je vingerafdruk of gezicht.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Wat daar staat wordt op dat moment uit je instellingen gelezen. Er staat dus nooit dat iets je beschermt terwijl het niet aanstaat -- dat zou erger zijn dan geen scherm, want dan denk je beschermd te zijn.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Gaat er iets mis, dan krijg je daar binnen het uur een mail over. Dat gebeurt bij drie dingen: veel mislukte inlogpogingen, mail die niet aankomt, en post die blijft liggen. Dat laatste klinkt vaag maar is de belangrijkste van de drie: je website zet een bericht eerst in een rij en verstuurt het daarna. Loopt dat vast, dan lijkt alles goed te gaan -- de bezoeker krijgt zijn bedankje en de aanvraag staat in je portaal -- terwijl jij geen mail krijgt. Komt die melding voorbij, stuur hem dan door; het is iets aan de server en niet aan je website.',
                                    )
                                }}
                            </p>

                            <template #voorbeeld>
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="showSafety()">
                                        <ShieldCheck class="size-4" />
                                        {{ $t('Open Veiligheid') }}
                                    </Link>
                                </Button>
                            </template>
                        </UitlegKaart>

                        <UitlegKaart
                            :titel="$t('Je eigen account')"
                            :icoon="UserCog"
                        >
                            <p>
                                {{
                                    $t(
                                        'Onder Instellingen → Profiel staan je naam en je e-mailadres. Dat adres is waarmee je inlogt; verander je het, dan log je voortaan met het nieuwe adres in. Het heeft niets te maken met het adres dat op je website staat -- dat beheer je los.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Onder Instellingen → Beveiliging wijzig je je wachtwoord en staat je tweestapsverificatie. Die vraagt bij het inloggen om een code uit een app op je telefoon, naast je wachtwoord. Hij staat aan en dat hoort zo: zonder is je wachtwoord het enige dat tussen een vreemde en je website staat.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Bewaar je recovery codes op een plek buiten je telefoon -- op papier of in je wachtwoordmanager. Raak je je telefoon kwijt, dan zijn die codes de enige manier om nog binnen te komen. Elke code werkt één keer. Zijn ze op of kwijt, dan kun je nieuwe laten maken zolang je nog ingelogd bent.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Voor dingen die je niet kunt terugdraaien vraagt het portaal nog een keer om een code uit je app, ook al ben je al ingelogd. Dat is bewust: het is de laatste drempel voor iemand die achter je computer is gaan zitten.',
                                    )
                                }}
                            </p>

                            <template #voorbeeld>
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <Link :href="editProfile()">
                                            <UserCog class="size-4" />
                                            {{ $t('Naar Profiel') }}
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <Link :href="editSecurity()">
                                            <ShieldCheck class="size-4" />
                                            {{ $t('Naar Beveiliging') }}
                                        </Link>
                                    </Button>
                                </div>
                            </template>
                        </UitlegKaart>

                        <UitlegKaart
                            :titel="$t('Inloggen met je vingerafdruk')"
                            :icoon="Fingerprint"
                        >
                            <p>
                                {{
                                    $t(
                                        'Een passkey is inloggen zonder wachtwoord: je browser vraagt om je vingerafdruk, je gezicht of de pincode van je apparaat, en je bent binnen. Het is niet verplicht, maar het is wel het makkelijkste én het veiligste dat er is -- een nepsite kan een passkey niet van je aftroggelen, want hij werkt alleen op jouw eigen website.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Je stelt er een in onder Instellingen → Beveiliging. Klik op "Passkey toevoegen", geef hem een naam waaraan je het apparaat herkent -- "iPhone" of "laptop" -- en bevestig op je apparaat. Vanaf dan staat er op het inlogscherm een knop om er meteen mee in te loggen.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Je kunt er meerdere naast elkaar hebben, één per apparaat, en je kunt ze los van elkaar weer weghalen. Je wachtwoord en je authenticator-app blijven gewoon werken; de code uit die app blijf je bovendien nodig voor handelingen die niet terug te draaien zijn.',
                                    )
                                }}
                            </p>

                            <template #voorbeeld>
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="editSecurity()">
                                        <Fingerprint class="size-4" />
                                        {{ $t('Naar Beveiliging') }}
                                    </Link>
                                </Button>
                            </template>
                        </UitlegKaart>
                    </div>

                    <!-- ---------------------- Website ---------------------- -->
                    <div
                        v-if="zoekt || onderdeel === 'website'"
                        class="space-y-3"
                    >
                        <p
                            v-if="!zoekt"
                            class="text-sm text-pretty text-muted-foreground"
                        >
                            {{
                                $t(
                                    'Dit zijn de schermen waarmee je je eigen website vult. Alles wat je hier opslaat staat meteen online.',
                                )
                            }}
                        </p>

                        <UitlegKaart
                            :titel="$t('Indeling')"
                            :icoon="LayoutList"
                        >
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

                        <UitlegKaart :titel="$t('Werkwijze')" :icoon="Route">
                            <p>
                                {{
                                    $t(
                                        'De stappen die iemand doorloopt als hij met je in zee gaat. Op je website staan ze genummerd naast elkaar, met een lijn die zich tekent terwijl de bezoeker scrolt en elke stap oplicht als hij aan de beurt is.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'De nummers vul je nergens in: die volgen uit de volgorde. Sleep je een stap naar voren, dan wordt dat 01 en schuift de rest op. Zet je een stap offline, dan schuiven de nummers ook op -- een bezoeker ziet nooit 01, 02, 04.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Per stap kun je er nog twee dingen bij zetten: hoe lang hij duurt en wat hij oplevert. Allebei mogen ze leeg blijven. Juist die twee zijn wat een bezoeker wil weten voordat hij belt, dus het loont om ze in te vullen.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Vul je bij minstens één stap ook het uitgebreide verhaal in, dan krijgt je werkwijze een eigen pagina met een knop ernaartoe. Laat je dat overal leeg, dan blijft het bij de korte kaarten -- en dat is geen gebrek, dat is een keuze.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Je kunt er zes maken, en dat is bewust. Een werkwijze is een verhaal dat iemand moet kunnen onthouden; voorbij een stuk of zes wordt het een projectplan. Heb je er meer nodig, dan zitten er bijna altijd twee tussen die eigenlijk één stap zijn.',
                                    )
                                }}
                            </p>

                            <template #voorbeeld>
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="werkwijzeRoutes.index()">
                                        <Route class="size-4" />
                                        {{ $t('Open de werkwijze') }}
                                    </Link>
                                </Button>
                            </template>
                        </UitlegKaart>

                        <UitlegKaart
                            :titel="$t('Projecten')"
                            :icoon="FolderKanban"
                        >
                            <p>
                                {{
                                    $t(
                                        'Je etalage: wat je hebt gedaan. Niet elk item hoeft een klassiek project te zijn -- je kiest er zelf een soort bij, zoals Interim-opdracht, Migratie of Audit. Staat jouw woord er niet bij, kies dan "Anders" en typ het zelf.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Met het sterretje licht je projecten uit, en dat mogen er zoveel zijn als je wilt. Op je voorpagina staat het bovenste uitgelichte project groot, met daarnaast de eerstvolgende drie uit je lijst en een knop naar al je projecten. Op je projectenpagina draaien alle uitgelichte projecten samen als een slideshow over de volle breedte.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Je mag ook niets uitlichten -- druk dan nog een keer op het sterretje. Dan verdwijnt het grote blok van je voorpagina en begint je projectenpagina gewoon met de lijst. Welk uitgelicht project groot op je voorpagina staat, bepaal je met de volgorde: sleep het naar boven.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Zet je een uitgelicht project offline, dan verdwijnt het van je website en komt er géén project zónder sterretje voor in de plaats; dat zou iets kiezen wat jij niet hebt gekozen. Heb je er meer uitgelicht, dan schuift het volgende uitgelichte project wel door.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Elk project krijgt ook zijn eigen pagina, met een eigen adres dat je kunt delen. Dat adres wordt gemaakt van de titel die je bij het aanmaken invult en blijft daarna staan, ook als je de titel later wijzigt -- zo blijft een link die je iemand hebt gestuurd werken.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'De knop onderaan zo-n projectpagina brengt je terug naar de plek waar je vandaan kwam: naar je voorpagina als je daar hebt geklikt, en anders naar het overzicht met al je projecten.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Een afbeelding is niet verplicht. Zet je er geen in, dan komt er een vlak met de eerste letter van de organisatie; het project staat er dan gewoon netjes bij.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Onder Weergave kies je hoe de pagina met al je projecten eruitziet: onder elkaar als lijst, of naast elkaar als kaarten. De lijst leest het makkelijkst en blijft compact, ook als je er twintig hebt; kaarten zijn luchtiger en komen beter tot hun recht zodra je projecten een afbeelding hebben. Je kunt het altijd omzetten -- het blok op je voorpagina verandert er niet door.',
                                    )
                                }}
                            </p>

                            <template #voorbeeld>
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="projectenRoutes.index()">
                                        <FolderKanban class="size-4" />
                                        {{ $t('Open Projecten') }}
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

                        <UitlegKaart :titel="$t('Certificaten')" :icoon="Award">
                            <p>
                                {{
                                    $t(
                                        'Wat je hebt gehaald en bij wie. Op je website staan ze als tegels met het logo van de uitgever groot bovenaan -- Microsoft, Cisco, wie het ook was. Dat logo is waar een bezoeker naar kijkt; de naam eronder leest hij daarna pas.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Je vult de naam in, de uitgever en de maand waarin je het haalde. Het certificaatnummer en een korte toelichting mogen, maar hoeven niet. Zet je er een van de twee neer, dan kan een bezoeker op de tegel klikken en opent er een venster met die gegevens.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Heeft een certificaat een geldigheidsdatum, vul die dan in. Is die voorbij, dan zie je dat hier in de lijst staan, maar je bezoekers merken er niets van: op je website blijft het gewoon staan en er staat nergens dat het verlopen is. Behaald is behaald. Wij halen er nooit iets vanaf zonder dat jij het zegt -- wil je het wél weg, dan zet je het uit of verwijder je het.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Er is geen maximum. Op je website staan er acht tegelijk, en op een telefoon vier -- daarna bladert je bezoeker met de knopjes eronder verder. Met de knop Volgorde bepaal je zelf welke vooraan staan.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Onderaan datzelfde scherm staat een tweede, kleiner blok voor je opleiding. Dat is helemaal optioneel: laat je het leeg, dan is er op je website niets van te zien. Vul je er iets in, dan komt er onder je certificaten een kort lijstje te staan.',
                                    )
                                }}
                            </p>

                            <template #voorbeeld>
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="certificatenRoutes.index()">
                                        <Award class="size-4" />
                                        {{ $t('Open Certificaten') }}
                                    </Link>
                                </Button>
                            </template>
                        </UitlegKaart>

                        <UitlegKaart
                            :titel="$t('Statistieken')"
                            :icoon="ChartNoAxesColumn"
                        >
                            <p>
                                {{
                                    $t(
                                        'Waar je goed in bent, in cijfers. Per cijfer kies je zelf de vorm: een balk die volloopt, een ring die zichzelf tekent, of een groot getal dat oploopt. Naast het formulier zie je meteen hoe het eruit gaat zien.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Een balk en een ring zijn percentages en lopen dus tot honderd. Wil je een aantal laten zien -- vijfhonderd opgeloste tickets, twintig jaar ervaring -- kies dan de teller; daar mag elk getal in. Het teken ervoor en erachter bepaal je zelf, dus "€ 1.200" en "500+" kunnen allebei.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Met het veld Groep zet je cijfers bij elkaar onder een kopje: alles met "Netwerk" komt bij elkaar te staan. Het veld stelt groepen voor die je al gebruikt, zodat je niet per ongeluk twee keer bijna hetzelfde typt. Laat je het leeg, dan staat het cijfer los bovenaan, boven de eerste kop.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Met de knop Indeling regel je de groepen. Daar staat elk vak voor één groep, met zijn naam erboven in het Nederlands en in het Engels. Sleep een cijfer naar een ander vak en het hoort bij die groep -- je ziet het vak oplichten zodra je erboven hangt. Werk je liever zonder slepen, dan kies je de groep in de lijst achter de regel.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'In datzelfde venster hernoem je een groep door in zijn naam te typen, maak je er een nieuwe bij met de knop onderaan, en verplaats je een hele groep met de pijltjes in zijn kop. Een groep opheffen kan ook; de cijfers erin blijven dan staan en komen los bovenaan. Een groep zonder cijfers verdwijnt van je website, want er is dan niets om een kopje boven te zetten.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Typ je een nieuwe groepsnaam of pas je er een aan, dan verschijnt er een klein knopje naast het Engelse veld dat die naam voor je vertaalt. Je leest het na en past aan wat je wilt; opgeslagen wordt het pas als jij op Opslaan drukt. Staat het Engels er al goed, dan is het knopje er niet -- dan valt er niets te vertalen zonder jouw tekst te overschrijven.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Binnen een groep houdt je website jouw volgorde aan. Staan er twee van dezelfde vorm achter elkaar, dan komen die naast elkaar op één rij; zet je er een andere vorm tussen, dan begint daaronder een nieuwe rij. Zo bepaal je zelf wat samen op een rij komt.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'En het leukste: zodra je bezoeker bij dit blok komt, vullen ze zich allemaal op. Niet tegelijk, maar als een golf van boven naar beneden, met een lichtpuntje dat met elke balk meeloopt. Daarna blijven de cijfers staan -- scrolt hij terug, dan verandert er niets meer.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Helemaal stil staat het daarna niet: er blijft een lichtpuntje rondgaan. Bij een ring loopt het een rondje over de boog, bij een balk schuift het over het gevulde stuk, en bij een groot getal zakt het langs het streepje ernaast. Eén tegelijk en rustig achter elkaar, zodat het leeft zonder dat het aandacht vraagt.',
                                    )
                                }}
                            </p>

                            <template #voorbeeld>
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="statistiekenRoutes.index()">
                                        <ChartNoAxesColumn class="size-4" />
                                        {{ $t('Open Statistieken') }}
                                    </Link>
                                </Button>
                            </template>
                        </UitlegKaart>

                        <UitlegKaart :titel="$t('Over mij')" :icoon="UserRound">
                            <p>
                                {{
                                    $t(
                                        'Een kort stuk over jezelf op je voorpagina, met je foto ernaast. Dat stuk is met opzet begrensd: het is een kennismaking en niet je hele verhaal, en een lange lap tekst midden op je voorpagina duwt de rest naar beneden.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Wil je meer vertellen, zet dan de aparte pagina aan. Bovenaan het scherm staat een schakelaar: "Op je website" of "Op je aparte pagina". Je ziet per keuze wat er nu staat, en met Bewerken pas je die versie aan in een eigen venster. Zo zie je altijd waar iets terechtkomt en typ je nooit per ongeluk in het verkeerde stuk.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Staat de pagina uit, dan kun je hem alsnog invullen en bewaren -- hij komt pas op je website zodra je het schuifje aanzet.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'In het venster van je aparte pagina bewerk je één taal tegelijk: bovenaan kies je Nederlands of English. Anders zou je zes velden onder elkaar krijgen, waarvan twee verhalen van duizenden tekens, en dan weet je na drie regels niet meer welke kolom welke is.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Die pagina bestaat pas als er ook een verhaal in staat. Zet je alleen het schuifje om, dan komt er geen knop op je voorpagina -- een knop naar een pagina met alleen een kop is erger dan geen knop.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Standaard staat er het portret uit je kop: je foto in het oog-embleem. Die zie je meteen op het scherm staan, met eronder welke foto het is, dus je kijkt nooit naar een leeg vak. Wil je een andere foto, zet die er dan zelf in; het bijsnijden werkt net als bij een logo. Haal je hem weer weg, dan komt het portret terug -- je kunt dus niet zonder foto komen te zitten.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Je hebt één foto, en die staat op je voorpagina én op je aparte pagina. Daarom hoort hij bij geen van de twee en heeft hij een eigen knop: "Foto aanpassen" staat bovenaan bij allebei, en opent zijn eigen venster. Twee plekken om dezelfde foto te kiezen zou betekenen dat de laatste die je opslaat de andere stil overschrijft.',
                                    )
                                }}
                            </p>

                            <template #voorbeeld>
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="overMijRoutes.index()">
                                        <UserRound class="size-4" />
                                        {{ $t('Open Over mij') }}
                                    </Link>
                                </Button>
                            </template>
                        </UitlegKaart>

                        <UitlegKaart
                            :titel="$t('Vragen')"
                            :icoon="CircleQuestionMark"
                        >
                            <p>
                                {{
                                    $t(
                                        'De vragen die je vaker krijgt, met je antwoord eronder. Op je website staat de vraag als één regel; een bezoeker klikt hem open en het antwoord schuift eronder uit. Er staat er altijd maar één open, dus de pagina blijft overzichtelijk.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Schrijf een vraag zoals een bezoeker hem zou stellen, niet zoals jij hem zou samenvatten: "Wat kost een migratie?" werkt beter dan "Tarieven". Dat is namelijk ook wat iemand bij Google intypt.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Er staan er zes per bladzijde met knopjes eronder, zodat het blok even hoog blijft hoeveel vragen je ook toevoegt. Zet met Volgorde bovenaan wat je het vaakst krijgt gevraagd -- dat is de bladzijde die de meeste mensen zien. Alle vragen staan wel in je pagina, ook die van bladzijde twee, zodat Google ze allemaal kan vinden.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Zet in de zin onder de kop wat iemand moet doen als zijn vraag er niet bij staat. Zonder die zin is een vragenlijst een doodlopende weg voor precies de bezoeker met de vraag die jij nog niet had bedacht -- en dat is vaak degene die je wilt spreken.',
                                    )
                                }}
                            </p>

                            <template #voorbeeld>
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="faqRoutes.index()">
                                        <CircleQuestionMark class="size-4" />
                                        {{ $t('Open Vragen') }}
                                    </Link>
                                </Button>
                            </template>
                        </UitlegKaart>

                        <UitlegKaart
                            :titel="$t('Contact')"
                            :icoon="MessageSquare"
                        >
                            <p>
                                {{
                                    $t(
                                        'Je contactformulier. Hier bepaal je drie dingen: waar bezoekers uit kunnen kiezen, wat ze moeten invullen, en wat ze terugkrijgen zodra ze op versturen drukken.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'De onderwerpen zijn de keuzes die een bezoeker krijgt bij "Onderwerp" -- bijvoorbeeld "Vrijblijvend gesprek" of "Offerte aanvragen". Hoe duidelijker die zijn, hoe beter je vooraf weet waar een bericht over gaat. Zet je er geen in, dan typt hij zelf iets; dat mag ook.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Bij een onderwerp kun je "Dit onderwerp uitlichten" aanvinken. Dat verandert niets op je website: het zorgt ervoor dat een aanvraag met dát onderwerp eruit springt in je postbus, met een sterretje en een gele tint. Handig voor spoed, zodat je in één blik ziet wat voorgaat. Je volgorde blijft gewoon zoals je hem had gesleept.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Vul je bij een onderwerp ook het Engels in, dan krijgt een Engelse bezoeker die naam te zien. In je eigen postbus staat altijd de naam in jouw taal -- ook bij een bericht van een Engelse bezoeker, zodat je lijst één taal blijft. Welke taal hij zelf gebruikte staat bij het bericht.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Bij de velden kies je per veld of het er staat en of het moet. Naam, e-mailadres en bericht staan vast: zonder die drie kun je niemand antwoorden. Bedrijfsnaam en telefoonnummer staan standaard uit. Bedenk wel: hoe meer je verplicht maakt, hoe minder berichten je krijgt.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Onder "Weergave" kies je of het formulier onderaan je website staat of dat er alleen een knop staat die naar een eigen contactpagina gaat. Dat tweede houdt je website korter en geeft je een adres dat je kunt delen, bijvoorbeeld op LinkedIn.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Onder "Bevestigingsmail" schrijf je wat iemand terugkrijgt. Die mail is vaak het eerste dat hij van je ziet, dus hij mag goed klinken. Vulde hij het formulier op de Engelse versie van je site in, dan krijgt hij automatisch de Engelse tekst.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Onder de verstuurknop staat ook je e-mailadres. Dat is er voor het geval het formulier het even niet doet -- bijvoorbeeld als de spamcontrole niet kan laden omdat er ergens anders iets hapert. Dan kan iemand je nog gewoon mailen in plaats van af te haken.',
                                    )
                                }}
                            </p>

                            <template #voorbeeld>
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="contactRoutes.index()">
                                        <MessageSquare class="size-4" />
                                        {{ $t('Open Contact') }}
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
                            :titel="
                                $t('De kop en de cijfers boven je tijdlijn')
                            "
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

                    <!-- -------------------- Administratie ------------------- -->
                    <div
                        v-if="zoekt || onderdeel === 'administratie'"
                        class="space-y-3"
                    >
                        <p
                            v-if="!zoekt"
                            class="text-sm text-pretty text-muted-foreground"
                        >
                            {{
                                $t(
                                    'De zakelijke kant: wat er de deur uit gaat. Voorlopig is dat alleen je mail; hier komt later meer bij.',
                                )
                            }}
                        </p>

                        <UitlegKaart :titel="$t('Aanvragen')" :icoon="Inbox">
                            <p>
                                {{
                                    $t(
                                        'Alles wat er via je contactformulier binnenkomt staat hier. Klik een regel open en je ziet het hele bericht, het e-mailadres, en wat hij verder heeft ingevuld. Een ongelezen aanvraag heeft een streepje in de accentkleur, zodat je in één blik ziet wat er nog ligt.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Met de twee keuzelijsten bovenaan kijk je gerichter: op stand (alleen ongelezen of onbeantwoord) en op onderwerp. Die twee werken samen, en samen met het zoekveld. Bij onderwerp staat onderaan ook "Zelf ingevuld" -- dat zijn de bezoekers die hun eigen onderwerp typten, en daar zit vaak iets wat nog niet in je lijst staat.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Antwoorden doe je niet hier maar vanuit je eigen mail -- je krijgt elk bericht ook gewoon toegestuurd, en je kunt er rechtstreeks op antwoorden. Dit scherm is er om bij te houden wat je al hebt gedaan.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Een aanvraag staat hier al zodra hij is opgeslagen, en dat gebeurt vóór de mails eruit gaan. Die twee kunnen dus los van elkaar misgaan: staat er een aanvraag waar je geen mail van hebt gekregen, kijk dan onder Beheer → Mail of hij is aangekomen. Lukt het versturen niet, dan krijg je daar binnen het uur ook een melding over. Het bericht zelf is in dat geval niet weg -- het staat hier.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Daarvoor zijn de twee vakjes vóór elke regel: een vinkje voor gelezen en een pijltje voor beantwoord. Je kunt ze meteen aantikken, zonder de regel eerst open te klappen. Die zet je zelf; de website raadt het niet. Beantwoord zet gelezen meteen mee aan, want het een kan niet zonder het ander.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Een aanvraag verdwijnt na een jaar vanzelf. Dat staat ook in je privacyverklaring, dus het is een belofte aan je bezoeker. Wil je er eerder een weg, bijvoorbeeld omdat iemand daarom vraagt, dan kan dat met het prullenbakje -- je moet dan wel een code uit je app invullen.',
                                    )
                                }}
                            </p>

                            <template #voorbeeld>
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="adminAanvragen.index()">
                                        <Inbox class="size-4" />
                                        {{ $t('Open Aanvragen') }}
                                    </Link>
                                </Button>
                            </template>
                        </UitlegKaart>

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
                                    'Dit gedeelte verandert niets aan je website. Het laat zien wat er gebeurd is: hoeveel bezoek je site krijgt, wie er wat wijzigde, wie er probeerde in te loggen en welke mail eruit ging.',
                                )
                            }}
                        </p>

                        <UitlegKaart :titel="$t('Bezoekers')" :icoon="Eye">
                            <p>
                                {{
                                    $t(
                                        'Hoeveel mensen je website bekijken, en of dat meer of minder is dan de periode ervoor. Je kiest zelf of je naar zeven, dertig of negentig dagen kijkt.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Daaronder staat waar ze vandaan komen -- of je LinkedIn echt bezoek oplevert bijvoorbeeld -- met welk apparaat ze kijken, en in welke taal. Dat laatste zegt of het Engels de moeite waard is.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Het cijfer over de berichten via het contactformulier is het nuttigste van allemaal: dat is het enige dat zegt of je site zijn werk doet in plaats van alleen bekeken te worden.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Eén ding om goed te weten: een bezoeker wordt één keer per dag geteld. Komt iemand drie dagen achter elkaar, dan zijn dat over die week drie bezoekers -- we kunnen niet zien dat het dezelfde persoon was. Dat is met opzet: er worden geen cookies gebruikt en er wordt niets op het apparaat van je bezoeker opgeslagen, en daarom hoeft er ook geen cookiemelding op je website te staan.',
                                    )
                                }}
                            </p>

                            <template #voorbeeld>
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="adminVisitors.index()">
                                        <Eye class="size-4" />
                                        {{ $t('Open Bezoekers') }}
                                    </Link>
                                </Button>
                            </template>
                        </UitlegKaart>

                        <!--
                        Deze kaart is er omdat de privacyverklaring op de
                        website een belofte doet die de eigenaar zelf moet
                        nakomen: binnen een maand antwoorden op een verzoek.
                        Dan hoort hij te weten waar hij moet kijken.
                    -->
                        <UitlegKaart
                            :titel="
                                $t('Als een bezoeker zijn gegevens opvraagt')
                            "
                            :icoon="ShieldCheck"
                        >
                            <p>
                                {{
                                    $t(
                                        'In de privacyverklaring op je website staat dat iemand mag vragen welke gegevens je van hem hebt, en dat je binnen een maand antwoordt. Dat komt zelden voor, maar als het gebeurt is dit waar je moet kijken.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Zijn bericht via het contactformulier staat in je portaal, onder Beheer → Aanvragen. Daar blijft het staan zolang de bewaartermijn loopt die onderaan dat scherm staat. Zoek op zijn e-mailadres, stuur hem wat je vindt, en verwijder het als hij daarom vraagt -- dat kun je hier zelf doen.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'In je eigen mailbox staat datzelfde bericht ook, want het is naar je toegestuurd. Dat moet je daar apart weghalen; dat kan de website niet voor je doen.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        "Staat hij in het beveiligingslogboek -- bij een mislukte inlogpoging of een geblokkeerd formulier -- dan vind je dat onder Beveiliging. Zoek daar op zijn e-mailadres of op het IP-adres dat hij je geeft. Zonder zo'n gegeven kun je niet zoeken, en dat mag je hem ook vragen.",
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Dat logboek verwijder je niet, ook niet als hij daarom vraagt. Het bestaat om misbruik te kunnen zien en tegenhouden, en een logboek waar regels uit te halen zijn doet dat niet meer. Dat mag je weigeren -- zeg dan wél waarom, en dat het na een jaar vanzelf verdwijnt. Zo staat het ook in je privacyverklaring.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Er is nog één plek, en die is kort: in het mailoverzicht staat zijn adres als ontvanger van de bevestiging die hij kreeg. Dat is een regel met een tijdstip en een status, zonder de inhoud van zijn bericht, en hij verdwijnt vanzelf. Noem hem wel, want hij hoort erbij.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Daarmee heb je ze gehad. De sessie van zijn bezoek verloopt na twee uur en ruimt zichzelf op. En uit de bezoekcijfers valt niets te halen en niets te verwijderen: daar staan alleen aantallen per dag in. Dat mag je gewoon zo antwoorden.',
                                    )
                                }}
                            </p>

                            <p>
                                {{
                                    $t(
                                        'Je hoeft dit allemaal niet te onthouden: op het scherm Juridisch staat één zoekveld waarmee je iemand opzoekt, een overzicht van wat er waar staat, en een antwoordtekst die je kunt kopiëren en versturen.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Vind je daar niemand, dan zegt het scherm erbij wat je daarna moet doen: zoek óók in je mailbox op dat adres. Een bericht dat ouder is dan de bewaartermijn staat niet meer in je portaal, maar kan daar nog wel liggen. Pas als je daar ook niets vindt, kun je antwoorden dat je niets van hem hebt.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'De antwoordtekst staat er in het Nederlands en in het Engels. Met de vlaggetjes erboven kies je welke je kopieert -- handig als je bezoeker je in het Engels schreef. Dat verandert niets aan de taal van je portaal.',
                                    )
                                }}
                            </p>
                            <p>
                                {{
                                    $t(
                                        'Klik je daar op "Kopieer de tekst", dan verschijnt er een vinkje op de knop. Dat is je teken dat de tekst op je klembord staat en dat je hem in je mail kunt plakken. Na een seconde staat de knop weer zoals hij was; je kunt zo vaak klikken als je wilt.',
                                    )
                                }}
                            </p>

                            <template #voorbeeld>
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="adminLegal.index()">
                                        <Scale class="size-4" />
                                        {{ $t('Open Juridisch') }}
                                    </Link>
                                </Button>
                            </template>
                        </UitlegKaart>

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

                        <UitlegKaart
                            :titel="$t('Activiteit')"
                            :icoon="Activity"
                        >
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

                    <!--
                        Niets gevonden. Zonder dit vak kijk je naar een lege
                        pagina en weet je niet of er niets is of dat er iets
                        stuk is.
                    -->
                    <div
                        v-if="zoekt && aantalTreffers === 0"
                        class="brand-uitleg-leeg"
                    >
                        <Search class="size-5 shrink-0" aria-hidden="true" />
                        <div class="space-y-1">
                            <p class="font-medium">
                                {{
                                    $t('Niets gevonden voor ":term"', {
                                        term: zoekterm,
                                    })
                                }}
                            </p>
                            <p
                                class="text-sm text-pretty text-muted-foreground"
                            >
                                {{
                                    $t(
                                        'Probeer een kortere zoekterm, of blader door de onderdelen hierboven.',
                                    )
                                }}
                            </p>
                        </div>
                    </div>
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
