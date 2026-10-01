<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { FolderPlus, Loader2 } from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import InputError from '@/components/InputError.vue';
import StatistiekGroepVak from '@/components/website/StatistiekGroepVak.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { bevestigBewerken, bevestigVerwijderen } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import website from '@/routes/website';
import statistieken from '@/routes/website/statistieken';
import type { StatistiekRij, StatistiekVak } from '@/types/statistieken';

/**
 * De indeling van de statistieken: de groepen én de volgorde.
 *
 * **Dit scherm is drie keer anders geweest. Het waarom is het onthouden
 * waard, want elke versie leek bij het bouwen logisch.**
 *
 * 1. **Eén platte lijst.** De pagina was gegroepeerd, de lijst niet. De
 *    klant sleepte iets en zag op zijn site iets anders gebeuren dan hij
 *    verwachtte -- of niets.
 * 2. **Een gegroepeerde lijst met een keuzelijst per regel.** Eerlijker:
 *    je zag waar een groep begon, en met de keuzelijst kon je verhuizen.
 *    Maar slepen deed nog steeds niets met de groep. Zijn melding: sleep
 *    je iets naar een andere groep, dan "moet je eerst opslaan en weer
 *    terugkomen voordat die daadwerkelijk in die groep staat". Dat klopte:
 *    de lijst *leek* te verhuizen omdat de regel onder een ander kopje
 *    belandde, maar zijn groep veranderde niet mee.
 * 3. **Een vak per groep**, wat het nu is.
 *
 * **Waarom een vak alles oplost.** De groep is nu geen veld meer dat
 * naast de lijst bestaat en ermee uit de pas kan lopen -- de groep *is*
 * het vak. Ligt een statistiek in dit vak, dan hoort hij bij deze groep.
 * Daarmee kan slepen niet meer iets anders betekenen dan wat je ziet, en
 * is er geen "eerst opslaan" meer: het vak waarin hij ligt is het
 * antwoord.
 *
 * Vier dingen die daarbij horen:
 *
 * - **De naam van een groep staat in een invoerveld**, altijd, zonder
 *   bewerkmodus. Hernoemen is dus typen, en een nieuwe groep is een leeg
 *   vak. Twee velden, Nederlands en Engels, zoals alles in dit project.
 * - **Alle cijfers in een vak krijgen bij het opslaan de namen van dat
 *   vak.** Een groep kan dus geen twee Engelse koppen meer hebben.
 * - **De pijltjes in de kop van een vak verplaatsen de hele groep.** De
 *   volgorde van de vakken is de volgorde op de website.
 * - **De keuzelijst achter een regel blijft**, want slepen tussen vakken
 *   werkt niet met een toetsenbord. Het is dezelfde verhuizing, zonder
 *   muis.
 * - **Een klein vertaalknopje naast het Engelse veld**, maar alleen als
 *   het Engels niet meer bij de Nederlandse naam kan kloppen. Zie
 *   `magVertalen`.
 *
 * Zie docs/architecture/modules/statistieken.md.
 */
