<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    EyeOff,
    Languages,
    Loader2,
    Plus,
    Sparkles,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import BrandSelect from '@/components/BrandSelect.vue';
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
import ervaring from '@/routes/website/ervaring';
import type {
    ErvaringCijferRij,
    ErvaringCijferSoort,
    ErvaringKeuze,
    ErvaringKopRij,
} from '@/types/ervaring';

/**
 * De kop boven de tijdlijn: de tekst én de drie cijfers.
 *
 * **Waarom dat één venster is.** Op de website staan de titel, de zin
 * eronder en de getallen in hetzelfde blok. Ze los laten bewerken zou
 * betekenen dat de eigenaar twee keer bevestigt voor één zichtbare
 * verandering, en dat er een tussenstand kan bestaan waarin de helft live
 * staat.
 *
 * **Per cijfer kiest de klant wat ermee gebeurt**: wij rekenen het uit,
 * hij vult zelf een getal in, of het staat er niet. Die derde stand was
 * er niet, en daardoor kon hij een cijfer niet weglaten -- leeg betekende
 * toen "reken het uit". Zie App\Enums\ExperienceStatModus.
 *
 * **Elke kaart toont een voorbeeld van het tegeltje** zoals het op de
 * website komt te staan: het getal groot, het woord eronder. Dat is de
 * kortste weg naar begrip. De keuzelijsten eronder zeggen wát er gebeurt,
 * maar pas het voorbeeld laat zien wát er komt te staan -- en verandert
 * meteen mee als je iets anders kiest.
 *
 * **Niets in dit venster wordt uit een rij gelezen die het soort volgt.**
 * Het woord, de uitleg en wat wij zouden tellen worden opgezocht bij het
 * soort, in `props.keuzes.soort`. Toen die gegevens in de rij zelf
 * stonden, klopten ze niet meer zodra de klant een ander soort koos, en
 * stond er "nu zouden wij er 0 tellen" boven een tijdlijn van
 * vijfendertig jaar.
 *
 * De vertaalknop werkt hetzelfde als in ErvaringDialoog, maar zonder de
 * voortgangsmelding: het gaat hier om twee korte regels, en die passen in
 * één verzoek aan de vertaaldienst.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
const props = defineProps<{
    cijfers: ErvaringCijferRij[];
    kop: ErvaringKopRij;
    keuzes: {
        soort: ErvaringCijferSoort[];
        modus: ErvaringKeuze[];
        maximum: number;
    };
    kanVertalen: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const bezig = ref(false);
const fouten = ref<Record<string, string>>({});

/** De vier tekstvelden, altijd als tekst zodat een leeg veld "" is. */
const tekst = ref({
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
let engelsBijOpenen = { title_en: '', intro_en: '' };

/** Of het Engels dat er nu staat van de vertaaldienst komt. */
const automatisch = ref(false);

const vertaalt = ref(false);
const vertaalFout = ref<string | null>(null);

/**
 * Welk cijfer op dit moment op zijn vertaling wacht, of null.
 *
 * De vertaalroute krijgt één woord en geeft er één terug; hij weet niet
 * bij welk cijfer het hoort. Dat onthoudt dit venster, want het heeft de
 * knop zelf ingedrukt. Er kan er maar één tegelijk lopen -- de knoppen
 * staan ondertussen uit -- dus één waarde volstaat.
 */
const vertaaltCijfer = ref<number | null>(null);

/**
 * De cijfers die in het venster staan.
 *
 * Een eigen kopie en niet de prop zelf: de klant kan er rijen bij zetten
 * en weghalen, en dat mag pas gebeuren als hij opslaat. Zou dit direct op
 * de prop werken, dan verandert het scherm eronder al terwijl hij nog aan
 * het twijfelen is.
 */
/**
 * Een cijfer zoals het in dit venster bewerkt wordt.
 *
 * De velden die aan een invoerveld hangen mogen hier geen `null` zijn:
 * een leeg veld is een lege tekst, en `null` erin stoppen levert een
 * typefout op bij `v-model`. Bij het opslaan wordt een lege tekst weer
 * `null`, want dat is wat "niets ingevuld" in de database betekent.
 */
type Bewerkbaar = Omit<
    ErvaringCijferRij,
    'label_nl' | 'label_en' | 'waarde'
> & {
    label_nl: string;
    label_en: string;
    waarde: number | string;

    /**
     * Een sleutel die alleen in dit venster bestaat.
     *
     * `id` is `null` bij een nieuwe rij, en dan is `id` als `:key` voor
     * twee nieuwe rijen hetzelfde. Vue hergebruikt dan het verkeerde
     * element: haal je de eerste van twee nieuwe cijfers weg, dan lijkt
     * de verkeerde te verdwijnen.
     */
    rijId: number;
};

/** Loopt op, zodat elke rij in dit venster een eigen sleutel heeft. */
let volgendeRijId = 0;

const cijfers = ref<Bewerkbaar[]>([]);

/**
 * Of het venster zichzelf opnieuw opent en de inhoud dus moet blijven.
 *
 * Hetzelfde verhaal als in ErvaringDialoog: het venster gaat dicht en weer
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
    // Een diepe kopie, want elke rij wordt los bewerkt.
    cijfers.value = props.cijfers.map((cijfer) => ({
        ...cijfer,
        label_nl: cijfer.label_nl ?? '',
        label_en: cijfer.label_en ?? '',
        waarde: cijfer.waarde ?? '',
        rijId: volgendeRijId++,
    }));

    tekst.value = {
        title_nl: props.kop.title_nl,
        title_en: props.kop.title_en ?? '',
        intro_nl: props.kop.intro_nl ?? '',
        intro_en: props.kop.intro_en ?? '',
    };

    engelsBijOpenen = {
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

/**
 * Het soort van een rij, opgezocht en niet meegedragen.
 *
 * Alles wat hieronder uit een cijfer wordt afgeleid gaat hier langs. Dat
 * is met opzet één plek: zo kan er geen tweede kopie van het soort
 * ontstaan die achterloopt op wat er in de keuzelijst staat.
 */
const soortVan = (cijfer: Bewerkbaar): ErvaringCijferSoort | undefined =>
    props.keuzes.soort.find((keuze) => keuze.value === cijfer.key);

/** Of wij dit soort uit de tijdlijn kunnen tellen. */
const berekenbaar = (cijfer: Bewerkbaar): boolean =>
    soortVan(cijfer)?.berekenbaar ?? false;

/** Wat wij nu voor dit soort zouden tellen, of null bij een eigen cijfer. */
const berekend = (cijfer: Bewerkbaar): number | null =>
    soortVan(cijfer)?.berekend ?? null;

/** Het standaardwoord onder het getal, als de klant niets invult. */
const standaardWoord = (cijfer: Bewerkbaar): string =>
    soortVan(cijfer)?.label ?? '';

/**
 * Welke modussen dit cijfer mag hebben.
 *
 * Een eigen cijfer kunnen wij niet uitrekenen, dus "automatisch" hoort er
 * daar niet bij te staan. Hem wél tonen en daarna afkeuren is een
 * keuzelijst die je straft voor het kiezen.
 */
const modussenVoor = (cijfer: Bewerkbaar): ErvaringKeuze[] =>
    props.keuzes.modus.filter(
        (keuze) => berekenbaar(cijfer) || keuze.value !== 'automatisch',
    );

/** Of dit soort al ergens anders in de lijst staat. */
const dubbel = (cijfer: Bewerkbaar, index: number): boolean =>
    berekenbaar(cijfer) &&
    cijfers.value.some(
        (ander, anderIndex) => anderIndex !== index && ander.key === cijfer.key,
    );

const kanErbij = computed(() => cijfers.value.length < props.keuzes.maximum);

/**
 * Het getal dat op de website komt te staan, of null als er niets komt.
 *
 * Dit is wat het voorbeeld toont, en het is ook precies de rekensom die
 * de server straks maakt. Eén regel, en daarmee is "wat gebeurt er als ik
 * dit kies" geen vraag meer.
 */
const voorbeeldGetal = (cijfer: Bewerkbaar): number | null => {
    if (cijfer.modus === 'verborgen') {
        return null;
    }

    if (cijfer.modus === 'automatisch') {
        return berekend(cijfer);
    }

    return String(cijfer.waarde).trim() === '' ? null : Number(cijfer.waarde);
};

/**
 * Het woord dat eronder komt te staan, in de taal van het portaal.
 *
 * Dezelfde terugval als op de website: het Engels als de bezoeker Engels
 * kijkt, anders het Nederlands, en is dat ook leeg dan het standaardwoord
 * van het soort. Die volgorde staat ook in `ExperienceStat::woord()`, en
 * hier alleen om het voorbeeld te laten kloppen met wat er straks komt.
 */
const voorbeeldWoord = (cijfer: Bewerkbaar): string => {
    const engels = usePage().props.locale === 'en';

    if (engels && cijfer.label_en.trim() !== '') {
        return cijfer.label_en.trim();
    }

    if (cijfer.label_nl.trim() !== '') {
        return cijfer.label_nl.trim();
    }

    return standaardWoord(cijfer);
};

/**
 * Een cijfer erbij, met de nuttigste stand die nog vrij is.
 *
 * Is er een soort dat wij kunnen tellen en dat nog niet in de lijst
 * staat, dan wordt het dát, op automatisch: dan staat er meteen een
 * kloppend getal en hoeft de klant niets in te vullen. Dit is ook de weg
 * terug voor wie "jaren ervaring" per ongeluk heeft weggehaald.
 *
 * Staan die er allemaal al, dan wordt het een eigen cijfer -- twee keer
 * hetzelfde mag niet, en dan is een leeg veld eerlijker dan een keuze die
 * meteen wordt afgekeurd.
 */
const voegToe = (): void => {
    if (!kanErbij.value) {
        return;
    }

    const bezet = new Set(cijfers.value.map((cijfer) => cijfer.key));

    const vrij = props.keuzes.soort.find(
        (soort) => soort.berekenbaar && !bezet.has(soort.value),
    );

    cijfers.value.push({
        id: null,
        key: vrij?.value ?? 'eigen',
        label_nl: '',
        label_en: '',
        modus: vrij === undefined ? 'eigen' : 'automatisch',
        waarde: '',
        rijId: volgendeRijId++,
    });
};

/**
 * Een cijfer weghalen, maar niet zonder het te vragen.
 *
 * Het gebeurt pas echt bij Opslaan, en toch hoort hier een bevestiging:
 * een prullenbakje dat een ingevuld cijfer meteen laat verdwijnen voelt
 * als iets kwijtraken, en er is geen ongedaan maken in dit formulier. Dat
 * is dezelfde afspraak als bij elke andere verwijderknop in het portaal.
 *
 * Het venster gaat er even voor dicht; zie de toelichting bij `opslaan`.
 */
const haalWeg = async (index: number): Promise<void> => {
    const cijfer = cijfers.value[index];

    if (cijfer === undefined) {
        return;
    }

    open.value = false;
    await adem();

    const akkoord = await bevestigVerwijderen({
        titel: t('Dit cijfer weghalen?'),
        tekst: t(
            'Het verdwijnt van je website zodra je opslaat. Wil je het alleen even niet tonen, kies dan "Niet tonen" -- dan blijven je woord en je getal bewaard.',
        ),
        knop: t('Ja, weghalen'),
    });

    await adem();
    openOpnieuw();

    if (!akkoord) {
        return;
    }

    cijfers.value.splice(index, 1);

    /*
     * De foutmeldingen van de server hangen aan een plek in de lijst
     * (`cijfers.2.waarde`) en niet aan een cijfer. Schuift er eentje weg,
     * dan wijzen ze naar het verkeerde kaartje -- een rood randje om een
     * veld waar niets mis mee is, terwijl het echte probleem er niet meer
     * staat. Weg ermee; de volgende poging tot opslaan levert de
     * meldingen die dan gelden.
     */
    fouten.value = {};
};

/**
 * Het soort van een rij omzetten.
 *
 * Alleen de sleutel, en verder niets: al het andere wordt opgezocht bij
 * het soort. Zet de klant er een eigen cijfer van, dan kan "automatisch"
 * niet blijven staan -- dat kunnen wij niet uitrekenen.
 */
const wisselSoort = (cijfer: Bewerkbaar, waarde: string): void => {
    cijfer.key = waarde;

    if (!berekenbaar(cijfer) && cijfer.modus === 'automatisch') {
        cijfer.modus = 'eigen';
    }
};

/**
 * Wijkt het ingevulde cijfer af van wat wij tellen?
 *
 * **Dit is de val van een eigen getal**: het blijft staan. Komt er een
 * functie bij, of gaat er een jaar voorbij, dan klopt het niet meer en
 * merkt niemand dat -- want het staat op de voorpagina waar de klant zelf
 * nooit naar kijkt. Vandaar dat het scherm het hem vertelt.
 */
const looptAchter = (cijfer: Bewerkbaar): boolean =>
    cijfer.modus === 'eigen' &&
    berekend(cijfer) !== null &&
    String(cijfer.waarde).trim() !== '' &&
    Number(cijfer.waarde) !== berekend(cijfer);

/* --- Automatisch vertalen --------------------------------------------- */

/**
 * De vertaaldienst antwoordt via een flash-prop en niet met JSON.
 *
 * Zo blijft er één HTTP-cliënt in het spel en hoeft de route niets terug
 * te geven wat de pagina niet al kent. Hetzelfde mechanisme als in
 * ErvaringDialoog; zie App\Support\Toast.
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

        /*
         * Het woord onder een cijfer komt terug op dezelfde route, maar
         * het hoort bij een andere knop. Welk cijfer het was onthoudt
         * `vertaaltCijfer`; de server weet daar niets van, want die
         * krijgt één woord en geeft er één terug.
         */
        if (typeof velden.woord_en === 'string') {
            const rij = cijfers.value.find(
                (cijfer) => cijfer.rijId === vertaaltCijfer.value,
            );

            if (rij !== undefined) {
                rij.label_en = velden.woord_en;
            }

            vertaaltCijfer.value = null;
            vertaalFout.value = null;

            return;
        }

        if (typeof velden.title_en === 'string') {
            tekst.value.title_en = velden.title_en;
        }

        if (typeof velden.intro_en === 'string') {
            tekst.value.intro_en = velden.intro_en;
        }

        /*
         * Alleen hier, en niet bij een vertaald woord. Dit merkje gaat
         * over de titel en de zin eronder; zou een vertaald woord het
         * ook zetten, dan staat er "automatisch vertaald" bij tekst die
         * de eigenaar zelf heeft geschreven.
         */
        automatisch.value = true;
        vertaalFout.value = null;
    });
});

