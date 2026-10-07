<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Image as Beeld, Languages, Loader2 } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import LocaleFlag from '@/components/LocaleFlag.vue';
import SegmentToggle from '@/components/SegmentToggle.vue';
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
import { Textarea } from '@/components/ui/textarea';
import { bevestigBewerken } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import website from '@/routes/website';
import overMij from '@/routes/website/over-mij';
import type { OverMijInstelling, OverMijOpties } from '@/types/over-mij';

/**
 * De aparte pagina bewerken: de titel, de inleiding en het verhaal.
 *
 * **Eén taal tegelijk, en dat is een correctie.** Hier stonden eerst alle
 * zes de velden onder elkaar: twee titels, twee inleidingen en twee
 * verhalen van zesduizend tekens. Dat is zo veel tekst in één venster dat
 * je moet scrollen om te zien waar je bent, en bij een lange tekst in twee
 * talen naast elkaar weet je na drie regels niet meer welke kolom welke is.
 *
 * Nu staat er dezelfde taalschakelaar boven als op de publieke site
 * ([`SegmentToggle`](../SegmentToggle.vue) met de vlaggen, zoals
 * [`LocaleToggle`](../site/LocaleToggle.vue) hem gebruikt): je bewerkt het
 * Nederlands óf het Engels. De namen van de talen komen uit dezelfde
 * gedeelde prop als die schakelaar, dus ze kunnen niet uiteen lopen.
 *
 * **Een verborgen veld met een foutmelding is een val**, dus bij een
 * afgekeurde opslag springt de schakelaar naar de taal waar de fout staat.
 * Zonder dat zou je een rode rand te zien krijgen op een veld dat niet in
 * beeld is, en lijkt het venster zomaar niet te willen opslaan.
 *
 * **Alleen de velden van déze pagina.** De samenvatting heeft een eigen
 * venster met een eigen eindpunt; zie OverMijBlokDialoog voor waarom dat
 * niet één verzoek is. De foto heeft er ook een, omdat hij op allebei de
 * versies staat en dus bij geen van de twee hoort -- dat staat met zoveel
 * woorden onderaan, want je ziet hem op de aparte pagina terug en dan is
 * "waar pas ik dit aan" een terechte vraag.
 *
 * **Het schuifje zit hier niet in.** Dat staat op het scherm zelf, want het
 * is één waarde en hoort niet het hele formulier langs de validatie te
 * sturen -- zelfde afspraak als het online-schuifje bij de andere modules.
 *
 * Zie docs/architecture/modules/over-mij.md.
 */
