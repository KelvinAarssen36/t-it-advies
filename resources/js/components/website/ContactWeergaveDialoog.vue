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
import contact from '@/routes/website/contact';
import type { ContactOpties, ContactWeergave } from '@/types/contact';

/**
 * Hoe het contact op de website wordt aangeboden.
 *
 * Twee keuzes: het formulier onderaan de landingspagina, of alleen een
 * knop daar met het formulier op een eigen pagina.
 *
 * **Twee kaarten en geen keuzelijst.** Het verschil zit niet in de naam
 * maar in wat het voor de bezoeker betekent, en dat is één regel uitleg
 * per optie. In een uitklaplijst zie je die regels niet.
 *
 * Zie docs/architecture/modules/contact.md.
 */
const props = defineProps<{
    weergave: ContactWeergave;
    opties: ContactOpties;
}>();

const open = defineModel<boolean>('open', { required: true });

const gekozen = ref<ContactWeergave>(props.weergave);
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
        titel: t('De weergave van je contact aanpassen?'),
        tekst: t('Dit verandert meteen hoe je website eruitziet.'),
    });

    if (!akkoord) {
        await adem();
        open.value = true;

        return;
    }

    bezig.value = true;

    router.put(
        contact.instellingen().url,
        { display: gekozen.value },
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
                <DialogTitle>{{ $t('Hoe je contact aanbiedt') }}</DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Allebei werken ze; het hangt ervan af wat je met je website wil. Je kunt het altijd omzetten.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-3">
                <button
                    v-for="optie in props.opties.weergave"
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
