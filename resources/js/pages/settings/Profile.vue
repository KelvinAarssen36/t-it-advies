<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { AtSign, ShieldCheck } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import LocaleSelect from '@/components/LocaleSelect.vue';
import InlogadresDialoog from '@/components/settings/InlogadresDialoog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import inlogadres from '@/routes/inlogadres';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

/**
 * Het profiel: de naam, het inlogadres en de taal.
 *
 * **Het e-mailadres is hier geen gewoon invoerveld meer.** Het stond
 * tussen de velden, en daarmee was het inlogadres van het portaal één
 * toetsaanslag van een adres dat niet bestaat: opslaan, uitloggen, en er
 * is niemand meer die binnenkomt. Nu staat het er als tekst, met een knop
 * ernaast die langs de authenticator gaat.
 *
 * Zie docs/security/inlogadres-wijzigen.md.
 */
type OpenstaandeWijziging = {
    naar: string;
    aangevraagd: string;
    verloopt: string;
};

const props = defineProps<{
    mustVerifyEmail: boolean;
    status: string | null;
    openInlogadresVenster: boolean;
    openstaandeWijziging: OpenstaandeWijziging | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profiel',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const vensterOpen = ref(props.openInlogadresVenster);

/*
 * De server zet deze vlag alleen op het verzoek dat meteen volgt op
 * `inlogadres.create` -- dus nadat de authenticator is gelukt. Een
 * `watch` en niet alleen de beginwaarde: Inertia hergebruikt dit
 * component, dus bij terugkomst op dit scherm is het niet opnieuw
 * opgebouwd.
 */
watch(
    () => props.openInlogadresVenster,
    (moetOpen) => {
        if (moetOpen) {
            vensterOpen.value = true;
        }
    },
);

const taal = computed(() =>
    (page.props.locale as string | undefined) === 'en' ? 'en-GB' : 'nl-NL',
);

/** Tot wanneer de bevestigingslink het nog doet, zoals de eigenaar het leest. */
const geldigTot = computed(() => {
    const wijziging = props.openstaandeWijziging;

    if (wijziging === null) {
        return '';
    }

    return new Intl.DateTimeFormat(taal.value, {
        day: 'numeric',
        month: 'long',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(wijziging.verloopt));
});
</script>

<template>
    <Head :title="$t('Profiel')" />

    <h1 class="sr-only">{{ $t('Profiel') }}</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="$t('Profiel')"
            :description="$t('Hoe je in het portaal heet')"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name" verplicht>{{ $t('Naam') }}</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    :placeholder="$t('Volledige naam')"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    variant="bewerken"
                    :disabled="processing"
                    data-test="update-profile-button"
                >
                    {{ $t('Opslaan') }}
                </Button>
            </div>
        </Form>
    </div>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="$t('Inlogadres')"
            :description="$t('Het adres waarmee je het portaal binnenkomt')"
        />

        <!--
            Het adres als tekst in een vak, en niet in een invoerveld. Een
            invoerveld nodigt uit om erin te typen; dit is het enige adres
            waarmee dit portaal te openen is.
        -->
        <div class="overflow-hidden rounded-lg border border-border">
            <div
                class="flex flex-wrap items-center justify-between gap-4 p-4 sm:p-5"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-border text-muted-foreground"
                        aria-hidden="true"
                    >
                        <AtSign class="size-5" />
                    </span>

                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ user.email }}</p>
                        <p
                            v-if="user.email_verified_at"
                            class="mt-0.5 flex items-center gap-1.5 text-sm text-muted-foreground"
                        >
                            <ShieldCheck class="size-3.5 shrink-0" />
                            {{ $t('Bevestigd') }}
                        </p>
                    </div>
                </div>

                <Button variant="bewerken" as-child>
                    <Link :href="inlogadres.create()">
                        {{ $t('Wijzigen') }}
                    </Link>
                </Button>
            </div>

            <!--
                Een aanvraag die nog loopt. Zonder dit vak lijkt er niets
                te gebeuren: het adres hierboven staat immers nog op het
                oude, en dat is precies de bedoeling.
            -->
            <div
                v-if="props.openstaandeWijziging"
                class="border-t border-border bg-muted/40 p-4 text-sm sm:p-5"
            >
                <p class="font-medium">
                    {{
                        $t('Er wacht een wijziging naar :adres.', {
                            adres: props.openstaandeWijziging.naar,
                        })
                    }}
                </p>
                <p class="mt-1 text-pretty text-muted-foreground">
                    {{
                        $t(
                            'Open de bevestigingslink in dat postvak vóór :tijd. Tot die tijd log je gewoon in met het adres hierboven.',
                            { tijd: geldigTot },
                        )
                    }}
                </p>
            </div>
        </div>

        <div v-if="props.mustVerifyEmail && !user.email_verified_at">
            <p class="text-sm text-muted-foreground">
                {{ $t('Je e-mailadres is nog niet geverifieerd.') }}
                <Link
                    :href="send()"
                    as="button"
                    class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                >
                    {{ $t('Stuur de verificatiemail opnieuw.') }}
                </Link>
            </p>

            <div
                v-if="props.status === 'verification-link-sent'"
                class="mt-2 text-sm font-medium text-green-600"
            >
                {{
                    $t(
                        'Er is een nieuwe verificatielink naar je e-mailadres gestuurd.',
                    )
                }}
            </div>
        </div>

        <InlogadresDialoog
            v-model:open="vensterOpen"
            :huidig-adres="user.email"
        />
    </div>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="$t('Taal')"
            :description="$t('In welke taal het portaal met je praat')"
        />

        <LocaleSelect />

        <p class="text-sm text-muted-foreground">
            {{
                $t(
                    'Deze keuze geldt voor het portaal én voor de website, en blijft bewaard bij je account.',
                )
            }}
        </p>
    </div>
</template>
