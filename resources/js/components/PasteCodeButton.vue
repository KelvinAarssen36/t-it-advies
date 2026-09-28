<script setup lang="ts">
import { ClipboardPaste } from '@lucide/vue';
import { usePasteCode } from '@/composables/usePasteCode';

/**
 * Knopje onder een code-invoer: haalt de code uit het klembord en vult hem
 * in. De aanroeper bepaalt wat er daarna gebeurt -- in de praktijk wordt
 * het formulier meteen verstuurd, want een ingevulde code die je alsnog
 * moet bevestigen is een stap te veel.
 *
 * Het klembord *lezen* mag alleen in een secure context: https, of
 * localhost. Op een gewone http-omgeving -- zoals `.test` bij Valet --
 * bestaat `navigator.clipboard` simpelweg niet. Daar tonen we in plaats van
 * een knop die niets doet een tip, want plakken met Ctrl+V in het
 * invoerveld werkt wél: dat vult alle zes de vakjes en daarna verstuurt het
 * formulier zichzelf.
 */
const emit = defineEmits<{ pasted: [code: string] }>();

const { supported, failed, paste } = usePasteCode((code) =>
    emit('pasted', code),
);
</script>

<template>
    <div class="flex flex-col items-center gap-1">
        <button
            v-if="supported"
            type="button"
            class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs text-muted-foreground transition-colors hover:text-brand-cyan"
            @click="paste"
        >
            <ClipboardPaste class="size-3.5" />
            Code plakken
        </button>

        <p v-else class="text-xs text-muted-foreground">
            Tip: plakken met Ctrl+V vult alle zes de vakjes in één keer.
        </p>

        <p v-if="failed" class="text-xs text-muted-foreground">
            Geen zescijferige code op het klembord gevonden.
        </p>
    </div>
</template>
