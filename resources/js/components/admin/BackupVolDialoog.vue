<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import PasteCodeButton from '@/components/PasteCodeButton.vue';
import { Badge } from '@/components/ui/badge';
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
import type { BackupRij } from '@/types/backups';

/**
 * Kiezen welke back-ups er weg mogen.
 *
 * Twee aanleidingen, en ze gebruiken hetzelfde venster omdat de vraag
 * dezelfde is:
 *
 * - **vol**: er moet er één weg om plaats te maken voor een nieuwe;
 * - **te veel**: er staan er meer dan het maximum en dat moet rechtgezet.
 *
 * Dat tweede hoort niet te kunnen, en toch kan het: zet je er twee vast
 * terwijl je er vijf hebt -- vastgezette tellen niet mee -- en laat je ze
 * daarna los, dan staan er zeven die meetellen. Dan komt dit venster
 * ongevraagd open.
 *
 * **Dit venster bestaat omdat het eerst vanzelf ging.** Bij de zesde
 * verdween de oudste, zonder vragen. Dat is precies het soort
 * hulpvaardigheid waar je spijt van krijgt: je maakt even een back-up
 * voor de zekerheid en raakt daarmee de back-up kwijt die je eigenlijk
 * wilde bewaren.
 *
 * **Er verdwijnt niets zonder authenticator-code.** Zou deze weg kunnen
 * zonder, dan is "een nieuwe maken" een sluiproute om de beveiliging op
 * verwijderen te omzeilen.
 *
 * Zie docs/operations/back-ups.md.
 */
const props = defineProps<{
    /** Alleen de back-ups die mogen wijken: eigen en niet vastgezet. */
    keuzes: BackupRij[];
    /** Hoeveel er gekozen moeten worden. */
    nodig: number;
    /** Komt dit venster ongevraagd op omdat er te veel staan? */
    teveel: boolean;
    maximum: number;
    bezig: boolean;
    fouten: Record<string, string>;
}>();

const open = defineModel<boolean>('open', { required: true });

const gekozen = ref<number[]>([]);
const code = ref('');

const emit = defineEmits<{
    (e: 'bevestig', ids: number[], code: string): void;
}>();

watch(open, (isOpen) => {
    if (!isOpen) {
        code.value = '';

        return;
    }

    // De oudste staan voor: dat is bijna altijd wat je bedoelt. Je kunt
    // het gewoon aanpassen.
    gekozen.value = props.keuzes
        .filter((k) => k.oudste)
        .slice(0, props.nodig)
        .map((k) => k.id);
});

const wissel = (id: number): void => {
    if (gekozen.value.includes(id)) {
        gekozen.value = gekozen.value.filter((gekozenId) => gekozenId !== id);

        return;
    }

    /*
     * Boven het aantal dat nodig is schuift de oudste keuze eruit. Zo
     * kun je blijven klikken zonder eerst te moeten uitvinken, en staat
     * er nooit meer geselecteerd dan er weg hoeft.
     */
    gekozen.value = [...gekozen.value, id].slice(-props.nodig);
};

const compleet = computed(
    () => gekozen.value.length === props.nodig && code.value.length === 6,
);

const bevestig = (): void => {
    if (compleet.value && !props.bezig) {
        emit('bevestig', gekozen.value, code.value);
    }
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{
                        props.teveel
                            ? $t('Je hebt er meer dan er mogen staan')
                            : $t('Je lijst zit vol')
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        props.teveel
                            ? $t(
                                  'Er staan er :nodig te veel. Kies welke er weg mogen; de oudste staan al aangevinkt.',
                                  { nodig: props.nodig },
                              )
                            : $t(
                                  'Je bewaart er :aantal. Kies welke er weg mag om plaats te maken voor de nieuwe.',
                                  { aantal: props.maximum },
                              )
                    }}
                </DialogDescription>
            </DialogHeader>

            <p class="text-sm text-pretty text-muted-foreground">
                {{
                    $t(
                        'Weggooien kun je niet terugdraaien. Heb je het bestand gedownload, dan kun je het later weer terugplaatsen.',
                    )
                }}
            </p>

            <div class="grid max-h-72 gap-2 overflow-y-auto">
                <button
                    v-for="keuze in props.keuzes"
                    :key="keuze.id"
                    type="button"
                    class="brand-keuzekaart"
                    :data-gekozen="gekozen.includes(keuze.id) ? '' : undefined"
                    :aria-pressed="gekozen.includes(keuze.id)"
                    @click="wissel(keuze.id)"
                >
                    <span class="brand-keuzekaart-vink" aria-hidden="true">
                        <Check
                            v-if="gekozen.includes(keuze.id)"
                            class="size-3.5"
                        />
                    </span>

                    <span class="min-w-0 space-y-1 text-left">
                        <span
                            class="flex flex-wrap items-center gap-2 font-medium"
                        >
                            {{ keuze.naam }}
                            <Badge
                                v-if="keuze.oudste"
                                variant="outline"
                                class="border-warning/40 text-warning"
                            >
                                {{ $t('Oudste') }}
                            </Badge>
                        </span>
                        <!--
                            De datum voluit. "Twee dagen geleden" zegt te
                            weinig als je moet kiezen welke je kwijt wilt.
                        -->
                        <span
                            class="block text-sm text-pretty text-muted-foreground"
                        >
                            {{ keuze.gemaaktOp }} &middot;
                            {{ keuze.groottePrettig }}
                        </span>
                    </span>
                </button>
            </div>

            <form class="grid gap-3" @submit.prevent="bevestig">
                <Label for="opruim-code" verplicht>
                    {{ $t('De code uit je authenticator') }}
                </Label>

                <div class="flex flex-col items-center gap-4">
                    <InputOTP
                        id="opruim-code"
                        v-model="code"
                        :maxlength="6"
                        :disabled="props.bezig"
                    >
                        <InputOTPGroup>
                            <InputOTPSlot
                                v-for="index in 6"
                                :key="index"
                                :index="index - 1"
                            />
                        </InputOTPGroup>
                    </InputOTP>

                    <InputError
                        :message="
                            props.fouten.code ??
                            props.fouten.vervang ??
                            props.fouten.ids
                        "
                    />

                    <PasteCodeButton @pasted="(c: string) => (code = c)" />
                </div>

                <DialogFooter>
                    <!--
                        Bij "te veel" staat er geen annuleren: wegklikken
                        lost niets op en de melding komt bij de volgende
                        keer toch terug. De kruisknop van het venster
                        blijft wel werken, want een venster dat je niet
                        weg krijgt is een val.
                    -->
                    <Button
                        v-if="!props.teveel"
                        type="button"
                        variant="secondary"
                        @click="open = false"
                    >
                        {{ $t('Annuleren') }}
                    </Button>
                    <Button
                        type="submit"
                        variant="verwijderen"
                        :disabled="props.bezig || !compleet"
                    >
                        {{
                            props.bezig
                                ? $t('Bezig...')
                                : props.nodig === 1
                                  ? $t('Deze weggooien')
                                  : $t('Deze :aantal weggooien', {
                                        aantal: props.nodig,
                                    })
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
