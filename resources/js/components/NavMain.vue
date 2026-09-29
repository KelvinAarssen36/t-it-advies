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
import VerlaatPortaal from '@/components/VerlaatPortaal.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

/**
 * Eén groep in de zijbalk.
 *
 * Het kopje is optioneel en bepaalt meteen het gedrag: een groep mét kopje
 * kun je inklappen, een groep zonder niet. Dat is geen toeval -- de groep
 * zonder kopje is het dashboard, en één regel wegklappen levert niets op.
 *
 * Zie docs/architecture/frontend-en-animatie.md.
 */
const props = defineProps<{
    label?: string;
    items: NavItem[];
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
                        <span
                            v-if="item.hoofd"
                            class="brand-nav-hoofd"
                            :title="$t('De hoofdpagina van dit onderdeel')"
                        >
                            *
                            <span class="sr-only">
                                {{ $t('De hoofdpagina van dit onderdeel') }}
                            </span>
                        </span>
                        <VerlaatPortaal
                            v-if="item.verlaat"
                            class="ml-auto group-data-[collapsible=icon]:hidden"
                        />
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
                            <!--
                                Het sterretje markeert de hoofdpagina van
                                de groep. Het is bewust níet het rode
                                sterretje van een verplicht veld: dat
                                betekent iets anders, en twee betekenissen
                                aan één teken hangen is vragen om
                                verwarring. Dit is klein en in de
                                merkkleur.

                                Het pijltje erachter betekent weer iets
                                anders: die regel brengt je buiten het
                                portaal. Zie VerlaatPortaal.vue.
                            -->
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                                <span
                                    v-if="item.hoofd"
                                    class="brand-nav-hoofd"
                                    :title="
                                        $t('De hoofdpagina van dit onderdeel')
                                    "
                                >
                                    *
                                    <span class="sr-only">
                                        {{
                                            $t(
                                                'De hoofdpagina van dit onderdeel',
                                            )
                                        }}
                                    </span>
                                </span>
                                <VerlaatPortaal
                                    v-if="item.verlaat"
                                    class="ml-auto group-data-[collapsible=icon]:hidden"
                                />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </CollapsibleContent>
        </Collapsible>
    </SidebarGroup>
</template>