const props = defineProps<{
    instelling: OverMijInstelling;
    opties: OverMijOpties;
    kanVertalen: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const bezig = ref(false);
const fouten = ref<Record<string, string>>({});

const formulier = ref({
    page_title_nl: '',
    page_title_en: '',
    page_intro_nl: '',
    page_intro_en: '',
    story_nl: '',
    story_en: '',
});

const automatisch = ref(false);
const vertaalt = ref(false);
const vertaalFout = ref<string | null>(null);

/* --- Welke taal je bewerkt --------------------------------------------- */

const taal = ref<'nl' | 'en'>('nl');

const page = usePage();

/**
 * De twee talen, met hun naam uit de gedeelde prop.
 *
 * Een taalnaam staat in zijn eigen taal en gaat dus niet door `$t()`:
 * "English" blijft English, ook in een Nederlands portaal.
 */
const talen = computed(() =>
    Object.entries(page.props.locales).map(([code, naam]) => ({
        value: code,
        label: naam,
    })),
);

/**
 * Welke kolommen de velden op dit moment bewerken.
 *
 * Als tabel en niet als twee blokken in het sjabloon: zo staat de opmaak
 * van een veld één keer, en kan de Engelse helft niet stil gaan afwijken
 * van de Nederlandse.
 */
const VELDEN = {
    nl: {
        titel: 'page_title_nl',
        inleiding: 'page_intro_nl',
        verhaal: 'story_nl',
    },
    en: {
        titel: 'page_title_en',
        inleiding: 'page_intro_en',
        verhaal: 'story_en',
    },
} as const;

const veld = computed(() => VELDEN[taal.value]);

/** De velden van één taal, om te zien waar een foutmelding staat. */
const veldenVan = (code: 'nl' | 'en'): string[] => Object.values(VELDEN[code]);

/**
 * Naar de taal springen waar de afgekeurde velden staan.
 *
 * Staat de fout in de taal die al in beeld is, dan blijft de schakelaar
 * staan: wegspringen van het veld waar je net in typte is hinderlijker dan
 * blijven.
 */
const naarDeFout = (ontvangen: Record<string, string>): void => {
    const heeftFout = (code: 'nl' | 'en'): boolean =>
        veldenVan(code).some((naam) => ontvangen[naam] !== undefined);

    if (heeftFout(taal.value)) {
        return;
    }

    if (heeftFout('nl')) {
        taal.value = 'nl';

        return;
    }

    if (heeftFout('en')) {
        taal.value = 'en';
    }
};

/* --- Openen en vullen --------------------------------------------------- */

/** De Engelse velden als één tekst, om te zien of er iets wijzigde. */
const engelsNu = (): string =>
    [
        formulier.value.page_title_en,
        formulier.value.page_intro_en,
        formulier.value.story_en,
    ].join('\u0000');

let engelsBijOpenen = '';

/** Of het venster zichzelf opnieuw opent en de inhoud dus moet blijven. */
let behoudInhoud = false;

const openOpnieuw = (): void => {
    behoudInhoud = true;
    open.value = true;
};

const vulIn = (): void => {
    const i = props.instelling;

    formulier.value = {
        page_title_nl: i.page_title_nl ?? '',
        page_title_en: i.page_title_en ?? '',
        page_intro_nl: i.page_intro_nl ?? '',
        page_intro_en: i.page_intro_en ?? '',
        story_nl: i.story_nl ?? '',
        story_en: i.story_en ?? '',
    };

    engelsBijOpenen = engelsNu();
    automatisch.value = i.automatisch_vertaald;

    /* Je begint in het Nederlands; dat is de tekst waar het Engels van komt. */
    taal.value = 'nl';

    fouten.value = {};
    vertaalFout.value = null;
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

/** De teller loopt mee met de taal die je bewerkt, niet met het Nederlands. */
const verhaalOver = computed(
    () => props.opties.verhaalMax - formulier.value[veld.value.verhaal].length,
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

        const zet = (sleutel: keyof typeof formulier.value): void => {
            const waarde = velden[sleutel];

            if (typeof waarde === 'string') {
                formulier.value[sleutel] = waarde;
            }
        };

        zet('page_title_en');
        zet('page_intro_en');
        zet('story_en');

        automatisch.value = true;
        vertaalFout.value = null;

        /* De vertaling landt in het Engels, dus daar hoort hij ook te staan. */
        taal.value = 'en';
    });
});

onBeforeUnmount(() => stopLuisteren?.());

/** Of er iets te vertalen is: zonder Nederlands verhaal valt er niets om. */
const erIsNederlands = computed(() => formulier.value.story_nl.trim() !== '');

const kanVertalen = computed(() => props.kanVertalen && erIsNederlands.value);

