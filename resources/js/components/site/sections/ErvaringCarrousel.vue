<script setup lang="ts">
import { ArrowUpRight, MouseIcon } from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import ErvaringIcoon from '@/components/site/ErvaringIcoon.vue';
import { gsap, prefersReducedMotion, scrollThrough } from '@/lib/motion';
import type { ErvaringOpDeSite } from '@/types/ervaring';

/**
 * De loopbaan als stapel kaarten waar je met je muiswiel doorheen gaat.
 *
 * **Het vak is de zone, en dat is letterlijk zo.** Hang je met je muis in
 * het omlijnde vak, dan gaat je scroll naar de loopbaan; hang je ernaast,
 * dan scrolt de pagina gewoon door. De pagina blijft ondertussen staan
 * waar hij staat. Dat is een bewuste keuze boven het alternatief -- het
 * blok vastzetten en de pagina eronder laten doorlopen -- omdat het vak
 * dan ook is wat het lijkt: je ziet een gebied, en dat gebied is waar het
 * gebeurt.
 *
 * De functie waar je bent staat vooraan op een eigen kaart; die ervóór en
 * die erna staan kleiner erboven en eronder, **leesbaar** -- je ziet dus
 * niet alleen dát er iets voor en na komt, maar ook wát.
 *
 * **Vijf dingen die hier bewust zo zijn:**
 *
 * 1. **Je komt er altijd uit.** Ben je aan het begin of het eind van de
 *    loopbaan, dan houden we de scroll niet meer tegen en gaat de pagina
 *    gewoon verder. Zonder die uitweg zit de bezoeker vast in het vak, en
 *    dat is het ergste wat een onderdeel als dit kan doen.
 * 2. **Het vak moet het midden van het scherm beslaan** voordat het je
 *    scroll pakt. Anders werkt het tegen terwijl je er alleen maar
 *    langsschuift. Zie `scrollThrough` in motion.ts.
 * 3. **Met het toetsenbord werkt het ook**: het vak is aan te wijzen met
 *    Tab, en dan bladeren de pijltjes, Home en End erdoorheen. Een
 *    onderdeel dat alleen met een muis te bedienen is, is stuk voor wie
 *    geen muis gebruikt.
 * 4. **De kaarten worden rechtstreeks verplaatst, buiten Vue om.** Dat
 *    loopt met elke scrollstap mee; zou Vue elke kaart opnieuw moeten
 *    tekenen, dan gaat het haperen. Wat Vue wél bijhoudt is welke ervaring
 *    vooraan staat.
 * 5. **Alleen de voorste kaart draagt zijn beschrijving.** De buren tonen
 *    periode, functie en organisatie; dat is wat je wilt weten over wat er
 *    aankomt. Zouden ze hun hele tekst meedragen, dan is er niets meer dat
 *    de voorste kaart onderscheidt.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
const props = defineProps<{ items: ErvaringOpDeSite[] }>();

const emit = defineEmits<{ open: [item: ErvaringOpDeSite] }>();

const laatste = computed(() => Math.max(props.items.length - 1, 0));

/**
 * Waar je bent in de rij, als kommagetal.
 *
 * Er zijn er **twee**, en dat is de kern van hoe soepel dit voelt.
 * `doel` is waar je scroll je heen stuurt; `positie` is waar de stapel
 * op dit moment staat, en die kruipt er elk beeldje een stukje naartoe.
 *
 * Zonder dat tussenstation springt de stapel per scrollgebeurtenis, en
 * die komen bij een muiswiel in schokken van honderd tegelijk binnen. Met
 * dat tussenstation loopt de beweging door nadat je bent gestopt, en
 * voelt het als iets wat glijdt in plaats van hapert.
 */
let doel = 0;
let positie = 0;

/**
 * Welke kaart vooraan staat. **Dit is het enige dat Vue aanstuurt.**
 *
 * `doel` en `positie` zijn bewust gewone variabelen en geen refs. Ze
 * veranderen bij elk beeldje van de animatielus; zou Vue ze volgen, dan
 * tekent het de hele lijst zestig keer per seconde opnieuw -- en dan is
 * het rechtstreeks verplaatsen van de kaarten voor niets geweest. Wat er
 * vooraan staat verandert een paar tientallen keren, en dát mag Vue doen.
 */
