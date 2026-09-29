import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    /**
     * De hoofdpagina van zijn groep: de indeling onder "Website", het
     * overzicht onder "Beheer". Krijgt een sterretje in de zijbalk.
     */
    hoofd?: boolean;
    /**
     * Deze regel brengt je buiten het portaal, naar de publieke site.
     * Krijgt het pijltje van VerlaatPortaal.vue achter de tekst. Let op:
     * dat is alleen een markering; er gaat geen tabblad open.
     */
    verlaat?: boolean;
};
