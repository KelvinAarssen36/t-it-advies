<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import SegmentToggle from '@/components/SegmentToggle.vue';
import { useAppearance } from '@/composables/useAppearance';
import { t } from '@/lib/i18n';

/**
 * De weergavekeuze in de instellingen.
 *
 * Twee opties, niet drie. "Systeem" is eruit: dat klinkt behulpzaam maar
 * betekent dat het portaal er anders uitziet naargelang een instelling die
 * ergens anders staat, en dat je niet kunt zien welke van de twee je nu
 * eigenlijk hebt gekozen. Bij een portaal met één gebruiker levert die
 * onzekerheid niets op.
 *
 * De donkere stand heet hier "Huisstijl" en niet "Donker". Dat is geen
 * woordenspel: dit ís de huisstijl en daarmee de basis. De landing staat er
 * altijd in, het portaal sluit daarop aan, en wie niets kiest krijgt hem.
 * "Licht" is de uitzondering, voor wie er de hele dag in werkt en dat
 * prettiger vindt.
 *
 * De opgeslagen waarde blijft `dark`: die stuurt de klasse op <html> aan en
 * daar hangt het hele kleurstelsel aan. Alleen het etiket is anders.
 *
 * Zie docs/architecture/huisstijl-en-kleuren.md.
 */
const { appearance, updateAppearance } = useAppearance();

const opties = computed(() => [
    { value: 'dark', label: t('Huisstijl') },
    { value: 'light', label: t('Licht') },
]);
</script>

<template>
    <SegmentToggle
        :model-value="appearance"
        :options="opties"
        :groep-label="t('Weergave')"
        @update:model-value="
            (waarde) => updateAppearance(waarde === 'light' ? 'light' : 'dark')
        "
    >
        <template #voor="{ optie }">
            <Moon v-if="optie.value === 'dark'" class="size-4" />
            <Sun v-else class="size-4" />
        </template>
    </SegmentToggle>
</template>
