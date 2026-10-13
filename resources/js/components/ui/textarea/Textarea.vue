<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { useVModel } from "@vueuse/core";
import { cn } from "@/lib/utils";

/*
  Een tekstvak met meer regels, met dezelfde opmaak als het gewone
  invoerveld. Hij deelt daarvoor `brand-control`, zodat de rand, de hoek en
  de focusring van allebei uit één blok komen en niet uit elkaar lopen.

  De schuifbalk krijgt `brand-scrollbar`, want de systeemschuifbalk is grijs
  en vierkant en valt midden in een donker portaal meteen op. Zie
  docs/architecture/formulieren-en-schuifbalken.md.
*/

const props = defineProps<{
    defaultValue?: string;
    modelValue?: string;
    class?: HTMLAttributes["class"];
}>();

const emits = defineEmits<{
    (e: "update:modelValue", payload: string): void;
}>();

const modelValue = useVModel(props, "modelValue", emits, {
    passive: true,
    defaultValue: props.defaultValue,
});
</script>

<template>
    <textarea
        v-model="modelValue"
        data-slot="textarea"
        :class="
            cn(
                'brand-control brand-textarea brand-scrollbar text-base selection:bg-primary selection:text-primary-foreground disabled:pointer-events-none md:text-sm',
                props.class,
            )
        "
    />
</template>
