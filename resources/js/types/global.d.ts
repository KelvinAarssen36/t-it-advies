import type { Directive } from 'vue';
import type { t } from '@/lib/i18n';
import type { Auth } from '@/types/auth';
import type { FlashProps, HoneypotProps } from '@/types/shared';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            locale: string;
            locales: Record<string, string>;
            /** De naam van de eigenaar, uit config/security.php. */
            eigenaar: string;
            /** Zijn LinkedIn-profiel, uit config/site.php. */
            linkedin: string;
            /** De woordenlijst van de actieve taal; leeg in het Nederlands. */
            translations: Record<string, string>;
            flash: FlashProps;
            honeypot: HoneypotProps;
            turnstileSiteKey: string | null;
            sidebarOpen: boolean;
            /** Alleen gevuld op het eerste bezoek na het inloggen. */
            welcome: { firstName: string } | null;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface GlobalDirectives {
        vFocus: Directive<HTMLElement, boolean | undefined>;
    }

    interface ComponentCustomProperties {
        /** Vertalen in een sjabloon. Zie resources/js/lib/i18n.ts. */
        $t: typeof t;
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
