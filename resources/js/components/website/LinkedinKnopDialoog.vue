<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Languages, Loader2 } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import LocaleFlag from '@/components/LocaleFlag.vue';
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
import { bevestigBewerken, bevestigVerwijderen } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import website from '@/routes/website';
import linkedin from '@/routes/website/linkedin';
import type {
    LinkedinInstelling,
    LinkedinOpties,
    LinkedinStandaardKnop,
} from '@/types/linkedin';

/**
 * De tekst op de knop van het LinkedIn-blok.
 *
 * Eén veld, twee talen, en allebei mogen ze leeg: dan zegt de knop wat hij
 * altijd zei, "Bekijk [naam]" in die taal. Die standaardtekst staat als
 * voorbeeld in het veld, zodat de eigenaar ziet wat er komt te staan.
 *
 * **Dit venster stuurt alleen zijn eigen velden.** De kop erboven heeft een
 * eigen venster en een eigen eindpunt; zie LinkedinController.
 *
 * Zie docs/architecture/modules/linkedin.md.
 */
const props = defineProps<{
    instelling: LinkedinInstelling;
    standaard: LinkedinStandaardKnop;
    opties: LinkedinOpties;
    kanVertalen: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const bezig = ref(false);
const fouten = ref<Record<string, string>>({});

const formulier = ref({
    button_nl: '',
    button_en: '',
});

/** Het Engels zoals het bij het openen van het venster stond. */
let engelsBijOpenen = '';

const automatisch = ref(false);
const vertaalt = ref(false);
const vertaalFout = ref<string | null>(null);

/** Of het venster zichzelf opnieuw opent en de inhoud dus moet blijven. */
let behoudInhoud = false;

const openOpnieuw = (): void => {
    behoudInhoud = true;
    open.value = true;
};

const vulIn = (): void => {
    formulier.value = {
        button_nl: props.instelling.button_nl ?? '',
        button_en: props.instelling.button_en ?? '',
    };

    engelsBijOpenen = formulier.value.button_en;
    automatisch.value = props.instelling.automatisch_vertaald;
    vertaalFout.value = null;
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

const overNl = computed(
    () => props.opties.knopMax - formulier.value.button_nl.length,
);

const overEn = computed(
    () => props.opties.knopMax - formulier.value.button_en.length,
);

/* --- Automatisch vertalen ---------------------------------------------- */

let stopLuisteren: (() => void) | undefined;

onMounted(() => {
    stopLuisteren = router.on('flash', (gebeurtenis) => {
        const velden = (gebeurtenis as CustomEvent).detail?.flash?.vertaling as
            | Record<string, unknown>
            | undefined;

        if (!velden || !open.value) {
            return;
        }

        const waarde = velden.button_en;

        if (typeof waarde === 'string') {
            formulier.value.button_en = waarde;
        }

        automatisch.value = true;

        /*
         * De ijkwaarde opnieuw. Wat de dienst zojuist heeft
         * voorgesteld is vanaf nu "Engels waar niemand aan heeft
         * gezeten". Stond dit er niet, dan viel de vergelijking bij
         * het opslaan altijd negatief uit en werd het merkje nooit
         * gezet -- terwijl de documentatie zegt dat het juist dan
         * hoort te staan. Zie docs/architecture/automatisch-vertalen.md.
         */
        engelsBijOpenen = formulier.value.button_en;
        vertaalFout.value = null;
    });
});

onBeforeUnmount(() => stopLuisteren?.());

const kanVertalen = computed(
    () => props.kanVertalen && formulier.value.button_nl.trim() !== '',
);

const vertaal = async (): Promise<void> => {
    if (!automatisch.value && formulier.value.button_en.trim() !== '') {
        open.value = false;
        await adem();

        const akkoord = await bevestigVerwijderen({
            titel: t('Het Engels dat er staat overschrijven?'),
            tekst: t(
                'De vertaaldienst zet er zijn eigen tekst voor in de plaats. Wat je zelf hebt geschreven is dan weg.',
            ),
            knop: t('Ja, opnieuw vertalen'),
        });

        await adem();
        openOpnieuw();

        if (!akkoord) {
            return;
        }
    }

    vertaalt.value = true;
    vertaalFout.value = null;

    router.post(
        website.vertalen().url,
        { button_nl: formulier.value.button_nl },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                vertaalFout.value = t(
                    'Het vertalen is niet gelukt. Probeer het zo nog eens, of vul het Engels zelf in.',
                );
            },
            onFinish: () => {
                vertaalt.value = false;
            },
        },
    );
};

