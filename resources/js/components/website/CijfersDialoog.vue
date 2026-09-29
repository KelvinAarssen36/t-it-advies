<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Loader2, Sparkles, TriangleAlert } from '@lucide/vue';
import { ref, watch } from 'vue';
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
import ervaring from '@/routes/website/ervaring';
import type { ErvaringCijferRij } from '@/types/ervaring';

/**
 * De drie cijfers boven de tijdlijn.
 *
 * **Leeg is hier geen ontbrekende waarde maar een keuze**: het betekent
 * "reken het zelf uit". Dat is ook de stand waarin een cijfer vanzelf
 * blijft kloppen zodra er een functie bij komt, en daarom staat het
 * berekende getal als tijdelijke tekst in het lege veld. Zo hoef je
 * "automatisch" niet te geloven -- je ziet wat er komt te staan.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
const props = defineProps<{ cijfers: ErvaringCijferRij[] }>();

const open = defineModel<boolean>('open', { required: true });

const bezig = ref(false);
const fouten = ref<Record<string, string>>({});

/** De ingevulde waarden als tekst; een lege string is "automatisch". */
const waarden = ref<Record<string, string>>({});

const vulIn = (): void => {
    waarden.value = Object.fromEntries(
        props.cijfers.map((cijfer) => [
            cijfer.key,
            cijfer.waarde === null ? '' : String(cijfer.waarde),
        ]),
    );

    fouten.value = {};
};

/**
 * Of het venster zichzelf opnieuw opent en de inhoud dus moet blijven.
 *
 * Hetzelfde verhaal als in ErvaringDialoog: het venster gaat dicht en weer
 * open bij een bevestiging die de klant afbreekt en bij een fout van de
 * server. Zonder deze vlag vult het zich dan opnieuw uit `props.cijfers`
 * -- en dan is zijn invoer weg **en** wordt de foutmelding die net was
 * gezet meteen weer gewist, dus hij ziet niet eens waaróm het misging.
 */
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

    vulIn();
});

/**
 * Wijkt het ingevulde cijfer af van wat wij tellen?
 *
 * **Dit is de val van een ingevuld getal**: het blijft staan. Komt er een
 * functie bij, of gaat er een jaar voorbij, dan klopt het niet meer en
 * merkt niemand dat -- want het staat op de voorpagina waar de klant zelf
 * nooit naar kijkt. Vandaar dat het scherm het hem vertelt.
 */
const looptAchter = (cijfer: ErvaringCijferRij): boolean => {
    const ingevuld = waarden.value[cijfer.key]?.trim() ?? '';

    return ingevuld !== '' && Number(ingevuld) !== cijfer.berekend;
};

/** Alles terug op automatisch, in één klik. */
const allesAutomatisch = (): void => {
    for (const cijfer of props.cijfers) {
        waarden.value[cijfer.key] = '';
    }
};

const opslaan = async (): Promise<void> => {
    /*
     * Het venster gaat dicht vóór de bevestiging in plaats van eronder te
     * liggen; zie de toelichting in pages/website/Indeling.vue.
     */
    open.value = false;
    await adem();

    const akkoord = await bevestigBewerken({
        titel: t('De cijfers op je website aanpassen?'),
    });

    if (!akkoord) {
        await adem();
        openOpnieuw();

        return;
    }

    bezig.value = true;

    router.put(
        ervaring.cijfers().url,
        {
            waarden: Object.fromEntries(
                props.cijfers.map((cijfer) => [
                    cijfer.key,
                    // Leeg wordt `null` en niet "": dat is het verschil
                    // tussen "reken het uit" en "zet er niets neer".
                    waarden.value[cijfer.key]?.trim() === ''
                        ? null
                        : Number(waarden.value[cijfer.key]),
                ]),
            ),
        },
        {
            preserveScroll: true,
            onError: (ontvangen) => {
                fouten.value = ontvangen;

                // Opnieuw open mét de foutmelding en de ingetypte
                // waarden. Zou hier `open.value = true` staan, dan wist
                // het venster allebei meteen weer.
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
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>{{ $t('Cijfers boven je tijdlijn') }}</DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Laat een veld leeg en we rekenen het uit op basis van je tijdlijn. Vul je iets in, dan staat dat er.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4">
                <div
                    v-for="cijfer in props.cijfers"
                    :key="cijfer.key"
                    class="grid gap-1.5"
                >
                    <Label :for="`cijfer-${cijfer.key}`">
                        {{ cijfer.label }}
                    </Label>

                    <div class="flex items-center gap-2">
                        <Input
                            :id="`cijfer-${cijfer.key}`"
                            v-model="waarden[cijfer.key]"
                            type="number"
                            inputmode="numeric"
                            min="0"
                            max="9999"
                            class="max-w-28"
                            :placeholder="String(cijfer.berekend)"
                        />

                        <span
                            v-if="waarden[cijfer.key]?.trim() === ''"
                            class="brand-ervaring-merk"
                            data-automatisch
                        >
                            <Sparkles class="size-3" />
                            {{
                                $t('Automatisch: :aantal', {
                                    aantal: cijfer.berekend,
                                })
                            }}
                        </span>
                    </div>

                    <!--
                        Het cijfer loopt achter op de werkelijkheid. Dat is
                        de val van een ingevuld getal: het blijft staan,
                        ook als er een functie bij komt of als er een jaar
                        voorbijgaat. Automatisch bijwerken doet hij alleen
                        als het veld leeg is, dus dat moet hier staan.
                    -->
                    <p
                        v-if="looptAchter(cijfer)"
                        class="brand-cijfer-waarschuwing"
                    >
                        <TriangleAlert class="size-3.5 shrink-0" />
                        <span>
                            {{
                                $t(
                                    'Wij tellen er :aantal. Maak het veld leeg om dit weer vanzelf te laten bijwerken.',
                                    { aantal: cijfer.berekend },
                                )
                            }}
                        </span>
                    </p>

                    <p class="text-xs text-pretty text-muted-foreground">
                        {{ cijfer.omschrijving }}
                    </p>

                    <InputError :message="fouten[`waarden.${cijfer.key}`]" />
                </div>
            </div>

            <DialogFooter class="gap-2 sm:justify-between sm:gap-2">
                <Button
                    variant="ghost"
                    class="gap-2"
                    :disabled="bezig"
                    @click="allesAutomatisch"
                >
                    <Sparkles class="size-4" />
                    {{ $t('Alles automatisch') }}
                </Button>

                <div class="flex gap-2">
                    <Button
                        variant="ghost"
                        :disabled="bezig"
                        @click="open = false"
                    >
                        {{ $t('Annuleren') }}
                    </Button>
                    <Button
                        variant="bewerken"
                        class="gap-2"
                        :disabled="bezig"
                        @click="opslaan"
                    >
                        <Loader2 v-if="bezig" class="size-4 animate-spin" />
                        {{ $t('Opslaan') }}
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
