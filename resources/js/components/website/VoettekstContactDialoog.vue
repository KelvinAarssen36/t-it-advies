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
 * Telefoonnummer en werkgebied in de voet.
 *
 * **Het e-mailadres staat er wel bij, maar niet als veld.** Het ligt vast
 * in de instellingen van de site, omdat het contactformulier daarheen
 * mailt; kon de eigenaar het hier wijzigen, dan kon hij zijn eigen
 * formulier omleiden zonder dat te zien. Het staat er dus ter informatie.
 *
 * **Het werkgebied is vertaalbaar en het nummer niet.** "Noord-Brabant en
 * omstreken" bevat Nederlandse woorden; een telefoonnummer niet.
 *
 * Zie docs/architecture/modules/voettekst.md.
 */
const props = defineProps<{
    instelling: VoetInstelling;
    opties: VoetOpties;
    email: string;
    kanVertalen: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const bezig = ref(false);
const fouten = ref<Record<string, string>>({});

const formulier = ref({
    phone: '',
    region_nl: '',
    region_en: '',
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
        phone: props.instelling.phone ?? '',
        region_nl: props.instelling.region_nl ?? '',
        region_en: props.instelling.region_en ?? '',
    };

    engelsBijOpenen = formulier.value.region_en;
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

        const waarde = velden.region_en;

        if (typeof waarde === 'string') {
            formulier.value.region_en = waarde;
        }

        automatisch.value = true;
        vertaalFout.value = null;
    });
});

onBeforeUnmount(() => stopLuisteren?.());

const kanVertalen = computed(
    () => props.kanVertalen && formulier.value.region_nl.trim() !== '',
);

const vertaal = async (): Promise<void> => {
    if (!automatisch.value && formulier.value.region_en.trim() !== '') {
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
        { region_nl: formulier.value.region_nl },
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
        titel: t('Je contactgegevens in de voet aanpassen?'),
    });

    if (!akkoord) {
        await adem();
        openOpnieuw();

        return;
    }

    bezig.value = true;

    router.put(
        footer.contact().url,
        {
            ...formulier.value,
            machine_translated:
                automatisch.value &&
                formulier.value.region_en === engelsBijOpenen,
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
                <DialogTitle>
                    {{ $t('Je contactgegevens in de voet') }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Wat er onderaan elke pagina onder "Contact" staat. Alles mag leeg blijven; wat je niet invult, staat er niet.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-5" @submit.prevent="opslaan">
                <!--
                    Het adres staat er ter informatie en niet als veld. Zie
                    de toelichting bovenin dit bestand.
                -->
                <div class="grid gap-2">
                    <Label>{{ $t('E-mailadres') }}</Label>
                    <p class="text-sm font-medium">{{ props.email }}</p>
                    <p class="text-xs text-pretty text-muted-foreground">
                        {{
                            $t(
                                'Dit adres ligt vast: je contactformulier mailt hierheen. Wil je het wijzigen, laat het dan aanpassen in de instellingen van de site.',
                            )
                        }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="voet-telefoon">
                        {{ $t('Telefoonnummer') }}
                    </Label>
                    <Input
                        id="voet-telefoon"
                        v-model="formulier.phone"
                        :maxlength="props.opties.telefoonMax"
                        inputmode="tel"
                        :placeholder="$t('06 12 34 56 78')"
                    />
                    <p class="text-xs text-pretty text-muted-foreground">
                        {{
                            $t(
                                'Schrijf het zoals je wilt dat het er staat -- met of zonder spaties. Bezoekers kunnen erop tikken om te bellen.',
                            )
                        }}
                    </p>
                    <InputError :message="fouten.phone" />
                </div>

                <div class="grid gap-2">
                    <Label for="voet-plaats">
                        {{ $t('Plaats of werkgebied') }}
                    </Label>
                    <Input
                        id="voet-plaats"
                        v-model="formulier.region_nl"
                        :maxlength="props.opties.plaatsMax"
                        :placeholder="$t('Noord-Brabant')"
                    />
                    <p class="text-xs text-pretty text-muted-foreground">
                        {{
                            $t(
                                'Geen volledig adres: dat hoort bij een winkel. Een plaats of regio zegt een bezoeker genoeg.',
                            )
                        }}
                    </p>
                    <InputError :message="fouten.region_nl" />
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
                        <Label for="voet-plaats-en">
                            {{ $t('Plaats of werkgebied') }}
                        </Label>
                        <Input
                            id="voet-plaats-en"
                            v-model="formulier.region_en"
                            :maxlength="props.opties.plaatsMax"
                        />
                        <InputError :message="fouten.region_en" />
                    </div>

                    <!--
                        Hier wél terugval, anders dan bij de zin onder het
                        logo: een plaatsnaam is geen zin, en hem weglaten zou
                        betekenen dat een Engelse bezoeker niet weet waar je
                        zit.
                    -->
                    <p class="text-xs text-pretty text-muted-foreground">
                        {{
                            $t(
                                'Laat je dit leeg, dan staat het Nederlands er ook op de Engelse site. Bij een plaatsnaam is dat meestal precies goed.',
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
