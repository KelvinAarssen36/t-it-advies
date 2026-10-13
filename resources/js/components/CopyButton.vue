<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';
import { onBeforeUnmount, ref } from 'vue';
import { cn } from '@/lib/utils';

/**
 * De kopieerknop van dit project. Gebruik hem overal waar iets te kopiëren
 * valt -- op de publieke site en in het portaal -- zodat kopiëren zich
 * altijd hetzelfde gedraagt.
 *
 * Twee varianten van hetzelfde recept: alleen een icoon, of een icoon met
 * tekst ernaast (`show-label`).
 *
 * Zie docs/architecture/frontend-en-animatie.md.
 */
const props = withDefaults(
    defineProps<{
        /** Wat er gekopieerd wordt. */
        value: string;
        /** Wat de knop doet. Wordt ook het aria-label. */
        label?: string;
        /** Tekst na het kopiëren. */
        copiedLabel?: string;
        /** Tekst naast het icoon tonen, of alleen het icoon. */
        showLabel?: boolean;
        /** Hoelang het vinkje blijft staan, in milliseconden. */
        resetAfter?: number;
        class?: string;
    }>(),
    {
        label: 'Kopiëren',
        copiedLabel: 'Gekopieerd',
        showLabel: false,
        resetAfter: 1200,
        class: undefined,
    },
);

const copied = ref(false);

let timer: ReturnType<typeof setTimeout> | undefined;

const markCopied = (): void => {
    copied.value = true;

    clearTimeout(timer);
    timer = setTimeout(() => {
        copied.value = false;
    }, props.resetAfter);
};

/**
 * De klassieke terugval: een tekstvak buiten beeld, selecteren, kopiëren,
 * opruimen. `readonly` erbij zodat een telefoon geen toetsenbord opent.
 */
const legacyCopy = (): void => {
    const area = document.createElement('textarea');

    area.value = props.value;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.left = '-9999px';

    document.body.appendChild(area);
    area.select();

    try {
        document.execCommand('copy');
    } finally {
        document.body.removeChild(area);
    }

    markCopied();
};

const copy = async (): Promise<void> => {
    // De moderne weg werkt alleen op https of localhost. Op een gewoon
    // http-adres bestaat `navigator.clipboard` soms wél maar weigert hij,
    // dus alleen op het bestaan toetsen is niet genoeg.
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(props.value);

            // Pas ná een geslaagde belofte het vinkje tonen. Anders bevestig
            // je iets wat misschien niet is gebeurd.
            markCopied();

            return;
        } catch {
            // Geweigerd; dan alsnog de oude weg.
        }
    }

    legacyCopy();
};

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <button
        type="button"
        :aria-label="label"
        :class="
            cn(
                'relative inline-flex shrink-0 items-center justify-center gap-2 rounded-md border border-border bg-transparent px-2.5 py-1.5 text-sm text-muted-foreground transition-colors hover:border-brand-line hover:text-foreground',
                !showLabel && 'size-9 px-0 py-0',
                props.class,
            )
        "
        @click="copy"
    >
        <!--
            Beide iconen staan er permanent, over elkaar heen. Er wordt niets
            omgewisseld in de DOM: alleen schaal en dekking wisselen. Daardoor
            blijft de knop exact even breed en springt een rij knoppen niet
            bij elke klik.
        -->
        <span class="relative inline-flex size-4 items-center justify-center">
            <!--
                Het kopieericoon krimpt helemaal weg, het vinkje veert maar
                een klein stukje op. Dat leest als vervangen in plaats van als
                kruisvervaging. 150ms eruit, 200ms erin, zodat ze net even
                overlappen.
            -->
            <Copy
                aria-hidden="true"
                class="size-4 transition duration-150"
                :class="copied ? 'scale-0 opacity-0' : 'scale-100 opacity-100'"
            />
            <Check
                aria-hidden="true"
                class="absolute size-4 text-success transition duration-200"
                :class="copied ? 'scale-100 opacity-100' : 'scale-75 opacity-0'"
            />
        </span>

        <span v-if="showLabel" class="relative whitespace-nowrap">
            <span
                class="transition-opacity duration-150"
                :class="copied ? 'opacity-0' : 'opacity-100'"
            >
                {{ label }}
            </span>
            <span
                class="absolute inset-0 flex items-center justify-center transition-opacity duration-200"
                :class="copied ? 'opacity-100' : 'opacity-0'"
            >
                {{ copiedLabel }}
            </span>
        </span>
    </button>
</template>
