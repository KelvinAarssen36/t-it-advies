<script setup lang="ts">
import {
    Briefcase,
    Building2,
    Code,
    GraduationCap,
    Lightbulb,
    Rocket,
    Server,
    ShieldCheck,
    Users,
    Wrench,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';

/**
 * Het pictogram bij een ervaring.
 *
 * Dit bestand is de **enige** koppeling tussen de sleutels uit
 * App\Enums\ExperienceIcon en de iconenbibliotheek. Daarom staan er in de
 * database ook beschrijvende sleutels ("beveiliging") en geen namen van
 * pictogrammen ("ShieldCheck"): wisselen we ooit van bibliotheek, dan
 * verandert alleen deze kaart en hoeft er geen rij in de database mee.
 *
 * Wordt gebruikt op de publieke tijdlijn én in het beheerscherm, zodat de
 * klant bij het kiezen hetzelfde ziet als zijn bezoekers.
 */
const props = defineProps<{ icoon: string }>();

const kaart: Record<string, Component> = {
    werk: Briefcase,
    bedrijf: Building2,
    ontwikkeling: Code,
    infrastructuur: Server,
    beheer: Wrench,
    beveiliging: ShieldCheck,
    advies: Lightbulb,
    opleiding: GraduationCap,
    team: Users,
    'eigen-bedrijf': Rocket,
};

/*
 * Een onbekende sleutel valt terug op de koffer in plaats van niets te
 * tonen. Dat kan alleen als iemand een case uit de enum haalt terwijl er
 * nog rijen naar verwijzen -- en dan is een verkeerd pictogram beter dan
 * een gat in de tijdlijn.
 */
const pictogram = computed(() => kaart[props.icoon] ?? Briefcase);
</script>

<template>
    <component :is="pictogram" />
</template>
