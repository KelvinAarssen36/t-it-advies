<script setup lang="ts">
import type { SwitchRootEmits, SwitchRootProps } from "reka-ui";
import type { HTMLAttributes } from "vue";
import { reactiveOmit } from "@vueuse/core";
import { SwitchRoot, SwitchThumb, useForwardPropsEmits } from "reka-ui";
import { cn } from "@/lib/utils";

/*
  Een schakelaar voor "aan of uit", waar een vinkje voor "gekozen of niet"
  is. Het verschil is niet cosmetisch: een schuifje zegt dat er meteen iets
  verandert aan de wereld, een vinkje dat je iets aankruist en later
  opslaat.

  Gebouwd op de primitief van reka-ui en niet op een <input type="checkbox">,
  om dezelfde reden als bij het keuzeveld: die laat zich niet volledig in de
  huisstijl zetten. Zie docs/architecture/formulieren-en-schuifbalken.md.
*/

const props = defineProps<
    SwitchRootProps & { class?: HTMLAttributes["class"] }
>();
const emits = defineEmits<SwitchRootEmits>();

const delegatedProps = reactiveOmit(props, "class");

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
    <SwitchRoot
        data-slot="switch"
        v-bind="forwarded"
        :class="
            cn(
                'peer data-[state=checked]:bg-primary data-[state=unchecked]:bg-input focus-visible:border-ring focus-visible:ring-ring/50 inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border border-transparent shadow-xs transition-colors outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50',
                props.class,
            )
        "
    >
        <SwitchThumb
            data-slot="switch-thumb"
            class="bg-background pointer-events-none block size-4 rounded-full shadow-sm ring-0 transition-transform data-[state=checked]:translate-x-4 data-[state=unchecked]:translate-x-0"
        />
    </SwitchRoot>
</template>
