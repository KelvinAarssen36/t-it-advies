import { createInertiaApp } from '@inertiajs/vue3';
import { defineAsyncComponent } from 'vue';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

/**
 * Bewust asynchroon geladen. PublicLayout trekt GSAP en Lenis mee; laad je
 * hem gewoon boven aan dit bestand, dan zit de hele animatielaag in de
 * hoofdbundel en betaalt ook het beheergedeelte daarvoor -- ruim honderd
 * kilobyte die daar niets doet.
 */
const PublicLayout = defineAsyncComponent(
    () => import('@/layouts/PublicLayout.vue'),
);

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            // De publieke site. Nieuwe openbare pagina's komen in
            // pages/public/ en krijgen deze layout dus vanzelf.
            case name === 'Welcome':
            case name.startsWith('public/'):
                return PublicLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    withApp: (app) => {
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        // Electric Blue. Zie docs/architecture/huisstijl-en-kleuren.md.
        color: '#0787E8',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
