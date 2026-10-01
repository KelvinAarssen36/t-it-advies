<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Loader2 } from '@lucide/vue';
import { ref } from 'vue';
import BrandSelect from '@/components/BrandSelect.vue';
import DigitaleKlok from '@/components/dashboard/DigitaleKlok.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import dashboardSettings from '@/routes/dashboard-settings';

/**
 * De instellingen van het dashboard.
 *
 * **Eén instelling, en toch een eigen gedeelte.** Dat is wat de eigenaar
 * vroeg: een aparte plek voor de instellingen van zijn dashboard, omdat er
 * meer bij komt. Zou de tijdzone nu bij "Weergave" staan, dan moet alles
 * wat erbij komt daar ook heen -- en dan gaat dat scherm over twee dingen.
 *
 * **Met het echte voorbeeld eronder.** Dezelfde klok als op het dashboard
 * en geen nabootsing, dus je ziet je keuze meteen lopen. Dat is dezelfde
 * aanpak als het voorbeeld in het statistiekvenster: een lijst met
 * tijdzonenamen zegt wat er in de database staat, een lopende klok zegt
 * hoe laat het daar is.
 *
 * **Geen bevestigingsvraag.** De vaste bevestigingsstroom van dit project
 * hangt aan "dit staat direct live op de website"; dit verandert niets aan
 * wat een bezoeker ziet. Het is een persoonlijke voorkeur, net als licht of
 * donker, en die slaat ook meteen op.
 *
 * Zie docs/architecture/dashboard.md.
 */
const props = defineProps<{
    tijdzone: string;
    opties: Array<{ value: string; label: string }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard' }],
    },
});

const keuze = ref(props.tijdzone);
const bezig = ref(false);

const opslaan = (): void => {
    bezig.value = true;

    router.patch(
        dashboardSettings.update().url,
        { tijdzone: keuze.value },
        {
            preserveScroll: true,
            onFinish: () => {
                bezig.value = false;
            },
        },
    );
};
</script>

<template>
    <Head :title="$t('Dashboard')" />

    <h1 class="sr-only">{{ $t('Dashboard') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="$t('Dashboard')"
            :description="
                $t('Wat er op je eigen beginscherm staat en hoe het staat.')
            "
        />

        <div class="space-y-2">
            <Label for="dashboard-tijdzone">{{ $t('Tijdzone') }}</Label>
            <BrandSelect
                id="dashboard-tijdzone"
                v-model="keuze"
                :options="props.opties"
            />
            <p class="text-sm text-pretty text-muted-foreground">
                {{
                    $t(
                        'De klok op je dashboard loopt in deze zone. Zomer- en wintertijd gaan automatisch mee.',
                    )
                }}
            </p>
        </div>

        <!--
            De echte klok, in de keuze die er nu in het veld staat -- dus
            ook voordat je opslaat. Zo kies je op wat je ziet en niet op
            een plaatsnaam.
        -->
        <div class="space-y-2">
            <p class="text-sm font-medium">{{ $t('Zo ziet het eruit') }}</p>
            <div class="rounded-xl border bg-muted/40 p-4">
                <DigitaleKlok :tijdzone="keuze" />
            </div>
        </div>

        <Button
            variant="bewerken"
            class="gap-2"
            :disabled="bezig || keuze === props.tijdzone"
            @click="opslaan"
        >
            <Loader2 v-if="bezig" class="size-4 animate-spin" />
            {{ $t('Opslaan') }}
        </Button>
    </div>
</template>
