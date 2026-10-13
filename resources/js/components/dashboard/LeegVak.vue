<script setup lang="ts">
import { Sparkles } from '@lucide/vue';

/**
 * Een vlak op het dashboard waar nog niets in zit.
 *
 * **Dit verving het diagonale streepjespatroon van de Laravel-starter.**
 * Dat patroon is een bouwsteiger: het zegt "hier is de ontwikkelaar nog
 * bezig", en dat is precies wat de eigenaar niet hoort te zien op het
 * eerste scherm dat hij elke dag opent.
 *
 * Wat er nu staat is rustig en met opzet leeg: een gestreepte rand, een
 * gedempt tekentje en één regel. Daarmee leest het als ruimte die bewaard
 * is in plaats van als iets dat stuk is of nog laadt.
 *
 * **Er staat met opzet geen verzonnen inhoud in.** Een vlak vullen met een
 * getal dat niemand heeft gevraagd maakt een dashboard niet nuttiger --
 * het maakt alleen moeilijker te zien wat er wél toe doet.
 *
 * Zie docs/architecture/dashboard.md.
 */
defineProps<{
    /** Eén regel over wat hier later komt. Optioneel. */
    tekst?: string;
}>();
</script>

<template>
    <div class="brand-leegvak">
        <Sparkles class="brand-leegvak-teken size-5" aria-hidden="true" />
        <p class="brand-leegvak-tekst">
            {{ tekst ?? $t('Hier komt later meer') }}
        </p>
    </div>
</template>