onBeforeUnmount(() => stopLuisteren?.());

/**
 * Staat er Engels dat de knop zou overschrijven?
 *
 * Wat de vertaaldienst zelf heeft neergezet telt niet mee: dat opnieuw
 * laten vertalen kost de klant niets. Het gaat om tekst die hij zelf heeft
 * getypt of aangepast.
 */
const eigenEngels = (): boolean =>
    !automatisch.value &&
    [tekst.value.title_en, tekst.value.intro_en].some(
        (waarde) => waarde.trim() !== '',
    );

const vertaal = async (): Promise<void> => {
    /*
     * Eerst waarschuwen als er Engels staat. De knop gooit dat zonder
     * pardon weg, en een vertaling die je net met de hand hebt
     * bijgeschaafd terugkrijgen als machinetaal is niet iets waar je van
     * terug kunt -- er is geen ongedaan maken in dit formulier.
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

/**
 * Het woord onder één cijfer vertalen.
 *
 * Een klein knopje naast het Engelse veld en niet de grote knop
 * hierboven, want het gaat om één woord. Om dezelfde reden is er geen
 * bevestiging: er valt hoogstens één woord te overschrijven, en dat
 * typt de eigenaar sneller terug dan hij een venster wegklikt. Bij de
 * titel en de zin ligt dat anders -- daar kan een zorgvuldig geschreven
 * alinea verdwijnen.
 *
 * Het venster blijft open. De knop staat ondertussen uit, zodat er nooit
 * twee vertalingen tegelijk onderweg zijn naar hetzelfde veld.
 */
const vertaalWoord = (cijfer: Bewerkbaar): void => {
    if (cijfer.label_nl.trim() === '' || vertaaltCijfer.value !== null) {
        return;
    }

    vertaaltCijfer.value = cijfer.rijId;
    vertaalFout.value = null;

    router.post(
        website.vertalen().url,
        { woord_nl: cijfer.label_nl.trim() },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                vertaalFout.value = t(
                    'Het vertalen is niet gelukt. Probeer het zo nog eens, of vul het Engels zelf in.',
                );
            },
            /*
             * Pas een tik later vrijgeven. De melding met het vertaalde
             * woord komt binnen terwijl het antwoord wordt verwerkt, dus
             * vóór `onFinish` -- en die melding heeft dit nummer nog
             * nodig om te weten bij welk cijfer het woord hoort. Meteen
             * op null zetten is de race waarin de vertaling binnenkomt
             * en nergens terechtkomt.
             *
             * Het hoort hier en niet alleen bij een fout: zonder dit
             * blijft de knop voorgoed uit staan zodra de vertaaldienst
             * één keer nee zegt, en dan lijkt hij stuk.
             */
            onFinish: () => {
                void nextTick(() => {
                    vertaaltCijfer.value = null;
                });
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
        titel: t('De kop boven je tijdlijn aanpassen?'),
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
        tekst.value.title_en === engelsBijOpenen.title_en &&
        tekst.value.intro_en === engelsBijOpenen.intro_en;

    router.put(
        ervaring.kop().url,
        {
            /*
             * De hele lijst gaat mee, ook de rijen die niet veranderd
             * zijn. De server trekt zijn tabel erop gelijk: wat er niet
             * bij zit is weggehaald.
             *
             * Een getal wordt hier uitdrukkelijk een getal of `null`.
             * Vue maakt van een `<input type="number">` al een getal
             * zodra je iets typt, maar een leeggemaakt veld levert een
             * lege string -- en die moet `null` worden en niet 0.
             */
            cijfers: cijfers.value.map((cijfer) => ({
                id: cijfer.id,
                key: cijfer.key,
                label_nl: cijfer.label_nl,
                label_en: cijfer.label_en,
                modus: cijfer.modus,
                waarde:
                    String(cijfer.waarde).trim() === ''
                        ? null
                        : Number(cijfer.waarde),
            })),
            title_nl: tekst.value.title_nl,
            title_en: tekst.value.title_en,
            intro_nl: tekst.value.intro_nl,
            intro_en: tekst.value.intro_en,
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

const adem = (): Promise<void> =>
    new Promise((klaar) => setTimeout(klaar, 200));
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="max-h-[85vh] brand-scrollbar overflow-y-auto sm:max-w-2xl"
        >
            <DialogHeader>
                <DialogTitle>{{ $t('De kop boven je tijdlijn') }}</DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'De titel, de zin eronder en de getallen. Op je website staat dit allemaal in hetzelfde blok, dus je slaat het in één keer op.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <!-- ---------------------- De tekst ---------------------- -->
            <div class="grid gap-4">
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

                <!--
                    Het Engels eronder en niet in een tweede stap, zoals
                    bij een ervaring. Het zijn hier twee korte regels; een
                    stap ervoor bouwen kost meer klikken dan het scheelt.
                -->
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

            <!-- --------------------- De cijfers --------------------- -->
            <div class="grid gap-4 border-t border-border pt-5">
                <div
                    class="flex flex-wrap items-baseline justify-between gap-2"
                >
                    <h3 class="text-sm font-semibold">
                        {{ $t('De cijfers erboven') }}
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        {{
                            $t(':aantal van :maximum', {
                                aantal: cijfers.length,
                                maximum: props.keuzes.maximum,
                            })
                        }}
                    </p>
                </div>

                <p class="text-sm text-pretty text-muted-foreground">
                    {{
                        $t(
                            'Per cijfer kies je wat er gebeurt: wij rekenen het uit, je vult zelf een getal in, of je zet het uit. Het woord eronder mag je zelf bepalen.',
                        )
                    }}
                </p>

                <p
                    v-if="cijfers.length === 0"
                    class="rounded-lg border border-dashed border-border px-4 py-6 text-center text-sm text-muted-foreground"
                >
                    {{
                        $t(
                            'Je hebt geen cijfers staan. Boven je tijdlijn komt dan niets.',
                        )
                    }}
                </p>

                <div
                    v-for="(cijfer, index) in cijfers"
                    :key="cijfer.rijId"
                    class="brand-cijferkaart"
                >
                    <!--
                        De kop van de kaart: het hoeveelste tegeltje dit
                        is, en de knop om het weg te halen. Het nummer
                        staat er zodat de klant deze kaart terugziet in de
                        rij op zijn website.
                    -->
                    <div class="brand-cijferkaart-kop">
                        <span class="brand-cijferkaart-nummer">
                            {{ index + 1 }}
                        </span>

                        <p class="brand-cijferkaart-titel">
                            {{ $t('Cijfer :nummer', { nummer: index + 1 }) }}
                        </p>

                        <Button
                            variant="verwijderen-zacht"
                            size="icon-sm"
                            :aria-label="$t('Dit cijfer weghalen')"
                            @click="haalWeg(index)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>

                    <!--
                        Het voorbeeld: precies het tegeltje zoals het op de
                        website komt te staan. Dit staat bovenaan en niet
                        onderaan, want het is het antwoord op de vraag die
                        de klant heeft -- de keuzes eronder zijn de weg
                        ernaartoe.
                    -->
                    <div class="brand-cijfervoorbeeld">
                        <p class="brand-cijfervoorbeeld-opschrift">
                            {{ $t('Op je website') }}
                        </p>

                        <template v-if="voorbeeldGetal(cijfer) !== null">
                            <p class="brand-cijfervoorbeeld-getal">
                                {{ voorbeeldGetal(cijfer) }}
                            </p>
                            <p class="brand-cijfervoorbeeld-woord">
                                {{ voorbeeldWoord(cijfer) }}
                            </p>
                        </template>

                        <!--
                            Er komt niets te staan, en waaróm niet
                            verschilt: uitgezet, nog geen getal ingevuld,
                            of niets op de tijdlijn om te tellen. Eén
                            algemene zin zou de klant laten zoeken naar
                            iets wat er niet is.
                        -->
                        <p v-else class="brand-cijfervoorbeeld-leeg">
                            <EyeOff class="size-3.5 shrink-0" />
                            <span>
                                {{
                                    cijfer.modus === 'verborgen'
                                        ? $t('Dit cijfer staat er niet.')
                                        : cijfer.modus === 'automatisch'
                                          ? $t(
                                                'Er staat nog niets op je tijdlijn om te tellen.',
                                            )
                                          : $t('Vul hieronder een getal in.')
                                }}
                            </span>
                        </p>
                    </div>

                    <!-- Stap 1: wat tellen we. -->
                    <div class="grid gap-1.5">
                        <Label :for="`soort-${index}`">
                            {{ $t('Waar dit cijfer over gaat') }}
                        </Label>
                        <BrandSelect
                            :id="`soort-${index}`"
                            v-model="cijfer.key"
                            :options="props.keuzes.soort"
                            @update:model-value="
                                (waarde) => wisselSoort(cijfer, String(waarde))
                            "
                        />
                        <p class="text-xs text-pretty text-muted-foreground">
                            {{ soortVan(cijfer)?.omschrijving }}
                        </p>
                        <InputError :message="fouten[`cijfers.${index}.key`]" />
                    </div>

                    <p
                        v-if="dubbel(cijfer, index)"
                        class="brand-cijfer-waarschuwing"
                    >
                        <TriangleAlert class="size-3.5 shrink-0" />
                        <span>
                            {{ $t('Dit cijfer staat er al een keer bij.') }}
                        </span>
                    </p>

                    <!-- Stap 2: waar het getal vandaan komt. -->
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label :for="`modus-${index}`">
                                {{ $t('Waar het getal vandaan komt') }}
                            </Label>
                            <BrandSelect
                                :id="`modus-${index}`"
                                v-model="cijfer.modus"
                                :options="modussenVoor(cijfer)"
                            />
                            <InputError
                                :message="fouten[`cijfers.${index}.modus`]"
                            />
                        </div>

                        <div
                            v-if="cijfer.modus === 'eigen'"
                            class="grid gap-1.5"
                        >
                            <Label :for="`waarde-${index}`" verplicht>
                                {{ $t('Het getal') }}
                            </Label>
                            <Input
                                :id="`waarde-${index}`"
                                v-model="cijfer.waarde"
                                type="number"
                                inputmode="numeric"
                                min="0"
                                max="9999"
                            />
                            <InputError
                                :message="fouten[`cijfers.${index}.waarde`]"
                            />
                        </div>

                        <p
                            v-else-if="
                                cijfer.modus === 'automatisch' &&
                                berekend(cijfer) !== null
                            "
                            class="brand-cijfer-telling"
                        >
                            <Sparkles class="size-3.5 shrink-0" />
                            <span>
                                {{
                                    $t(
                                        'Wij tellen er nu :aantal, en dat werkt vanzelf bij.',
                                        { aantal: berekend(cijfer) ?? 0 },
                                    )
                                }}
                            </span>
                        </p>
                    </div>

                    <!--
                        Het getal loopt achter op de werkelijkheid. Dat is
                        de val van een eigen getal: het blijft staan, ook
                        als er een functie bij komt of als er een jaar
                        voorbijgaat.
                    -->
                    <p
                        v-if="looptAchter(cijfer)"
                        class="brand-cijfer-waarschuwing"
                    >
                        <TriangleAlert class="size-3.5 shrink-0" />
                        <span>
                            {{
                                $t(
                                    'Wij tellen er :aantal. Zet dit cijfer op automatisch om het vanzelf te laten bijwerken.',
                                    { aantal: berekend(cijfer) ?? 0 },
                                )
                            }}
                        </span>
                    </p>

                    <!-- Stap 3: het woord eronder. -->
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label :for="`woord-nl-${index}`">
                                <LocaleFlag locale="nl" size="sm" />
                                {{ $t('Woord eronder') }}
                            </Label>
                            <Input
                                :id="`woord-nl-${index}`"
                                v-model="cijfer.label_nl"
                                maxlength="40"
                                :placeholder="standaardWoord(cijfer)"
                            />
                        </div>

                        <div class="grid gap-1.5">
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <Label :for="`woord-en-${index}`">
                                    <LocaleFlag locale="en" size="sm" />
                                    {{ $t('Woord eronder') }}
                                </Label>

                                <!--
                                    Eén woord vertalen, met een klein
                                    knopje in plaats van de grote knop
                                    boven de tekstvelden. Hij verschijnt
                                    pas als er Nederlands staat om te
                                    vertalen; een knop die niets kan doen
                                    laat je beter weg dan uitgegrijsd
                                    staan.
                                -->
                                <Button
                                    v-if="
                                        props.kanVertalen &&
                                        cijfer.label_nl.trim() !== ''
                                    "
                                    variant="ghost"
                                    size="sm"
                                    class="h-6 gap-1 px-1.5 text-xs"
                                    :disabled="vertaaltCijfer !== null"
                                    :aria-label="
                                        $t('Dit woord naar het Engels vertalen')
                                    "
                                    @click="vertaalWoord(cijfer)"
                                >
                                    <Loader2
                                        v-if="vertaaltCijfer === cijfer.rijId"
                                        class="size-3 animate-spin"
                                    />
                                    <Languages v-else class="size-3" />
                                    {{ $t('Vertaal') }}
                                </Button>
                            </div>

                            <Input
                                :id="`woord-en-${index}`"
                                v-model="cijfer.label_en"
                                maxlength="40"
                                :placeholder="standaardWoord(cijfer)"
                            />
                        </div>
                    </div>

                    <p class="text-xs text-pretty text-muted-foreground">
                        {{
                            $t(
                                'Laat leeg en we gebruiken het standaardwoord, dat vanzelf meegaat met de taal van je bezoeker.',
                            )
                        }}
                    </p>
                </div>

                <div v-if="kanErbij">
                    <Button
                        variant="aanmaken"
                        size="sm"
                        class="gap-2"
                        @click="voegToe"
                    >
                        <Plus class="size-4" />
                        {{ $t('Cijfer erbij') }}
                    </Button>
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
