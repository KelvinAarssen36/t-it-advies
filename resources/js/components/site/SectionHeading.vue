<script setup lang="ts">
/**
 * De kop van een sectie: een klein bovenschrift, een titel en een inleiding.
 *
 * Het bovenschrift is een `<p>` en geen kop-element. Het ziet eruit als een
 * label, maar in de koppenstructuur van de pagina hoort het niet thuis --
 * een schermlezer zou anders een niveau tegenkomen dat nergens heen leidt.
 */
withDefaults(
    defineProps<{
        eyebrow?: string;
        title: string;
        intro?: string;
        level?: 'h1' | 'h2';
    }>(),
    { level: 'h2' },
);
</script>

<template>
    <div class="max-w-2xl">
        <p
            v-if="eyebrow"
            data-reveal
            class="mb-4 text-sm tracking-[0.2em] text-brand-cyan uppercase opacity-0"
        >
            {{ eyebrow }}
        </p>

        <component
            :is="level"
            data-reveal
            class="text-3xl font-semibold tracking-tight text-balance text-white opacity-0 sm:text-4xl"
        >
            {{ title }}
        </component>

        <p
            v-if="intro"
            data-reveal
            class="mt-4 text-lg text-pretty text-muted-foreground opacity-0"
        >
            {{ intro }}
        </p>
    </div>
</template>
