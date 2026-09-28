<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { CheckCircle2, QrCode, ShieldCheck } from '@lucide/vue';
import { onMounted, onUnmounted, ref, watchEffect } from 'vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { logout } from '@/routes';
import { finish } from '@/routes/security/two-factor/setup';
import { enable } from '@/routes/two-factor';

/**
 * De verplichte eerste stap: tweestapsverificatie instellen.
 *
 * Hier kom je vanzelf terecht zolang `two_factor_confirmed_at` leeg is en
 * de eis aanstaat. Deze pagina staat buiten die middleware, anders zou je
 * worden weggestuurd van de pagina waar je naartoe wordt gestuurd.
 *
 * Zie docs/security/authenticatie-en-2fa.md.
 */

const props = defineProps<{
    requiresConfirmation: boolean;
    twoFactorEnabled: boolean;
    twoFactorConfirmed: boolean;
    /** Wachtwoord zojuist bevestigd? Dan zijn we een stap verder. */
    passwordConfirmed: boolean;
}>();

defineOptions({
    layout: {
        title: 'Beveilig je account',
        description:
            'Voordat je verder kunt, zetten we tweestapsverificatie aan.',
    },
});

// Ook de kop verandert mee, anders leest stap 2 als dezelfde pagina.
watchEffect(() => {
    if (props.twoFactorConfirmed) {
        setLayoutProps({
            title: 'Gelukt',
            description: 'Je account is nu met twee stappen beveiligd.',
        });

        return;
    }

    if (props.passwordConfirmed) {
        setLayoutProps({
            title: 'Koppel je authenticator',
            description: 'Nog één stap: scannen en één code invullen.',
        });
    }
});

const apps = [
    'Google Authenticator',
    'Microsoft Authenticator',
    '1Password',
    'Bitwarden',
];

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref(false);

// Ben je hier eerder geweest en halverwege gestopt, dan staat het geheim er
// al. Dan hoeft er niets opnieuw aangezet te worden en kan het venster
// meteen open -- anders zit je naar een knop te kijken die het werk
// overdoet dat al gedaan is.
onMounted(() => {
    if (!props.twoFactorConfirmed && props.twoFactorEnabled) {
        showSetupModal.value = true;
    }
});

onUnmounted(() => clearTwoFactorAuthData());
</script>

<template>
    <Head title="Beveilig je account" />

    <!--
        Stap 2. Je komt hier terug nadat Fortify je wachtwoord heeft
        gevraagd; die onderschept de aanzetten-knop en voert hem daarna niet
        opnieuw uit. Zonder een eigen scherm zou je terugkomen op precies
        dezelfde pagina en lijkt het alsof er niets is gebeurd.
    -->
    <div v-if="!twoFactorConfirmed && passwordConfirmed" class="space-y-6">
        <div class="space-y-3">
            <div
                class="flex gap-3 rounded-lg border border-success/40 bg-success/10 p-3 text-sm"
            >
                <CheckCircle2 class="mt-0.5 size-5 shrink-0 text-success" />
                <p>Je wachtwoord is bevestigd.</p>
            </div>

            <div class="flex gap-3 text-sm text-muted-foreground">
                <QrCode class="mt-0.5 size-5 shrink-0 text-brand-cyan" />
                <p>
                    Nu koppelen we je authenticator-app. Je krijgt een QR-code
                    te zien die je scant, en daarna vul je één keer de code uit
                    de app in.
                </p>
            </div>
        </div>

        <Button
            v-if="hasSetupData || twoFactorEnabled"
            class="w-full"
            @click="showSetupModal = true"
        >
            <QrCode />
            Verder met instellen
        </Button>

        <Form
            v-else
            v-bind="enable.form()"
            @success="showSetupModal = true"
            #default="{ processing }"
        >
            <Button type="submit" class="w-full" :disabled="processing">
                <Spinner v-if="processing" />
                <QrCode v-else />
                Verder met instellen
            </Button>
        </Form>

        <p class="text-center text-xs text-muted-foreground">
            Houd je telefoon bij de hand.
        </p>
    </div>

    <div v-else-if="!twoFactorConfirmed" class="space-y-6">
        <div class="space-y-3">
            <p class="text-sm text-muted-foreground">
                Je hebt hier een authenticator-app voor nodig. Bij elke volgende
                keer inloggen vul je daaruit een zescijferige code in.
            </p>

            <!--
                De apps als losse labels en niet als opsomming in een zin:
                het is een keuzelijstje, en dat lees je scannend in plaats
                van van voor naar achter.
            -->
            <ul class="flex flex-wrap gap-2">
                <li
                    v-for="app in apps"
                    :key="app"
                    class="rounded-full border border-border bg-muted/40 px-3 py-1 text-xs text-foreground"
                >
                    {{ app }}
                </li>
            </ul>

            <p class="text-xs text-muted-foreground">
                Heb je er al een op je telefoon? Dan kun je die gewoon
                gebruiken.
            </p>
        </div>

        <Button
            v-if="hasSetupData"
            class="w-full"
            @click="showSetupModal = true"
        >
            <ShieldCheck />
            Verder met instellen
        </Button>

        <Form
            v-else
            v-bind="enable.form()"
            @success="showSetupModal = true"
            #default="{ processing }"
        >
            <Button type="submit" class="w-full" :disabled="processing">
                <Spinner v-if="processing" />
                <ShieldCheck v-else />
                Tweestapsverificatie aanzetten
            </Button>
        </Form>

        <div class="space-y-2 text-center">
            <p class="text-xs text-muted-foreground">
                Dit is eenmalig. Zonder tweestapsverificatie kun je het portaal
                niet gebruiken.
            </p>

            <!--
                Een weg terug voor wie zijn telefoon niet bij de hand heeft.
                Dat is uitloggen en niet "overslaan": overslaan bestaat niet,
                maar vastzitten op een scherm zonder uitweg is erger dan
                opnieuw moeten inloggen.
            -->
            <Link
                :href="logout()"
                method="post"
                as="button"
                type="button"
                class="text-xs text-muted-foreground underline-offset-4 transition-colors hover:text-brand-cyan hover:underline"
            >
                Nu even niet, terug naar het inlogscherm
            </Link>
        </div>
    </div>

    <div v-else class="space-y-6">
        <div
            class="flex gap-3 rounded-lg border border-success/40 bg-success/10 p-3 text-sm"
        >
            <CheckCircle2 class="mt-0.5 size-5 shrink-0 text-success" />
            <p>
                Tweestapsverificatie staat aan. Vanaf nu vraagt het portaal bij
                elke keer inloggen om een code uit je app.
            </p>
        </div>

        <!--
            De codes staan hier open en zonder knop om nieuwe te maken: dit
            is het enige moment waarop de eigenaar er zeker langskomt, en hij
            heeft ze zojuist gekregen. Nieuwe codes maken hoort in de
            instellingen thuis, niet hier.
        -->
        <TwoFactorRecoveryCodes always-visible :allow-regenerate="false" />

        <Button as-child class="w-full">
            <Link :href="finish()">Doorgaan naar het portaal</Link>
        </Button>
    </div>

    <TwoFactorSetupModal
        v-model:isOpen="showSetupModal"
        :requiresConfirmation="requiresConfirmation"
        :twoFactorEnabled="twoFactorEnabled"
    />
</template>
