<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    Archive,
    Award,
    ChartNoAxesColumn,
    ClipboardList,
    CircleQuestionMark,
    Eye,
    FolderKanban,
    Globe,
    Heading,
    History,
    LayoutGrid,
    Inbox,
    LayoutList,
    Lightbulb,
    Mail,
    MessageSquare,
    Milestone,
    Route,
    Scale,
    ShieldAlert,
    UserRound,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import adminActivity from '@/routes/admin/activity';
import adminLegal from '@/routes/admin/legal';
import adminAanvragen from '@/routes/admin/submissions';
import adminMail from '@/routes/admin/mail';
import adminSecurity from '@/routes/admin/security';
import adminBackups from '@/routes/admin/backups';
import adminUsers from '@/routes/admin/users';
import adminVisitors from '@/routes/admin/visitors';
import site from '@/routes/site';
import certificaten from '@/routes/website/certificaten';
import contact from '@/routes/website/contact';
import diensten from '@/routes/website/diensten';
import projecten from '@/routes/website/projecten';
import werkwijze from '@/routes/website/werkwijze';
import ervaring from '@/routes/website/ervaring';
import faq from '@/routes/website/faq';
import kerngegevens from '@/routes/website/kerngegevens';
import statistieken from '@/routes/website/statistieken';
import kop from '@/routes/website/kop';
import overMij from '@/routes/website/over-mij';
import website from '@/routes/website';
import type { NavItem } from '@/types';

/**
 * De zijbalk van het portaal.
 *
 * Vier groepen, en die indeling volgt waar iemand naar op zoek is:
 *
 * 1. **Zonder kopje** bovenaan: het dashboard. Een kopje boven één regel is
 *    meer ruis dan houvast.
 * 2. **Website**: alles waarmee de eigenaar zijn eigen site vult. Dit is
 *    waar hij het vaakst moet zijn, dus het staat bovenaan. De indeling is
 *    er het startpunt van; zie de toelichting daar.
 * 3. **Administratie**: de zakelijke kant. Nu alleen de mail; dit is de
 *    groep die gaat groeien.
 * 4. **Beheer**: de logboeken en de gebruikers. Dat kijk je na, dat gebruik
 *    je niet dagelijks, dus het staat onderaan.
 *
 * **Het verschil tussen 3 en 4 is niet willekeurig.** Beheer gaat over het
 * portaal zelf -- wie wat wijzigde, wie probeerde in te loggen, welke
 * accounts er zijn. Administratie gaat over het bedrijf: wat eruit is
 * gegaan, en straks aan wie en waarvoor. Weet je van iets nieuws niet waar
 * het hoort, stel dan die vraag: gaat het over de website, over de zaak,
 * of over het portaal?
 *
 * **Vier is het maximum.** Een zijbalk met zeven secties is een
 * inhoudsopgave, en daar zoek je langer in dan in een lijst. Komt er iets
 * bij, dan hoort het in een van deze vier.
 */
const page = usePage();

/**
 * Het hele beheergedeelte hangt achter één recht. Er is één rol en die heeft
 * alles; een menu waarin het ene item wel verschijnt en het andere niet,
 * bestaat hier dus niet. Zie docs/security/rollen-en-rechten.md.
 *
 * Dit is puur cosmetisch: wie de URL raadt wordt alsnog door de middleware
 * tegengehouden.
 */
const magBeheren = computed(
    () => page.props.auth.permissions?.includes('manage portal') ?? false,
);

const startItems = computed<NavItem[]>(() => [
    {
        title: t('Dashboard'),
        href: dashboard(),
        icon: LayoutGrid,
    },
]);

/**
 * De inhoud van de website.
 *
 * **Elke module krijgt hier zijn eigen regel.** Dat is een expliciete
 * keuze: het beheerscherm van een onderdeel is waar de eigenaar dagelijks
 * moet zijn, en daar hoor je in één klik te komen. De indeling is een
 * handige kaart van de site -- en je kunt van daaruit ook doorklikken naar
 * elk onderdeel -- maar hij is niet de ingang.
 *
 * Bouw je een module, zet hem dan **hier** erbij, tussen de indeling en
 * "Bekijk de website" in. `AppSidebarTest` valt om als je het vergeet.
 *
 * De indeling draagt `hoofd: true` en krijgt daarmee een sterretje: dat
 * markeert de hoofdpagina van de groep. Per groep hoort er precies één te
 * zijn, anders zegt het teken niets meer.
 *
 * "Bekijk de website" staat onderaan omdat je daar vanaf élk scherm heen
 * wilt kunnen, en niet alleen vanaf de indeling.
 */
