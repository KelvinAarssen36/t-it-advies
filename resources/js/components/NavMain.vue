<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

/**
 * Eén groep in de zijbalk.
 *
 * Het kopje is optioneel en bepaalt meteen het gedrag: een groep mét kopje
 * kun je inklappen, een groep zonder niet. Dat is geen toeval -- de groep
 * zonder kopje is het dashboard, en één regel wegklappen levert niets op.
 *
 * `note` is een zin onder de items, voor een groep die nog niet af is. De
 * aanroeper bepaalt wanneer hij weg mag -- niet dit component -- want alleen
 * daar weet je of wat er staat de lading al dekt.
 *
 * Zie docs/architecture/frontend-en-animatie.md.
 */
const props = defineProps<{
    label?: string;
    items: NavItem[];
    note?: string;
}>();

const { isCurrentUrl } = useCurrentUrl();
const { state } = useSidebar();

/**
 * Of de groep open staat.
 *
 * De keuze wordt onthouden per groep, in localStorage. Dat is precies waar
 * dat voor bedoeld is: een gemak voor deze ene bezoeker op dit ene apparaat,
 * en niets dat ergens anders hoeft te kloppen. Lukt het lezen of schrijven
 * niet -- een privévenster, geblokkeerde opslag -- dan staat de groep
 * gewoon open. Dichtklappen is het gemak; openstaan is de juiste uitkomst.
 */
const sleutel = computed(() => `sidebar-groep:${props.label ?? ''}`);

const lees = (): boolean => {
    try {
        return localStorage.getItem(sleutel.value) !== 'dicht';
    } catch {
        return true;
    }
};

const open = ref(lees());

watch(open, (waarde) => {
    try {
        localStorage.setItem(sleutel.value, waarde ? 'open' : 'dicht');
    } catch {
        // Niet kunnen onthouden mag het inklappen zelf niet in de weg staan.
    }
});

/**
 * Ingeklapt tot pictogrammen staat alles open.
 *
 * In die stand is er geen kopje om op te klikken, dus een dichtgeklapte
 * groep zou onbereikbaar zijn: je ziet hem niet en je kunt hem niet openen.
 */
const uitgeklapt = computed({
    get: () => state.value === 'collapsed' || open.value,
    set: (waarde: boolean) => {
        open.value = waarde;
    },
});
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <!-- Zonder kopje: geen knop, geen inklappen. -->
        <SidebarMenu v-if="!label">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>

        <Collapsible v-else v-model:open="uitgeklapt">
            <!--
                Het kopje is de knop. Geen apart pijltje om aan te klikken:
                een doelwit van twaalf pixels naast een tekst die er niets
                mee doet, is een raadsel dat je niet hoeft op te geven.

                In de pictogramstand verdwijnt het kopje helemaal. Alleen een
                pijltje zonder tekst zou een knop zijn waarvan je niet kunt
                zien wat hij doet -- en de groep staat daar toch al open.
            -->
            <CollapsibleTrigger
                class="brand-nav-kop group/kop group-data-[collapsible=icon]:hidden"
                :disabled="state === 'collapsed'"
            >
                <span>{{ label }}</span>
                <ChevronDown
                    class="size-3.5 shrink-0 transition-transform duration-300 group-data-[state=closed]/kop:-rotate-90"
                    aria-hidden="true"
                />
            </CollapsibleTrigger>

            <CollapsibleContent class="brand-nav-inhoud">
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in items" :key="item.title">
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrl(item.href)"
                            :tooltip="item.title"
                        >
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>

                <!--
                    Verdwijnt zodra de zijbalk is ingeklapt tot pictogrammen:
                    daar is geen ruimte voor een zin, en afgekapte tekst
                    leest als een fout.
                -->
                <p
                    v-if="note"
                    class="px-2 py-1.5 text-xs text-balance text-muted-foreground group-data-[collapsible=icon]:hidden"
                >
                    {{ note }}
                </p>
            </CollapsibleContent>
        </Collapsible>
    </SidebarGroup>
</template>
