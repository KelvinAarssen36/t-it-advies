<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    Globe,
    History,
    LayoutGrid,
    LayoutList,
    Mail,
    Milestone,
    ShieldAlert,
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
import adminMail from '@/routes/admin/mail';
import adminSecurity from '@/routes/admin/security';
import adminUsers from '@/routes/admin/users';
import site from '@/routes/site';
import ervaring from '@/routes/website/ervaring';
import website from '@/routes/website';
import type { NavItem } from '@/types';

/**
 * De zijbalk van het portaal.
 *
 * Drie groepen, en die indeling volgt waar iemand naar op zoek is:
 *
 * 1. **Zonder kopje** bovenaan: het dashboard. Een kopje boven één regel is
 *    meer ruis dan houvast.
 * 2. **Website**: alles waarmee de eigenaar zijn eigen site vult. Dit is
 *    waar hij het vaakst moet zijn, dus het staat boven het beheer. De
 *    indeling is er het startpunt van; zie de toelichting daar.
 * 3. **Beheer**: de logboeken, de mail en de gebruikers. Dat kijk je na, dat
 *    gebruik je niet dagelijks.
 *
 * Meer kopjes dan dit worden het niet. Een zijbalk met zeven secties is een
 * inhoudsopgave, en daar zoek je langer in dan in een lijst.
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
        title: t('Ervaring'),
        href: ervaring.index(),
        icon: Milestone,
    },
    {
        title: t('Bekijk de website'),
        href: site.enter(),
        icon: Globe,
        verlaat: true,
    },
]);

const beheerItems = computed<NavItem[]>(() =>
    magBeheren.value
        ? [
              {
                  title: t('Overzicht'),
                  href: adminDashboard(),
                  icon: Activity,
                  hoofd: true,
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
                  title: t('Mail'),
                  href: adminMail.index(),
                  icon: Mail,
              },
              {
                  title: t('Gebruikers'),
                  href: adminUsers.index(),
                  icon: Users,
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

        <SidebarContent>
            <NavMain :items="startItems" />

            <NavMain :label="$t('Website')" :items="websiteItems" />

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
