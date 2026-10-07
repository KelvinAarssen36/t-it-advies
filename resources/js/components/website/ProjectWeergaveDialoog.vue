<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { bevestigBewerken } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import projecten from '@/routes/website/projecten';
import type { ProjectOpties, ProjectWeergave } from '@/types/projecten';

/**
 * Hoe de pagina met alle projecten eruitziet.
 *
 * Twee keuzes: afwisselend groot en klein, of allemaal even grote kaarten.
 *
 * **Twee kaarten en geen keuzelijst**, net als bij de weergave van het
 * contact. Het verschil zit niet in de naam maar in wat het met de pagina
 * doet, en dat is een regel uitleg per optie; in een uitklaplijst zie je
 * die regels niet.
 *
 * **Dit raakt alleen `/projecten`.** Het blok op de voorpagina blijft wat
 * het is -- één groot project met kaarten ernaast -- want dat is een
 * voorproefje en geen overzicht. Dat staat ook in het venster, zodat de
 * eigenaar niet gaat zoeken naar een verandering die daar niet komt.
 *
 * Zie docs/architecture/modules/projecten.md.
 */
const props = defineProps<{
    weergave: ProjectWeergave;
    opties: ProjectOpties;
}>();

const open = defineModel<boolean>('open', { required: true });

const gekozen = ref<ProjectWeergave>(props.weergave);
const bezig = ref(false);

watch(open, (isOpen) => {
    if (isOpen) {
        gekozen.value = props.weergave;
    }
});

const opslaan = async (): Promise<void> => {
    if (gekozen.value === props.weergave) {
        open.value = false;

        return;
    }

    open.value = false;
    await adem();

    const akkoord = await bevestigBewerken({
        titel: t('De weergave van je projectenpagina aanpassen?'),
        tekst: t('Dit verandert meteen hoe je website eruitziet.'),
    });

    if (!akkoord) {
        await adem();
        open.value = true;

        return;
    }

    bezig.value = true;

    router.put(
        projecten.weergave().url,
        { weergave: gekozen.value },
        {
            preserveScroll: true,
            onFinish: () => {
                bezig.value = false;
            },
        },
    );
};

const adem = (): Promise<void> =>
    new Promise((klaar) => setTimeout(klaar, 200));
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>
                    {{ $t('Hoe je projectenpagina eruitziet') }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Dit gaat over de pagina met al je projecten. Het blok op je voorpagina blijft zoals het is. Je kunt het altijd omzetten.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-3">
                <button
                    v-for="optie in props.opties.weergaven"
                    :key="optie.value"
                    type="button"
                    class="brand-keuzekaart"
                    :data-gekozen="optie.value === gekozen ? '' : undefined"
                    :aria-pressed="optie.value === gekozen"
                    @click="gekozen = optie.value"
                >
                    <span class="brand-keuzekaart-vink" aria-hidden="true">
                        <Check
                            v-if="optie.value === gekozen"
                            class="size-3.5"
                        />
                    </span>

                    <span class="min-w-0 space-y-1 text-left">
                        <span class="block font-medium">{{ optie.label }}</span>
                        <span
                            class="block text-sm text-pretty text-muted-foreground"
                        >
                            {{ optie.omschrijving }}
                        </span>
                    </span>
                </button>
            </div>

            <DialogFooter>
                <Button type="button" variant="secondary" @click="open = false">
                    {{ $t('Annuleren') }}
                </Button>
                <Button
                    type="button"
                    variant="bewerken"
                    :disabled="bezig"
                    @click="opslaan"
                >
                    {{ bezig ? $t('Bezig...') : $t('Opslaan') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