const props = defineProps<{
    items: StatistiekRij[];

    /** Of de vertaaldienst er is; zonder dienst geen vertaalknopje. */
    kanVertalen: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const bezig = ref(false);

/**
 * De sleutel van het vak met de losse cijfers.
 *
 * Een vaste waarde en geen gegenereerde: dit vak bestaat altijd, staat
 * altijd vooraan en kan niet weg. Op de website zijn dit de cijfers die
 * boven het eerste kopje staan.
 */
const LOS = 'los';

/**
 * Een eigen sleutel per vak, los van de naam.
 *
 * Zou de naam de sleutel zijn, dan verspringt een vak terwijl je zijn
 * naam typt, en zijn twee nieuwe vakken zonder naam niet van elkaar te
 * onderscheiden.
 */
let volgnummer = 0;
const nieuweSleutel = (): string => `vak-${++volgnummer}`;

/**
 * De vakken zoals ze uit de lijst van de server volgen.
 *
 * Dezelfde indeling als de website: het losse vak vooraan, daarna elke
 * groep in de volgorde waarin zijn eerste cijfer staat. Zie
 * `HomeController::statistiekGroepen()`.
 *
 * De Engelse naam van een groep is **de eerste die er een heeft**. Op de
 * website komt het kopje van het eerste item, dus in de praktijk is dat
 * hetzelfde -- maar staat er per ongeluk op het ene item wél en op het
 * andere geen Engelse naam, dan pakt dit de naam op in plaats van hem
 * te laten vallen. Bij het opslaan krijgt elk cijfer in het vak die
 * naam, en daarmee is het verschil ook echt weg.
 */
const bouwVakken = (rijen: StatistiekRij[]): StatistiekVak[] => {
    const los: StatistiekVak = {
        id: LOS,
        naam: null,
        naamEn: null,
        naamBron: null,
        items: [],
    };

    const vakken: StatistiekVak[] = [los];
    const perNaam = new Map<string, StatistiekVak>();

    rijen.forEach((rij) => {
        const naam = rij.group_nl ?? '';

        if (naam === '') {
            los.items.push({ ...rij });

            return;
        }

        let vak = perNaam.get(naam);

        if (vak === undefined) {
            vak = {
                id: nieuweSleutel(),
                naam,
                naamEn: null,

                // De naam zoals hij binnenkwam, om later te zien of de
                // klant deze groep hernoemt.
                naamBron: naam,

                items: [],
            };

            perNaam.set(naam, vak);
            vakken.push(vak);
        }

        if (vak.naamEn === null && rij.group_en) {
            vak.naamEn = rij.group_en;
        }

        vak.items.push({ ...rij });
    });

    return vakken;
};

/**
 * De werkkopie.
 *
 * Alles wordt gekopieerd en niet doorgegeven: annuleren moet echt
 * annuleren. Zou hier de lijst van de pagina staan, dan verspringt de
 * tabel erachter al terwijl er niets is opgeslagen.
 */
const concept = ref<StatistiekVak[]>([]);

/** Of het venster zichzelf opnieuw opent en de inhoud dus moet blijven. */
let behoudInhoud = false;

watch(open, (isOpen) => {
    if (!isOpen) {
        behoudInhoud = false;

        return;
    }

    if (behoudInhoud) {
        behoudInhoud = false;

        return;
    }

    concept.value = bouwVakken(props.items);
});

/* --- De vakken --------------------------------------------------------- */

const genoemdeVakken = computed(() =>
    concept.value.filter((vak) => vak.id !== LOS),
);

/**
 * De keuzes in de lijst achter een regel: alle vakken die er zijn.
 *
 * Een vak zonder naam staat er ook in -- dat is een groep die nog geen
 * naam heeft gekregen. Hem weglaten zou betekenen dat je er alleen met
 * slepen iets in kunt leggen.
 */
const keuzes = computed(() => [
    { value: LOS, label: t('Zonder groep') },
    ...genoemdeVakken.value.map((vak) => ({
        value: vak.id,
        label: (vak.naam ?? '').trim() || t('Groep zonder naam'),
    })),
]);

/* --- De naam van een groep laten vertalen ------------------------------ */

/**
 * Of het vertaalknopje bij dit vak hoort te staan.
 *
 * **Niet altijd, en dat is de bedoeling.** De eigenaar vroeg om een heel
 * klein knopje "als een bepaalde groepsnaam veranderd wordt", en die
 * voorwaarde doet meteen iets nuttigs: hij voorkomt dat één klik een
 * Engelse naam overschrijft die hij zelf heeft ingetypt en waar niets
 * aan mis is.
 *
 * Het knopje staat er dus als er een Nederlandse naam is én het Engels
 * daar niet meer bij kan kloppen -- omdat het leeg is, of omdat de naam
 * sinds het openen is veranderd.
 *
 * Zo is er ook geen "weet je het zeker" nodig zoals in het bewerkvenster:
 * er valt niets te overschrijven wat nog klopt.
 */
const magVertalen = (vak: StatistiekVak): boolean => {
    if (!props.kanVertalen) {
        return false;
    }

    const naam = (vak.naam ?? '').trim();

    if (naam === '') {
        return false;
    }

    return (vak.naamEn ?? '').trim() === '' || naam !== vak.naamBron;
};

/** Welk vak op dit moment op zijn vertaling wacht; voor het molentje. */
const vertaaltVak = ref<string | null>(null);

const vertaalFout = ref<string | null>(null);

/*
 * Voor wie het antwoord is.
 *
 * Bewust **niet** reactief en niet hetzelfde als `vertaaltVak`: de
 * `onFinish` van het verzoek en de flash-gebeurtenis met het antwoord
 * komen in een orde die we niet in de hand hebben. Zou het molentje en
 * de bestemming dezelfde variabele zijn, dan kan het antwoord aankomen
 * nadat die al is leeggemaakt -- en dan verdwijnt de vertaling zonder
 * spoor. Deze blijft staan tot het antwoord er is.
 */
let vraagVoorVak: string | null = null;

let stopLuisteren: (() => void) | undefined;

onMounted(() => {
    stopLuisteren = router.on('flash', (gebeurtenis) => {
        const velden = (gebeurtenis as CustomEvent).detail?.flash?.vertaling as
            | Record<string, unknown>
            | undefined;

        if (!velden || vraagVoorVak === null) {
            return;
        }

        const vak = concept.value.find((ander) => ander.id === vraagVoorVak);
        vraagVoorVak = null;

        if (vak === undefined || typeof velden.groep_en !== 'string') {
            return;
        }

        vak.naamEn = velden.groep_en;
        vertaalFout.value = null;
    });
});

onBeforeUnmount(() => stopLuisteren?.());

/**
 * De naam van één groep laten vertalen.
 *
 * Alleen die ene naam gaat mee, en er wordt niets opgeslagen: de klant
 * krijgt een voorstel in zijn veld en drukt daarna zelf op Opslaan.
 * Zie TranslateController.
 */
const vertaalGroep = (vak: StatistiekVak): void => {
    const naam = (vak.naam ?? '').trim();

    // Eén tegelijk. Twee verzoeken tegelijk leveren twee antwoorden op
    // die niet te onderscheiden zijn -- de flash zegt alleen "groep_en".
    if (naam === '' || vertaaltVak.value !== null) {
        return;
    }

    vertaaltVak.value = vak.id;
    vraagVoorVak = vak.id;
    vertaalFout.value = null;

    router.post(
        website.vertalen().url,
        { groep_nl: naam },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                vraagVoorVak = null;
                vertaalFout.value = t(
                    'Het vertalen is niet gelukt. Probeer het zo nog eens, of vul het Engels zelf in.',
                );
            },
            onFinish: () => {
                vertaaltVak.value = null;
            },
        },
    );
};