const websiteItems = computed<NavItem[]>(() => [
    {
        title: t('Indeling'),
        href: website.index(),
        icon: LayoutList,
        hoofd: true,
    },
    {
        title: t('Kop'),
        href: kop.index(),
        icon: Heading,
    },
    /*
     * Kerngegevens staat tussen de kop en "Over mij", net als op de
     * website zelf: het is de strook die direct onder je koptekst komt.
     */
    {
        title: t('Kerngegevens'),
        href: kerngegevens.index(),
        icon: ClipboardList,
    },
    {
        title: t('Over mij'),
        href: overMij.index(),
        icon: UserRound,
    },
    {
        title: t('Diensten'),
        href: diensten.index(),
        icon: Lightbulb,
    },
    {
        title: t('Werkwijze'),
        href: werkwijze.index(),
        icon: Route,
    },
    {
        title: t('Projecten'),
        href: projecten.index(),
        icon: FolderKanban,
    },
    {
        title: t('Ervaring'),
        href: ervaring.index(),
        icon: Milestone,
    },
    {
        title: t('Certificaten'),
        href: certificaten.index(),
        icon: Award,
    },
    {
        title: t('Statistieken'),
        href: statistieken.index(),
        icon: ChartNoAxesColumn,
    },
    {
        title: t('Vragen'),
        href: faq.index(),
        icon: CircleQuestionMark,
    },
    {
        title: t('Contact'),
        href: contact.index(),
        icon: MessageSquare,
    },
    {
        title: t('Bekijk de website'),
        href: site.enter(),
        icon: Globe,
        verlaat: true,
    },
]);

/**
 * De zakelijke kant: correspondentie en wat daar later bij komt.
 *
 * **Dit is geen beheer en dat onderscheid is de reden dat het een eigen
 * groep is.** Beheer gaat over het portaal zelf -- wie wat wijzigde, wie
 * probeerde in te loggen, welke accounts er zijn. Administratie gaat over
 * het bedrijf: wat eruit is gegaan, en straks aan wie en waarvoor.
 *
 * Nu staat er alleen de mail in en lijkt een eigen kopje overdreven. Dat
 * is het ook, tot het tweede onderdeel erbij komt -- en dan is het
 * prettiger dat de plek er al is dan dat de mail van groep verhuist
 * terwijl de eigenaar hem net had gevonden.
 */
const administratieItems = computed<NavItem[]>(() =>
    magBeheren.value
        ? [
              /*
               * De aanvragen staan vóór het mailoverzicht, want dit is wat
               * hij dagelijks opent. Het mailoverzicht is er voor als er
               * iets niet aankomt.
               */
              {
                  title: t('Aanvragen'),
                  href: adminAanvragen.index(),
                  icon: Inbox,
              },
              {
                  title: t('Mail'),
                  href: adminMail.index(),
                  icon: Mail,
              },
          ]
        : [],
);

/**
 * Het portaal zelf in de gaten houden.
 *
 * De logboeken en de accounts. Dit kijk je na; je gebruikt het niet
 * dagelijks, en daarom staat deze groep onderaan.
 */
const beheerItems = computed<NavItem[]>(() =>
    magBeheren.value
        ? [
              {
                  title: t('Overzicht'),
                  href: adminDashboard(),
                  icon: Activity,
                  hoofd: true,
              },
              /*
               * De bezoekcijfers staan bovenaan deze groep, direct onder
               * het overzicht. Van alles wat je hier nakijkt is dit het
               * enige waar je uit eigen beweging naar toe gaat -- de
               * logboeken bekijk je pas als er iets is.
               */
              {
                  title: t('Bezoekers'),
                  href: adminVisitors.index(),
                  icon: Eye,
              },
              {
                  title: t('Activiteit'),
                  href: adminActivity.index(),
                  icon: History,
              },
              {
                  title: t('Beveiliging'),
                  href: adminSecurity.index(),
                  icon: ShieldAlert,
              },
              {
                  title: t('Gebruikers'),
                  href: adminUsers.index(),
                  icon: Users,
              },
              /*
               * Back-ups staan tussen de logboeken en Juridisch in. Het
               * is het enige scherm in deze groep waar je iets dóét in
               * plaats van naleest, maar het hoort hier wel: het gaat
               * over het portaal zelf en niet over de website.
               */
              {
                  title: t('Back-ups'),
                  href: adminBackups.index(),
                  icon: Archive,
              },
              /*
               * Juridisch staat onderaan, en dat is geen degradatie: je
               * komt hier alleen als iemand om zijn gegevens vraagt, en
               * dat gebeurt zelden. Maar dan moet het er wel staan, want
               * zoeken naar een procedure terwijl de klok van een maand
               * loopt is precies hoe het fout gaat.
               */
              {
                  title: t('Juridisch'),
                  href: adminLegal.index(),
                  icon: Scale,
              },
          ]
        : [],
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <!--
            De scrollbalk staat links en niet rechts.

            Het menu is lang genoeg om te scrollen, en rechts zou de balk
            tegen de rand van het portaal aan liggen -- precies tussen het
            menu en de inhoud in, waar hij de scheiding tussen die twee
            vertroebelt. Links ligt hij tegen de buitenrand van het scherm
            en hoort hij zichtbaar bij de zijbalk.
        -->
        <SidebarContent class="brand-scrollbar-links">
            <NavMain :items="startItems" />

            <NavMain :label="$t('Website')" :items="websiteItems" />

            <NavMain
                v-if="magBeheren"
                :label="$t('Administratie')"
                :items="administratieItems"
            />

            <NavMain
                v-if="magBeheren"
                :label="$t('Beheer')"
                :items="beheerItems"
            />
        </SidebarContent>

        <!--
            Hier stonden de links naar de repository en de documentatie van
            de starter kit. Die zijn weggehaald: dit portaal is van de klant,
            en reclame voor het gereedschap waarmee het gebouwd is hoort daar
            niet in. Alleen het accountmenu blijft over.
        -->
        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
