<script setup lang="ts">
import type { LabelProps } from "reka-ui";
import { reactiveOmit } from "@vueuse/core";
import { Label } from "reka-ui";
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";

/**
 * Het label boven een invoerveld.
 *
 * **`verplicht` zet er een sterretje bij, en dat is een projectafspraak.**
 * Elk veld dat verplicht is krijgt er een; velden zonder sterretje leest de
 * klant daarmee als optioneel. Een vergeten sterretje is dus geen
 * schoonheidsfoutje maar onjuiste informatie. Loop bij een nieuw formulier
 * de regels van de bijbehorende FormRequest langs en zet er een bij alles
 * met `required`.
 *
 * Het staat hier op één plek en niet als tekentje dat je zelf achter het
 * label typt: anders verschilt de opmaak per scherm en ontbreekt het
 * ergens.
 *
 * Zie docs/architecture/formulieren-en-schuifbalken.md.
 */
const props = defineProps<
    LabelProps & { class?: HTMLAttributes["class"]; verplicht?: boolean }
>();

const delegatedProps = reactiveOmit(props, "class", "verplicht");
</script>

<template>
    <Label
        data-slot="label"
        v-bind="delegatedProps"
        :class="
            cn(
                'flex items-center gap-2 text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50',
                props.class,
            )
        "
    >
        <slot />

        <!--
            Het sterretje is versiering voor het oog; een schermlezer krijgt
            het woord. Zou het teken zelf worden voorgelezen, dan hoor je
            "ster" achter elk verplicht veld en weet je nog niets.

            `-ml-1` haalt de helft van de `gap-2` hierboven weg: het
            sterretje hoort tegen het woord aan te staan en niet als los
            onderdeel ernaast. De rest van de labels in de applicatie zet
            juist wél iets naast de tekst, dus die ruimte blijft staan.
        -->
        <template v-if="verplicht">
            <span class="brand-verplicht -ml-1" aria-hidden="true">*</span>
            <span class="sr-only">{{ $t("(verplicht)") }}</span>
        </template>
    </Label>
</template>