const nieuweGroep = async (): Promise<void> => {
    const sleutel = nieuweSleutel();

    concept.value.push({
        id: sleutel,
        naam: '',
        naamEn: '',
        naamBron: null,
        items: [],
    });

    // Meteen in het naamveld staan: een leeg vak zonder naam is het
    // eerste wat hij wil invullen.
    await nextTick();
    document.getElementById(`groep-nl-${sleutel}`)?.focus();
};

/**
 * Een statistiek naar een ander vak, zonder slepen.
 *
 * Hij komt achteraan in zijn nieuwe vak. Dat is de enige plek die geen
 * uitleg nodig heeft: ertussen zetten zou een plek moeten verzinnen.
 *
 * **Er wordt een nieuwe lijst gezet en niet in de bestaande gesplitst**,
 * en dat is geen smaak maar noodzaak. `SortableList` houdt zijn eigen
 * kopie van de lijst bij en kijkt of de doorgegeven lijst een *andere*
 * is. Zou je hier `splice` en `push` gebruiken, dan blijft het dezelfde
 * array en ziet dat component er niets van: de regel blijft in het oude
 * vak staan en komt in het nieuwe niet aan.
 */
const verhuis = (id: number, naarVak: string): void => {
    const vanaf = concept.value.find((vak) =>
        vak.items.some((item) => item.id === id),
    );

    const naar = concept.value.find((vak) => vak.id === naarVak);

    if (vanaf === undefined || naar === undefined || vanaf === naar) {
        return;
    }

    const item = vanaf.items.find((regel) => regel.id === id);

    if (item === undefined) {
        return;
    }

    vanaf.items = vanaf.items.filter((regel) => regel.id !== id);
    naar.items = [...naar.items, item];
};

/** Een hele groep een plek op of neer. Het losse vak blijft vooraan. */
const verplaatsGroep = (vak: StatistiekVak, richting: -1 | 1): void => {
    const index = concept.value.indexOf(vak);
    const doel = index + richting;

    if (index < 1 || doel < 1 || doel >= concept.value.length) {
        return;
    }

    const kopie = [...concept.value];
    kopie.splice(index, 1);
    kopie.splice(doel, 0, vak);

    concept.value = kopie;
};

