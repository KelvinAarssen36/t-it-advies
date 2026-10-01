<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import PasswordLock from '@/components/PasswordLock.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { t } from '@/lib/i18n';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editDashboard } from '@/routes/dashboard-settings';
import { show as showDocumentatie } from '@/routes/documentation';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

/**
 * `slot: true` markeert het item waar een wachtwoordbevestiging achter zit.
 * Dat staat hier en niet als vergelijking op de URL in het sjabloon: komt er
 * ooit een tweede zo'n pagina, dan is dit de enige plek om aan te passen.
 */
type Instelling = NavItem & { slot?: boolean };

const sidebarNavItems = computed<Instelling[]>(() => [
    {
        title: t('Profiel'),
        href: editProfile(),
    },
    {
        title: t('Beveiliging'),
        href: editSecurity(),
        slot: true,
    },
    {
        title: t('Weergave'),
        href: editAppearance(),
    },
    /*
     * Het dashboard staat ná de weergave: die twee gaan allebei over hoe
     * het portaal eruitziet, en dit is de specifieke van de twee. Een eigen
     * regel en geen onderdeel van "Weergave", want daar komt meer bij -- de
     * eigenaar wil zijn dashboard verder kunnen inrichten.
     */
    {
        title: t('Dashboard'),
        href: editDashboard(),
    },
    /*
     * De handleiding staat onderaan en niet bovenaan. Wie hier komt heeft
     * een vraag, en die vraag gaat over het scherm waar hij vandaan komt
     * -- niet over zijn profiel. Bovenaan zou hij elke keer in de weg
     * staan van de instelling die je wél dagelijks nodig hebt.
     */
    {
        title: t('Documentatie'),
        href: showDocumentatie(),
    },
]);

const { isCurrentOrParentUrl } = useCurrentUrl();

/*
 * De instellingen zijn formulieren, en een formulier van 900 pixels breed
 * leest niet prettiger dan een van 600. Daarom staat de kolom standaard
 * smal. De handleiding is geen formulier maar een pagina met kaarten en
 * voorbeelden, en die mag breder; zie `breed` in
 * resources/js/pages/settings/Documentatie.vue.
 *
 * Inertia geeft de layout-props aan élke layout in de stapel, dus AppLayout
 * krijgt `breed` ook binnen. Die negeert hem: ongedeclareerde props komen
 * daar niet in de HTML terecht.
 */
defineProps<{ breed?: boolean }>();
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            :title="$t('Instellingen')"
            :description="
                $t('Beheer je profiel en de instellingen van je account')
            "
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav
                    class="flex flex-col space-y-1 space-x-0"
                    :aria-label="$t('Instellingen')"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'w-full justify-start',
                            { 'bg-muted': isCurrentOrParentUrl(item.href) },
                        ]"
                        as-child
                    >
                        <!--
                            `ml-auto` op het slotje en niet `justify-between`
                            op de link. Deze link krijgt via `as-child` ook de
                            klassen van de knop mee, en daar zit al
                            `justify-center` in. Die twee gaan niet door
                            tailwind-merge heen -- het zijn losse
                            class-attributen -- dus welke wint hangt af van de
                            volgorde in het gebouwde stylesheet. Met een
                            automatische marge is er niets om over te
                            ruziën: het slotje staat altijd rechts.
                        -->
                        <Link :href="item.href" class="w-full">
                            <span class="flex items-center gap-2">
                                <component :is="item.icon" class="h-4 w-4" />
                                {{ item.title }}
                            </span>

                            <PasswordLock v-if="item.slot" class="ml-auto" />
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1" :class="breed ? '' : 'md:max-w-2xl'">
                <section
                    class="space-y-12"
                    :class="breed ? 'max-w-3xl' : 'max-w-xl'"
                >
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
