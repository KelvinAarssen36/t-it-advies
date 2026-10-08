<script setup lang="ts">
import {
    Download,
    Pin,
    PinOff,
    RotateCcw,
    ShieldCheck,
    Trash2,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import backups from '@/routes/admin/backups';
import type { BackupRij } from '@/types/backups';

/**
 * Eén regel in de lijst met back-ups.
 *
 * Staat apart omdat er twee lijsten zijn -- je eigen back-ups en de
 * veiligheidskopieën eronder -- en die regels hetzelfde horen te zijn.
 * Twee keer dezelfde opmaak naast elkaar is twee keer onderhoud, en dan
 * gaat er ooit één achterlopen.
 *
 * Zie docs/operations/back-ups.md.
 */
const props = defineProps<{
    rij: BackupRij;
    bezig: boolean;
}>();

defineEmits<{
    (e: 'controleren' | 'vastzetten' | 'terugzetten' | 'verwijderen'): void;
}>();

const soortLabel = (rij: BackupRij): string =>
    ({
        handmatig: t('Zelf gemaakt'),
        automatisch: t('Vóór een terugzetting'),
        geupload: t('Teruggeplaatst bestand'),
    })[rij.soort];
</script>

<template>
    <li class="flex flex-wrap items-center justify-between gap-4 p-4 sm:p-5">
        <div class="min-w-0">
            <p class="flex flex-wrap items-center gap-2 font-medium">
                {{ props.rij.naam }}

                <!--
                    Subtiel en met woorden, niet met een kleur alleen: wie
                    kleuren slecht onderscheidt moet ook kunnen zien welke
                    de nieuwste is.
                -->
                <Badge
                    v-if="props.rij.nieuwste"
                    variant="outline"
                    class="border-success/40 text-success"
                >
                    {{ $t('Nieuwste') }}
                </Badge>

                <Badge
                    v-if="props.rij.oudste"
                    variant="outline"
                    class="border-warning/40 text-warning"
                >
                    {{ $t('Oudste') }}
                </Badge>

                <Pin
                    v-if="props.rij.vastgezet"
                    class="size-3.5 shrink-0 text-brand-cyan"
                    aria-hidden="true"
                />
            </p>

            <p class="mt-0.5 text-sm text-muted-foreground">
                {{ soortLabel(props.rij) }} &middot;
                <!--
                    De datum voluit en niet alleen "twee dagen geleden".
                    Bij een teruggeplaatst bestand is dit de datum waarop
                    de inhoud is vastgelegd, en die kan maanden eerder
                    liggen dan het moment dat je hem terugplaatste.
                -->
                <span :title="props.rij.gemaaktGeleden ?? undefined">
                    {{ props.rij.gemaaktOp }}
                </span>
                &middot;
                {{ props.rij.groottePrettig }} &middot;
                {{ $t(':aantal onderdelen', { aantal: props.rij.totaal }) }}
            </p>

            <p
                class="mt-0.5 flex items-center gap-1.5 text-sm"
                :class="
                    props.rij.gecontroleerd
                        ? 'text-muted-foreground'
                        : 'text-warning'
                "
            >
                <ShieldCheck class="size-3.5 shrink-0" aria-hidden="true" />
                {{
                    props.rij.gecontroleerd
                        ? $t('Gecontroleerd :wanneer', {
                              wanneer: props.rij.gecontroleerdGeleden ?? '',
                          })
                        : $t('Nog niet gecontroleerd')
                }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Button
                variant="ghost"
                size="sm"
                :disabled="props.bezig || !props.rij.bestaat"
                :title="$t('Controleren')"
                @click="$emit('controleren')"
            >
                <ShieldCheck class="size-4" />
                <span class="sr-only sm:not-sr-only">
                    {{ $t('Controleren') }}
                </span>
            </Button>

            <Button
                variant="ghost"
                size="sm"
                as-child
                :title="$t('Downloaden')"
            >
                <a :href="backups.download(props.rij.id).url" download>
                    <Download class="size-4" />
                    <span class="sr-only sm:not-sr-only">
                        {{ $t('Downloaden') }}
                    </span>
                </a>
            </Button>

            <Button
                variant="ghost"
                size="sm"
                :title="
                    props.rij.vastgezet
                        ? $t('Weer laten opruimen')
                        : $t('Vastzetten')
                "
                @click="$emit('vastzetten')"
            >
                <PinOff v-if="props.rij.vastgezet" class="size-4" />
                <Pin v-else class="size-4" />
            </Button>

            <Button
                variant="bewerken"
                size="sm"
                :disabled="!props.rij.bestaat"
                @click="$emit('terugzetten')"
            >
                <RotateCcw class="size-4" />
                {{ $t('Terugzetten') }}
            </Button>

            <Button
                variant="ghost"
                size="sm"
                :title="$t('Verwijderen')"
                @click="$emit('verwijderen')"
            >
                <Trash2 class="size-4 text-destructive" />
            </Button>
        </div>
    </li>
</template>