/**
 * Een groep opheffen.
 *
 * De cijfers erin gaan **niet** mee weg; die komen bij de losse cijfers
 * te staan. Dat staat ook in de vraag, want "groep verwijderen" leest
 * anders al snel als "deze vijf cijfers verwijderen".
 *
 * Er komt een vraag bij, ook al is er verderop nog een knop Opslaan. Dat
 * is een vaste afspraak in dit project: een regel uit een lijst halen is
 * verwijderen, ook binnen een formulier.
 */
const hefGroepOp = async (vak: StatistiekVak): Promise<void> => {
    const naam = (vak.naam ?? '').trim();

    open.value = false;
    await adem();

    const akkoord = await bevestigVerwijderen({
        titel: naam
            ? t('De groep ":naam" opheffen?', { naam })
            : t('Deze groep opheffen?'),
        tekst:
            vak.items.length > 0
                ? t(
                      'De cijfers erin blijven staan; ze komen bij de losse cijfers bovenaan.',
                  )
                : undefined,
    });

    await adem();
    behoudInhoud = true;
    open.value = true;

    if (!akkoord) {
        return;
    }

    const los = concept.value.find((ander) => ander.id === LOS);

    // Opnieuw zetten en niet aanvullen; zie `verhuis` voor waarom dat
    // verschil uitmaakt.
    if (los !== undefined) {
        los.items = [...los.items, ...vak.items];
    }

    concept.value = concept.value.filter((ander) => ander !== vak);
};

/* --- Wat er mis kan zijn ---------------------------------------------- */

/**
 * De fouten per vak.
 *
 * Twee dingen kunnen niet, en allebei zouden ze op de website iets
 * anders opleveren dan wat hier staat:
 *
 * 1. **Een groep met cijfers maar zonder naam.** De naam is de sleutel
 *    waarop gegroepeerd wordt; zonder naam zijn het losse cijfers
 *    geworden, en dan is het vak een leugen.
 * 2. **Twee groepen met dezelfde naam.** Die worden er op de website
 *    één, dus wat je hier in twee vakken hebt gezet staat daar in één.
 *
 * Een leeg vak zonder naam is geen fout: dat is een groep die je hebt
 * aangemaakt en nog niet gebruikt, en die verdwijnt gewoon.
 */
const fouten = computed<Record<string, string>>(() => {
    const uitkomst: Record<string, string> = {};
    const gezien = new Set<string>();

    genoemdeVakken.value.forEach((vak) => {
        const naam = (vak.naam ?? '').trim();

        if (naam === '') {
            if (vak.items.length > 0) {
                uitkomst[vak.id] = t(
                    'Geef deze groep een naam, of sleep de cijfers naar een ander vak.',
                );
            }

            return;
        }

        const sleutel = naam.toLocaleLowerCase();

        if (gezien.has(sleutel)) {
            uitkomst[vak.id] = t(
                'Er is al een groep met deze naam. Op je website worden dat er twee met hetzelfde kopje.',
            );

            return;
        }

        gezien.add(sleutel);
    });

    return uitkomst;
});

/**
 * Of elk cijfer nog ergens in een vak ligt.
 *
 * **Dit hoort nooit af te gaan.** Het is een vangnet onder het slepen
 * tussen twee vakken: raakt daar ooit een regel tussen wal en schip,
 * dan weigert de server de halve lijst toch al ("de lijst klopt niet
 * meer"), en dan ziet de eigenaar een foutmelding ná het opslaan terwijl
 * hij al twee keer "ja" heeft gezegd. Zo ziet hij het ervóór, met iets
 * wat hij kan doen.
 */
const alleCijfersErIn = computed(() => {
    const ids = new Set<number>();

    concept.value.forEach((vak) => {
        vak.items.forEach((item) => ids.add(item.id));
    });

    return ids.size === props.items.length;
});

const geldig = computed(
    () => Object.keys(fouten.value).length === 0 && alleCijfersErIn.value,
);

/* --- Opslaan ----------------------------------------------------------- */