const huidig = ref(0);

/** Of je muis in het vak hangt; de rand licht dan op. */
const binnenVak = ref(false);

const actief = computed<ErvaringOpDeSite | undefined>(
    () => props.items[huidig.value],
);

/* --- De stapel ------------------------------------------------------- */

const vak = ref<HTMLElement | null>(null);
const stapel = ref<HTMLElement | null>(null);

/*
 * De lijn langs de rail en de balk onderin. Ze worden net als de kaarten
 * rechtstreeks aangestuurd; alleen `transform`, dus de browser hoeft de
 * pagina niet opnieuw door te rekenen.
 */
const bereik = ref<HTMLElement | null>(null);
const balk = ref<HTMLElement | null>(null);

/**
 * Hoeveel kaarten er aan elke kant meedoen.
 *
 * Twee is genoeg om te laten zien dat er een rij is en om de volgende te
 * kunnen lezen; bij meer wordt het een grijze waaier waarin niets meer te
 * onderscheiden valt.
 */
const BUREN = 2;

/**
 * Hoeveel scroll er in één ervaring gaat.
 *
 * Eén muiswielklik is ongeveer honderd, dus dit komt neer op iets meer dan
 * één klik per functie. Lager en je vliegt eroverheen; hoger en een lange
 * loopbaan wordt een karwei.
 */
const PER_ERVARING = 120;

/**
 * De ruimte tussen de voorste kaart en zijn eerste buur, en die tussen de
 * buren onderling.
 *
 * Dat zijn twee verschillende getallen, en met reden: de voorste kaart
 * draagt zijn beschrijving en is daarmee flink hoger dan de rest. Met één
 * vaste afstand valt hij over zijn buren heen.
 *
 * **Ze worden gemeten en niet geraden.** Eerder stonden hier vaste
 * getallen, en daarna een percentage van de hoogte van het vak -- allebei
 * gaan ze mis zodra de kaart hoger of lager uitvalt dan verwacht, en dat
 * gebeurt bij elke schermbreedte en bij elke beschrijving opnieuw. Nu
 * vragen we de kaarten zelf hoe hoog ze zijn; dan klopt het overal, ook
 * op een telefoon.
 */
const maten = ref({ gat: 150, stap: 80 });

/** Lucht tussen twee kaarten, zodat ze elkaar niet raken. */
const LUCHT = 14;

const meet = (): void => {
    const alle = kaarten();

    if (alle.length < 2) {
        return;
    }

    const voorste = alle[huidig.value]?.offsetHeight ?? 0;

    // Een willekeurige buur: die dragen allemaal dezelfde inhoud, dus ze
    // zijn even hoog.
    const buur = alle[huidig.value === 0 ? 1 : 0]?.offsetHeight ?? voorste;

    if (voorste === 0 || buur === 0) {
        return;
    }

    maten.value = {
        // Van het midden van de voorste naar het midden van zijn buur.
        gat: voorste / 2 + buur / 2 + LUCHT,
        stap: buur + LUCHT,
    };
};

const kaarten = (): HTMLElement[] =>
    Array.from(stapel.value?.children ?? []) as HTMLElement[];

/** Elke kaart op zijn plek zetten voor de huidige positie in de rij. */
const plaats = (): void => {
    const { gat, stap } = maten.value;

    const deel = laatste.value === 0 ? 1 : positie / laatste.value;

    if (bereik.value !== null) {
        bereik.value.style.transform = `scaleY(${deel.toFixed(4)})`;
    }

    if (balk.value !== null) {
        balk.value.style.transform = `scaleX(${deel.toFixed(4)})`;
    }

    kaarten().forEach((kaart, index) => {
        const verschil = index - positie;
        const ver = Math.abs(verschil);

        if (ver > BUREN + 0.6) {
            kaart.style.opacity = '0';
            kaart.style.visibility = 'hidden';

            return;
        }

        // Tot de eerste buur het grote gat, daarna de kleinere stap.
        const y =
            Math.sign(verschil) *
            (Math.min(ver, 1) * gat + Math.max(0, ver - 1) * stap);

        const schaal = 1 - Math.min(ver, BUREN) * 0.1;

        kaart.style.visibility = 'visible';
        kaart.style.opacity = String(Math.max(0, 1 - ver * 0.4));
        kaart.style.transform = `translateY(calc(-50% + ${y.toFixed(1)}px)) scale(${schaal.toFixed(3)})`;
        kaart.style.zIndex = String(100 - Math.round(ver * 10));
    });
};

