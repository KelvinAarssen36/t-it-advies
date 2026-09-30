<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { scrollNaar } from '@/lib/motion';

/**
 * De voet van de publieke site. Geen inloglink: zie SiteHeader.vue.
 *
 * **De links komen van de server**, uit dezelfde `navigation` als de kop.
 * Ze stonden hier hardgecodeerd, en dat was dezelfde fout als boven in de
 * balk: zette de klant een onderdeel uit, dan bleef hier een link staan
 * naar een anker dat niet bestond -- en klikken deed dan niets. Versleept
 * hij de onderdelen, dan schuift deze lijst nu mee.
 */
type NavItem = { key: string; label: string };

const page = usePage();

const items = computed<NavItem[]>(
    () => (page.props.navigation as NavItem[] | undefined) ?? [],
);

const year = new Date().getFullYear();
</script>

<template>
    <footer class="bg-brand-navy">
        <div class="brand-rule" />

        <div class="mx-auto max-w-6xl px-6 py-14">
            <div class="flex flex-col justify-between gap-10 sm:flex-row">
                <div class="max-w-sm">
                    <p class="text-lg font-semibold tracking-tight">
                        <span class="brand-text-gradient">@T</span>
                        <span class="text-white"> IT Advies</span>
                    </p>
                    <p class="mt-3 text-sm text-muted-foreground">
                        {{
                            $t(
                                'IT-advies en realisatie. Van advies tot bouw en beheer.',
                            )
                        }}
                    </p>
                </div>

                <!--
                    Niets tonen als er geen onderdelen zijn. Dat is het
                    geval op een foutpagina: de voet staat er wel, maar de
                    ankers hebben daar niets om heen te wijzen.
                -->
                <nav
                    v-if="items.length > 0"
                    class="flex flex-col gap-2 text-sm"
                >
                    <a
                        v-for="item in items"
                        :key="item.key"
                        :href="`#${item.key}`"
                        class="text-muted-foreground transition-colors hover:text-brand-cyan"
                        @click.prevent="scrollNaar(`#${item.key}`)"
                    >
                        {{ item.label }}
                    </a>
                </nav>
            </div>

            <p class="mt-12 text-sm text-muted-foreground">
                &copy; {{ year }} @T IT Advies
            </p>
        </div>
    </footer>
</template>
