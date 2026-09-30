<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ScanLine } from '@lucide/vue';
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue';
import AlertError from '@/components/AlertError.vue';
import CopyButton from '@/components/CopyButton.vue';
import InputError from '@/components/InputError.vue';
import PasteCodeButton from '@/components/PasteCodeButton.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { confirm } from '@/routes/two-factor';
import type { TwoFactorConfigContent } from '@/types';

type Props = {
    requiresConfirmation: boolean;
    twoFactorEnabled: boolean;
};

const props = defineProps<Props>();
const isOpen = defineModel<boolean>('isOpen');

const { qrCodeSvg, manualSetupKey, clearSetupData, fetchSetupData, errors } =
    useTwoFactorAuth();

const showVerificationStep = ref(false);
const code = ref<string>('');

const pinInputContainerRef = useTemplateRef('pinInputContainerRef');

// Zodra het zesde cijfer staat, versturen. Zie ConfirmTwoFactor.vue voor
// waarom dat via een verborgen knop gaat.
const autoSubmit = useTemplateRef<HTMLButtonElement>('autoSubmit');

const modalConfig = computed<TwoFactorConfigContent>(() => {
    if (props.twoFactorEnabled) {
        return {
            title: 'Tweestapsverificatie staat aan',
            description:
                'Wil je een tweede apparaat toevoegen? Scan de QR-code of vul de sleutel handmatig in je authenticator-app in.',
            buttonText: 'Sluiten',
        };
    }

    if (showVerificationStep.value) {
        return {
            title: 'Controleer de code',
            description:
                'Vul de zescijferige code in die je authenticator-app nu toont.',
            buttonText: 'Doorgaan',
        };
    }

    return {
        title: 'Tweestapsverificatie aanzetten',
        description:
            'Scan de QR-code met je authenticator-app, of vul de sleutel handmatig in.',
        buttonText: 'Doorgaan',
    };
});

const handleModalNextStep = () => {
    if (props.requiresConfirmation) {
        showVerificationStep.value = true;

        nextTick(() => {
            pinInputContainerRef.value?.querySelector('input')?.focus();
        });

        return;
    }

    clearSetupData();
    isOpen.value = false;
};

const resetModalState = () => {
    if (props.twoFactorEnabled) {
        clearSetupData();
    }

    showVerificationStep.value = false;
    code.value = '';
};

watch(
    () => isOpen.value,
    async (isOpen) => {
        if (!isOpen) {
            resetModalState();

            return;
        }

        if (!qrCodeSvg.value) {
            await fetchSetupData();
        }
    },
);
</script>

