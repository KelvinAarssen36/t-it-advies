<script setup lang="ts" generic="T extends { key: string }">
import { animations } from '@formkit/drag-and-drop';
import { useDragAndDrop } from '@formkit/drag-and-drop/vue';
import { ChevronDown, ChevronUp, GripVertical } from '@lucide/vue';
import { ref, watch } from 'vue';

/**
 * Een lijst die je kunt herschikken: met slepen én met pijltjes.
 *
 * **Waarom allebei.** Slepen is wat iedereen verwacht, maar het werkt niet
 * met een toetsenbord, het is lastig met een trillende hand en op een
 * telefoon vecht het met het scrollen van de pagina. De pijltjes zijn geen
 * tweede keus maar de betrouwbare weg; het slepen is de snelle. Wie geen
 * van beide kan gebruiken, kan de volgorde helemaal niet aanpassen -- en
 * dat is geen optie voor het enige scherm waar dat kan.
 *
 * **Wat dit component wel en niet doet.** Het beheert de volgorde, meer
 * niet. Hoe een regel eruitziet bepaalt de aanroeper in de slot, en wat er
 * verder in een item staat ook. Daardoor is hij straks net zo goed te
 * gebruiken voor de items *binnen* een onderdeel -- de punten van een
 * tijdlijn bijvoorbeeld -- als voor de onderdelen zelf.
 *
 * De objecten in de lijst worden **niet gekopieerd**, alleen de array
 * eromheen. Verandert de aanroeper iets aan een item in de slot, dan is dat
 * hetzelfde object en klopt het meteen aan beide kanten.
 *
 * Zie docs/architecture/pagina-indeling.md.
 */

const props = withDefaults(
    defineProps<{
        modelValue: T[];
        /** Buiten de bewerkmodus staat de lijst stil en zijn de knoppen weg. */
        disabled?: boolean;
        /** Wat een schermlezer over deze lijst hoort te zeggen. */
        label?: string;
    }>(),
    { disabled: false, label: undefined },
);

const emit = defineEmits<{ 'update:modelValue': [T[]] }>();

/** De volgorde als één string, om twee lijsten goedkoop te vergelijken. */
const volgordeVan = (lijst: readonly T[]): string =>
    lijst.map((item) => item.key).join('|');

/**
 * Tijdens het slepen doet de bibliotheek de beweging zelf. De
 * verschuifanimatie van Vue moet er dan uit, anders animeren er twee dingen
 * tegelijk op hetzelfde element en schokt het.
 */
const sleept = ref(false);

/**
 * De instellingen staan apart, en dat is geen netheid maar noodzaak.
 *
 * `updateConfig` van de bibliotheek **vervangt** de hele configuratie in
 * plaats van hem aan te vullen. Zou je alleen `{ disabled }` meegeven, dan
 * verdwijnen de greep, de animaties en de klassen -- en dan is de lijst
 * daarna wel weer sleepbaar, maar overal en zonder overgang.
 */
const instellingen = {
    // De hele regel oppakken zou betekenen dat je niet meer op een link in
    // die regel kunt klikken zonder dat hij begint te slepen.
    dragHandle: '[data-sorteer-greep]',
    plugins: [animations({ duration: 180 })],
    draggingClass: 'brand-sorteer-sleept',
    dropZoneClass: 'brand-sorteer-doel',
    synthDraggingClass: 'brand-sorteer-sleept',
    synthDropZoneClass: 'brand-sorteer-doel',
    onDragstart: () => {
        sleept.value = true;
    },
    onDragend: () => {
        sleept.value = false;
    },
};

const [lijstElement, items, stelConfigIn] = useDragAndDrop<T>(
    [...props.modelValue],
    { ...instellingen, disabled: props.disabled },
);

watch(items, (nieuw) => {
    if (volgordeVan(nieuw) !== volgordeVan(props.modelValue)) {
        emit('update:modelValue', [...nieuw]);
    }
});

// De andere kant op: annuleert de aanroeper de bewerking, dan zet hij de
// oude volgorde terug en moet deze lijst mee. De vergelijking voorkomt dat
// de twee watchers elkaar aan de gang houden.
watch(
    () => props.modelValue,
    (nieuw) => {
        if (volgordeVan(nieuw) !== volgordeVan(items.value)) {
            items.value = [...nieuw];
        }
    },
);

watch(
    () => props.disabled,
    (uit) => stelConfigIn({ ...instellingen, disabled: uit }),
);

/** Eén plek op of neer, met de pijltjes. */
const verplaats = (vanaf: number, naar: number): void => {
    if (naar < 0 || naar >= items.value.length) {
        return;
    }

    const kopie = [...items.value];
    const [item] = kopie.splice(vanaf, 1);
    kopie.splice(naar, 0, item);

    items.value = kopie;
};
</script>

<template>
    <ul
        ref="lijstElement"
        class="flex flex-col gap-2"
        :aria-label="props.label"
    >
        <!--
            Zonder `tag` rendert TransitionGroup geen eigen element, dus
            blijven de regels rechtstreekse kinderen van de <ul>. Dat moet
            ook: de sleepbibliotheek verwacht ze daar, en een <div> ertussen
            zou de lijst voor een schermlezer bovendien geen lijst meer
            maken.
        -->
        <TransitionGroup :name="sleept ? undefined : 'brand-sorteer'">
            <li
                v-for="(item, index) in items"
                :key="item.key"
                class="brand-sorteer-rij"
            >
                <button
                    v-if="!props.disabled"
                    type="button"
                    data-sorteer-greep
                    class="brand-sorteer-greep"
                    :aria-label="$t('Versleep om te verplaatsen')"
                >
                    <GripVertical class="size-4" />
                </button>

                <div class="min-w-0 flex-1">
                    <slot :item="item" :index="index" />
                </div>

                <!--
                    De pijltjes staan bewust naast de greep en niet erachter
                    weggestopt: ze zijn de enige weg voor wie niet sleept, en
                    iets wat je alleen vindt als je ernaar zoekt, bestaat
                    voor de helft niet.
                -->
                <div v-if="!props.disabled" class="flex flex-col gap-0.5">
                    <button
                        type="button"
                        class="brand-sorteer-pijl"
                        :disabled="index === 0"
                        :aria-label="$t('Eén plek omhoog')"
                        @click="verplaats(index, index - 1)"
                    >
                        <ChevronUp class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="brand-sorteer-pijl"
                        :disabled="index === items.length - 1"
                        :aria-label="$t('Eén plek omlaag')"
                        @click="verplaats(index, index + 1)"
                    >
                        <ChevronDown class="size-4" />
                    </button>
                </div>
            </li>
        </TransitionGroup>
    </ul>
</template>
