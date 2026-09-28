<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Eye, EyeOff, LockKeyhole, RefreshCw } from '@lucide/vue';
import { computed, nextTick, onMounted, ref, useTemplateRef } from 'vue';
import AlertError from '@/components/AlertError.vue';
import CopyButton from '@/components/CopyButton.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { regenerateRecoveryCodes } from '@/routes/two-factor';

/**
 * De recovery codes.
 *
 * Twee standen, want dit component staat op twee heel verschillende
 * plekken. Bij het verplicht instellen zijn de codes het hele punt: dan
 * staan ze meteen open en hoort er geen knop bij die nieuwe codes maakt --
 * je hebt ze net gekregen. In de instellingen zijn ze een naslagwerk dat je
 * af en toe opzoekt, en daar horen ze juist verborgen te beginnen.
 */
const props = withDefaults(
    defineProps<{
        /** Codes meteen tonen in plaats van achter een knop. */
        alwaysVisible?: boolean;
        /** De knop om nieuwe codes aan te maken tonen. */
        allowRegenerate?: boolean;
    }>(),
    { alwaysVisible: false, allowRegenerate: true },
);

const { recoveryCodesList, fetchRecoveryCodes, errors } = useTwoFactorAuth();

const isRecoveryCodesVisible = ref(props.alwaysVisible);
const recoveryCodeSectionRef = useTemplateRef('recoveryCodeSectionRef');

const allCodes = computed(() => recoveryCodesList.value.join('\n'));

const toggleRecoveryCodesVisibility = async () => {
    if (!isRecoveryCodesVisible.value && !recoveryCodesList.value.length) {
        await fetchRecoveryCodes();
    }

    isRecoveryCodesVisible.value = !isRecoveryCodesVisible.value;

    if (isRecoveryCodesVisible.value) {
        await nextTick();
        recoveryCodeSectionRef.value?.scrollIntoView({ behavior: 'smooth' });
    }
};

onMounted(async () => {
    if (!recoveryCodesList.value.length) {
        await fetchRecoveryCodes();
    }
});
</script>

<template>
    <Card class="w-full">
        <CardHeader>
            <CardTitle class="flex items-center gap-2 text-base">
                <LockKeyhole class="size-4 text-brand-cyan" />
                Recovery codes
            </CardTitle>
            <CardDescription>
                Met een recovery code kom je weer binnen als je je telefoon
                kwijt bent. Bewaar ze in een wachtwoordmanager, niet in je
                mailbox.
            </CardDescription>
        </CardHeader>

        <CardContent class="space-y-3">
            <div class="flex flex-wrap items-center gap-2 select-none">
                <Button
                    v-if="!alwaysVisible"
                    variant="outline"
                    size="sm"
                    @click="toggleRecoveryCodesVisibility"
                >
                    <component :is="isRecoveryCodesVisible ? EyeOff : Eye" />
                    {{ isRecoveryCodesVisible ? 'Verberg' : 'Toon' }} codes
                </Button>

                <CopyButton
                    v-if="isRecoveryCodesVisible && recoveryCodesList.length"
                    :value="allCodes"
                    label="Kopieer alle codes"
                    show-label
                    :reset-after="1500"
                />

                <Form
                    v-if="
                        allowRegenerate &&
                        isRecoveryCodesVisible &&
                        recoveryCodesList.length
                    "
                    v-bind="regenerateRecoveryCodes.form()"
                    method="post"
                    :options="{ preserveScroll: true }"
                    @success="fetchRecoveryCodes"
                    #default="{ processing }"
                >
                    <Button
                        variant="outline"
                        size="sm"
                        type="submit"
                        :disabled="processing"
                    >
                        <RefreshCw /> Nieuwe codes
                    </Button>
                </Form>
            </div>

            <div
                :class="[
                    'relative overflow-hidden transition-all duration-300',
                    isRecoveryCodesVisible
                        ? 'h-auto opacity-100'
                        : 'h-0 opacity-0',
                ]"
            >
                <AlertError v-if="errors?.length" :errors="errors" />

                <div v-else class="space-y-3">
                    <div
                        ref="recoveryCodeSectionRef"
                        class="grid gap-1 rounded-lg border border-border bg-muted/50 p-4 font-mono text-sm"
                    >
                        <div v-if="!recoveryCodesList.length" class="space-y-2">
                            <div
                                v-for="n in 8"
                                :key="n"
                                class="h-4 animate-pulse rounded bg-muted-foreground/20"
                            />
                        </div>
                        <div
                            v-for="(code, index) in recoveryCodesList"
                            v-else
                            :key="index"
                        >
                            {{ code }}
                        </div>
                    </div>

                    <p class="text-xs text-muted-foreground select-none">
                        Elke code werkt één keer en verdwijnt daarna.
                    </p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
