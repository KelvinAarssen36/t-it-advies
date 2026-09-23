<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
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
        title: 'Bevestig met je authenticator',
        description:
            'Deze actie vraagt om een verse code uit je authenticator-app.',
    },
});
</script>

<template>
    <Head title="Bevestig met je authenticator" />

    <Form
        v-bind="confirmTwoFactor.store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
    >
        <div class="space-y-6">
            <div class="grid gap-2">
                <Label for="code">Zescijferige code</Label>
                <input
                    id="code"
                    name="code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    pattern="[0-9]*"
                    maxlength="6"
                    required
                    v-focus
                    class="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-center text-lg tracking-[0.5em] focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                />

                <InputError :message="errors.code" />

                <p class="text-sm text-muted-foreground">
                    Recovery codes werken hier niet. Die zijn alleen bedoeld om
                    weer in te loggen als je je authenticator kwijt bent.
                </p>
            </div>

            <Button class="w-full" :disabled="processing">
                <Spinner v-if="processing" />
                Bevestigen
            </Button>
        </div>
    </Form>
</template>
