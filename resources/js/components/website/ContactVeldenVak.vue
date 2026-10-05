<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Info, Lock } from '@lucide/vue';
import { ref, watch } from 'vue';
import BrandSelect from '@/components/BrandSelect.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { bevestigBewerken } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import contact from '@/routes/website/contact';
import type { ContactOpties, VeldRij, VeldStatus } from '@/types/contact';

/**
 * De velden van het contactformulier: aan, uit of verplicht.
 *
 * **Eén keuze per veld en geen twee schuifjes.** "Zichtbaar" en
 * "verplicht" zijn twee schakelaars die samen vier standen maken waarvan
 * er één onzin is: onzichtbaar én verplicht. Die stand ontstaat vanzelf --
 * je zet Telefoonnummer op verplicht, bedenkt je, en zet alleen de
 * zichtbaarheid uit. Dan staat er een formulier dat niet te versturen is
 * om een veld dat er niet staat. Zie App\Enums\ContactVeldStatus.
 *
 * **Drie velden staan vergrendeld.** Naam, e-mailadres en bericht blijven
 * altijd staan en altijd verplicht, want zonder die drie kun je niemand
 * antwoorden. Het slotje zegt dat, met de reden erbij -- een schuifje dat
 * niets doet zonder uitleg is een schuifje dat stuk lijkt.
 *
 * Zie docs/architecture/modules/contact.md.
 */
const props = defineProps<{
    velden: VeldRij[];
    opties: ContactOpties;
}>();

/** De werkkopie. Opslaan gaat in één keer, met één bevestiging. */
const werk = ref<VeldRij[]>([]);

const bezig = ref(false);

const vulIn = (): void => {
    werk.value = props.velden.map((veld) => ({ ...veld }));
};

vulIn();

// De server is de waarheid: komt er een verse lijst binnen na het opslaan,
// dan is dat wat er staat.
watch(() => props.velden, vulIn, { deep: true });

/** Of er iets is veranderd ten opzichte van wat er is opgeslagen. */
const gewijzigd = (): boolean =>
    werk.value.some((veld, index) => {
        const origineel = props.velden[index];

        return (
            veld.status !== origineel?.status ||
            veld.eigenToegestaan !== origineel?.eigenToegestaan
        );
    });

const opslaan = async (): Promise<void> => {
    if (!gewijzigd()) {
        return;
    }

    const akkoord = await bevestigBewerken({
        titel: t('De velden van je formulier aanpassen?'),
        tekst: t(
            'Dit verandert meteen wat je bezoekers op je website zien en moeten invullen.',
        ),
    });

    if (!akkoord) {
        return;
    }

    bezig.value = true;

    router.put(
        contact.velden().url,
        {
            velden: werk.value.map((veld) => ({
                key: veld.key,
                status: veld.status,
                allow_custom: veld.eigenToegestaan,
            })),
        },
        {
            preserveScroll: true,
            onFinish: () => {
                bezig.value = false;
            },
        },
    );
};

/** De drie standen, zonder "uit" bij een vergrendeld veld. */
const standenVoor = (veld: VeldRij) =>
    veld.vast
        ? props.opties.standen.filter((stand) => stand.value === 'verplicht')
        : props.opties.standen;

const zet = (veld: VeldRij, waarde: VeldStatus): void => {
    if (veld.vast) {
        return;
    }

    veld.status = waarde;
};
</script>

<template>
    <div class="rounded-xl border p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
                <p class="brand-bezoeklijst-kop">
                    {{ $t('De velden van je formulier') }}
                </p>
                <p
                    class="max-w-prose text-sm text-pretty text-muted-foreground"
                >
                    {{
                        $t(
                            'Bepaal zelf wat je bezoeker moet invullen. Hoe meer velden verplicht zijn, hoe minder berichten je krijgt -- dus vraag alleen wat je echt nodig hebt.',
                        )
                    }}
                </p>
            </div>

            <Button
                type="button"
                variant="bewerken"
                :disabled="bezig"
                @click="opslaan"
            >
                {{ bezig ? $t('Bezig...') : $t('Velden opslaan') }}
            </Button>
        </div>

        <ul class="mt-4 space-y-2">
            <li
                v-for="veld in werk"
                :key="veld.key"
                class="brand-contactveld"
                :data-vast="veld.vast ? '' : undefined"
            >
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-2 font-medium">
                        {{ veld.label }}
                        <span v-if="veld.vast" class="brand-contactveld-slot">
                            <Lock class="size-3" aria-hidden="true" />
                            {{ $t('Staat vast') }}
                        </span>
                    </p>
                    <p
                        v-if="veld.vast"
                        class="mt-0.5 text-xs text-pretty text-muted-foreground"
                    >
                        {{
                            $t(
                                'Dit veld blijft altijd staan en blijft verplicht: zonder naam, e-mailadres en bericht kun je niemand antwoorden.',
                            )
                        }}
                    </p>
                </div>

                <div class="flex flex-col items-stretch gap-2 sm:w-56">
                    <BrandSelect
                        :id="`veld-${veld.key}`"
                        :model-value="veld.status"
                        :options="standenVoor(veld)"
                        :disabled="veld.vast"
                        @update:model-value="
                            (waarde) => zet(veld, waarde as VeldStatus)
                        "
                    />

                    <!--
                        Alleen bij het onderwerp. Staat dit uit, dan moet de
                        bezoeker kiezen uit de lijst van de eigenaar; staat
                        het aan, dan mag hij er zelf een typen.
                    -->
                    <label
                        v-if="veld.heeftEigenKeuze && veld.status !== 'uit'"
                        class="flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <Switch v-model="veld.eigenToegestaan" />
                        {{ $t('Eigen onderwerp mag') }}
                    </label>
                </div>
            </li>
        </ul>

        <p class="brand-bezoekuitleg mt-4">
            <Info class="size-4 shrink-0" aria-hidden="true" />
            <span class="text-sm text-pretty">
                {{
                    $t(
                        'Wil je een veld dat hier niet tussen staat? Laat het weten, dan bouwen wij het erbij. Zelf velden verzinnen kan niet: elk veld heeft een eigen soort invoer en een eigen controle, en die moeten kloppen.',
                    )
                }}
            </span>
        </p>
    </div>
</template>
