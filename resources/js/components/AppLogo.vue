<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const name = usePage().props.name;
</script>

<template>
    <!--
        Geen gekleurd vierkant achter het merkteken: het logo heeft zijn
        eigen vorm en achtergrond, en een blok eromheen maakt er een
        plaatje-in-een-plaatje van.
    -->
    <div class="flex size-8 shrink-0 items-center justify-center">
        <AppLogoIcon class="size-8 object-contain" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{
            name
        }}</span>
    </div>
</template>
