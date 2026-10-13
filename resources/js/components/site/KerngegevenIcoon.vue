<script setup lang="ts">
import {
    Building2,
    CalendarCheck,
    Clock,
    Handshake,
    Languages,
    Laptop,
    MapPin,
    ScrollText,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';

/**
 * Het pictogram bij een kerngegeven.
 *
 * Dit bestand is de **enige** koppeling tussen de sleutels uit
 * App\Enums\CoreFactIcon en de iconenbibliotheek. Daarom staan er in de
 * database ook beschrijvende sleutels ("reactietijd") en geen namen van
 * pictogrammen ("Clock"): wisselen we ooit van bibliotheek, dan verandert
 * alleen deze kaart en hoeft er geen rij in de database mee.
 *
 * Wordt gebruikt op de publieke site én in het beheerscherm, zodat de
 * klant bij het kiezen hetzelfde ziet als zijn bezoekers.
 *
 * Zie ook DienstIcoon.vue en ErvaringIcoon.vue; hetzelfde patroon voor
 * een andere set.
 */
const props = defineProps<{ icoon: string }>();

const kaart: Record<string, Component> = {
    beschikbaarheid: CalendarCheck,
    werkgebied: MapPin,
    werkvorm: Laptop,
    reactietijd: Clock,
    talen: Languages,
    samenwerking: Handshake,
    bedrijf: Building2,
    voorwaarden: ScrollText,
};

/*
 * Een onbekende sleutel valt terug op de agenda in plaats van niets te
 * tonen. Dat kan alleen als iemand een case uit de enum haalt terwijl er
 * nog rijen naar verwijzen -- en dan is een verkeerd pictogram beter dan
 * een gat in de strook.
 */
const pictogram = computed(() => kaart[props.icoon] ?? CalendarCheck);
</script>

<template>
    <component :is="pictogram" />
</template>
