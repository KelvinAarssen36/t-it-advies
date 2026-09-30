<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Languages, Loader2 } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
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
import kop from '@/routes/website/kop';
import type { SiteKopRij } from '@/types/ervaring';

/**
 * De drie teksten van de kop, in één venster.
 *
 * **Waarom dat één venster is.** Op de website staan het opschrift, de
 * titel en de zin eronder in hetzelfde blok. Ze los laten bewerken zou
 * betekenen dat de eigenaar drie keer bevestigt voor één zichtbare
 * verandering, en dat er een tussenstand kan bestaan waarin een deel live
 * staat.
 *
 * De opbouw is die van CijfersDialoog: eerst het Nederlands, dan het
 * Engels, met de vertaalknop eronder. Het Engels staat niet in een tweede
 * stap zoals bij een ervaring -- het zijn hier drie korte regels, en een
 * stap ervoor bouwen kost meer klikken dan het scheelt.
 *
 * Zie docs/architecture/modules/kop.md.
 */
const props = defineProps<{
    kop: SiteKopRij;
    kanVertalen: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const bezig = ref(false);
const fouten = ref<Record<string, string>>({});

/** De zes velden, altijd als tekst zodat een leeg veld "" is. */
const tekst = ref({
    eyebrow_nl: '',
    eyebrow_en: '',
    title_nl: '',
    title_en: '',
    intro_nl: '',
    intro_en: '',
});

/**
 * Het Engels zoals het bij het openen van het venster stond.
 *
 * Hiermee bepalen we of het merkje "automatisch vertaald" nog klopt. Zou
 * je alleen naar de vlag uit de database kijken, dan verdwijnt dat merkje
 * bij elke opslag -- ook als de klant het Engels niet heeft aangeraakt.
 */
let engelsBijOpenen = { eyebrow_en: '', title_en: '', intro_en: '' };

/** Of het Engels dat er nu staat van de vertaaldienst komt. */
const automatisch = ref(false);

const vertaalt = ref(false);
const vertaalFout = ref<string | null>(null);

/**
 * Of het venster zichzelf opnieuw opent en de inhoud dus moet blijven.
 *
 * Hetzelfde verhaal als in CijfersDialoog: het venster gaat dicht en weer
 * open bij een bevestiging die de klant afbreekt en bij een fout van de
 * server. Zonder deze vlag vult het zich dan opnieuw uit de props -- en
 * dan is zijn invoer weg **en** wordt de foutmelding die net was gezet
 * meteen weer gewist, dus hij ziet niet eens waaróm het misging.
 */
let behoudInhoud = false;

const openOpnieuw = (): void => {
    behoudInhoud = true;
    open.value = true;
};

const vulIn = (): void => {
    tekst.value = {
        eyebrow_nl: props.kop.eyebrow_nl,
        eyebrow_en: props.kop.eyebrow_en ?? '',
        title_nl: props.kop.title_nl,
        title_en: props.kop.title_en ?? '',
        intro_nl: props.kop.intro_nl ?? '',
        intro_en: props.kop.intro_en ?? '',
    };

    engelsBijOpenen = {
        eyebrow_en: tekst.value.eyebrow_en,
        title_en: tekst.value.title_en,
        intro_en: tekst.value.intro_en,
    };

    automatisch.value = props.kop.automatisch_vertaald;
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

/* --- Automatisch vertalen --------------------------------------------- */

/**
 * De vertaaldienst antwoordt via een flash-prop en niet met JSON.
 *
 * Zo blijft er één HTTP-cliënt in het spel en hoeft de route niets terug
 * te geven wat de pagina niet al kent. Hetzelfde mechanisme als in
 * CijfersDialoog; zie App\Support\Toast.
 */
let stopLuisteren: (() => void) | undefined;

onMounted(() => {
    stopLuisteren = router.on('flash', (gebeurtenis) => {
        const velden = (gebeurtenis as CustomEvent).detail?.flash?.vertaling as
            | Record<string, string>
            | undefined;

        if (!velden || !open.value) {
            return;
        }

        if (typeof velden.eyebrow_en === 'string') {
            tekst.value.eyebrow_en = velden.eyebrow_en;
        }

        if (typeof velden.title_en === 'string') {
            tekst.value.title_en = velden.title_en;
        }

        if (typeof velden.intro_en === 'string') {
            tekst.value.intro_en = velden.intro_en;
        }

        automatisch.value = true;
        vertaalFout.value = null;
    });
});

onBeforeUnmount(() => stopLuisteren?.());

/**
 * Staat er Engels dat de knop zou overschrijven?
 *
 * Wat de vertaaldienst zelf heeft neergezet telt niet mee: dat opnieuw
 * laten vertalen kost de klant niets. Het gaat om tekst die hij zelf
 * heeft getypt of aangepast.
 */
const eigenEngels = (): boolean =>
    !automatisch.value &&
    [tekst.value.eyebrow_en, tekst.value.title_en, tekst.value.intro_en].some(
        (waarde) => waarde.trim() !== '',
    );

const vertaal = async (): Promise<void> => {
    /*
     * Eerst waarschuwen als er Engels staat. De knop gooit dat zonder
     * pardon weg, en er is geen ongedaan maken in dit formulier.
     */
    if (eigenEngels()) {
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
        {
            eyebrow_nl: tekst.value.eyebrow_nl,
            title_nl: tekst.value.title_nl,
            intro_nl: tekst.value.intro_nl,
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
    /*
     * Het venster gaat dicht vóór de bevestiging in plaats van eronder te
     * liggen; zie de toelichting in pages/website/Indeling.vue.
     */
    open.value = false;
    await adem();

    const akkoord = await bevestigBewerken({
        titel: t('De kop van je landingspagina aanpassen?'),
    });

    if (!akkoord) {
        await adem();
        openOpnieuw();

        return;
    }

    bezig.value = true;

    /*
     * Het merkje blijft alleen staan als het Engels sinds het openen niet
     * met de hand is aangeraakt. Zonder die vergelijking verdween het bij
     * elke opslag, ook als er niets aan veranderd was.
     */
    const engelsOngemoeid =
        tekst.value.eyebrow_en === engelsBijOpenen.eyebrow_en &&
        tekst.value.title_en === engelsBijOpenen.title_en &&
        tekst.value.intro_en === engelsBijOpenen.intro_en;

    router.put(
        kop.update().url,
        {
            ...tekst.value,
            automatisch_vertaald: automatisch.value && engelsOngemoeid,
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

/**
 * Even wachten tot een venster is weggeanimeerd voordat het volgende komt.
 *
 * Twee vensters die elkaar overlappen zien er niet alleen rommelig uit: de
 * dialooglaag zet tijdens het openen de rest van de pagina op
 * `pointer-events: none`, en twee die tegelijk openen en sluiten kunnen
 * dat op elkaar achterlaten. Dan is de pagina daarna niet meer
 * aanklikbaar.
 */
const adem = (): Promise<void> =>
    new Promise((klaar) => setTimeout(klaar, 200));
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="max-h-[85vh] brand-scrollbar overflow-y-auto sm:max-w-2xl"
        >
            <DialogHeader>
                <DialogTitle>
                    {{ $t('De kop van je landingspagina') }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Het opschrift, de grote titel en de zin eronder. Op je website staat dit in hetzelfde blok, dus je slaat het in één keer op.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <!-- ------------------- Het Nederlands ------------------- -->
            <div class="grid gap-4">
                <div class="grid gap-1.5">
                    <Label for="kop-eyebrow-nl" verplicht>
                        <LocaleFlag locale="nl" size="sm" />
                        {{ $t('Opschrift') }}
                    </Label>
                    <Input
                        id="kop-eyebrow-nl"
                        v-model="tekst.eyebrow_nl"
                        maxlength="60"
                    />
                    <p class="text-xs text-muted-foreground">
                        {{
                            $t(
                                'De kleine regel in hoofdletters boven de titel. Op een telefoon staat dit onder je naam op het visitekaartje.',
                            )
                        }}
                    </p>
                    <InputError :message="fouten.eyebrow_nl" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="kop-title-nl" verplicht>
                        <LocaleFlag locale="nl" size="sm" />
                        {{ $t('Titel') }}
                    </Label>
                    <Input
                        id="kop-title-nl"
                        v-model="tekst.title_nl"
                        maxlength="120"
                    />
                    <p class="text-xs text-muted-foreground">
                        {{
                            $t(
                                'De grote zin die letter voor letter wordt ingetikt. Houd hem kort; op een telefoon staat een lange zin al snel op vijf regels.',
                            )
                        }}
                    </p>
                    <InputError :message="fouten.title_nl" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="kop-intro-nl">
                        <LocaleFlag locale="nl" size="sm" />
                        {{ $t('Zin eronder') }}
                    </Label>
                    <Input
                        id="kop-intro-nl"
                        v-model="tekst.intro_nl"
                        maxlength="300"
                    />
                    <p class="text-xs text-muted-foreground">
                        {{
                            $t(
                                'Laat leeg als je alleen een titel wilt; dan staat er gewoon geen zin onder.',
                            )
                        }}
                    </p>
                    <InputError :message="fouten.intro_nl" />
                </div>

                <!-- --------------------- Het Engels -------------------- -->
                <div class="grid gap-1.5 border-t border-border pt-4">
                    <Label for="kop-eyebrow-en">
                        <LocaleFlag locale="en" size="sm" />
                        {{ $t('Opschrift') }}
                    </Label>
                    <Input
                        id="kop-eyebrow-en"
                        v-model="tekst.eyebrow_en"
                        maxlength="60"
                    />
                    <InputError :message="fouten.eyebrow_en" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="kop-title-en">
                        <LocaleFlag locale="en" size="sm" />
                        {{ $t('Titel') }}
                    </Label>
                    <Input
                        id="kop-title-en"
                        v-model="tekst.title_en"
                        maxlength="120"
                    />
                    <InputError :message="fouten.title_en" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="kop-intro-en">
                        <LocaleFlag locale="en" size="sm" />
                        {{ $t('Zin eronder') }}
                    </Label>
                    <Input
                        id="kop-intro-en"
                        v-model="tekst.intro_en"
                        maxlength="300"
                    />
                    <InputError :message="fouten.intro_en" />
                </div>

                <div v-if="props.kanVertalen" class="grid gap-2">
                    <div>
                        <Button
                            variant="outline"
                            size="sm"
                            class="gap-2"
                            :disabled="vertaalt || tekst.title_nl.trim() === ''"
                            @click="vertaal"
                        >
                            <Loader2
                                v-if="vertaalt"
                                class="size-4 animate-spin"
                            />
                            <Languages v-else class="size-4" />
                            {{
                                vertaalt
                                    ? $t('Bezig met vertalen…')
                                    : $t('Vertaal automatisch')
                            }}
                        </Button>
                    </div>

                    <p
                        v-if="automatisch && !vertaalt"
                        class="text-sm text-pretty text-muted-foreground"
                    >
                        {{
                            $t(
                                'Automatisch vertaald. Loop het even na en pas aan wat je anders wilt.',
                            )
                        }}
                    </p>

                    <p
                        v-if="vertaalFout"
                        class="text-sm text-pretty text-destructive"
                    >
                        {{ vertaalFout }}
                    </p>
                </div>
            </div>

            <DialogFooter class="gap-2">
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
