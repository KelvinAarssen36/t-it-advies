<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import { ref, useTemplateRef } from 'vue';
import InputError from '@/components/InputError.vue';
import PasteCodeButton from '@/components/PasteCodeButton.vue';
import { Button } from '@/components/ui/button';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import confirmTwoFactor from '@/routes/security/two-factor/confirm';

/**
 * Bevestiging van een gevoelige actie met een verse authenticator-code.
 *
 * Bewust geen recovery-code-optie op dit scherm: die geldt hier niet, en een
 * knop tonen die altijd faalt is alleen maar verwarrend. De server weigert
 * hem ook expliciet, zie ConfirmTwoFactorController.
 */

defineOptions({
    layout: {
        // Zie ConfirmPassword: ook dit onderbreekt je terwijl je al binnen
        // bent, dus het volgt de themavoorkeur.
        variant: 'portaal',
        title: 'Bevestig met je authenticator',
        description:
            'Deze actie vraagt om een verse code uit je authenticator-app.',
    },
});

const code = ref('');

// De zesde cijfer invoeren is het laatste wat je wilt doen; daarna nog een
// knop moeten zoeken is een stap te veel. Een verborgen knop indrukken werkt
// los van de slot-API van het formulier, en de zichtbare knop blijft staan
// voor wie met het toetsenbord navigeert of de code plakt.
const autoSubmit = useTemplateRef<HTMLButtonElement>('autoSubmit');

const onComplete = () => {
    autoSubmit.value?.click();
};

const onPasted = (plakcode: string) => {
    code.value = plakcode;
    onComplete();
};
</script>

<template>
    <Head title="Bevestig met je authenticator" />

    <Form
        v-bind="confirmTwoFactor.store.form()"
        reset-on-error
        @error="code = ''"
        v-slot="{ errors, processing }"
    >
        <input type="hidden" name="code" :value="code" />

        <div class="space-y-6">
            <div class="flex flex-col items-center gap-4">
                <InputOTP
                    v-model="code"
                    :maxlength="6"
                    :disabled="processing"
                    autofocus
                    @complete="onComplete"
                >
                    <InputOTPGroup>
                        <InputOTPSlot
                            v-for="index in 6"
                            :key="index"
                            :index="index - 1"
                        />
                    </InputOTPGroup>
                </InputOTP>

                <InputError :message="errors.code" />

                <PasteCodeButton @pasted="onPasted" />
            </div>

            <div
                class="flex gap-3 rounded-lg border border-border bg-muted/40 p-3 text-sm text-muted-foreground"
            >
                <ShieldCheck class="mt-0.5 size-4 shrink-0 text-brand-cyan" />
                <p>
                    Recovery codes werken hier niet. Die zijn alleen bedoeld om
                    weer in te loggen als je je authenticator kwijt bent.
                </p>
            </div>

            <Button class="w-full" :disabled="processing || code.length < 6">
                <Spinner v-if="processing" />
                Bevestigen
            </Button>

            <button ref="autoSubmit" type="submit" class="hidden" tabindex="-1">
                Bevestigen
            </button>
        </div>
    </Form>
</template>
