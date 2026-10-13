<script setup lang="ts">
import { Calendar, X } from '@lucide/vue';
import {
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
} from 'reka-ui';
import { computed, ref, watch } from 'vue';
import BrandSelect from '@/components/BrandSelect.vue';
import type { Optie } from '@/types/ervaring';

/**
 * Eén veld waarin je een maand en een jaar kiest.
 *
 * **Waarom geen datumveld met dagen.** Bij een loopbaan weet niemand meer
 * op welke dág hij ergens begon, en op de website staat alleen "maart
 * 2021". Zou je hier een dag kunnen kiezen, dan vraag je om precisie die
 * niet bestaat en die je vervolgens weggooit. Dit kiest dus maanden, maar
 * wel op de manier van een datumveld: één knop, één paneel, klaar.
 *
 * **Waarom dit een eigen component is en geen twee keuzelijsten.** Dat
 * waren er vier op één scherm -- maand, jaar, maand, jaar -- en dan moet je
 * bij elke datum twee keer een lijst opentrekken. Nu is het één knop met
 * "maart 2021" erop, en zie je in één oogopslag wat er staat.
 *
 * De maandnamen en de jaren komen van de server, niet uit `Intl` in de
 * browser: anders hangt de taal van de lijst af van het besturingssysteem
 * van de bezoeker in plaats van van de taal die hij in het portaal heeft
 * gekozen. Zie docs/architecture/vertalingen.md.
 */
const props = withDefaults(
    defineProps<{
        /** De twaalf maanden als "01".."12", met hun naam. */
        maanden: Optie[];
        /** De jaren waaruit gekozen kan worden, aflopend. */
        jaren: Optie[];
        placeholder?: string;
        ariaLabel?: string;
        /**
         * Het id van de knop die de kalender opent.
         *
         * Net als bij `BrandSelect`: dit component tekent zelf geen
         * element, dus een `id` van buitenaf valt nergens op neer en een
         * `<label for>` ernaast wijst naar niets. De browser meldt dat als
         * *Incorrect use of `<label for=FORM_ELEMENT>`*.
         */
        id?: string;
        disabled?: boolean;
        /** Mag het veld leeggemaakt worden? Standaard niet. */
        wisbaar?: boolean;
    }>(),
    { wisbaar: false },
);

/** De waarde als "2021-03", of een lege string. */
const model = defineModel<string>({ default: '' });

const open = ref(false);

const gekozenJaar = computed(() => model.value.split('-')[0] ?? '');
const gekozenMaand = computed(() => model.value.split('-')[1] ?? '');

/**
 * Het jaar dat het paneel laat zien.
 *
 * Dat is niet hetzelfde als het gekozen jaar: je moet door de jaren kunnen
 * bladeren zonder meteen iets te kiezen. Staat er nog niets, dan begint hij
 * bij het bovenste jaar uit de lijst -- dat is het huidige, en dat is
 * vrijwel altijd het jaar waar je moet zijn.
 */
const bladJaar = ref('');

const beginJaar = (): string =>
    gekozenJaar.value || (props.jaren[0]?.value ?? '');

watch(open, (isOpen) => {
    if (isOpen) {
        bladJaar.value = beginJaar();
    }
});

/** Wat er op de knop staat: "maart 2021", of de tijdelijke tekst. */
const opschrift = computed(() => {
    if (model.value === '') {
        return null;
    }

    const maand = props.maanden.find(
        (optie) => optie.value === gekozenMaand.value,
    );

    return `${maand?.label ?? gekozenMaand.value} ${gekozenJaar.value}`;
});

const kies = (maand: string): void => {
    model.value = `${bladJaar.value}-${maand}`;
    open.value = false;
};

const wis = (): void => {
    model.value = '';
    open.value = false;
};

/** Staat deze maand van dit bladerjaar al gekozen? */
const isGekozen = (maand: string): boolean =>
    model.value === `${bladJaar.value}-${maand}`;
</script>

<template>
    <PopoverRoot v-model:open="open">
        <PopoverTrigger
            :id="props.id"
            :disabled="disabled"
            :aria-label="ariaLabel"
            class="group inline-flex brand-control w-full items-center justify-between gap-2"
        >
            <span
                :class="opschrift === null ? 'text-[var(--control-muted)]' : ''"
            >
                {{ opschrift ?? placeholder ?? $t('Kies een maand') }}
            </span>

            <Calendar
                aria-hidden="true"
                class="size-4 shrink-0 text-[var(--control-muted)] transition-colors group-data-[state=open]:text-[var(--control-ring)]"
            />
        </PopoverTrigger>

        <PopoverPortal>
            <PopoverContent
                :side-offset="6"
                align="start"
                class="z-50 w-[17rem] brand-panel p-3 data-[side=bottom]:slide-in-from-top-1 data-[side=top]:slide-in-from-bottom-1 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95"
            >
                <!--
                    Het jaar is een keuzelijst en geen pijltjes links en
                    rechts. Een loopbaan begint zomaar vijfendertig jaar
                    terug, en dan klik je jezelf suf. De lijst is lang genoeg
                    om zelf een zoekveldje aan te zetten.
                -->
                <BrandSelect
                    v-model="bladJaar"
                    :options="jaren"
                    :aria-label="$t('Jaar')"
                    :zoek-tekst="$t('Jaar')"
                    class="w-full"
                />

                <div class="mt-3 grid grid-cols-3 gap-1">
                    <button
                        v-for="maand in maanden"
                        :key="maand.value"
                        type="button"
                        class="brand-maand"
                        :data-gekozen="isGekozen(maand.value) ? '' : undefined"
                        @click="kies(maand.value)"
                    >
                        {{ maand.label }}
                    </button>
                </div>

                <button
                    v-if="wisbaar && model !== ''"
                    type="button"
                    class="mt-3 inline-flex w-full items-center justify-center gap-1.5 rounded-md py-1.5 text-xs text-[var(--control-muted)] transition-colors hover:text-[var(--control-text)]"
                    @click="wis"
                >
                    <X class="size-3.5" />
                    {{ $t('Leegmaken') }}
                </button>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>
