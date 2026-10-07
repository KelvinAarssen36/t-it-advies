<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import LogoKiezer from '@/components/LogoKiezer.vue';
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
import overMij from '@/routes/website/over-mij';
import type { OverMijInstelling } from '@/types/over-mij';

/**
 * De foto van "Over mij", in een eigen venster.
 *
 * **Hij zat eerst bij het korte blok, en dat was de verkeerde plek.** Daar
 * werd hij gekozen, dus daar stond hij. Maar hij staat op de voorpagina én
 * op de aparte pagina, en dan is "hij hoort bij het blok" een afspraak die
 * je moet onthouden in plaats van iets wat je ziet. Nu staat hij naast de
 * twee versies in plaats van in één ervan: een eigen knop in beide koppen,
 * dit venster, en een eigen eindpunt.
 *
 * Dat laatste is geen netheid. Zat de foto nog bij het blok, dan stuurt een
 * opslag van de samenvatting ook de fotovelden mee, en moet de server elke
 * keer uitzoeken of er wel iets over de foto is gezegd. Komt dit verzoek
 * binnen, dan gaat het over de foto.
 *
 * **De kiezer staat ingevuld en niet leeg.** `bestaand` krijgt het beeld dat
 * er nú geldt, en dat is standaard het portret uit de kop. Een leeg
 * uploadvak zou suggereren dat er nog niets is, terwijl er altijd een foto
 * op de site staat. Haal je het weg, dan komt het medaillon terug: de server
 * zet `photo_path` op `null` en valt daar vanzelf op terug.
 *
 * Zie docs/architecture/modules/over-mij.md.
 */
const props = defineProps<{ instelling: OverMijInstelling }>();

const open = defineModel<boolean>('open', { required: true });

const bezig = ref(false);
const fouten = ref<Record<string, string>>({});

/* De fotokiezer; dezelfde als bij een certificaatlogo. */
const bestand = ref<File | null>(null);
const weghalen = ref(false);
const zoom = ref(1);
const x = ref(0);
const y = ref(0);
const plaat = ref(true);

/** Of het venster zichzelf opnieuw opent en de inhoud dus moet blijven. */
let behoudInhoud = false;

const openOpnieuw = (): void => {
    behoudInhoud = true;
    open.value = true;
};

const vulIn = (): void => {
    bestand.value = null;
    weghalen.value = false;
    zoom.value = 1;
    x.value = 0;
    y.value = 0;
    plaat.value = true;

    fouten.value = {};
};

watch(open, (isOpen) => {
    if (!isOpen) {
        behoudInhoud = false;

        return;
    }

    if (behoudInhoud) {
        behoudInhoud = false;

        return;
    }

    vulIn();
});

const opslaan = async (): Promise<void> => {
    open.value = false;
    await adem();

    const akkoord = await bevestigBewerken({
        titel: weghalen.value
            ? t('Je foto weghalen?')
            : t('Je foto aanpassen?'),
        tekst: weghalen.value
            ? t(
                  'Het portret uit je kop komt er dan weer te staan, op allebei de plekken.',
              )
            : t(
                  'Dit verandert de foto op je voorpagina en op je aparte pagina.',
              ),
    });

    if (!akkoord) {
        await adem();
        openOpnieuw();

        return;
    }

    bezig.value = true;

    router.put(
        overMij.foto().url,
        {
            foto: bestand.value,
            foto_verwijderen: weghalen.value,
            foto_zoom: zoom.value,
            foto_x: x.value,
            foto_y: y.value,
            foto_plaat: plaat.value,
        },
        {
            preserveScroll: true,
            forceFormData: true,
            onError: (ontvangen) => {
                fouten.value = ontvangen;
                openOpnieuw();
            },
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
        <DialogContent
            class="max-h-[85vh] brand-scrollbar overflow-y-auto sm:max-w-2xl"
        >
            <DialogHeader>
                <DialogTitle>{{ $t('Je foto') }}</DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Eén foto voor allebei: hij staat naast je korte stuk op je voorpagina en bovenaan je aparte pagina. Daarom staat hij hier apart en niet bij een van de twee.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-2">
                <p class="text-xs text-pretty text-muted-foreground">
                    {{
                        props.instelling.foto.eigen
                            ? $t(
                                  'Je eigen foto staat er nu. Haal je hem weg, dan komt het portret uit je kop er weer te staan.',
                              )
                            : $t(
                                  'Nu staat het portret uit je kop er: je foto in het oog-embleem. Zet je hier een eigen foto in, dan gaat die voor.',
                              )
                    }}
                </p>

                <LogoKiezer
                    :bestaand="props.instelling.foto.src"
                    v-model:bestand="bestand"
                    v-model:weghalen="weghalen"
                    v-model:zoom="zoom"
                    v-model:x="x"
                    v-model:y="y"
                    v-model:plaat="plaat"
                />
                <InputError :message="fouten.foto" />
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