/**
 * De lus die `positie` naar `doel` toe trekt.
 *
 * Hij loopt op de ticker van GSAP en niet op een eigen
 * `requestAnimationFrame`: dan tikt alle beweging op de site op dezelfde
 * klok, en die klok is al gekoppeld aan Lenis. Twee losse lussen naast
 * elkaar levert beelden op die net niet gelijk vallen.
 *
 * Zodra het verschil te klein is om te zien, klikt hij op het doel en
 * stopt de lus. Anders blijft er zestig keer per seconde iets berekend
 * worden voor een beweging van een duizendste pixel.
 */
let loopt = false;

const tik = (): void => {
    const verschil = doel - positie;

    if (Math.abs(verschil) < 0.0015) {
        positie = doel;
        plaats();
        stopLus();

        return;
    }

    // Een vaste fractie per beeldje: dat geeft een beweging die snel
    // begint en zacht uitdempt, precies zoals smooth scrolling zelf.
    positie += verschil * 0.16;

    huidig.value = Math.round(positie);
    plaats();
};

const startLus = (): void => {
    /*
     * Bij een voorkeur voor minder beweging springen we er meteen heen.
     * Het onderdeel blijft dus gewoon werken -- je bladert door dezelfde
     * loopbaan -- alleen zonder het glijden. Eerder verdween de hele
     * carrousel hier, en dat is te ver: minder beweging betekent rustiger,
     * niet minder functie.
     */
    if (prefersReducedMotion()) {
        positie = doel;
        huidig.value = Math.round(positie);
        plaats();

        return;
    }

    if (loopt) {
        return;
    }

    loopt = true;
    gsap.ticker.add(tik);
};

const stopLus = (): void => {
    if (!loopt) {
        return;
    }

    loopt = false;
    gsap.ticker.remove(tik);
};

/**
 * Verschuiven, en teruggeven of we de beweging hebben gebruikt.
 *
 * Zit je al aan het begin of het eind, dan gebruiken we hem niet en
 * scrolt de pagina verder. Dat is de uitweg uit het vak.
 */
const verplaats = (verschuiving: number): boolean => {
    const nieuw = Math.min(
        Math.max(doel + verschuiving / PER_ERVARING, 0),
        laatste.value,
    );

    if (nieuw === doel) {
        return false;
    }

    doel = nieuw;
    startLus();

    return true;
};

/** Naar één ervaring springen: met het toetsenbord of via een jaartal. */
const ga = (naar: number): void => {
    doel = Math.min(Math.max(naar, 0), laatste.value);
    startLus();
};

/* --- Erop drukken ---------------------------------------------------- */

/**
 * Waar de aanwijzer naar beneden ging.
 *
 * Een veeg op een telefoon eindigt ook in een klik. Zonder deze
 * vergelijking opent het venster elke keer dat je door de loopbaan veegt,
 * en dat is het laatste wat je dan wilt.
 */
let neerX = 0;
let neerY = 0;

const opNeer = (gebeurtenis: PointerEvent): void => {
    neerX = gebeurtenis.clientX;
    neerY = gebeurtenis.clientY;
};

const opKlik = (index: number, gebeurtenis: MouseEvent): void => {
    const afgelegd = Math.hypot(
        gebeurtenis.clientX - neerX,
        gebeurtenis.clientY - neerY,
    );

    // Meer dan een paar pixels: dit was geen klik maar een veeg.
    if (afgelegd > 8) {
        return;
    }

    // Op een buur klikken brengt hem naar voren; op de voorste klikken
    // opent hem helemaal.
    if (index === huidig.value) {
        emit('open', props.items[index]!);

        return;
    }

    ga(index);
};

