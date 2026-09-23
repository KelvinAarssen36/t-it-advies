<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * De Cloudflare Turnstile-widget.
 *
 * Turnstile levert een token dat als `cf-turnstile-response` meegaat met het
 * formulier. De server controleert dat token bij Cloudflare; zonder die
 * servercontrole stelt de widget niets voor.
 *
 * Is er geen site key ingesteld, dan rendert dit component niets. Lokaal
 * ontwikkelen kan dus zonder Cloudflare-account: de validatieregel op de
 * server slaat de check dan ook over. In productie is de site key verplicht.
 */

const SCRIPT_SRC =
    'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

declare global {
    interface Window {
        turnstile?: {
            render: (
                element: HTMLElement,
                options: Record<string, unknown>,
            ) => string;
            remove: (widgetId: string) => void;
            reset: (widgetId?: string) => void;
        };
    }
}

const page = usePage();
const siteKey = computed(() => page.props.turnstileSiteKey);

const container = ref<HTMLDivElement | null>(null);
const widgetId = ref<string | null>(null);

function loadScript(): Promise<void> {
    if (window.turnstile) {
        return Promise.resolve();
    }

    const existing = document.querySelector<HTMLScriptElement>(
        `script[src="${SCRIPT_SRC}"]`,
    );

    if (existing) {
        return new Promise((resolve) =>
            existing.addEventListener('load', () => resolve(), { once: true }),
        );
    }

    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = SCRIPT_SRC;
        script.async = true;
        script.defer = true;
        script.addEventListener('load', () => resolve(), { once: true });
        script.addEventListener('error', () => reject(), { once: true });
        document.head.appendChild(script);
    });
}

onMounted(async () => {
    if (!siteKey.value || !container.value) {
        return;
    }

    try {
        await loadScript();
    } catch {
        // Cloudflare niet bereikbaar. Het formulier blijft bruikbaar; de
        // server weigert de inzending dan alsnog, en dat is de juiste plek.
        return;
    }

    if (!window.turnstile || !container.value) {
        return;
    }

    widgetId.value = window.turnstile.render(container.value, {
        sitekey: siteKey.value,
        theme: 'auto',
    });
});

onBeforeUnmount(() => {
    if (widgetId.value && window.turnstile) {
        window.turnstile.remove(widgetId.value);
    }
});

defineExpose({
    reset: () => {
        if (widgetId.value && window.turnstile) {
            window.turnstile.reset(widgetId.value);
        }
    },
});
</script>

<template>
    <div v-if="siteKey" ref="container" class="min-h-[65px]" />
</template>
