<script setup lang="ts">
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';

/**
 * Een keuze uit een handvol opties, als pil met een glijdende indicator.
 *
 * Alle opties staan er altijd, dus je ziet in één oogopslag waar je staat én
 * wat je kunt kiezen. Bij twee of drie opties is dat prettiger dan een
 * keuzelijst, die eerst open moet voordat hij iets zegt.
 *
 * Dit is de vorm, niet de inhoud: wat er gebeurt als je klikt bepaalt de
 * aanroeper. Gebruikt door de taalkeuze op de publieke site en door de
 * weergavekeuze in de instellingen.
 *
 * Kleuren komen uit de besturingsvariabelen, dus de pil klopt in het lichte
 * én het donkere thema. Zie
 * docs/architecture/formulieren-en-schuifbalken.md.
 */
type Optie = {
    value: string;
    label: string;
};

const props = defineProps<{
    options: Optie[];
    modelValue: string;
    /**
     * Wat een schermlezer over de hele groep voorleest.
     *
     * Bewust niet `ariaLabel`: die naam botst met het HTML-attribuut
     * `aria-label`, en dan komt de waarde als los attribuut op het element te
     * staan in plaats van als prop binnen.
     */
    groepLabel: string;
}>();

const emit = defineEmits<{ 'update:modelValue': [waarde: string] }>();

/**
 * De optie waar de indicator naartoe staat.
 *
 * Die loopt vooruit op de aanroeper: is een keuze een verzoek aan de server,
 * dan zou wachten op het antwoord de knop een tel lang kapot laten lijken.
 * De `watch` hieronder trekt hem recht zodra de echte waarde binnen is.
 */
const vooruit = ref<string | null>(null);

const actief = computed(() => vooruit.value ?? props.modelValue);

const baan = ref<HTMLElement | null>(null);
const vakken = ref<HTMLButtonElement[]>([]);

const zetVak = (el: unknown, index: number) => {
    // Vue roept dit bij het opruimen met null aan. Zou die null blijven
    // staan, dan meet de volgende meting een leeg vak en springt de
    // indicator naar nul.
    if (el instanceof HTMLButtonElement) {
        vakken.value[index] = el;
    }
};

/**
 * Waar de indicator staat en hoe breed hij is, in pixels.
 *
 * Gemeten en niet berekend: de vakken zijn zo breed als hun tekst, en
 * "Nederlands" is nu eenmaal langer dan "English". Een vaste breedte zou bij
 * een extra optie meteen misstaan.
 */
const positie = ref({ x: 0, breedte: 0 });

const meet = () => {
    const index = props.options.findIndex(
        (optie) => optie.value === actief.value,
    );
    const vak = vakken.value[index];

    if (!vak) {
        return;
    }

    positie.value = { x: vak.offsetLeft, breedte: vak.offsetWidth };
};

/**
 * De eerste meting mag niet animeren, anders schuift de indicator bij het
 * laden van elke pagina even vanaf links naar zijn plek.
 */
const klaar = ref(false);

/** Zet kort een veeg over de indicator, precies zolang hij onderweg is. */
const veegt = ref(false);
let veegTimer: ReturnType<typeof setTimeout> | undefined;

/**
 * Welke kant de veeg op loopt: 1 naar rechts, -1 naar links.
 *
 * De veeg hoort tegen de indicator in te lopen, niet ermee mee. Schuif je
 * naar rechts, dan trekt het licht naar links weg -- alsof je er met je
 * duim overheen veegt en de glans achterblijft. Meelopen leest als een
 * tweede ding dat dezelfde kant op gaat, en dan zie je twee bewegingen
 * in plaats van één.
 *
 * Hier liep hij eerst altijd dezelfde kant op, ongeacht de wissel, en
 * daarna een ronde lang de verkeerde kant op. Vandaar deze uitleg: de
 * richting is min de looprichting, en niet plus.
 */
const richting = ref(1);

let waarnemer: ResizeObserver | undefined;

onMounted(async () => {
    await nextTick();
    meet();

    // Twee frames, want de browser moet de beginpositie eerst een keer
    // hebben getekend voordat een overgang ergens vandaan kan komen.
    requestAnimationFrame(() =>
        requestAnimationFrame(() => (klaar.value = true)),
    );

    // De vakken worden breder of smaller als het lettertype binnenkomt of
    // als de taal wisselt. Zonder dit staat de indicator daarna scheef.
    if (typeof ResizeObserver !== 'undefined' && baan.value) {
        waarnemer = new ResizeObserver(() => meet());
        waarnemer.observe(baan.value);
    }
});

onBeforeUnmount(() => {
    waarnemer?.disconnect();
    clearTimeout(veegTimer);
});

watch(
    () => props.modelValue,
    async () => {
        vooruit.value = null;
        await nextTick();
        meet();
    },
);

watch(
    () => props.options,
    async () => {
        await nextTick();
        meet();
    },
    { deep: true },
);

const kies = (waarde: string) => {
    if (waarde === actief.value) {
        return;
    }

    // De richting wordt bepaald vóórdat de nieuwe waarde doorgaat, want
    // daarna is er geen "waar kwam hij vandaan" meer.
    const vanaf = props.options.findIndex((o) => o.value === actief.value);
    const naar = props.options.findIndex((o) => o.value === waarde);

    richting.value = naar >= vanaf ? -1 : 1;

    vooruit.value = waarde;
    veegt.value = true;

    clearTimeout(veegTimer);
    veegTimer = setTimeout(() => (veegt.value = false), 600);

    void nextTick(meet);

    emit('update:modelValue', waarde);
};

/** Voor de aanroeper, als een mislukt verzoek de indicator moet terugzetten. */
const herstel = () => {
    vooruit.value = null;
    void nextTick(meet);
};

defineExpose({ herstel });
</script>

<template>
    <div
        ref="baan"
        class="brand-segment"
        :class="{ 'is-ready': klaar, 'is-sweeping': veegt }"
        :style="{
            '--vak-x': `${positie.x}px`,
            '--vak-w': `${positie.breedte}px`,
            '--veeg-richting': richting,
        }"
        role="group"
        :aria-label="groepLabel"
    >
        <!--
            De indicator ligt achter de vakken en is aria-hidden: hij zegt
            niets wat de knoppen zelf niet al zeggen met aria-pressed.
        -->
        <span class="brand-segment-indicator" aria-hidden="true" />

        <button
            v-for="(optie, index) in options"
            :key="optie.value"
            :ref="(el) => zetVak(el, index)"
            type="button"
            class="brand-segment-vak"
            :class="{ 'is-active': optie.value === actief }"
            :aria-pressed="optie.value === actief"
            @click="kies(optie.value)"
        >
            <slot name="voor" :optie="optie" :actief="optie.value === actief" />

            <span>{{ optie.label }}</span>
        </button>
    </div>
</template>
