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
 * Je keuze blijft staan, ook als de zijbalk smal wordt.
 *
 * Dat was eerst niet zo: in de pictogramstand klapte alles open, omdat
 * er dan geen kopje was om op te klikken en een dichte groep dus
 * onbereikbaar zou zijn. Het gevolg was dat een groep die je net had
 * dichtgeklapt weer opensprong zodra je de balk versmalde -- en dat
 * leest als een instelling die niet blijft hangen.
 *
 * De oplossing zit niet hier maar in het kopje zelf: dat verdwijnt in
 * de smalle stand niet, maar krimpt tot een knopje van één pictogram.
 * Daarmee is de groep daar nog steeds open en dicht te klappen, en hoeft
 * niemand iets voor je te beslissen. Zie `[data-collapsible='icon']` in
 * app.css.
 */
const uitgeklapt = open;
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
                        <VerlaatPortaal v-if="item.verlaat" class="ml-auto" />
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>

        <Collapsible v-else v-model:open="uitgeklapt">
            <!--
                Het kopje is de knop. Geen apart pijltje om aan te klikken:
                een doelwit van twaalf pixels naast een tekst die er niets
                mee doet, is een raadsel dat je niet hoeft op te geven.

                In de pictogramstand krimpt hij tot alleen dat pijltje,
                gecentreerd in de smalle balk. Zo blijft de groep daar ook
                open en dicht te klappen -- en blijft je keuze dus staan
                in plaats van dat de balk hem voor je omgooit. Het
                `aria-label` draagt daar de naam van de groep, want de
                tekst is dan verborgen.
            -->
            <CollapsibleTrigger
                class="brand-nav-kop group/kop"
                :aria-label="label"
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
                                    class="ml-auto"
                                />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </CollapsibleContent>
        </Collapsible>
    </SidebarGroup>
</template>
