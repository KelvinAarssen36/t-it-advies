<script setup lang="ts">
import { Check, ShieldCheck } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { BackupDekking } from '@/types/backups';

/**
 * Wat er in een back-up meegaat.
 *
 * **Dit venster stelt gerust en vraagt niets.** Er valt niets te kiezen en
 * niets te bevestigen: je maakt het open, je ziet dat alles erin zit, en
 * je doet het weer dicht. Daarom één knop onderaan, en geen "Annuleren"
 * ernaast -- er is niets om van af te zien.
 *
 * **De datum erachter is het punt.** Een vinkje zegt "dit gaat mee", maar
 * dat is een belofte over later. De dag waarop het voor het laatst is
 * vastgelegd is een feit over wat er nu al veilig staat, en dat is wat
 * iemand eigenlijk wil weten.
 *
 * Staat er geen datum, dan is dat onderdeel nog nooit meegegaan -- meestal
 * omdat het nieuwer is dan de nieuwste back-up. Dan staat dat er ook,
 * want een leeg plekje leest als een storing en "nog niet" als een
 * oproep om er een te maken. De regel is dan gedempt en zonder vinkje:
 * het klopt dat die anders aanvoelt dan de rest.
 */
defineProps<{
    open: boolean;
    dekking: BackupDekking[];
}>();

defineEmits<{ 'update:open': [waarde: boolean] }>();
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <ShieldCheck class="size-5 text-success" />
                    {{ $t('Dit gaat er mee in een back-up') }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Alles wat je zelf beheert gaat mee, in beide talen. Achter elk onderdeel staat wanneer het voor het laatst is vastgelegd.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <ul class="brand-dekking">
                <li
                    v-for="regel in dekking"
                    :key="regel.naam"
                    class="brand-dekking-regel"
                    :data-nog-niet="regel.laatst === null ? '' : undefined"
                >
                    <span class="brand-dekking-teken" aria-hidden="true">
                        <Check v-if="regel.laatst !== null" class="size-3.5" />
                    </span>

                    <span class="brand-dekking-naam">{{ regel.naam }}</span>

                    <span class="brand-dekking-wanneer">
                        {{
                            regel.laatst === null
                                ? $t('nog niet meegenomen')
                                : regel.laatst
                        }}
                    </span>
                </li>
            </ul>

            <DialogFooter>
                <Button variant="outline" @click="$emit('update:open', false)">
                    {{ $t('Duidelijk') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
