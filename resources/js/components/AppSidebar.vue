<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    BookOpen,
    FolderGit2,
    LayoutGrid,
    Mail,
    ShieldAlert,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
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
import { dashboard } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import adminMail from '@/routes/admin/mail';
import adminSecurity from '@/routes/admin/security';
import type { NavItem } from '@/types';

const page = usePage();

/**
 * Menu-items van het beveiligde gedeelte verschijnen alleen bij het juiste
 * recht. Dit is puur cosmetisch: wie de URL raadt wordt alsnog door de
 * middleware tegengehouden. Zie routes/admin.php.
 */
const can = (permission: string) =>
    page.props.auth.permissions?.includes(permission) ?? false;

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    ...(can('view security log')
        ? [
              {
                  title: 'Beheer',
                  href: adminDashboard(),
                  icon: Activity,
              },
              {
                  title: 'Beveiliging',
                  href: adminSecurity.index(),
                  icon: ShieldAlert,
              },
          ]
        : []),
    ...(can('view mail log')
        ? [
              {
                  title: 'Mail',
                  href: adminMail.index(),
                  icon: Mail,
              },
          ]
        : []),
]);

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
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
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