/* --- Opslaan ----------------------------------------------------------- */

const opslaan = async (): Promise<void> => {
    open.value = false;
    await adem();

    const akkoord = await bevestigBewerken({
        titel: t('De tekst op je LinkedIn-knop aanpassen?'),
    });

    if (!akkoord) {
        await adem();
        openOpnieuw();

        return;
    }

    bezig.value = true;

    router.put(
        linkedin.knop().url,
        {
            ...formulier.value,
            machine_translated:
                automatisch.value &&
                formulier.value.button_en === engelsBijOpenen,
        },
        {
            preserveScroll: true,
            onError: (ontvangen: Record<string, string>) => {
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
            class="max-h-[85vh] brand-scrollbar overflow-y-auto sm:max-w-xl"
        >
            <DialogHeader>
                <DialogTitle>{{ $t('De tekst op de knop') }}</DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Kort en concreet, want het staat op een knop. Laat je het leeg, dan staat er de standaardtekst.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-5" @submit.prevent="opslaan">
                <div class="grid gap-2">
                    <Label for="linkedin-knop">{{ $t('Knoptekst') }}</Label>
                    <Input
                        id="linkedin-knop"
                        v-model="formulier.button_nl"
                        :maxlength="props.opties.knopMax"
                        :placeholder="props.standaard.nl"
                    />
                    <p class="text-xs text-muted-foreground">
                        {{ $t('Nog :aantal tekens', { aantal: overNl }) }}
                    </p>
                    <InputError :message="fouten.button_nl" />
                </div>

                <!-- Het Engels, onder een streep. -->
                <div class="grid gap-5 border-t border-border pt-5">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <span
                            class="flex items-center gap-2 text-sm font-medium"
                        >
                            <LocaleFlag locale="en" class="size-4" />
                            {{ $t('Engels') }}
                        </span>

                        <Button
                            v-if="kanVertalen"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="gap-2"
                            :disabled="vertaalt"
                            @click="vertaal"
                        >
                            <Loader2
                                v-if="vertaalt"
                                class="size-4 animate-spin"
                            />
                            <Languages v-else class="size-4" />
                            {{ vertaalt ? $t('Bezig...') : $t('Vertaal') }}
                        </Button>
                    </div>

                    <p v-if="vertaalFout" class="text-sm text-destructive">
                        {{ vertaalFout }}
                    </p>

                    <div class="grid gap-2">
                        <Label for="linkedin-knop-en">
                            {{ $t('Knoptekst') }}
                        </Label>
                        <Input
                            id="linkedin-knop-en"
                            v-model="formulier.button_en"
                            :maxlength="props.opties.knopMax"
                            :placeholder="props.standaard.en"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{ $t('Nog :aantal tekens', { aantal: overEn }) }}
                        </p>
                        <InputError :message="fouten.button_en" />
                    </div>

                    <!--
                        De terugval is de Engelse standaardtekst en niet het
                        Nederlands: een Nederlands woord op een Engelse knop
                        leest als een fout.
                    -->
                    <p class="text-xs text-pretty text-muted-foreground">
                        {{
                            $t(
                                'Laat je dit leeg, dan staat er op de Engelse site ":tekst" -- niet je Nederlandse tekst.',
                                { tekst: props.standaard.en },
                            )
                        }}
                    </p>
                </div>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="open = false">
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
