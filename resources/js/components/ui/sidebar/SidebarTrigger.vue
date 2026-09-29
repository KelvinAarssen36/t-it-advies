<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { PanelLeftClose, PanelLeftOpen } from "@lucide/vue";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { useSidebar } from "./utils";

const props = defineProps<{
    class?: HTMLAttributes["class"];
}>();

const { isMobile, state, toggleSidebar } = useSidebar();
</script>

<template>
    <Button
        data-sidebar="trigger"
        data-slot="sidebar-trigger"
        variant="ghost"
        size="icon"
        :class="cn('h-7 w-7', props.class)"
        @click="toggleSidebar"
    >
        <PanelLeftOpen v-if="isMobile || state === 'collapsed'" />
        <PanelLeftClose v-else />

        <!--
            Een knop met alleen een pictogram heeft een naam nodig, anders
            leest een schermlezer "knop" en verder niets. In het
            Nederlands, want de rest van het portaal is dat ook.
        -->
        <span class="sr-only">{{ $t('Zijbalk in- of uitklappen') }}</span>
    </Button>
</template>
