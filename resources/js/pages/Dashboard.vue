<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DigitaleKlok from '@/components/dashboard/DigitaleKlok.vue';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { dashboard } from '@/routes';

/**
 * Het dashboard van het portaal.
 *
 * De indeling van het standaard Laravel-dashboard blijft staan: drie
 * kleine vlakken bovenin en één groot eronder. In het middelste kleine
 * vlak staat de klok.
 *
 * **Op een telefoon staat de klok bovenaan en is het vlak een dunne
 * strook.** Dat is één klasse op dit vlak -- `order-first md:order-none`
 * -- en bewust niets op de andere twee: die hoeven niet aangepast te
 * worden om de klok vooraan te krijgen, en wat je niet aanraakt kan ook
 * niet stuk. De hoogte van die strook zit in `brand-klok` in app.css.
 *
 * Zie docs/architecture/dashboard.md.
 */
const props = defineProps<{ tijdzone: string }>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="$t('Dashboard')" />

    <div
        class="brand-schuif-x flex h-full flex-1 brand-scrollbar flex-col gap-4 rounded-xl p-4"
    >
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div
                class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <PlaceholderPattern />
            </div>

            <!--
                De klok. `order-first` haalt hem op een telefoon naar
                boven; vanaf `md` staat hij weer op zijn eigen plek in de
                rij, en dat is de middelste. Geen `aspect-video` op een
                telefoon: daar hoort dit een dunne strook te zijn en geen
                vierkant.
            -->
            <div
                class="relative order-first overflow-hidden rounded-xl border border-sidebar-border/70 md:order-none md:aspect-video dark:border-sidebar-border"
            >
                <DigitaleKlok :tijdzone="props.tijdzone" />
            </div>
            <div
                class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <PlaceholderPattern />
            </div>
        </div>
        <div
            class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border"
        >
            <PlaceholderPattern />
        </div>
    </div>
</template>
