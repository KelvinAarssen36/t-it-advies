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

/*
 * **Een label en geen opdracht.** Hier stond "Of log in met je
 * e-mailadres", en op het scherm Even je wachtwoord "Of bevestig met je
 * wachtwoord". Dat laatste viel op: de knop erboven zegt "Bevestig met een
 * passkey", de knop eronder "Bevestigen", en daartussen stond een derde
 * keer hetzelfde werkwoord -- in hoofdletters, want deze regel wordt
 * `uppercase` getekend. Een scheidingsregel hoort te zeggen wát het andere
 * is, niet wat je moet doen; het werkwoord staat al op de knop.
 */
const scheidingstekst = computed(
    () => props.separator ?? t('Of met je e-mailadres'),
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

        <!--
            **Twee streepjes met de tekst ertussen, en geen tekst óver een
            streep heen.**

            Hier lag één streep over de volle breedte met de tekst erop, en
            die tekst had een eigen achtergrond om een gat in de streep te
            maken. Dat gat was `bg-background` -- de kleur van de pagina --
            terwijl dit blok op een kaart staat met `bg-card`. Twee
            verschillende kleuren, dus je zag geen gat maar een lichter
            vlakje met harde randen om de tekst.

            Het is daar ook niet op te lossen door de goede kleur te
            kiezen: bij de merkvariant is de kaart `bg-card/70` met een
            waas en een gradient erachter, dus er ís geen dekkende kleur
            die klopt.

            Twee streepjes die de ruimte verdelen hebben geen achtergrond
            nodig en weten dus niets over het vlak waar ze op staan. Het
            streepje zit in een `flex-1`-omhulsel omdat `Separator` zelf
            `shrink-0` en `w-full` meebrengt.
        -->
        <div class="my-6 flex items-center gap-3">
            <div class="flex-1"><Separator /></div>
            <span
                class="text-xs whitespace-nowrap text-muted-foreground uppercase"
            >
                {{ scheidingstekst }}
            </span>
            <div class="flex-1"><Separator /></div>
        </div>
    </div>
</template>
