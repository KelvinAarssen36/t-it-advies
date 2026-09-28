<script setup lang="ts">
import { Check, ChevronDown, ChevronUp } from '@lucide/vue';
import {
    SelectContent,
    SelectItem,
    SelectItemIndicator,
    SelectItemText,
    SelectPortal,
    SelectRoot,
    SelectScrollDownButton,
    SelectScrollUpButton,
    SelectTrigger,
    SelectValue,
    SelectViewport,
} from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Het keuzeveld van het portaal.
 *
 * Gebouwd op de losse onderdelen van reka-ui en niet op het inheemse
 * `<select>`. Dat veld is op zichzelf prima te stylen, maar de lijst die
 * eruit klapt tekent het besturingssysteem: vierkante hoeken, een felblauwe
 * balk en een grijze systeemschuifbalk, midden in een donker portaal. Daar
 * is met CSS niets aan te doen -- alleen door de lijst zelf te tekenen.
 *
 * Wat we daarmee inleveren is het wieltje dat een telefoon onderaan het
 * scherm toont. Reka-ui vangt dat op met een lijst die wél met een vinger
 * te bedienen is en die de toetsenbordbediening netjes nabouwt.
 *
 * Zie docs/architecture/formulieren-en-schuifbalken.md.
 */
type Optie = {
    value: string;
    label: string;
};

const props = withDefaults(
    defineProps<{
        options: Optie[];
        placeholder?: string;
        disabled?: boolean;
        class?: HTMLAttributes['class'];
        /** Wat een schermlezer voorleest als er geen zichtbaar label is. */
        ariaLabel?: string;
    }>(),
    { placeholder: 'Maak een keuze' },
);

const model = defineModel<string>({ default: '' });

/**
 * Reka-ui weigert een optie met een lege waarde: die is bij hem
 * gereserveerd om de keuze te wissen. Onze filters gebruiken juist een lege
 * waarde voor "alles", omdat dat de parameter uit de URL houdt.
 *
 * Daarom vertaalt dit component tussen de twee. Buiten dit bestand is de
 * lege waarde gewoon een lege string; binnen het paneel heet hij anders.
 */
const LEEG = '__alles__';

const intern = computed({
    get: () => (model.value === '' ? LEEG : model.value),
    set: (waarde: string) => {
        model.value = waarde === LEEG ? '' : waarde;
    },
});

const opties = computed(() =>
    props.options.map((optie) => ({
        ...optie,
        waarde: optie.value === '' ? LEEG : optie.value,
    })),
);
</script>

<template>
    <SelectRoot v-model="intern" :disabled="disabled">
        <SelectTrigger
            :aria-label="ariaLabel"
            :class="
                cn(
                    'group inline-flex brand-control items-center justify-between gap-2',
                    props.class,
                )
            "
        >
            <SelectValue :placeholder="placeholder" />

            <!--
                Het pijltje draait mee met het paneel. Klein gebaar, maar het
                is het enige dat vertelt of de lijst open of dicht is zodra
                je ernaast kijkt.
            -->
            <ChevronDown
                aria-hidden="true"
                class="size-4 shrink-0 text-[var(--control-muted)] transition-transform duration-200 group-data-[state=open]:rotate-180"
            />
        </SelectTrigger>

        <SelectPortal>
            <!--
                `flex flex-col` en `overflow-hidden` zijn niet cosmetisch. Het
                viewport hieronder schuift zelf en staat op `flex: 1`; zonder
                een flexkolom met een begrensde hoogte krijgt het die hoogte
                nooit en schuift het paneel in plaats daarvan als geheel.
                Dan zoekt reka-ui de gemarkeerde regel op in een element dat
                niet schuift, en dat is precies wat hakkelt.
            -->
            <SelectContent
                position="popper"
                :side-offset="6"
                class="z-50 flex max-h-[min(20rem,var(--reka-select-content-available-height))] min-w-[var(--reka-select-trigger-width)] flex-col overflow-hidden brand-panel data-[side=bottom]:slide-in-from-top-1 data-[side=top]:slide-in-from-bottom-1 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95"
            >
                <SelectScrollUpButton
                    class="flex cursor-default items-center justify-center py-1 text-[var(--control-muted)]"
                >
                    <ChevronUp class="size-4" />
                </SelectScrollUpButton>

                <!--
                    Dit is het element dat daadwerkelijk schuift. De
                    schuifbalk hoort hier en niet op het paneel eromheen;
                    reka-ui verbergt die van zichzelf, wat we in app.css
                    weer terugdraaien.
                -->
                <SelectViewport class="brand-scrollbar">
                    <SelectItem
                        v-for="optie in opties"
                        :key="optie.waarde"
                        :value="optie.waarde"
                        class="brand-option"
                    >
                        <SelectItemText>{{ optie.label }}</SelectItemText>

                        <span
                            class="absolute right-2.5 flex size-4 items-center justify-center"
                        >
                            <SelectItemIndicator>
                                <Check
                                    class="size-4 text-[var(--control-ring)]"
                                />
                            </SelectItemIndicator>
                        </span>
                    </SelectItem>
                </SelectViewport>

                <SelectScrollDownButton
                    class="flex cursor-default items-center justify-center py-1 text-[var(--control-muted)]"
                >
                    <ChevronDown class="size-4" />
                </SelectScrollDownButton>
            </SelectContent>
        </SelectPortal>
    </SelectRoot>
</template>
