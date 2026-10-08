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
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import backups from '@/routes/admin/backups';
import type { BackupRij, Verschil } from '@/types/backups';

/**
 * Terugzetten of verwijderen, met de sloten erop.
 *
 * Eén venster voor allebei, want de drempels zijn dezelfde: een verse
 * authenticator-code, en bij terugzetten ook de naam overtypen.
 *
 * **De code staat in dit venster en niet op het aparte codescherm.** De
 * middleware `2fa.confirm` kan een POST niet onthouden: je zou je code
 * invullen, terugkomen op een leeg scherm en opnieuw moeten beginnen. Zie
 * App\Support\Security\Authenticator.
 *
 * **Het verschil staat erbij, met cijfers.** "Weet je het zeker, alles
 * wordt overschreven" klik je weg; "14 projecten worden 11 projecten"
 * lees je. Dat is het hele doel van het bovenste blok.
 *
 * Zie docs/operations/back-ups.md.
 */
const props = defineProps<{
    backup: BackupRij | null;
    /** 'terugzetten' of 'verwijderen'. */
    actie: 'terugzetten' | 'verwijderen';
    verschil: Verschil | null;
    /** Wordt het verschil nog opgehaald? */
    laadt: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const code = ref('');
const overgetypt = ref('');
const bezig = ref(false);
const fouten = ref<Record<string, string>>({});

watch(open, (isOpen) => {
    if (!isOpen) {
        code.value = '';
        overgetypt.value = '';
        fouten.value = {};
    }
});

const terugzetten = computed(() => props.actie === 'terugzetten');

/** Pas versturen als alles er staat; de knop blijft tot die tijd uit. */
const compleet = computed(() => {
    if (code.value.length < 6) {
        return false;
    }

    return (
        !terugzetten.value ||
        overgetypt.value.trim() === props.backup?.bevestigcode
    );
});

const versturen = (): void => {
    if (!compleet.value || bezig.value || props.backup === null) {
        return;
    }

    bezig.value = true;
    fouten.value = {};

    const opties = {
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
    };

    if (terugzetten.value) {
        router.post(
            backups.terugzetten(props.backup.id).url,
            { code: code.value, bevestigcode: overgetypt.value.trim() },
            opties,
        );

        return;
    }

    router.delete(backups.destroy(props.backup.id).url, {
        ...opties,
        data: { code: code.value },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{
                        terugzetten
                            ? $t('Je website terugzetten?')
                            : $t('Deze back-up verwijderen?')
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        terugzetten
                            ? $t(
                                  'Je website gaat terug naar hoe hij was toen deze back-up werd gemaakt. Je account, je passkeys en de berichten van bezoekers blijven zoals ze nu zijn.',
                              )
                            : $t(
                                  'Het bestand gaat weg en is daarna niet meer terug te halen.',
                              )
                    }}
                </DialogDescription>
            </DialogHeader>

            <!-- Wat er verandert, in cijfers. -->
            <div
                v-if="terugzetten"
                class="rounded-lg border border-border text-sm"
            >
                <p v-if="props.laadt" class="p-4 text-muted-foreground sm:p-5">
                    {{ $t('Bezig met uitrekenen wat er verandert...') }}
                </p>

                <template v-else-if="props.verschil">
                    <ul class="divide-y divide-border">
                        <li
                            v-for="regel in props.verschil.regels.filter(
                                (r) => r.verschil !== 0,
                            )"
                            :key="regel.tabel"
                            class="flex items-center justify-between gap-4 px-4 py-2.5 sm:px-5"
                        >
                            <span class="min-w-0 truncate">{{
                                regel.label
                            }}</span>
                            <span
                                class="shrink-0 tabular-nums"
                                :class="
                                    regel.verschil < 0
                                        ? 'text-destructive'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ regel.nu }} → {{ regel.straks }}
                            </span>
                        </li>
                    </ul>

                    <p
                        v-if="
                            props.verschil.regels.every((r) => r.verschil === 0)
                        "
                        class="p-4 text-muted-foreground sm:p-5"
                    >
                        {{
                            $t(
                                'Er verandert niets aan de aantallen. De inhoud van je teksten kan wel verschillen.',
                            )
                        }}
                    </p>
                </template>
            </div>

            <!--
                De twee dingen die je echt vóór het terugzetten wil weten.
                Allebei geen beletsel, wel een reden om nog eens te kijken.
            -->
            <div
                v-if="terugzetten && props.verschil?.andereSite"
                class="flex items-start gap-3 rounded-lg border border-border bg-muted/40 p-4 text-sm"
            >
                <TriangleAlert
                    class="mt-0.5 size-4 shrink-0 text-destructive"
                    aria-hidden="true"
                />
                <p class="text-pretty text-muted-foreground">
                    {{
                        $t(
                            'Deze back-up lijkt van een andere website te komen. Weet je zeker dat dit de juiste is?',
                        )
                    }}
                </p>
            </div>

            <div
                v-if="terugzetten && props.verschil?.anderSchema"
                class="flex items-start gap-3 rounded-lg border border-border bg-muted/40 p-4 text-sm"
            >
                <TriangleAlert
                    class="mt-0.5 size-4 shrink-0 text-warning"
                    aria-hidden="true"
                />
                <p class="text-pretty text-muted-foreground">
                    {{
                        $t(
                            'Deze back-up is van vóór een update van het portaal. Hij werkt, maar velden die er toen nog niet waren blijven leeg. Je krijgt achteraf te zien welke dat waren.',
                        )
                    }}
                </p>
            </div>

            <form class="grid gap-5" @submit.prevent="versturen">
                <!--
                    Vier cijfers overtypen. Dezelfde drempel die GitHub
                    gebruikt bij het verwijderen van een repository, maar
                    korter: eerst was het de volledige naam
                    (`2026-10-08-1130`), en vijftien tekens met streepjes
                    overtypen is vooral vervelend. Vervelend is niet
                    hetzelfde als zorgvuldig -- wie geïrriteerd raakt, let
                    minder op.

                    **Cijfers en geen woord**, want een woord zou
                    Nederlands of Engels zijn en dan klopt het in één van
                    de twee talen niet.
                -->
                <div v-if="terugzetten" class="grid gap-2">
                    <Label for="backup-overtypen" verplicht>
                        {{ $t('Typ deze vier cijfers over') }}
                    </Label>

                    <div class="flex flex-wrap items-center gap-3">
                        <!--
                            De code groot en uitgelicht. Hij stond eerst
                            midden in de labeltekst, en dan is niet te
                            zien wát je precies moet overtypen.
                        -->
                        <span
                            class="rounded-lg border border-border bg-muted/50 px-4 py-2 font-mono text-2xl tracking-[0.3em] tabular-nums select-all"
                        >
                            {{ props.backup?.bevestigcode }}
                        </span>

                        <Input
                            id="backup-overtypen"
                            v-model="overgetypt"
                            class="w-32 text-center font-mono text-lg tracking-[0.2em] tabular-nums"
                            inputmode="numeric"
                            autocomplete="off"
                            spellcheck="false"
                            maxlength="4"
                            placeholder="0000"
                        />
                    </div>

                    <InputError :message="fouten.bevestigcode" />
                </div>

                <div class="grid gap-3">
                    <Label for="backup-code" verplicht>
                        {{ $t('De code uit je authenticator') }}
                    </Label>

                    <div class="flex flex-col items-center gap-4">
                        <InputOTP
                            id="backup-code"
                            v-model="code"
                            :maxlength="6"
                            :disabled="bezig"
                        >
                            <InputOTPGroup>
                                <InputOTPSlot
                                    v-for="index in 6"
                                    :key="index"
                                    :index="index - 1"
                                />
                            </InputOTPGroup>
                        </InputOTP>

                        <InputError :message="fouten.code" />

                        <PasteCodeButton @pasted="(c: string) => (code = c)" />
                    </div>
                </div>

                <p
                    v-if="terugzetten"
                    class="text-sm text-pretty text-muted-foreground"
                >
                    {{
                        $t(
                            'Vlak voordat er iets verandert maakt het portaal een kopie van hoe het nu is. Ook een verkeerde terugzetting kun je dus ongedaan maken.',
                        )
                    }}
                </p>

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
                        variant="verwijderen"
                        :disabled="bezig || !compleet"
                    >
                        {{
                            bezig
                                ? $t('Bezig...')
                                : terugzetten
                                  ? $t('Ja, zet mijn website terug')
                                  : $t('Ja, verwijderen')
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
