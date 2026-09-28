<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import LocaleFlag from '@/components/LocaleFlag.vue';
import BrandSelect from '@/components/BrandSelect.vue';
import { switchMethod as switchLocale } from '@/routes/locale';

/**
 * De keuzelijst voor de instellingen.
 *
 * Bewust een andere vorm dan de omwisselknop in het accountmenu: hier hoort
 * taal bij de andere instellingen thuis, en dan verwacht je een lijst.
 *
 * Er is geen opslaan-knop. De keuze is meteen de handeling -- een taal die
 * je kiest maar nog moet bevestigen is een stap die niemand verwacht.
 */
const page = usePage();

const current = computed(() => page.props.locale);
const locales = computed(() => page.props.locales);

const opties = computed(() =>
    Object.entries(locales.value).map(([code, naam]) => ({
        value: code,
        label: naam,
    })),
);

const kies = (gekozen: string) => {
    // Niets veranderd? Dan ook niets schrijven.
    if (gekozen === '' || gekozen === current.value) {
        return;
    }

    router.post(switchLocale(gekozen).url, {}, { preserveScroll: true });
};
</script>

<template>
    <!--
        De vlag staat náást de lijst en niet erin. Sinds de lijst van onszelf
        is zou een vlag per regel kunnen, maar dan staat dezelfde vlag twee
        keer in beeld zodra je hem opent. Eén keer, naast het veld, zegt
        genoeg: hij toont de taal die nu geldt en wisselt mee zodra je kiest.
    -->
    <div class="flex max-w-xs items-center gap-3">
        <LocaleFlag :locale="current" size="lg" aria-hidden="true" />

        <BrandSelect
            :model-value="current"
            :options="opties"
            aria-label="Kies een taal"
            @update:model-value="kies"
        />
    </div>
</template>