const vertaal = (): void => {
    vertaalt.value = true;
    vertaalFout.value = null;

    router.post(
        website.vertalen().url,
        {
            page_title_nl: formulier.value.page_title_nl,
            page_intro_nl: formulier.value.page_intro_nl,
            story_nl: formulier.value.story_nl,
        },
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
        titel: t('Je aparte pagina aanpassen?'),
        tekst: props.instelling.page_enabled
            ? undefined
            : t(
                  'De pagina staat uit, dus dit wordt wel bewaard maar nog niet op je website gezet.',
              ),
    });

    if (!akkoord) {
        await adem();
        openOpnieuw();

        return;
    }

    bezig.value = true;

    router.put(
        overMij.pagina().url,
        {
            ...formulier.value,
            machine_translated:
                automatisch.value && engelsNu() === engelsBijOpenen,
        },
        {
            preserveScroll: true,
            onError: (ontvangen) => {
                fouten.value = ontvangen;
                naarDeFout(ontvangen);
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
                <DialogTitle>{{ $t('Op je aparte pagina') }}</DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Dit komt alleen op /over-mij te staan, achter een knop onder je korte stuk. Hier is ruimte voor je hele verhaal.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <!--
                De taalschakelaar. Je bewerkt één taal tegelijk; zes velden
                onder elkaar waren te veel voor één venster.
            -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <SegmentToggle
                    v-model="taal"
                    :options="talen"
                    :groep-label="$t('Welke taal je bewerkt')"
                >
                    <template #voor="{ optie }">
                        <LocaleFlag
                            :locale="optie.value"
                            size="md"
                            aria-hidden="true"
                        />
                    </template>
                </SegmentToggle>

                <Button
                    v-if="taal === 'en' && kanVertalen"
                    type="button"
                    variant="ghost"
                    size="sm"
                    :disabled="vertaalt"
                    @click="vertaal"
                >
                    <Loader2 v-if="vertaalt" class="size-3.5 animate-spin" />
                    <Languages v-else class="size-3.5" />
                    {{ $t('Vertaal alles') }}
                </Button>
            </div>

            <!--
                Zonder Nederlands verhaal valt er niets te vertalen, en de
                knop hierboven staat er dan niet. Dat moet je wel gezegd
                worden: het Nederlands staat nu achter de schakelaar, dus je
                ziet niet dat het leeg is.
            -->
            <p
                v-if="taal === 'en' && props.kanVertalen && !erIsNederlands"
                class="text-xs text-pretty text-muted-foreground"
            >
                {{
                    $t(
                        'Vul eerst je Nederlandse verhaal in. Daarna kun je het hier in één klik laten vertalen.',
                    )
                }}
            </p>

            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="paginatitel">
                        {{ $t('Titel van de pagina') }}
                    </Label>
                    <Input
                        id="paginatitel"
                        v-model="formulier[veld.titel]"
                        :maxlength="120"
                        :placeholder="
                            taal === 'nl'
                                ? $t('Bijvoorbeeld: Even voorstellen')
                                : undefined
                        "
                        v-focus
                    />
                    <InputError :message="fouten[veld.titel]" />
                    <p class="text-xs text-pretty text-muted-foreground">
                        {{
                            $t(
                                'Laat je de titel leeg, dan gebruikt de pagina de titel van het blok op je voorpagina. Een eigen titel leest beter: dan merkt een bezoeker dat hij is verdergegaan.',
                            )
                        }}
                    </p>
                </div>

                <div class="grid gap-2 border-t border-border pt-4">
                    <Label for="paginaintro">{{ $t('Inleiding') }}</Label>
                    <Textarea
                        id="paginaintro"
                        v-model="formulier[veld.inleiding]"
                        :maxlength="300"
                        rows="3"
                    />
                    <InputError :message="fouten[veld.inleiding]" />
                </div>

                <div class="grid gap-2 border-t border-border pt-4">
                    <Label for="paginaverhaal">{{ $t('Je verhaal') }}</Label>
                    <Textarea
                        id="paginaverhaal"
                        v-model="formulier[veld.verhaal]"
                        :maxlength="props.opties.verhaalMax"
                        rows="12"
                        :placeholder="
                            taal === 'nl'
                                ? $t(
                                      'Een witregel tussen twee stukken maakt er op je website twee alinea\'s van.',
                                  )
                                : undefined
                        "
                    />
                    <InputError :message="fouten[veld.verhaal]" />
                    <InputError :message="vertaalFout ?? undefined" />
                    <p class="text-xs text-muted-foreground">
                        {{ $t(':aantal tekens over', { aantal: verhaalOver }) }}
                    </p>

                    <p
                        v-if="taal === 'nl'"
                        class="text-xs text-pretty text-muted-foreground"
                    >
                        {{
                            $t(
                                'Zonder verhaal bestaat de pagina niet, ook niet als de schakelaar aanstaat. Een pagina met alleen een kop, met een knop ernaartoe op je voorpagina, is erger dan geen pagina.',
                            )
                        }}
                    </p>
                    <p v-else class="text-xs text-pretty text-muted-foreground">
                        {{
                            $t(
                                'Laat je dit leeg, dan bestaat je aparte pagina niet op de Engelse versie van je website. We zetten er met opzet geen Nederlands verhaal neer voor een Engelse bezoeker.',
                            )
                        }}
                    </p>
                </div>
            </div>

            <!--
                Er is één foto en die hoort bij het blok op de voorpagina. Hij
                staat ook op de aparte pagina, dus zonder deze regel zoek je
                hem hier.
            -->
            <p
                class="flex items-start gap-2 border-t border-border pt-4 text-xs text-pretty text-muted-foreground"
            >
                <Beeld class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                <span>
                    {{
                        $t(
                            'Je foto staat hier niet bij: er is er één, en die staat op je voorpagina én op deze pagina. Daarom heeft hij een eigen knop "Foto aanpassen" bovenaan het scherm.',
                        )
                    }}
                </span>
            </p>

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
