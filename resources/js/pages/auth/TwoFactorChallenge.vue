<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, useTemplateRef, watchEffect } from 'vue';
import InputError from '@/components/InputError.vue';
import PasteCodeButton from '@/components/PasteCodeButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { store } from '@/routes/two-factor/login';
import type { TwoFactorConfigContent } from '@/types';

/**
 * De tweede stap bij het inloggen.
 *
 * Hier mag een recovery code wél, in tegenstelling tot bij een gevoelige
 * actie: je probeert juist weer binnen te komen omdat je je authenticator
 * kwijt bent. Zie docs/security/gevoelige-acties.md voor waarom dat verschil
 * er is.
 */

const props = withDefaults(defineProps<{ viaPasskey?: boolean }>(), {
    viaPasskey: false,
});

const showRecoveryInput = ref(false);
const code = ref('');

/*
 * De titel en de omschrijving gaan naar de layout, en die vertaalt ze bij
 * het tekenen -- dat is hoe alle schermen hier het doen. De knoptekst
 * staat midden in een zin op déze pagina, dus die vertalen we hier.
 */
const authConfigContent = computed<TwoFactorConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: 'Recovery code',
            description:
                'Vul een van je recovery codes in om weer binnen te komen.',
            buttonText: t('inloggen met een code uit je app'),
        };
    }

    return {
        title: 'Tweestapsverificatie',
        description: 'Vul de zescijferige code uit je authenticator-app in.',
        buttonText: t('inloggen met een recovery code'),
    };
});

watchEffect(() => {
    setLayoutProps({
        title: authConfigContent.value.title,
        description: authConfigContent.value.description,
    });
});

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
};

// Zodra het zesde cijfer staat, versturen. Nog een knop moeten zoeken is een
// stap te veel op een scherm waar je elke keer langskomt.
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
    <Head :title="$t('Tweestapsverificatie')" />

    <div class="space-y-6">
        <!--
            Komt hij hier na een passkey, dan hoort er te staan waarom. Hij
            dacht klaar te zijn; een codeveld zonder uitleg leest als een
            mislukte passkey. Zie docs/security/extra-stap-na-een-passkey.md.
        -->
        <p
            v-if="props.viaPasskey"
            class="rounded-lg border border-border bg-muted/40 p-4 text-sm text-pretty text-muted-foreground"
        >
            {{
                $t(
                    'Je passkey is goedgekeurd. Je hebt zelf ingesteld dat daar ook nog je authenticator-code bij hoort; dat zet je uit onder Instellingen → Beveiliging.',
                )
            }}
        </p>

        <Form
            v-if="!showRecoveryInput"
            v-bind="store.form()"
            class="space-y-6"
            reset-on-error
            @error="code = ''"
            #default="{ errors, processing, clearErrors }"
        >
            <input type="hidden" name="code" :value="code" />

            <div class="flex flex-col items-center gap-4">
                <InputOTP
                    id="otp"
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

            <Button
                type="submit"
                class="w-full"
                :disabled="processing || code.length < 6"
            >
                <Spinner v-if="processing" />
                {{ $t('Doorgaan') }}
            </Button>

            <button ref="autoSubmit" type="submit" class="hidden" tabindex="-1">
                {{ $t('Doorgaan') }}
            </button>

            <p class="text-center text-sm text-muted-foreground">
                {{ $t('Authenticator niet bij de hand? Je kunt ook') }}
                <button
                    type="button"
                    class="text-brand-cyan underline-offset-4 hover:underline"
                    @click="() => toggleRecoveryMode(clearErrors)"
                >
                    {{ authConfigContent.buttonText }}</button
                >.
            </p>
        </Form>

        <Form
            v-else
            v-bind="store.form()"
            class="space-y-6"
            reset-on-error
            #default="{ errors, processing, clearErrors }"
        >
            <div class="grid gap-2">
                <Input
                    name="recovery_code"
                    type="text"
                    :placeholder="$t('Bijvoorbeeld: abcdefghij-klmnopqrst')"
                    autocomplete="one-time-code"
                    v-focus
                    required
                />
                <InputError :message="errors.recovery_code" />
                <p class="text-sm text-muted-foreground">
                    {{
                        $t(
                            'Elke recovery code werkt één keer. Maak er nieuwe aan zodra je weer binnen bent.',
                        )
                    }}
                </p>
            </div>

            <Button type="submit" class="w-full" :disabled="processing">
                <Spinner v-if="processing" />
                {{ $t('Doorgaan') }}
            </Button>

            <p class="text-center text-sm text-muted-foreground">
                {{ $t('Of') }}
                <button
                    type="button"
                    class="text-brand-cyan underline-offset-4 hover:underline"
                    @click="() => toggleRecoveryMode(clearErrors)"
                >
                    {{ authConfigContent.buttonText }}</button
                >.
            </p>
        </Form>
    </div>
</template>
