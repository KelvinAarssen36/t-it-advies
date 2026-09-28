<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceToggle from '@/components/AppearanceToggle.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Weergave',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="$t('Weergave')" />

    <h1 class="sr-only">{{ $t('Weergave') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="$t('Weergave')"
            :description="
                $t(
                    'De huisstijl is de basis. Licht is er voor wie dat prettiger werkt.',
                )
            "
        />

        <AppearanceToggle />

        <p class="text-sm text-muted-foreground">
            {{
                $t(
                    'De huisstijl is hetzelfde donkere palet als de website zelf, en dat is waar alles op gebouwd wordt. Deze keuze geldt alleen voor het beheerportaal; de website blijft altijd in de huisstijl staan.',
                )
            }}
        </p>
    </div>
</template>
