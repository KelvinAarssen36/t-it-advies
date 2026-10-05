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
import { Switch } from '@/components/ui/switch';
import {
    bevestigAanmaken,
    bevestigBewerken,
    bevestigVerwijderen,
} from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import website from '@/routes/website';
import contact from '@/routes/website/contact';
import type { OnderwerpRij } from '@/types/contact';

/**
 * Een contactonderwerp aanmaken of bewerken.
 *
 * **Het kleinste venster van alle modules**: één tekstveld, twee keer.
 * Een onderwerp is een regel in een keuzelijst en geen inhoud, dus er is
 * niets om er een tweede stap voor te maken. Het Engels staat onder een
 * streep, precies zoals bij de kop van een blok.
 *
 * Zie docs/architecture/modules/contact.md.
 */
const props = defineProps<{
    item: OnderwerpRij | null;
    kanVertalen: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const bewerkt = computed(() => props.item !== null);

const bezig = ref(false);
const fouten = ref<Record<string, string>>({});

const formulier = ref({
    label_nl: '',
    label_en: '',
    published: true,
    featured: false,
});

/** Of de klant hem meteen online wil; alleen bij een nieuwe. */
const meteenOnline = ref(true);

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
    const item = props.item;

    formulier.value = {
        label_nl: item?.label_nl ?? '',
        label_en: item?.label_en ?? '',
        published: item?.published ?? true,
        featured: item?.featured ?? false,
    };

    engelsBijOpenen = formulier.value.label_en;
    automatisch.value = item?.automatischVertaald ?? false;
    meteenOnline.value = true;
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

        if (typeof velden.label_en === 'string') {
            formulier.value.label_en = velden.label_en;
        }

        automatisch.value = true;
        vertaalFout.value = null;
    });
});

onBeforeUnmount(() => stopLuisteren?.());

const kanVertalen = computed(
    () => props.kanVertalen && formulier.value.label_nl.trim() !== '',
);

