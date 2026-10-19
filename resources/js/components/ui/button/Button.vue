<script setup lang="ts">
import type { PrimitiveProps } from "reka-ui";
import type { HTMLAttributes } from "vue";
import type { ButtonVariants } from ".";
import { Primitive } from "reka-ui";
import { computed } from "vue";
import { cn } from "@/lib/utils";
import { buttonVariants } from ".";

interface Props extends PrimitiveProps {
    variant?: ButtonVariants["variant"];
    size?: ButtonVariants["size"];
    class?: HTMLAttributes["class"];

    /**
     * De knop mag nog niet, maar vangt zijn klik wél op.
     *
     * **Dit is bewust geen `disabled`.** Een uitgeschakelde knop doet
     * niets als je erop drukt: geen melding, geen uitleg, en een
     * schermlezer slaat hem helemaal over. De klant staat dan voor een
     * grijze knop zonder te weten waarom. Met `geblokkeerd` ziet hij er
     * net zo uit, maar komt de klik binnen -- en juist die klik levert
     * de uitleg op welk veld nog leeg is.
     *
     * Gebruik `disabled` voor wat echt niet kan (er loopt al een
     * verzoek), en `geblokkeerd` voor wat nog niet mag.
     *
     * Zie lib/verplicht.ts en components/VerplichtMelding.vue.
     */
    geblokkeerd?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    as: "button",
});

/*
 * De zweefklassen gaan eruit zodra de knop geblokkeerd is. Een echte
 * `disabled` knop zweeft ook niet -- daar zorgt `disabled:pointer-events-none`
 * voor -- maar dat kunnen we hier niet gebruiken, want dan verdwijnt de
 * klik die we juist nodig hebben. Ze er met de hand uit halen geeft
 * hetzelfde beeld en houdt de knop bereikbaar.
 *
 * Dit werkt voor elke variant, ook voor varianten die er later bij komen:
 * er wordt op het voorvoegsel gefilterd en niet op een lijst namen.
 */
const klassen = computed(() => {
    const vorm = buttonVariants({ variant: props.variant, size: props.size });

    if (!props.geblokkeerd) {
        return cn(vorm, props.class);
    }

    const zonderZweef = vorm
        .split(" ")
        .filter((klasse) => !klasse.startsWith("hover:"))
        .join(" ");

    return cn(
        zonderZweef,
        "opacity-50 shadow-none cursor-not-allowed",
        props.class,
    );
});
</script>

<template>
    <Primitive
        data-slot="button"
        :data-variant="variant"
        :data-size="size"
        :aria-disabled="geblokkeerd || undefined"
        :as="as"
        :as-child="asChild"
        :class="klassen"
    >
        <slot />
    </Primitive>
</template>