const opToets = (gebeurtenis: KeyboardEvent): void => {
    /*
     * Staat de aandacht op een link of een knop binnen het vak, dan is
     * die aan zet en wij niet. Zonder deze regel kaapte het vak de Enter
     * van de link naar de organisatie: de browser navigeerde niet, maar
     * er ging een venster open. Precies het soort ding dat alleen
     * opvalt als je het zonder muis probeert.
     */
    if ((gebeurtenis.target as HTMLElement)?.closest('a, button') !== null) {
        return;
    }

    /*
     * Enter en spatie openen de voorste kaart. Daarmee is dit onderdeel
     * ook zonder muis compleet: met Tab kom je in het vak, met de
     * pijltjes blader je, en met Enter lees je verder.
     */
    if (gebeurtenis.key === 'Enter' || gebeurtenis.key === ' ') {
        const item = props.items[huidig.value];

        if (item !== undefined) {
            gebeurtenis.preventDefault();
            emit('open', item);
        }

        return;
    }

    const sprong: Record<string, number> = {
        ArrowDown: doel + 1,
        PageDown: doel + 3,
        ArrowUp: doel - 1,
        PageUp: doel - 3,
        Home: 0,
        End: laatste.value,
    };

    const naar = sprong[gebeurtenis.key];

    if (naar === undefined) {
        return;
    }

    // Anders scrolt de pagina óók, en spring je twee keer.
    gebeurtenis.preventDefault();

    ga(Math.round(naar));
};

let stopScroll: (() => void) | undefined;
let toezicht: ResizeObserver | undefined;

onMounted(() => {
    meet();
    plaats();

    if (vak.value === null) {
        return;
    }

    stopScroll = scrollThrough(vak.value, { onVerplaats: verplaats });

    /*
     * De maten hangen aan de hoogte van het vak, en die verandert bij het
     * draaien van een telefoon en bij het verslepen van een venster. Een
     * waarnemer is hier beter dan een resize-luisteraar: die vuurt ook als
     * alleen de breedte verandert, en dat kost werk voor niets.
     */
    if (stapel.value !== null) {
        toezicht = new ResizeObserver(() => {
            meet();
            plaats();
        });

        toezicht.observe(stapel.value);
    }
});

/*
 * De voorste kaart is hoger dan de rest, want alleen hij draagt zijn
 * beschrijving -- en hoe hoog precies hangt af van hoe lang die tekst is.
 * Opnieuw meten bij elke wisseling dus. Dat is een paar tientallen keer
 * per bezoek en niet per beeldje, dus het kost niets.
 */
watch(huidig, () => {
    nextTick(() => {
        meet();
        plaats();
    });
});

onBeforeUnmount(() => {
    stopScroll?.();
    stopLus();
    toezicht?.disconnect();
});

/**
 * De jaartallen langs de rail.
 *
 * Alleen waar een nieuw jaar begint, en alleen als er genoeg ruimte is
 * sinds het vorige label. Bij vijfentwintig functies over vijfendertig
 * jaar zouden ze anders over elkaar heen vallen, en dan is het geen
 * oriëntatiepunt meer maar een grijze streep.
 */
const jaarMerken = computed(() => {
    const aantal = props.items.length;

    if (aantal === 0) {
        return [];
    }

    const merken: { jaar: string; positie: number; index: number }[] = [];
    let vorige = -100;

    props.items.forEach((item, index) => {
        const nieuwJaar =
            index === 0 || props.items[index - 1]?.jaar !== item.jaar;

        if (!nieuwJaar) {
            return;
        }

        const plek = aantal === 1 ? 0 : (index / (aantal - 1)) * 100;

        if (plek - vorige < 10) {
            return;
        }

        merken.push({ jaar: item.jaar, positie: plek, index });
        vorige = plek;
    });

    return merken;
});
</script>