<template>
    <Dialog :open="isOpen" @update:open="isOpen = $event">
        <DialogContent
            class="sm:max-w-md"
            overlay-class="bg-brand-navy/60 backdrop-blur-md"
        >
            <DialogHeader class="flex items-center justify-center">
                <div
                    class="mb-3 w-auto rounded-full border border-border bg-card p-0.5 shadow-sm"
                >
                    <div
                        class="relative overflow-hidden rounded-full border border-border bg-muted p-2.5"
                    >
                        <div
                            class="absolute inset-0 grid grid-cols-5 opacity-50"
                        >
                            <div
                                v-for="i in 5"
                                :key="`col-${i}`"
                                class="border-r border-border last:border-r-0"
                            />
                        </div>
                        <div
                            class="absolute inset-0 grid grid-rows-5 opacity-50"
                        >
                            <div
                                v-for="i in 5"
                                :key="`row-${i}`"
                                class="border-b border-border last:border-b-0"
                            />
                        </div>
                        <ScanLine
                            class="relative z-20 size-6 text-foreground"
                        />
                    </div>
                </div>
                <DialogTitle>{{ modalConfig.title }}</DialogTitle>
                <DialogDescription class="text-center">
                    {{ modalConfig.description }}
                </DialogDescription>
            </DialogHeader>

            <div
                class="relative flex w-auto flex-col items-center justify-center space-y-5"
            >
                <template v-if="!showVerificationStep">
                    <AlertError v-if="errors?.length" :errors="errors" />
                    <template v-else>
                        <!--
                            Altijd op wit, ook in het donkere thema, en
                            nooit met een invert-filter eroverheen. Een
                            omgekeerde QR-code -- lichte modules op donker --
                            is voor veel scanners onleesbaar; de camera-app
                            van een telefoon trekt het meestal nog wel, de
                            scanner in een authenticator-app niet.

                            De witte rand eromheen is geen opmaak maar de
                            stille zone die de QR-standaard voorschrijft. Hij
                            zit ook al in de SVG zelf (zie User::twoFactorQrCodeSvg);
                            dit is de tweede laag, voor het geval iemand het
                            formaat ooit aanpast.
                        -->
                        <div
                            class="relative mx-auto flex aspect-square w-60 items-center justify-center overflow-hidden rounded-xl bg-white p-4"
                        >
                            <Spinner
                                v-if="!qrCodeSvg"
                                class="size-6 text-brand-navy"
                            />
                            <div
                                v-else
                                v-html="qrCodeSvg"
                                class="flex size-full items-center justify-center [&>svg]:size-full"
                            />
                        </div>

                        <div class="flex w-full items-center space-x-5">
                            <Button class="w-full" @click="handleModalNextStep">
                                {{ modalConfig.buttonText }}
                            </Button>
                        </div>

                        <div
                            class="relative flex w-full items-center justify-center"
                        >
                            <div
                                class="absolute inset-0 top-1/2 h-px w-full bg-border"
                            />
                            <span
                                class="relative bg-card px-2 py-1 text-sm text-muted-foreground"
                                >{{
                                    $t('of vul de sleutel handmatig in')
                                }}</span
                            >
                        </div>

                        <div
                            class="flex w-full items-center justify-center space-x-2"
                        >
                            <div
                                class="flex w-full items-stretch overflow-hidden rounded-xl border border-border"
                            >
                                <div
                                    v-if="!manualSetupKey"
                                    class="flex h-full w-full items-center justify-center bg-muted p-3"
                                >
                                    <Spinner />
                                </div>
                                <template v-else>
                                    <input
                                        type="text"
                                        readonly
                                        :value="manualSetupKey"
                                        class="h-full w-full bg-background p-3 text-foreground"
                                    />
                                    <CopyButton
                                        :value="manualSetupKey || ''"
                                        label="Kopieer de sleutel"
                                        class="h-auto rounded-none border-0 border-l border-border px-3"
                                    />
                                </template>
                            </div>
                        </div>
                    </template>
                </template>

                <template v-else>
                    <Form
                        v-bind="confirm.form()"
                        error-bag="confirmTwoFactorAuthentication"
                        reset-on-error
                        @finish="code = ''"
                        @success="isOpen = false"
                        v-slot="{ errors, processing }"
                    >
                        <input type="hidden" name="code" :value="code" />
                        <div
                            ref="pinInputContainerRef"
                            class="relative w-full space-y-3"
                        >
                            <div
                                class="flex w-full flex-col items-center justify-center space-y-3 py-2"
                            >
                                <InputOTP
                                    id="otp"
                                    v-model="code"
                                    :maxlength="6"
                                    :disabled="processing"
                                    autofocus
                                    @complete="autoSubmit?.click()"
                                >
                                    <InputOTPGroup>
                                        <InputOTPSlot
                                            v-for="index in 6"
                                            :key="index"
                                            :index="index - 1"
                                        />
                                    </InputOTPGroup>
                                </InputOTP>
                                <InputError :message="errors?.code" />

                                <PasteCodeButton
                                    @pasted="
                                        (plakcode) => {
                                            code = plakcode;
                                            autoSubmit?.click();
                                        }
                                    "
                                />
                            </div>

                            <div class="flex w-full items-center space-x-5">
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="w-auto flex-1"
                                    @click="showVerificationStep = false"
                                    :disabled="processing"
                                >
                                    {{ $t('Terug') }}
                                </Button>
                                <Button
                                    type="submit"
                                    class="w-auto flex-1"
                                    :disabled="processing || code.length < 6"
                                >
                                    {{ $t('Bevestigen') }}
                                </Button>
                            </div>

                            <button
                                ref="autoSubmit"
                                type="submit"
                                class="hidden"
                                tabindex="-1"
                            >
                                {{ $t('Bevestigen') }}
                            </button>
                        </div>
                    </Form>
                </template>
            </div>
        </DialogContent>
    </Dialog>
</template>
