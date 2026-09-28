<script setup lang="ts">
import { Sparkles } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';

/**
 * De begroeting die één keer verschijnt nadat je bent binnengekomen.
 *
 * De animaties zijn met opzet pure CSS. GSAP zit in een eigen chunk die
 * alleen de publieke site ophaalt; die hierheen trekken zou het portaal
 * ruim honderd kilobyte kosten voor een begroeting van twee seconden.
 *
 * Of hij verschijnt bepaalt de server: DashboardController haalt de vlag
 * met `pull` op, dus hij is er één keer en niet opnieuw bij een verversing.
 */
defineProps<{ firstName?: string | null }>();

const open = ref(true);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="welcome-dialog overflow-hidden border-border bg-card text-center sm:max-w-md"
            :show-close-button="false"
        >
            <div class="welcome-dialog-glow" aria-hidden="true" />

            <div class="relative flex flex-col items-center gap-4 py-2">
                <div
                    class="welcome-dialog-item welcome-dialog-badge flex size-12 items-center justify-center rounded-full brand-surface text-white"
                    style="--welcome-delay: 0ms"
                >
                    <Sparkles class="size-6" />
                </div>

                <h2
                    class="welcome-dialog-item text-2xl font-semibold text-balance text-foreground"
                    style="--welcome-delay: 90ms"
                >
                    {{
                        firstName
                            ? $t('Welkom terug, :naam', { naam: firstName })
                            : $t('Welkom terug')
                    }}
                </h2>

                <p
                    class="welcome-dialog-item max-w-xs text-sm text-pretty text-muted-foreground"
                    style="--welcome-delay: 160ms"
                >
                    {{
                        $t(
                            'Je bent ingelogd op het beheerportaal van @T IT Advies. Hier houd je de inhoud van de website bij.',
                        )
                    }}
                </p>

                <div
                    class="welcome-dialog-item brand-rule w-24"
                    style="--welcome-delay: 220ms"
                />

                <Button
                    class="welcome-dialog-item mt-2 w-full"
                    style="--welcome-delay: 280ms"
                    @click="open = false"
                >
                    {{ $t('Aan de slag') }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
