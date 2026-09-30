<script setup lang="ts">
import {
    Cloud,
    Database,
    Hammer,
    Laptop,
    Lightbulb,
    Network,
    ShieldCheck,
    Wrench,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';

/**
 * Het pictogram op een dienstkaart.
 *
 * Dit bestand is de **enige** koppeling tussen de sleutels uit
 * App\Enums\ServiceIcon en de iconenbibliotheek. Daarom staan er in de
 * database ook beschrijvende sleutels ("beveiliging") en geen namen van
 * pictogrammen ("ShieldCheck"): wisselen we ooit van bibliotheek, dan
 * verandert alleen deze kaart en hoeft er geen rij in de database mee.
 *
 * Wordt gebruikt op de publieke site én in het beheerscherm, zodat de
 * klant bij het kiezen hetzelfde ziet als zijn bezoekers.
 *
 * Zie ook ErvaringIcoon.vue; hetzelfde patroon voor een andere set.
 */
const props = defineProps<{ icoon: string }>();

const kaart: Record<string, Component> = {
    advies: Lightbulb,
    realisatie: Hammer,
    beheer: Wrench,
    netwerk: Network,
    beveiliging: ShieldCheck,
    cloud: Cloud,
    werkplek: Laptop,
    data: Database,
};

/*
 * Een onbekende sleutel valt terug op het gloeilampje in plaats van
 * niets te tonen. Dat kan alleen als iemand een case uit de enum haalt
 * terwijl er nog rijen naar verwijzen -- en dan is een verkeerd
 * pictogram beter dan een gat in de kaart.
 */
const pictogram = computed(() => kaart[props.icoon] ?? Lightbulb);
</script>

<template>
    <component :is="pictogram" />
</template>
