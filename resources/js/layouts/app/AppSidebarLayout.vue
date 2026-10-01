<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import TwoFactorNudge from '@/components/TwoFactorNudge.vue';
import WelcomeDialog from '@/components/WelcomeDialog.vue';
import { Toaster } from '@/components/ui/sonner';
import { useAppearance } from '@/composables/useAppearance';
import type { BreadcrumbItem } from '@/types';

const page = usePage();

/**
 * Licht of huisstijl, voor de meldingen rechtsonder.
 *
 * Dezelfde bron als de rest van het portaal -- de module-brede `ref` uit
 * useAppearance -- dus hij wisselt mee op het moment dat de eigenaar het
 * omzet, zonder paginalading.
 */
const { appearance } = useAppearance();

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

        <!--
            Allebei één keer voor het hele portaal, want allebei horen ze bij
            geen enkel scherm in het bijzonder: de meldingen komen uit de
            gedeelde props, en het bevestigingsvenster wordt vanuit code
            geopend met bevestig() uit lib/bevestiging.ts.

            **Hier staat met opzet geen `expand`.** Meerdere meldingen
            stapelen dus als een pak kaarten, en klappen uit zodra je er met
            de muis op gaat staan -- dat is wat de eigenaar wil houden: één
            melding in beeld, en alles te zien zodra je erbij wil.

            Dat stapelen zag er eerst rommelig uit, maar de oorzaak lag niet
            bij vue-sonner; die zat in onze eigen opmaak. Zie `.brand-toast`
            in app.css en docs/architecture/meldingen.md.

            Drie tegelijk is het maximum (de standaard van vue-sonner), dus
            een reeks snelle handelingen kan het scherm niet vullen.

            **`theme` volgt de keuze van de gebruiker en staat niet vast.**
            De standaard van vue-sonner is `light`, en dat is hier altijd
            fout: het portaal staat standaard in de huisstijl. Het zet
            `data-theme` op het vak om de meldingen, en daar hangt de
            grijstrap van vue-sonner zelf aan -- de kleuren van onze kaart
            komen al uit de rol-tokens en gingen dus altijd mee, maar wat
            vue-sonner er zelf bij tekent niet.
        -->
        <Toaster :theme="appearance" />
        <ConfirmDialog />
    </AppShell>
</template>