const vertaal = async (): Promise<void> => {
    if (!automatisch.value && formulier.value.label_en.trim() !== '') {
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
        { label_nl: formulier.value.label_nl },
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

    let akkoord: boolean;

    if (bewerkt.value) {
        akkoord = await bevestigBewerken({
            titel: t('Dit onderwerp aanpassen?'),
        });
    } else {
        meteenOnline.value = true;

        akkoord = await bevestigAanmaken({
            titel: t('Dit onderwerp toevoegen?'),
            keuze: {
                model: meteenOnline,
                label: t('Meteen op je website zetten'),
                tekst: t(
                    'Zet je hem uit, dan staat hij hier klaar en kunnen bezoekers hem pas kiezen als je hem online zet.',
                ),
            },
        });

        formulier.value.published = meteenOnline.value;
    }

    if (!akkoord) {
        await adem();
        openOpnieuw();

        return;
    }

    bezig.value = true;

    const lading = {
        ...formulier.value,
        automatisch_vertaald:
            automatisch.value && formulier.value.label_en === engelsBijOpenen,
    };

    const opties = {
        preserveScroll: true,
        onError: (ontvangen: Record<string, string>) => {
            fouten.value = ontvangen;
            openOpnieuw();
        },
        onFinish: () => {
            bezig.value = false;
        },
    };

    /*
     * Twee volledige aanroepen en niet `const verzoek = bewerkt ?
     * router.put : router.post`: dat laatste verliest zijn `this` en doet
     * dan stilletjes niets. Zie DienstDialoog.
     */
    if (bewerkt.value) {
        router.put(contact.update(props.item!.id).url, lading, opties);

        return;
    }

    router.post(contact.store().url, lading, opties);
};

const adem = (): Promise<void> =>
    new Promise((klaar) => setTimeout(klaar, 200));
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{
                        bewerkt
                            ? $t('Onderwerp aanpassen')
                            : $t('Nieuw onderwerp')
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Dit is een van de keuzes die je bezoeker krijgt bij "Onderwerp". Houd het kort -- het is een regel in een lijstje, geen zin.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="onderwerp-nl" verplicht>
                        <LocaleFlag locale="nl" size="sm" />
                        {{ $t('Onderwerp') }}
                    </Label>
                    <Input
                        id="onderwerp-nl"
                        v-model="formulier.label_nl"
                        :maxlength="80"
                        :placeholder="$t('Bijvoorbeeld: Vrijblijvend gesprek')"
                        v-focus
                    />
                    <InputError :message="fouten.label_nl" />
                </div>

                <div class="border-t border-border pt-4">
                    <div class="grid gap-2">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <Label for="onderwerp-en">
                                <LocaleFlag locale="en" size="sm" />
                                {{ $t('Onderwerp') }}
                            </Label>

                            <Button
                                v-if="kanVertalen"
                                type="button"
                                variant="ghost"
                                size="sm"
                                :disabled="vertaalt"
                                @click="vertaal"
                            >
                                <Loader2
                                    v-if="vertaalt"
                                    class="size-3.5 animate-spin"
                                />
                                <Languages v-else class="size-3.5" />
                                {{ $t('Vertaal') }}
                            </Button>
                        </div>

                        <Input
                            id="onderwerp-en"
                            v-model="formulier.label_en"
                            :maxlength="80"
                            :placeholder="
                                $t('Bijvoorbeeld: Informal conversation')
                            "
                        />
                        <InputError :message="fouten.label_en" />
                        <InputError :message="vertaalFout ?? undefined" />

                        <p class="text-xs text-pretty text-muted-foreground">
                            {{
                                $t(
                                    'Laat je dit leeg, dan ziet een Engelse bezoeker de Nederlandse naam. Een keuzelijst met een gat erin is geen keuzelijst.',
                                )
                            }}
                        </p>
                    </div>
                </div>

                <!--
                    Uitlichten.

                    **Dit gaat over de postbus en niet over de website.**
                    Een aanvraag met dit onderwerp springt eruit onder
                    Beheer → Aanvragen; op het formulier verandert er niets.
                    Daar stond eerst een snelkeuze bóven de keuzelijst, en
                    dat was een verkeerde lezing: het formulier is voor de
                    bezoeker en hoort een neutrale lijst te blijven.

                    **Een eigen vinkje en niet "zet hem bovenaan".** Dat
                    laatste kan de eigenaar al met slepen, maar dan is hij
                    zijn volgorde kwijt zodra hij iets anders wil
                    uitlichten. Zo blijft de volgorde van hem en is dit één
                    vinkje dat je net zo makkelijk weer uitzet.
                -->
                <div class="border-t border-border pt-4">
                    <label class="flex cursor-pointer items-start gap-3">
                        <Switch v-model="formulier.featured" />
                        <span class="space-y-1">
                            <span class="block text-sm font-medium">
                                {{ $t('Dit onderwerp uitlichten') }}
                            </span>
                            <span
                                class="block text-xs text-pretty text-muted-foreground"
                            >
                                {{
                                    $t(
                                        'Dit verandert niets op je website. Het zorgt ervoor dat een aanvraag met dit onderwerp eruit springt onder Beheer → Aanvragen, met een sterretje en een gele tint. Handig voor spoed, zodat je in één blik ziet wat voorgaat.',
                                    )
                                }}
                            </span>
                        </span>
                    </label>
                </div>
            </div>

            <DialogFooter>
                <Button type="button" variant="secondary" @click="open = false">
                    {{ $t('Annuleren') }}
                </Button>
                <Button
                    type="button"
                    :variant="bewerkt ? 'bewerken' : 'aanmaken'"
                    :disabled="bezig || formulier.label_nl.trim() === ''"
                    @click="opslaan"
                >
                    {{ bezig ? $t('Bezig...') : $t('Opslaan') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
