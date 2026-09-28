<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import TwoFactorNudge from '@/components/TwoFactorNudge.vue';
import WelcomeDialog from '@/components/WelcomeDialog.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

const page = usePage();

/**
 * De begroeting komt uit de gedeelde props en staat daarom in de layout en
 * niet op één pagina: na het inloggen land je op de pagina die je open had
 * staan, en dat is lang niet altijd het dashboard.
 */
const welcome = computed(() => page.props.welcome);

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <TwoFactorNudge />
            <slot />

            <WelcomeDialog v-if="welcome" :first-name="welcome.firstName" />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
