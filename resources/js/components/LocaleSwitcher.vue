<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import LocaleFlag from '@/components/LocaleFlag.vue';
import { switchMethod as switchLocale } from '@/routes/locale';

/**
 * De omwisselknop. Twee talen, dus geen keuzelijst: hij toont waar je nu
 * staat en waar je heen gaat, en één klik is genoeg.
 *
 * De taal komt uit de gedeelde props en niet uit een eigen toestand -- de
 * server beslist, deze knop vraagt het alleen. Zie
 * docs/architecture/vertalingen.md.
 */
withDefaults(defineProps<{ compact?: boolean; size?: 'sm' | 'md' }>(), {
    compact: false,
    size: 'sm',
});

const page = usePage();

const current = computed(() => page.props.locale);
const locales = computed(() => Object.keys(page.props.locales));

const target = computed(
    () => locales.value.find((code) => code !== current.value) ?? current.value,
);

const label = computed(() => page.props.locales[target.value] ?? target.value);

const wissel = () => {
    // Een volledige herlading van de pagina is nodig: vertaalde tekst wordt
    // bij het renderen bepaald, dus alleen de taal omzetten laat de rest van
    // het scherm in de oude taal staan.
    router.post(switchLocale(target.value).url, {}, { preserveScroll: true });
};
</script>

<template>
    <button
        type="button"
        class="flex w-full cursor-pointer items-center gap-2 text-left"
        :aria-label="`Schakel over naar ${label}`"
        @click="wissel"
    >
        <!--
            De twee vlaggen naast elkaar, met de doeltaal op 60%. Dat ene
            verschil in dekking maakt zonder tekst duidelijk welke taal je
            hébt en welke je krijgt; zonder dat zijn het twee gelijkwaardige
            vlaggen en moet je de pijl lezen.

            Beide zijn aria-hidden omdat het aria-label van de knop het al
            zegt: anders hoort een schermlezer drie keer een taalnaam.
        -->
        <LocaleFlag :locale="current" :size="size" aria-hidden="true" />
        <span aria-hidden="true" class="text-muted-foreground">&rarr;</span>
        <LocaleFlag
            :locale="target"
            :size="size"
            class="opacity-60"
            aria-hidden="true"
        />

        <span v-if="!compact">{{ label }}</span>
    </button>
</template>
