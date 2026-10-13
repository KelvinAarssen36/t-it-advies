<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import LocaleFlag from '@/components/LocaleFlag.vue';
import SegmentToggle from '@/components/SegmentToggle.vue';
import { t } from '@/lib/i18n';
import { switchMethod as switchLocale } from '@/routes/locale';

/**
 * De taalkeuze op de publieke site en onder het inlogscherm.
 *
 * De vorm komt uit [`SegmentToggle.vue`](../SegmentToggle.vue); hier staat
 * alleen wat er in de vakjes komt en wat er gebeurt bij een klik. Beide
 * talen staan er dus altijd: je ziet in één oogopslag waar je staat én wat
 * je kunt kiezen.
 *
 * In het portaal staat de rustige omwisselknop in het accountmenu
 * ([`LocaleSwitcher.vue`](../LocaleSwitcher.vue)). Dat is bewust: het
 * beheergedeelte is gereedschap, geen etalage.
 *
 * Zie docs/architecture/vertalingen.md.
 */
const props = withDefaults(defineProps<{ compact?: boolean }>(), {
    compact: false,
});

const page = usePage();

const huidig = computed(() => page.props.locale);

/**
 * In de kop van de site staan alleen de taalcodes; daar telt elke pixel, en
 * naast een vlag is NL of EN duidelijk genoeg. In het uitklapmenu en onder
 * het inlogscherm is ruimte zat, dus daar staat de volledige naam.
 */
const opties = computed(() =>
    Object.entries(page.props.locales).map(([code, naam]) => ({
        value: code,
        label: props.compact ? code.toUpperCase() : naam,
    })),
);

const schakelaar = ref<InstanceType<typeof SegmentToggle> | null>(null);

const kies = (code: string) => {
    // Een volledige herlading is nodig: vertaalde tekst wordt bij het
    // renderen bepaald, dus alleen de taal omzetten laat de rest van het
    // scherm in de oude taal staan.
    router.post(
        switchLocale(code).url,
        {},
        {
            preserveScroll: true,
            onError: () => schakelaar.value?.herstel(),
        },
    );
};
</script>

<template>
    <SegmentToggle
        ref="schakelaar"
        :model-value="huidig"
        :options="opties"
        :groep-label="t('Taal')"
        @update:model-value="kies"
    >
        <template #voor="{ optie }">
            <LocaleFlag :locale="optie.value" size="md" aria-hidden="true" />
        </template>
    </SegmentToggle>
</template>
