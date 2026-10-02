<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { usePasskeyVerify } from '@laravel/passkeys/vue';
import { KeyRound } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';

/**
 * Inloggen of bevestigen met een passkey.
 *
 * Dit component komt uit de Laravel starter kit; de werking zelf laten we
 * met rust. Wat wij eraan hebben gedaan is het Nederlands en de huisstijl.
 *
 * **De drie teksten hadden een Engelse terugval**, en op het inlogscherm
 * werden ze alle drie gebruikt: daar stond "Sign in with a passkey" en "Or
 * continue with email", óók in het Nederlands. Het scherm Even je
 * wachtwoord gaf ze wel mee, dus het viel alleen op de ene plek op waar
 * het het meest opvalt.
 *
 * De vertaaltest ving dit niet: die zoekt naar Nederlandse zinnen die
 * `$t()` omzeilen, en dit was Engels in een stukje JavaScript.
 */
type Props = {
    routes?: {
        options: UrlMethodPair;
        submit: UrlMethodPair;
    };
    label?: string;
    loadingLabel?: string;
    separator?: string;
};

const props = defineProps<Props>();

/*
 * De standaardteksten staan hier en niet in `withDefaults`: die wordt
 * uitgerekend wanneer de module laadt, en dan is er nog geen pagina met
 * een woordenlijst. In een computed worden ze pas bij het tekenen gelezen,
 * en veranderen ze mee als je van taal wisselt.
 */
const knoptekst = computed(() => props.label ?? t('Inloggen met een passkey'));

const bezigtekst = computed(
    () => props.loadingLabel ?? t('Bezig met inloggen…'),
);

const scheidingstekst = computed(
    () => props.separator ?? t('Of log in met je e-mailadres'),
);

const { verify, isLoading, error, isSupported } = usePasskeyVerify({
    ...(props.routes
        ? {
              routes: {
                  options: props.routes.options.url,
                  submit: props.routes.submit.url,
              },
          }
        : {}),
    onSuccess: (response) => {
        router.visit(response.redirect ?? '/dashboard');
    },
});
</script>

<template>
    <div v-if="isSupported">
        <div class="grid gap-2">
            <Button
                type="button"
                variant="outline"
                class="w-full"
                @click="verify"
                :disabled="isLoading"
            >
                <Spinner v-if="isLoading" />
                <KeyRound v-else class="h-4 w-4" />
                {{ isLoading ? bezigtekst : knoptekst }}
            </Button>

            <div v-if="error" class="text-center">
                <InputError :message="error" />
            </div>
        </div>

        <div class="relative my-6">
            <div class="absolute inset-0 flex items-center">
                <Separator class="w-full" />
            </div>
            <div class="relative flex justify-center text-xs uppercase">
                <span class="bg-background px-2 text-muted-foreground">
                    {{ scheidingstekst }}
                </span>
            </div>
        </div>
    </div>
</template>