<template>
    <!--
        Het vak is de zone: hier gaat je scroll naar de loopbaan in plaats
        van naar de pagina. De rand licht op zodra je muis erin hangt, dus
        wat je ziet klopt met wat er gebeurt.

        `tabindex` en de toetsafhandeling zijn geen bijzaak: zonder dat is
        dit onderdeel alleen met een muis te bedienen.
    -->
    <div
        v-if="actief"
        ref="vak"
        class="brand-loopbaan-vak"
        :data-binnen="binnenVak ? '' : undefined"
        tabindex="0"
        role="group"
        :aria-label="$t('Door de loopbaan bladeren')"
        @pointerenter="binnenVak = true"
        @pointerleave="binnenVak = false"
        @focusin="binnenVak = true"
        @focusout="binnenVak = false"
        @keydown="opToets"
    >
        <div class="brand-loopbaan-binnen">
            <!-- De rail met de jaartallen, als oriëntatiepunt. -->
            <div class="brand-loopbaan-rail">
                <div class="brand-loopbaan-spoor" aria-hidden="true">
                    <div ref="bereik" class="brand-loopbaan-bereik" />
                </div>

                <div class="brand-loopbaan-jaren">
                    <button
                        v-for="merk in jaarMerken"
                        :key="merk.jaar"
                        type="button"
                        tabindex="-1"
                        class="brand-loopbaan-jaar"
                        :style="{ top: `${merk.positie}%` }"
                        :data-actief="
                            actief.jaar === merk.jaar ? '' : undefined
                        "
                        @click="ga(merk.index)"
                    >
                        {{ merk.jaar }}
                    </button>
                </div>
            </div>

            <!--
                De stapel. Alle ervaringen staan hier in de HTML -- ook de
                kaarten die je niet ziet, zodat een zoekmachine de hele
                loopbaan leest. Wat niet vooraan staat draagt `inert` en
                zit dus niet in de tab- of voorleesvolgorde.
            -->
            <div ref="stapel" class="brand-loopbaan-stapel">
                <article
                    v-for="(item, index) in items"
                    :key="item.id"
                    class="brand-loopbaan-kaart"
                    :data-actief="index === huidig ? '' : undefined"
                    :inert="index === huidig ? undefined : true"
                    @pointerdown="opNeer"
                    @click="opKlik(index, $event)"
                >
                    <!--
                        Het beeldmerk. Wat hier staat is altijd een vierkant
                        plaatje van de server, met de achtergrond er al in
                        gebakken -- vandaar dat er geen enkele uitzondering
                        voor een logo of een foto nodig is. Zie
                        App\Support\Media\Logo.
                    -->
                    <span class="brand-loopbaan-bel" aria-hidden="true">
                        <img v-if="item.logo" :src="item.logo" alt="" />
                        <ErvaringIcoon v-else :icoon="item.icon" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <div
                            class="flex flex-wrap items-center gap-x-3 gap-y-1"
                        >
                            <span class="brand-loopbaan-periode">
                                {{ item.periode }}
                            </span>
                            <span v-if="item.loopt" class="brand-tijdlijn-nu">
                                {{ $t('Nu') }}
                            </span>
                        </div>

                        <h3 class="brand-loopbaan-functie">
                            {{ item.functie }}
                        </h3>

                        <p class="brand-tijdlijn-meta mt-1.5">
                            <span class="brand-tijdlijn-org">
                                <a
                                    v-if="item.website"
                                    :href="item.website"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 hover:underline"
                                    @click.stop
                                >
                                    {{ item.organisatie }}
                                    <ArrowUpRight class="size-3.5" />
                                </a>
                                <template v-else>
                                    {{ item.organisatie }}
                                </template>
                            </span>

                            <span>{{ item.duur }}</span>

                            <span v-for="deel in item.meta" :key="deel">
                                {{ deel }}
                            </span>
                        </p>

                        <!--
                            Alleen de voorste kaart draagt zijn
                            beschrijving; anders is er niets meer dat hem
                            onderscheidt van zijn buren. En geen kopje boven
                            een lege beschrijving: wat er niet is, staat er
                            niet.
                        -->
                        <p
                            v-if="index === huidig && item.beschrijving"
                            class="brand-loopbaan-tekst"
                        >
                            {{ item.beschrijving }}
                        </p>
                    </div>
                </article>
            </div>
        </div>

        <!--
            De voet: waar je bent, en hoe je verder komt. Het muisje wordt
            duidelijker zodra je erin hangt -- dan is het ook waar.
        -->
        <div class="brand-loopbaan-voet">
            <span class="tabular-nums">
                {{ huidig + 1 }} / {{ items.length }}
            </span>

            <span class="brand-loopbaan-hint">
                <MouseIcon class="size-3.5" />
                {{ $t('Scroll hier om te bladeren, klik om te lezen') }}
            </span>
        </div>

        <div ref="balk" class="brand-loopbaan-balk" aria-hidden="true" />
    </div>
</template>
