<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import PasteCodeButton from '@/components/PasteCodeButton.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import { passkeyStap } from '@/routes/security';

/**
 * De extra stap na een passkey omzetten, met de code erbij.
 *
 * **Het codeveld staat hier en niet op het aparte codescherm**, en dat is
 * een reparatie van hoe het eerst werkte. De middleware `2fa.confirm` kan
 * een PUT niet onthouden: je werd naar dat scherm gestuurd, je code klopte,
 * en je kwam terug op een schuifje dat was teruggesprongen -- omdat je
 * verzoek onderweg was weggegooid. Dan moest je het nog een keer omzetten.
 *
 * Nu is het één handeling. De server controleert de code met dezelfde
 * klasse als dat scherm; zie App\Support\Security\Authenticator.
 *
 * **De waarschuwing staat bij uitzetten en niet bij aanzetten.** Uitzetten
 * haalt een slot weg; dat is de kant waar iets te verliezen valt.
 *
 * Zie docs/security/extra-stap-na-een-passkey.md.
 */
const props = defineProps<{
    /** Wat de eigenaar wil: aan of uit. */
    gewenst: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const code = ref('');
const bezig = ref(false);
const fouten = ref<Record<string, string>>({});

watch(open, (isOpen) => {
    if (!isOpen) {
        code.value = '';
        fouten.value = {};
    }
});

const titel = computed(() =>
    props.gewenst ? 'De extra stap aanzetten?' : 'De extra stap uitzetten?',
);

const uitleg = computed(() =>
    props.gewenst
        ? 'Na elke passkey-login vraagt het portaal dan ook je authenticator-code. Heb je je telefoon niet bij de hand, dan kom je er met je passkey alleen niet meer in -- je recovery codes werken dan wel.'
        : 'Je passkey is daarna in zijn eentje genoeg om binnen te komen. Wie je telefoon of laptop ontgrendeld in handen krijgt, is dan in je portaal.',
);

const versturen = (): void => {
    if (code.value.length < 6 || bezig.value) {
        return;
    }

    bezig.value = true;
    fouten.value = {};

    router.put(
        passkeyStap().url,
        { aan: props.gewenst, code: code.value },
        {
            preserveScroll: true,
            onError: (ontvangen: Record<string, string>) => {
                fouten.value = ontvangen;
                code.value = '';
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

const opGeplakt = (plakcode: string): void => {
    code.value = plakcode;
    versturen();
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ $t(titel) }}</DialogTitle>
                <DialogDescription>{{ $t(uitleg) }}</DialogDescription>
            </DialogHeader>

            <div
                v-if="!props.gewenst"
                class="flex items-start gap-3 rounded-lg border border-border bg-muted/40 p-4 text-sm"
            >
                <TriangleAlert
                    class="mt-0.5 size-4 shrink-0 text-warning"
                    aria-hidden="true"
                />
                <p class="text-pretty text-muted-foreground">
                    {{
                        $t(
                            'Je haalt hiermee een slot weg. Weet je het zeker, vul dan je code in.',
                        )
                    }}
                </p>
            </div>

            <form class="grid gap-3" @submit.prevent="versturen">
                <Label for="passkeystap-code" verplicht>
                    {{ $t('De code uit je authenticator') }}
                </Label>

                <div class="flex flex-col items-center gap-4">
                    <InputOTP
                        id="passkeystap-code"
                        v-model="code"
                        :maxlength="6"
                        :disabled="bezig"
                        autofocus
                        @complete="versturen"
                    >
                        <InputOTPGroup>
                            <InputOTPSlot
                                v-for="index in 6"
                                :key="index"
                                :index="index - 1"
                            />
                        </InputOTPGroup>
                    </InputOTP>

                    <InputError :message="fouten.code ?? fouten.aan" />

                    <PasteCodeButton @pasted="opGeplakt" />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="secondary"
                        @click="open = false"
                    >
                        {{ $t('Annuleren') }}
                    </Button>
                    <Button
                        type="submit"
                        :variant="props.gewenst ? 'bewerken' : 'verwijderen'"
                        :disabled="bezig || code.length < 6"
                    >
                        {{
                            bezig
                                ? $t('Bezig...')
                                : props.gewenst
                                  ? $t('Ja, aanzetten')
                                  : $t('Ja, uitzetten')
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
