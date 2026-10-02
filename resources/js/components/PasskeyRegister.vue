<script setup lang="ts">
import { usePasskeyRegister } from '@laravel/passkeys/vue';
import { computed, ref } from 'vue';
import { TriangleAlert } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const emit = defineEmits<{
    success: [];
}>();

const getDefaultPasskeyName = () => {
    const ua = navigator.userAgent;

    const browser = [
        { pattern: /Edg|Edge/, name: 'Edge' },
        { pattern: /OPR|Opera|OPiOS/, name: 'Opera' },
        { pattern: /Firefox|FxiOS/, name: 'Firefox' },
        { pattern: /Chrome|CriOS/, name: 'Chrome' },
        { pattern: /Safari/, name: 'Safari' },
    ].find(({ pattern }) => pattern.test(ua))?.name;

    const os = [
        { pattern: /iPhone/, name: 'iPhone' },
        { pattern: /iPad|Macintosh(?=.*Mobile)/, name: 'iPad' },
        { pattern: /Android/, name: 'Android' },
        { pattern: /Mac/, name: 'Mac' },
        { pattern: /Windows/, name: 'Windows' },
    ].find(({ pattern }) => pattern.test(ua))?.name;

    return [browser, os].filter(Boolean).join(' on ') || '';
};

const name = ref(getDefaultPasskeyName());
const showForm = ref(false);

/**
 * Of dit een beveiligde verbinding is.
 *
 * **Dit is het verschil tussen twee heel verschillende oorzaken die er
 * eerst allebei uitzagen als "je browser kan het niet".** De controle van
 * de bibliotheek is `globalThis.PublicKeyCredential !== undefined`, en
 * browsers stellen dat object alleen beschikbaar op https of localhost. Op
 * een gewoon `.test`-adres bestaat het dus niet, en kreeg je te horen dat
 * je browser geen passkeys kende -- terwijl die het prima kan.
 *
 * Een foutmelding die de verkeerde schuldige aanwijst kost iemand een
 * middag zoeken in de verkeerde richting. Nu staat er wat er echt aan de
 * hand is.
 */
const beveiligdeVerbinding = computed(
    () => typeof window === 'undefined' || window.isSecureContext,
);

const { register, isLoading, error, isSupported } = usePasskeyRegister({
    onSuccess: () => {
        name.value = '';
        showForm.value = false;
        emit('success');
    },
});

const handleSubmit = async (event: Event) => {
    event.preventDefault();

    if (!name.value.trim()) {
        return;
    }

    await register(name.value);
};

const handleCancel = () => {
    showForm.value = false;
    name.value = '';
};
</script>

<template>
    <!--
        Twee oorzaken, twee meldingen. Zie `beveiligdeVerbinding`: de
        bibliotheek kan ze niet uit elkaar houden, want in allebei de
        gevallen ontbreekt hetzelfde object.
    -->
    <div v-if="!isSupported" class="brand-passkeymelding">
        <TriangleAlert class="size-4 shrink-0" aria-hidden="true" />
        <p class="text-pretty">
            <template v-if="!beveiligdeVerbinding">
                {{
                    $t(
                        'Passkeys werken alleen op een beveiligde verbinding (https). Je kijkt nu naar een adres zonder slotje, dus je browser staat het hier niet toe. Op de echte website werkt het wel.',
                    )
                }}
            </template>
            <template v-else>
                {{
                    $t(
                        'Deze browser kan niet met passkeys overweg. Probeer het in een recente versie van Chrome, Edge, Safari of Firefox.',
                    )
                }}
            </template>
        </p>
    </div>

    <Button v-else-if="!showForm" variant="aanmaken" @click="showForm = true">
        {{ $t('Passkey toevoegen') }}
    </Button>

    <form
        v-else
        @submit="handleSubmit"
        class="space-y-4 rounded-lg border border-border bg-muted/50 p-4"
    >
        <div class="grid gap-2">
            <Label for="passkey-name" verplicht>
                {{ $t('Naam van de passkey') }}
            </Label>
            <Input
                id="passkey-name"
                type="text"
                v-model="name"
                :placeholder="$t('Bijvoorbeeld: MacBook Pro, iPhone')"
                class="mt-1 block w-full border-foreground/20"
                v-focus
            />
            <p class="text-xs text-muted-foreground">
                {{ $t('Aan die naam herken je later welk apparaat dit was.') }}
            </p>
        </div>

        <InputError v-if="error" :message="error" />

        <div class="flex gap-2">
            <Button
                variant="aanmaken"
                type="submit"
                :disabled="isLoading || !name.trim()"
            >
                {{ isLoading ? $t('Bezig...') : $t('Passkey vastleggen') }}
            </Button>
            <Button type="button" variant="ghost" @click="handleCancel">
                {{ $t('Annuleren') }}
            </Button>
        </div>
    </form>
</template>