/**
 * De lijst zoals de server hem wil: per cijfer zijn plek en zijn groep.
 *
 * De volgorde is die van de vakken en daarbinnen die van de regels --
 * precies de volgorde waarin ze op de website komen te staan. Lege
 * vakken leveren niets op en verdwijnen daarmee vanzelf.
 *
 * **De groepsnamen komen van het vak en niet van het item.** Daarom
 * repareert dit venster meteen een groep waarvan het ene cijfer een
 * Engelse naam had en het andere niet.
 */
const platteLijst = (
    vakken: StatistiekVak[],
): Array<{ id: number; groep: string | null; groep_en: string | null }> =>
    vakken.flatMap((vak) => {
        const naam = vak.id === LOS ? null : (vak.naam ?? '').trim() || null;
        const naamEn = naam === null ? null : (vak.naamEn ?? '').trim() || null;

        return vak.items.map((item) => ({
            id: item.id,
            groep: naam,
            groep_en: naamEn,
        }));
    });

const alsTekst = (
    rij: Array<{ id: number; groep: string | null; groep_en: string | null }>,
): string =>
    rij
        .map(
            (regel) =>
                `${regel.id}:${regel.groep ?? ''}:${regel.groep_en ?? ''}`,
        )
        .join('|');

/*
 * Vergelijken met de stand waarmee het venster opent en niet met de
 * platte lijst van de server: anders meldt hij "er is iets veranderd"
 * zodra je het opent en meteen weer opslaat, puur omdat het groeperen de
 * volgorde al had bijgetrokken.
 */
const veranderd = computed(
    () =>
        alsTekst(platteLijst(concept.value)) !==
        alsTekst(platteLijst(bouwVakken(props.items))),
);

const opslaan = async (): Promise<void> => {
    if (!geldig.value) {
        return;
    }

    // Niets veranderd is geen opslag waard: geen vraag, geen verzoek,
    // geen melding.
    if (!veranderd.value) {
        open.value = false;

        return;
    }

    open.value = false;
    await adem();

    const akkoord = await bevestigBewerken({
        titel: t('De indeling van je statistieken aanpassen?'),
        tekst: t(
            'Ze komen in deze groepen en in deze volgorde op je website te staan.',
        ),
    });

    if (!akkoord) {
        await adem();
        behoudInhoud = true;
        open.value = true;

        return;
    }

    bezig.value = true;

    router.put(
        statistieken.volgorde().url,
        { statistieken: platteLijst(concept.value) },
        {
            preserveScroll: true,
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
                <DialogTitle>
                    {{ $t('De indeling van je statistieken') }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Elk vak is een groep, en zo staan ze ook op je website. Sleep een cijfer naar een ander vak om het te verhuizen, sleep binnen een vak om de volgorde te bepalen, en gebruik de pijltjes in de kop van een vak om een hele groep te verplaatsen.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-3">
                <StatistiekGroepVak
                    v-for="(vak, index) in concept"
                    :key="vak.id"
                    :vak="vak"
                    :naamloos="vak.id === LOS"
                    :eerste="index <= 1"
                    :laatste="index === concept.length - 1"
                    :keuzes="keuzes"
                    :kan-vertalen="magVertalen(vak)"
                    :vertaalt="vertaaltVak === vak.id"
                    :vertaal-wacht="vertaaltVak !== null"
                    :fout="fouten[vak.id]"
                    @update:items="vak.items = $event"
                    @update:naam="vak.naam = $event"
                    @update:naam-en="vak.naamEn = $event"
                    @verhuis="verhuis"
                    @vertaal="vertaalGroep(vak)"
                    @omhoog="verplaatsGroep(vak, -1)"
                    @omlaag="verplaatsGroep(vak, 1)"
                    @verwijder="hefGroepOp(vak)"
                />

                <Button
                    variant="outline"
                    class="w-full gap-2"
                    :disabled="bezig"
                    @click="nieuweGroep"
                >
                    <FolderPlus class="size-4" />
                    {{ $t('Nieuwe groep') }}
                </Button>

                <InputError :message="vertaalFout ?? undefined" />

                <!-- Het vangnet; zie `alleCijfersErIn`. -->
                <InputError
                    :message="
                        alleCijfersErIn
                            ? undefined
                            : $t(
                                  'Er is een cijfer kwijtgeraakt tijdens het slepen. Annuleer en open dit venster opnieuw; er is nog niets opgeslagen.',
                              )
                    "
                />
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
                        :disabled="bezig || !geldig"
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
