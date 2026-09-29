<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { edit as profiel } from '@/routes/profile';
import { store } from '@/routes/password/confirm';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';

/**
 * De titel en de omschrijving staan hier als Nederlandse zin en niet in een
 * `t()`: `defineOptions` draait in de moduleruimte, voordat Inertia een
 * pagina heeft. AuthSimpleLayout vertaalt ze bij het tekenen. Zie
 * docs/architecture/vertalingen.md.
 */
defineOptions({
    layout: {
        // Portaalvariant: je bent al ingelogd en aan het werk, en dit scherm
        // onderbreekt je. Het volgt dus de themavoorkeur in plaats van
        // altijd donker te staan.
        variant: 'portaal',
        title: 'Even je wachtwoord',
        description:
            'Hierachter zit iets dat je niet zomaar wilt wijzigen. Bevestig dat jij het bent.',
    },
});
</script>

<template>
    <Head :title="$t('Even je wachtwoord')" />

    <PasskeyVerify
        :routes="{
            options: confirmOptions(),
            submit: confirmStore(),
        }"
        :label="$t('Bevestig met een passkey')"
        :loading-label="$t('Bezig met bevestigen…')"
        :separator="$t('Of bevestig met je wachtwoord')"
    />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
    >
        <div class="space-y-6">
            <div class="grid gap-2">
                <Label htmlFor="password" verplicht>{{
                    $t('Wachtwoord')
                }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="current-password"
                    autofocus
                />

                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="confirm-password-button"
                >
                    <Spinner v-if="processing" />
                    {{ $t('Bevestigen') }}
                </Button>
            </div>

            <!--
                Een weg terug. Zonder deze link is dit scherm een doodlopende
                straat: je komt hier ongevraagd terecht doordat je op een
                menu-item klikte, en dan is "ik wilde dit toch niet" een
                normale gedachte. Uitloggen als enige uitweg is buiten
                verhouding.

                Naar de instellingen en niet naar het dashboard: dit scherm
                staat op dit moment alleen voor de beveiligingsinstellingen,
                dus dáár kwam je vandaan. Komt er ooit een tweede pagina
                achter een wachtwoordbevestiging, dan moet deze bestemming
                mee veranderen.

                En niet de terugknop van de browser: die brengt je terug op
                de pagina die je juist niet mag zien, waarna je hier meteen
                weer staat.
            -->
            <div class="text-center">
                <Link
                    :href="profiel()"
                    class="text-xs text-muted-foreground underline-offset-4 transition-colors hover:text-brand-cyan hover:underline"
                >
                    {{ $t('Toch niet, terug naar mijn instellingen') }}
                </Link>
            </div>
        </div>
    </Form>
</template>
