<script setup lang="ts">
import { KeyRound, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { bevestigVerwijderen } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import type { Passkey } from '@/types/auth';

/**
 * Eén passkey in de lijst op het scherm Beveiliging.
 *
 * **Het verwijderen gaat via het gedeelde bevestigingsvenster**
 * (`lib/bevestiging`), net als overal elders in het portaal. Hier stond
 * eerst een eigen `Dialog` -- in de goede kleuren, dus het viel niet op,
 * maar het was wel de enige plek in het hele project met een tweede
 * verwijdervenster. Zo gaan twee vensters uit elkaar lopen zodra er iets
 * aan de afspraak verandert.
 */
const props = defineProps<{
    passkey: Passkey;
}>();

const emit = defineEmits<{
    remove: [id: number, onError: () => void];
}>();

const bezig = ref(false);

const verwijder = async (): Promise<void> => {
    const akkoord = await bevestigVerwijderen({
        titel: t('De passkey ":naam" verwijderen?', {
            naam: props.passkey.name,
        }),
        tekst: t(
            'Je kunt daarna niet meer met dit apparaat inloggen. Je wachtwoord en je authenticator-app blijven gewoon werken.',
        ),
    });

    if (!akkoord) {
        return;
    }

    bezig.value = true;

    emit('remove', props.passkey.id, () => {
        bezig.value = false;
    });
};
</script>

<template>
    <div
        class="flex items-center justify-between gap-4 border-b p-4 last:border-b-0"
    >
        <div class="flex min-w-0 items-center gap-4">
            <div
                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-muted"
            >
                <KeyRound class="size-5 text-muted-foreground" />
            </div>
            <div class="min-w-0 space-y-1">
                <div class="flex flex-wrap items-center gap-2.5">
                    <p class="font-medium tracking-tight">{{ passkey.name }}</p>
                    <span
                        v-if="passkey.authenticator"
                        class="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-0.5 text-[11px] font-medium tracking-wide text-muted-foreground uppercase ring-1 ring-border ring-inset"
                    >
                        {{ passkey.authenticator }}
                    </span>
                </div>
                <p class="text-sm text-pretty text-muted-foreground">
                    {{
                        $t('Toegevoegd :wanneer', {
                            wanneer: passkey.created_at_diff,
                        })
                    }}
                    <template v-if="passkey.last_used_at_diff">
                        <span class="mx-1 text-muted-foreground/50">/</span>
                        {{
                            $t('laatst gebruikt :wanneer', {
                                wanneer: passkey.last_used_at_diff,
                            })
                        }}
                    </template>
                </p>
            </div>
        </div>

        <Button
            variant="verwijderen-zacht"
            size="icon-sm"
            :disabled="bezig"
            @click="verwijder"
        >
            <Trash2 class="size-4" />
            <span class="sr-only">{{ $t('Verwijderen') }}</span>
        </Button>
    </div>
</template>
