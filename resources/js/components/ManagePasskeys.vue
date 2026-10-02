<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { KeyRound } from '@lucide/vue';
import type { Passkey } from '@/types/auth';
import Heading from '@/components/Heading.vue';
import PasskeyItem from '@/components/PasskeyItem.vue';
import PasskeyRegister from '@/components/PasskeyRegister.vue';
import { destroy } from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyRegistrationController';

export type Props = {
    canManagePasskeys?: boolean;
    passkeys?: Passkey[];
};

withDefaults(defineProps<Props>(), {
    canManagePasskeys: false,
    passkeys: () => [],
});

const handleDelete = (id: number, onError: () => void) => {
    router.delete(destroy.url(id), {
        preserveScroll: true,
        onError,
    });
};

const handleRegisterSuccess = () => {
    router.reload();
};
</script>

<template>
    <div v-if="canManagePasskeys" class="space-y-6">
        <Heading
            variant="small"
            :title="$t('Passkeys')"
            :description="
                $t('Inloggen zonder wachtwoord, met je vinger of je gezicht.')
            "
        />

        <div class="overflow-hidden rounded-lg border border-border">
            <template v-if="passkeys.length">
                <PasskeyItem
                    v-for="passkey in passkeys"
                    :key="passkey.id"
                    :passkey="passkey"
                    @remove="handleDelete"
                />
            </template>

            <!--
                Nog geen passkeys. Het tekentje staat in de accentkleur en
                niet in het grijs van de starter: dit is geen melding dat
                er iets mis is, het is een plek die nog gevuld kan worden.
            -->
            <div v-else class="p-8 text-center">
                <div
                    class="brand-passkeyleeg mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl"
                >
                    <KeyRound class="size-7" />
                </div>
                <p class="font-medium">{{ $t('Nog geen passkeys') }}</p>
                <p
                    class="mx-auto mt-1 max-w-sm text-sm text-pretty text-muted-foreground"
                >
                    {{
                        $t(
                            'Zet er een op dit apparaat en je logt voortaan in zonder wachtwoord.',
                        )
                    }}
                </p>
            </div>
        </div>

        <PasskeyRegister @success="handleRegisterSuccess" />
    </div>
</template>
