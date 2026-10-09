<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
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
import { Label } from '@/components/ui/label';
import { bevestigBewerken } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import footer from '@/routes/website/footer';
import type { VoetInstelling, VoetOpties } from '@/types/voettekst';

/**
 * KvK- en btw-nummer in de voet.
 *
 * **Geen vertaalknop, en dat is geen vergissing.** Een nummer is in elke
 * taal hetzelfde; een knop erboven zou suggereren dat er iets te vertalen
 * valt.
 *
 * **De teller onder het KvK-veld rekent met hetzelfde getal als de
 * server.** Dat getal komt mee als prop, zodat het scherm niet kan
 * goedkeuren wat de server afkeurt.
 *
 * Zie docs/architecture/modules/voettekst.md.
 */
const props = defineProps<{
    instelling: VoetInstelling;
    opties: VoetOpties;
}>();

const open = defineModel<boolean>('open', { required: true });

const bezig = ref(false);
const fouten = ref<Record<string, string>>({});

const formulier = ref({
    kvk: '',
    vat: '',
});

/** Of het venster zichzelf opnieuw opent en de inhoud dus moet blijven. */
let behoudInhoud = false;

const openOpnieuw = (): void => {
    behoudInhoud = true;
    open.value = true;
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

    formulier.value = {
        kvk: props.instelling.kvk ?? '',
        vat: props.instelling.vat ?? '',
    };

    fouten.value = {};
});

/**
 * Hoeveel cijfers er nu in het KvK-veld staan.
 *
 * Spaties tellen niet mee: hoe hij het nummer schrijft is aan hem, hoeveel
 * cijfers erin zitten niet.
 */
const cijfers = computed(() => formulier.value.kvk.replace(/\D/g, '').length);

const kvkKlopt = computed(
    () =>
        formulier.value.kvk.trim() === '' ||
        cijfers.value === props.opties.kvkCijfers,
);

const opslaan = async (): Promise<void> => {
    open.value = false;
    await adem();

    const akkoord = await bevestigBewerken({
        titel: t('Je bedrijfsgegevens in de voet aanpassen?'),
    });

    if (!akkoord) {
        await adem();
        openOpnieuw();

        return;
    }

    bezig.value = true;

    router.put(footer.bedrijf().url, formulier.value, {
        preserveScroll: true,
        onError: (ontvangen: Record<string, string>) => {
            fouten.value = ontvangen;
            openOpnieuw();
        },
        onFinish: () => {
            bezig.value = false;
        },
    });
};

const adem = (): Promise<void> =>
    new Promise((klaar) => setTimeout(klaar, 200));
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="max-h-[85vh] brand-scrollbar overflow-y-auto sm:max-w-xl"
        >
            <DialogHeader>
                <DialogTitle>
                    {{ $t('Je bedrijfsgegevens in de voet') }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Onderaan elke pagina, naast je copyrightregel. Allebei mogen leeg blijven.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-5" @submit.prevent="opslaan">
                <div class="grid gap-2">
                    <Label for="voet-kvk">{{ $t('KvK-nummer') }}</Label>
                    <Input
                        id="voet-kvk"
                        v-model="formulier.kvk"
                        :maxlength="props.opties.nummerMax"
                        inputmode="numeric"
                        :placeholder="$t('12 34 56 78')"
                    />

                    <!--
                        De teller telt cijfers en geen tekens: spaties
                        mogen, en hoeveel hij er zet is aan hem.
                    -->
                    <p
                        class="text-xs"
                        :class="
                            kvkKlopt
                                ? 'text-muted-foreground'
                                : 'text-destructive'
                        "
                    >
                        {{
                            $t(
                                'Een KvK-nummer heeft :hoort cijfers; je hebt er nu :nu.',
                                {
                                    hoort: props.opties.kvkCijfers,
                                    nu: cijfers,
                                },
                            )
                        }}
                    </p>
                    <InputError :message="fouten.kvk" />
                </div>

                <div class="grid gap-2">
                    <Label for="voet-btw">{{ $t('Btw-nummer') }}</Label>
                    <Input
                        id="voet-btw"
                        v-model="formulier.vat"
                        :maxlength="props.opties.nummerMax"
                        placeholder="NL001234567B01"
                    />
                    <p class="text-xs text-pretty text-muted-foreground">
                        {{
                            $t(
                                'Hoeft niet. Zet je het erbij, dan staat het achter je KvK-nummer.',
                            )
                        }}
                    </p>
                    <InputError :message="fouten.vat" />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="secondary"
                        @click="open = false"
                    >
                        {{ $t('Annuleren') }}
                    </Button>
                    <Button type="submit" variant="bewerken" :disabled="bezig">
                        {{ bezig ? $t('Bezig...') : $t('Opslaan') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
