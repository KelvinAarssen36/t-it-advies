<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Check, EyeOff, Lock, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import SectionThumb from '@/components/website/SectionThumb.vue';
import { Switch } from '@/components/ui/switch';
import type { SectieRij } from '@/types/secties';

/**
 * Eén onderdeel, zoals het op het indelingsscherm staat.
 *
 * **De drie toestanden zijn het hele punt van dit scherm.** Een onderdeel
 * dat niet op de website staat, kan daar twee heel verschillende redenen
 * voor hebben, en die mogen er niet hetzelfde uitzien:
 *
 * | Toestand              | Op de website | Hier                            |
 * | --------------------- | ------------- | ------------------------------- |
 * | Aan en gevuld         | ja            | gewoon, met een vinkje          |
 * | Zelf uitgezet         | nee           | rustig en gedempt, geen alarm   |
 * | Aan maar nog leeg     | nee           | uitroepteken en een link erheen |
 *
 * Het verschil tussen die laatste twee is waar dit scherm voor bestaat.
 * Iets dat je zelf hebt uitgezet, is geen probleem -- daar hoort geen
 * waarschuwing bij, want dan leer je waarschuwingen negeren. Iets dat
 * aanstaat maar leeg is, is wél een probleem: de eigenaar denkt dat het op
 * zijn site staat en het staat er niet.
 *
 * Er zijn twee vormen. De volle staat in het venster op de pagina en toont
 * ook de omschrijving; de compacte staat in het bewerkvenster, waar het om
 * de volgorde gaat en een regel uitleg per onderdeel alleen maar afleidt.
 */
const props = withDefaults(
    defineProps<{
        rij: SectieRij;
        /** In het bewerkvenster: werkt het schuifje en zijn de links weg. */
        bewerken?: boolean;
        /** De korte vorm, zonder omschrijving, voor in het bewerkvenster. */
        compact?: boolean;
        /** Een eigen kader eromheen in plaats van een regel in een lijst. */
        kaart?: boolean;
    }>(),
    { bewerken: false, compact: false, kaart: false },
);

const emit = defineEmits<{ 'update:zichtbaar': [boolean] }>();

/** Aan, maar er staat niets in. De enige toestand met een uitroepteken. */
const leeg = computed(
    () => !props.rij.fixed && props.rij.visible && !props.rij.filled,
);

const uitgezet = computed(() => !props.rij.fixed && !props.rij.visible);

const staat = computed<'live' | 'uit' | 'leeg'>(() => {
    if (leeg.value) {
        return 'leeg';
    }

    return uitgezet.value ? 'uit' : 'live';
});
</script>

<template>
    <div
        class="brand-sectie-rij"
        :data-staat="staat"
        :data-kaart="kaart ? '' : undefined"
        :data-compact="compact ? '' : undefined"
    >
        <SectionThumb :sectie="rij.key" :staat="staat" />

        <div class="flex min-w-0 flex-1 flex-col gap-1">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span class="font-medium">{{ rij.label }}</span>

                <span v-if="rij.fixed" class="brand-sectie-merk">
                    <Lock class="size-3" />
                    {{ $t('Staat vast') }}
                </span>
                <span v-else-if="leeg" class="brand-sectie-merk">
                    <TriangleAlert class="size-3" />
                    {{ $t('Nog niets ingevuld') }}
                </span>
                <span v-else-if="uitgezet" class="brand-sectie-merk">
                    <EyeOff class="size-3" />
                    {{ $t('Uitgezet') }}
                </span>
                <span v-else class="brand-sectie-merk">
                    <Check class="size-3" />
                    {{ $t('Op de website') }}
                </span>

                <span
                    v-if="rij.count !== null && !leeg"
                    class="text-xs text-muted-foreground"
                >
                    {{ $t(':aantal items', { aantal: rij.count }) }}
                </span>
            </div>

            <p
                v-if="!compact"
                class="text-sm text-pretty text-muted-foreground"
            >
                {{ rij.description }}
            </p>

            <!--
                Alleen bij het lege onderdeel staat er een zin die uitlegt
                wat er aan de hand is. Bij de andere twee spreekt het merkje
                voor zich, en een regel uitleg onder elke regel maakt het
                scherm juist onleesbaar.
            -->
            <p v-if="leeg && !compact" class="brand-sectie-waarschuwing">
                {{
                    $t(
                        'Dit onderdeel staat aan, maar er is nog niets ingevuld. Daarom laten we het niet op de website zien.',
                    )
                }}
            </p>
        </div>

        <!--
            Op een telefoon zakt dit blok naar een eigen regel; zie
            `brand-sectie-acties` in app.css. Zonder dat duwt het de
            omschrijving ernaast zo smal dat er één woord per regel
            overblijft.
        -->
        <div class="brand-sectie-acties flex shrink-0 items-center gap-3">
            <Link
                v-if="rij.manageUrl && !bewerken"
                :href="rij.manageUrl"
                class="brand-sectie-link"
            >
                {{ leeg ? $t('Items toevoegen') : $t('Inhoud aanpassen') }}
                <ArrowRight class="size-3.5" />
            </Link>

            <span
                v-else-if="!rij.manageUrl && !rij.fixed && !compact"
                class="text-xs text-muted-foreground"
            >
                {{ $t('Nog niet te beheren') }}
            </span>

            <!--
                Het schuifje staat er ook buiten het bewerkvenster, maar dan
                uitgeschakeld. Weghalen zou betekenen dat de regel van vorm
                verandert zodra je gaat bewerken, en dan herken je hem niet
                meer terug als dezelfde regel.
            -->
            <Switch
                v-if="!rij.fixed"
                :model-value="rij.visible"
                :disabled="!bewerken"
                :aria-label="
                    $t('Toon :naam op de website', { naam: rij.label })
                "
                @update:model-value="emit('update:zichtbaar', $event)"
            />

            <!--
                Op de plek van het schuifje bij een vast onderdeel: een
                slotje. Niets neerzetten zou de regel laten inspringen ten
                opzichte van de rest.
            -->
            <span v-else class="brand-sectie-slot">
                <Lock class="size-3.5" />
            </span>
        </div>
    </div>
</template>
