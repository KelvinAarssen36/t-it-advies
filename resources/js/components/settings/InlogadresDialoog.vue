<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import inlogadres from '@/routes/inlogadres';

/**
 * Een ander inlogadres aanvragen.
 *
 * **Dit venster is het tweede en derde slot, niet het eerste.** De knop
 * die het opent gaat langs `inlogadres.create`, en die route staat achter
 * `2fa.confirm`. Wie hier kijkt heeft zijn authenticator dus al gehad; hier
 * komen het nieuwe adres en het huidige wachtwoord bij.
 *
 * **Er zit geen "weet je het zeker" achter de knop**, anders dan bij de
 * vensters van de website. Dat is geen vergeetachtigheid: de eigenaar heeft
 * net een code uit zijn telefoon overgetypt en zijn wachtwoord ingevuld.
 * Een vierde bevestiging voegt daar niets aan toe behalve ruis -- en de
 * aanvraag zelf verandert nog niets, want zijn huidige adres blijft werken
 * tot de link in het nieuwe postvak is geopend.
 *
 * Zie docs/security/inlogadres-wijzigen.md.
 */
const props = defineProps<{
    huidigAdres: string;
}>();

const open = defineModel<boolean>('open', { required: true });

const bezig = ref(false);
const fouten = ref<Record<string, string>>({});

const formulier = ref({
    email: '',
    password: '',
});

watch(open, (isOpen) => {
    if (!isOpen) {
        /*
         * Het wachtwoord mag niet in het geheugen van dit scherm blijven
         * staan voor de volgende keer dat het venster opengaat.
         */
        formulier.value = { email: '', password: '' };
        fouten.value = {};
    }
});

const aanvragen = (): void => {
    bezig.value = true;
    fouten.value = {};

    router.post(
        inlogadres.store().url,
        { ...formulier.value },
        {
            preserveScroll: true,
            onError: (ontvangen: Record<string, string>) => {
                fouten.value = ontvangen;
                formulier.value.password = '';
            },
            onSuccess: () => {
                open.value = false;
            },
            onFinish: () => {
                bezig.value = false;
            },
        },
    );
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ $t('Een ander inlogadres') }}</DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Je logt nu in met :adres. Dat blijft zo tot je de bevestiging in je nieuwe postvak hebt geopend.',
                            { adres: props.huidigAdres },
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-5" @submit.prevent="aanvragen">
                <div class="grid gap-2">
                    <Label for="inlogadres-email" verplicht>
                        {{ $t('Nieuw inlogadres') }}
                    </Label>
                    <Input
                        id="inlogadres-email"
                        v-model="formulier.email"
                        type="email"
                        autocomplete="off"
                        inputmode="email"
                        :placeholder="$t('naam@voorbeeld.nl')"
                    />
                    <p class="text-sm text-pretty text-muted-foreground">
                        {{
                            $t(
                                'Hier gaat de bevestiging naartoe. Zorg dat je bij dit postvak kunt.',
                            )
                        }}
                    </p>
                    <InputError :message="fouten.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="inlogadres-wachtwoord" verplicht>
                        {{ $t('Je huidige wachtwoord') }}
                    </Label>
                    <PasswordInput
                        id="inlogadres-wachtwoord"
                        v-model="formulier.password"
                        autocomplete="current-password"
                        :placeholder="$t('Je huidige wachtwoord')"
                    />
                    <InputError :message="fouten.password" />
                </div>

                <!--
                    Wat er hierna gebeurt, in de volgorde waarin het gebeurt.
                    Deze drie regels staan er omdat de eigenaar anders niet
                    kan weten dat er een mail naar zijn óude adres gaat, en
                    dat die mail bewaard moet blijven.
                -->
                <ol
                    class="grid gap-2 rounded-lg border border-border p-4 text-sm text-muted-foreground"
                >
                    <li>
                        {{
                            $t(
                                '1. Je nieuwe adres krijgt een bevestigingslink. Die is een uur geldig.',
                            )
                        }}
                    </li>
                    <li>
                        {{
                            $t(
                                '2. Je huidige adres krijgt een waarschuwing met een knop om dit terug te draaien. Bewaar die mail -- hij werkt twee weken en is je weg terug.',
                            )
                        }}
                    </li>
                    <li>
                        {{
                            $t(
                                '3. Pas als je de bevestiging opent, verandert je inlogadres.',
                            )
                        }}
                    </li>
                </ol>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="secondary"
                        @click="open = false"
                    >
                        {{ $t('Annuleren') }}
                    </Button>
                    <Button type="submit" variant="bewerken" :disabled="bezig">
                        {{
                            bezig ? $t('Bezig...') : $t('Stuur de bevestiging')
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
