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
import footer from '@/routes/website/footer';
import type { VoetInstelling, VoetOpties } from '@/types/voettekst';

/**
 * De zin onder het merk in de voet.
 *
 * Eén veld, twee talen. Een `Input` en geen `Textarea`, en dat is de regel
 * zelf en geen opmaakkeuze: een zin die niet op één regel past is in een
 * voet geen zin meer maar een alinea.
 *
 * **Dit venster stuurt alleen zijn eigen velden.** De voet heeft er drie,
 * elk met een eigen eindpunt. Zie FooterController voor waarom dat zo moet.
 *
 * Zie docs/architecture/modules/voettekst.md.
 */
const props = defineProps<{
    instelling: VoetInstelling;
    opties: VoetOpties;
    kanVertalen: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const bezig = ref(false);
const fouten = ref<Record<string, string>>({});

const formulier = ref({
    tagline_nl: '',
    tagline_en: '',
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
        tagline_nl: props.instelling.tagline_nl ?? '',
        tagline_en: props.instelling.tagline_en ?? '',
    };

    engelsBijOpenen = formulier.value.tagline_en;
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
    () => props.opties.taglineMax - formulier.value.tagline_nl.length,
);

const overEn = computed(
    () => props.opties.taglineMax - formulier.value.tagline_en.length,
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

        const waarde = velden.tagline_en;

        if (typeof waarde === 'string') {
            formulier.value.tagline_en = waarde;
        }

        automatisch.value = true;
        vertaalFout.value = null;
    });
});

onBeforeUnmount(() => stopLuisteren?.());

const kanVertalen = computed(
    () => props.kanVertalen && formulier.value.tagline_nl.trim() !== '',
);

const vertaal = async (): Promise<void> => {
    if (!automatisch.value && formulier.value.tagline_en.trim() !== '') {
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
        { tagline_nl: formulier.value.tagline_nl },
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
        titel: t('De zin onder je logo aanpassen?'),
    });

    if (!akkoord) {
        await adem();
        openOpnieuw();

        return;
    }

    bezig.value = true;

    router.put(
        footer.tekst().url,
        {
            ...formulier.value,
            machine_translated:
                automatisch.value &&
                formulier.value.tagline_en === engelsBijOpenen,
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
                <DialogTitle>{{ $t('De zin onder je logo') }}</DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Eén regel onderaan elke pagina, direct onder je naam. Mag leeg blijven.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-5" @submit.prevent="opslaan">
                <div class="grid gap-2">
                    <Label for="voet-zin">{{ $t('Zin onder je logo') }}</Label>
                    <Input
                        id="voet-zin"
                        v-model="formulier.tagline_nl"
                        :maxlength="props.opties.taglineMax"
                        :placeholder="
                            $t('IT-advies en realisatie. Van advies tot bouw.')
                        "
                    />
                    <p class="text-xs text-muted-foreground">
                        {{ $t('Nog :aantal tekens', { aantal: overNl }) }}
                    </p>
                    <InputError :message="fouten.tagline_nl" />
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
                        <Label for="voet-zin-en">
                            {{ $t('Zin onder je logo') }}
                        </Label>
                        <Input
                            id="voet-zin-en"
                            v-model="formulier.tagline_en"
                            :maxlength="props.opties.taglineMax"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{ $t('Nog :aantal tekens', { aantal: overEn }) }}
                        </p>
                        <InputError :message="fouten.tagline_en" />
                    </div>

                    <!--
                        Geen terugval hier, anders dan bij een label of een
                        nummer: deze zin staat direct onder een Engels menu
                        op een Engelse pagina, en één Nederlandse regel
                        daartussen leest als een fout.
                    -->
                    <p class="text-xs text-pretty text-muted-foreground">
                        {{
                            $t(
                                'Laat je dit leeg, dan staat er op de Engelse site geen zin onder je naam -- het Nederlands komt er niet voor in de plaats.',
                            )
                        }}
                    </p>
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
