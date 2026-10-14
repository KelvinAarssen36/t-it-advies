<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown, Menu, X } from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useTemplateRef,
    watch,
} from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LocaleToggle from '@/components/site/LocaleToggle.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import {
    gsap,
    prefersReducedMotion,
    scrollNaar,
    volgSecties,
} from '@/lib/motion';
import { useSectieLink } from '@/lib/sectielink';
import { home } from '@/routes';
import portal from '@/routes/portal';

/**
 * De kop van de publieke site.
 *
 * **Het menu komt van de server en staat niet in dit bestand.** Dat is de
 * belangrijkste regel hier. De klant bepaalt in het portaal welke
 * onderdelen op zijn site staan en in welke volgorde; stond het menu hier
 * hardgecodeerd, dan liep het daar niet mee. Dat was ook precies wat er
 * misging: Ervaring ontbrak, een uitgezet onderdeel hield een link naar
 * een anker dat niet bestond, en na een herordening stond het menu in de
 * oude volgorde.
 *
 * Nu komt `navigation` uit dezelfde bron als de pagina zelf, uit
 * HomeController. Zet de klant iets uit, dan verdwijnt de link. Versleept
 * hij iets, dan schuift de link mee. Er is niets om te vergeten.
 *
 * Er staat bewust géén inlogknop op de publieke site. Er is maar één
 * gebruiker -- de eigenaar -- en die kent zijn eigen adres. Een inlogknop
 * zou bezoekers alleen maar wijzen op een deur die niet voor hen is, en
 * nodigt uit tot proberen. De link naar het portaal verschijnt alleen als
 * je al ingelogd bent.
 */

const page = usePage();

/**
 * Wat een menu-item is, en waar het heen wijst.
 *
 * **Dit bepaalt de hele opzet van de kop.** Op de voorpagina is elk item een
 * anker: een klik scrollt door dezelfde pagina, met de streep die
 * meeschuift. Op een subpagina is datzelfde item een link naar de
 * voorpagina bij dat onderdeel.
 *
 * Daarmee is de navigatie zelf de weg terug. Hier stond eerst het
 * omgekeerde: was het menu leeg, dan verving de kop de hele balk door één
 * "Terug naar de website" -- en omdat elke subpagina daar ook nog zijn
 * eigen teruglink bij zette, stonden er op /privacy drie dezelfde links.
 * Nu staat het menu er gewoon, en kom je in één klik bij het onderdeel dat
 * je wilde in plaats van bovenaan.
 *
 * Het zit in [`sectielink`](../../lib/sectielink.ts) en niet hier, omdat de
 * voettekst het ook nodig heeft -- en daar liep het mis zolang het twee
 * keer bestond.
 */
/*
 * `secties` is de lijst met onderdelen die op de voorpagina staan. Leeg op
 * een pagina die geen landing is -- een foutpagina bijvoorbeeld -- en dan
 * toont de kop alleen het merk en de taalkeuze, want een anker naar een
 * onderdeel dat hier niet staat is een link die niets doet.
 */
const { opDeVoorpagina, anker, secties: items, tag: menuTag } = useSectieLink();

/**
 * Hoeveel onderdelen er hoogstens los in de balk staan.
 *
 * Er wordt gemeten hoeveel er passen (zie `pasIn()`), maar ook op een breed
 * scherm staan er niet meer dan zeven: daarboven is de balk een rij
 * woorden en geen menu meer, en dan zoekt een bezoeker langer dan hij in
 * een lijstje achter "Meer" zou doen.
 */
const MAX_IN_DE_BALK = 7;

/**
 * Hoeveel er nu los in de balk staan. Gemeten, niet gegokt.
 *
 * **Hier stond een vast getal (vijf), en dat klopte nergens.** Op 1440
 * pixels stonden er vier woorden en "Meer" in een balk met ruimte voor
 * het dubbele; op een tablet liepen dezelfde vijf bijna tegen de knoppen
 * aan. De breedte van een woord hangt af van de taal ("Certificaten",
 * "Certifications") en van wat de klant aanzet, dus een vast aantal is
 * altijd voor één situatie goed.
 *
 * Tot de eerste meting staat het op het maximum, en de balk is dan
 * onzichtbaar (`data-gemeten`), zodat je bij het laden geen items ziet
 * verschijnen en weer verdwijnen.
 */
const inDeBalk = ref(MAX_IN_DE_BALK);
const gemeten = ref(false);

/** De onderdelen die los in de balk staan. */
const zichtbaar = computed(() => items.value.slice(0, inDeBalk.value));

/** En wat er achter "Meer" verdwijnt. Leeg zolang alles past. */
const rest = computed(() => items.value.slice(zichtbaar.value.length));

/** Of het contactonderdeel er is; de knop rechtsboven hangt daaraan. */
const heeftContact = computed(() =>
    items.value.some((item) => item.key === 'contact'),
);

/**
 * Of de link naar het portaal getoond mag worden.
 *
 * Dit staat hier als één benoemde waarde en niet als losse `v-if` per plek,
 * zodat er maar één regel is om te vergeten. De link staat op twee plekken
 * (de kop op desktop en het uitklapmenu op mobiel); komt er een derde bij,
 * dan hoort die deze waarde te gebruiken en niet zijn eigen controle te
 * verzinnen.
 *
 * De optionele ketting is geen sierraad: `auth` is een gedeelde prop, en bij
 * een gedeeltelijke herlading die hem niet opvraagt is hij er even niet.
 * Zonder `?.` zou dat een fout geven; mét `?.` valt hij terug op "niet
 * tonen", en dat is de veilige kant.
 *
 * Wat je hier ziet is bovendien alleen de nette weergave. De echte grendel
 * zit op de server: /dashboard staat achter 'auth', 'verified' en
 * 'two-factor.required', dus wie het adres raadt komt er evengoed niet in.
 * Zie routes/web.php.
 */
const magPortaalZien = computed(() => Boolean(page.props.auth?.user));

// Boven aan de pagina zweeft de kop over de hero heen; zodra je scrollt komt
// er een achtergrond onder, anders loopt de tekst door het beeld.
const scrolled = ref(false);
const open = ref(false);

/* --- Soepel naar een onderdeel ---------------------------------------- */

/**
 * Een klik op een menu-item gaat via Lenis in plaats van via de browser.
 *
 * Het anker blijft in de `href` staan, en dat is met opzet: zo werkt de
 * link ook als er iets misgaat met JavaScript, kun je hem kopiëren, en
 * ziet een zoekmachine waar hij heen gaat. Dit onderschept hem alleen als
 * het doel er echt is.
 *
 * Het adres wordt bijgewerkt met `replaceState` en niet met `pushState`:
 * anders vult de terugknop zich met een lange rij ankers en kom je nooit
 * meer terug op de pagina waar je vandaan kwam.
 */
const gaNaar = (sleutel: string, gebeurtenis: MouseEvent): void => {
    // Een middelklik of ctrl-klik hoort een nieuw tabblad te openen; die
    // laten we met rust.
    if (gebeurtenis.metaKey || gebeurtenis.ctrlKey || gebeurtenis.shiftKey) {
        return;
    }

    if (document.getElementById(sleutel) === null && sleutel !== 'top') {
        return;
    }

    gebeurtenis.preventDefault();

    /*
     * De bestaande `state` blijft staan, en dat is geen detail: daarin
     * bewaart Inertia de pagina en de scrollpositie. Hier stond `null`, en
     * dan wist een klik in het menu precies dat -- ga je daarna naar
     * /werkwijze en weer terug, dan heeft de terugknop niets om te
     * herstellen en blijf je op /werkwijze staan. De hero en de FAQ deden
     * het al zo.
     */
    window.history.replaceState(
        window.history.state,
        '',
        sleutel === 'top' ? window.location.pathname : `#${sleutel}`,
    );

    /*
     * Eerst het menu dicht, dán pas scrollen.
     *
     * Deed je het tegelijk -- en dat deed het -- dan begint de animatie
     * terwijl het uitklapmenu nog uit de pagina verdwijnt en de
     * scrollvergrendeling eraf gaat. De hele pagina verspringt dus
     * onderweg, en de plek waar je heen ging schuift mee. Op een telefoon
     * las dat als een haperende, trage sprong.
     *
     * Twee frames wachten en niet één: de eerste haalt het paneel uit de
     * DOM, de tweede laat de browser de nieuwe hoogte doorrekenen. Pas
     * daarna staat vast waar het doel is.
     */
    if (!open.value) {
        scrollNaar(sleutel);

        return;
    }

    open.value = false;

    requestAnimationFrame(() =>
        requestAnimationFrame(() => scrollNaar(sleutel)),
    );
};

/**
 * Een klik op een menu-item, waar je ook staat.
 *
 * Op de voorpagina doet `gaNaar` alles: hij onderschept de klik, scrollt en
 * sluit onderweg het uitklapmenu.
 *
 * **Buiten de voorpagina moet het menu hier dicht.** Daar bestaat het anker
 * niet, dus `gaNaar` breekt meteen af en laat de link zijn werk doen -- en
 * `gaNaar` is ook de plek waar `open` normaal op false gaat. De kop blijft
 * bij een Inertia-bezoek staan, dus zonder deze regel staat het
 * uitklapmenu bij aankomst op de voorpagina nog open.
 */
const kiesItem = (sleutel: string, gebeurtenis: MouseEvent): void => {
    if (!opDeVoorpagina.value) {
        open.value = false;
    }

    gaNaar(sleutel, gebeurtenis);
};

/* --- Waar sta je? ----------------------------------------------------- */

const actief = ref<string | null>(null);

/** Of er iets uit "Meer" op dit moment actief is. */
const restIsActief = computed(() =>
    rest.value.some((item) => item.key === actief.value),
);

const balk = useTemplateRef<HTMLElement>('balk');

/** Waar de markering onder het actieve item staat, in beeldpunten. */
const streep = ref({ x: 0, breedte: 0 });

/**
 * Gemeten en niet berekend, net als bij de segmentknop: de items zijn zo
 * breed als hun tekst, en "Werkwijze" is nu eenmaal langer dan "Contact".
 *
 * **Het woord wordt gemeten en niet de knop eromheen.** Een link heeft
 * ruimte om zich heen zodat je hem met een vinger kunt raken, en die
 * ruimte hoort niet onderstreept te worden: de markering liep dan aan
 * allebei de kanten een stuk door onder het niets. Op een ruime balk
 * viel dat weg tegen de witruimte ernaast, op een tablet -- waar de
 * items dicht op elkaar staan -- stond hij zichtbaar niet onder het
 * woord. Vandaar de losse `<span>` om het label heen.
 *
 * **Met `getBoundingClientRect` en niet met `offsetLeft`.** Die laatste
 * geeft een afgerond getal terug, en de balk staat zelden op een hele
 * pixel: hij zit tussen een merk en een knoppenrij die allebei zo breed
 * zijn als hun tekst. Een halve pixel scheef valt op zichzelf niet op,
 * maar hij telt op bij de afronding van de breedte.
 */
const meetStreep = (): void => {
    if (balk.value === null || actief.value === null) {
        streep.value = { ...streep.value, breedte: 0 };

        return;
    }

    const woord = balk.value.querySelector<HTMLElement>(
        `[data-woord="${actief.value}"]`,
    );

    if (woord === null) {
        streep.value = { ...streep.value, breedte: 0 };

        return;
    }

    const baan = balk.value.getBoundingClientRect();
    const vak = woord.getBoundingClientRect();

    streep.value = { x: vak.left - baan.left, breedte: vak.width };
};

watch(actief, () => void nextTick(meetStreep));

/**
 * Rekent uit hoeveel onderdelen er in de balk passen.
 *
 * Gemeten aan een onzichtbare kopie van alle items (`data-maat`) die in
 * de balk zelf ligt, met dezelfde klasse en dus dezelfde letter en
 * opvulling. De balk heeft `flex: 1`: zijn breedte is de ruimte tussen
 * het merk en de knoppen, en niet de breedte van wat erin staat. Daardoor
 * verandert die breedte niet als er een item bij komt of afgaat, en kan
 * dit niet heen en weer gaan springen.
 *
 * Past niet alles, dan telt "Meer" mee: anders past het laatste item
 * precies en valt "Meer" van de balk af.
 */
const pasIn = (): void => {
    const baan = balk.value;

    if (baan === null) {
        return;
    }

    const maten = [
        ...baan.querySelectorAll<HTMLElement>('[data-maat-item]'),
    ].map((item) => item.getBoundingClientRect().width);
    const meer =
        baan
            .querySelector<HTMLElement>('[data-maat-meer]')
            ?.getBoundingClientRect().width ?? 0;
    const tussen = parseFloat(getComputedStyle(baan).columnGap) || 0;
    const ruimte = baan.clientWidth;

    const breedte = (aantal: number, metMeer: boolean): number =>
        maten.slice(0, aantal).reduce((som, maat) => som + maat, 0) +
        (metMeer ? meer : 0) +
        tussen * Math.max(0, aantal + (metMeer ? 1 : 0) - 1);

    let aantal = Math.min(maten.length, MAX_IN_DE_BALK);

    while (aantal > 0 && breedte(aantal, aantal < maten.length) > ruimte) {
        aantal -= 1;
    }

    inDeBalk.value = aantal;
    gemeten.value = true;
};

/** Eerst passen, dan pas de markering: die hangt af van wat er staat. */
const herschik = (): void => {
    pasIn();
    void nextTick(meetStreep);
};

/* --- Het uitklapmenu op mobiel ---------------------------------------- */

const paneel = useTemplateRef<HTMLElement>('paneel');
const knop = useTemplateRef<HTMLElement>('knop');

/**
 * Het menu vouwt open en de regels komen erachteraan.
 *
 * De hoogte wordt van `auto` naar een getal gerekend door GSAP zelf; dat
 * is precies waarvoor `height: 'auto'` in een tween bestaat. Zelf meten
 * en een pixelwaarde zetten werkt ook, tot iemand de tekst groter zet.
 */
const vouwOpen = (): void => {
    if (paneel.value === null || prefersReducedMotion()) {
        return;
    }

    /*
     * Het menu gaat open als een diafragma, vanuit de knop waar je op
     * tikte: een cirkel die vanaf die plek groeit tot hij het scherm vult.
     * Zo zie je waar het vandaan komt, en het is dezelfde beweging als de
     * iris in de hero. Alleen `clip-path` en de regels eronder; de hoogte
     * van het paneel ligt vast, dus er valt niets op te meten.
     */
    const vlak = knop.value?.getBoundingClientRect();
    const midden =
        vlak === undefined
            ? '100% 0%'
            : `${vlak.left + vlak.width / 2}px ${vlak.top + vlak.height / 2}px`;

    gsap.fromTo(
        paneel.value,
        { clipPath: `circle(0% at ${midden})` },
        {
            clipPath: `circle(150% at ${midden})`,
            duration: 0.65,
            ease: 'expo.inOut',
            clearProps: 'clipPath',
        },
    );
    gsap.fromTo(
        paneel.value.querySelectorAll('[data-menu-regel]'),
        { opacity: 0, y: 28 },
        {
            opacity: 1,
            y: 0,
            duration: 0.55,
            stagger: 0.045,
            delay: 0.18,
            ease: 'expo.out',
        },
    );
};

watch(open, (nu) => {
    /*
     * De pagina eronder staat stil zolang het menu open is. Zonder dat
     * scrollt de pagina achter een menu dat het hele scherm vult, en dan
     * sta je na het sluiten ergens anders dan waar je was.
     */
    document.documentElement.style.overflow = nu ? 'hidden' : '';

    if (nu) {
        void nextTick(vouwOpen);
    }
});

const opToets = (gebeurtenis: KeyboardEvent): void => {
    if (gebeurtenis.key === 'Escape' && open.value) {
        open.value = false;
    }
};

/* --- Levensloop -------------------------------------------------------- */

/**
 * Of de kop een achtergrond heeft.
 *
 * **Twee grenzen en niet één**, en dat is geen overdaad. Met één grens op
 * acht pixels klapt hij heen en weer zodra je daar in de buurt blijft --
 * bij het uitveren van een telefoon, of met een muis die één regel per
 * stap scrollt. En elke klap bouwt de achtergrondvervaging opnieuw op,
 * wat je als trillende tekst ziet.
 *
 * Nu gaat hij aan boven de twaalf en pas weer uit onder de vier. Daar
 * tussenin verandert er niets.
 */
const onScroll = () => {
    const hoogte = window.scrollY;

    if (hoogte > 12) {
        scrolled.value = true;
    } else if (hoogte < 4) {
        scrolled.value = false;
    }
};

let stopVolgen: (() => void) | undefined;

const hervatVolgen = (): void => {
    stopVolgen?.();

    /*
     * Eerst leeg, en dat is niet overbodig.
     *
     * Op een subpagina bestaat geen enkel onderdeel, dus `volgSecties`
     * maakt nul triggers en meldt nooit iets. Zonder deze regel blijft de
     * markering staan onder het onderdeel waar je stond vóórdat je
     * wegklikte -- een streep onder "Projecten" terwijl je op de
     * privacyverklaring staat.
     */
    actief.value = null;

    stopVolgen = volgSecties(
        items.value.map((item) => item.key),
        (sleutel) => (actief.value = sleutel),
    );
};

/*
 * Verandert de lijst met onderdelen -- de klant heeft iets versleept of
 * uitgezet en de pagina is opnieuw geladen -- dan moeten de triggers
 * opnieuw. Een oude reeks wijst naar elementen die er niet meer zijn, en
 * dan licht er nooit meer iets op.
 */
watch(
    items,
    () =>
        void nextTick(() => {
            hervatVolgen();
            // Een andere taal is een andere breedte per woord.
            herschik();
        }),
    { deep: true },
);

/**
 * Opnieuw meten zodra de balk van maat verandert.
 *
 * Een luisteraar op `resize` van het venster is niet genoeg, en dat is
 * waar de markering op een tablet scheef ging staan. De balk verandert
 * ook zonder dat het venster dat doet: het merk ernaast krimpt op een
 * smal scherm tot alleen het teken, de link naar het portaal verschijnt
 * zodra je bent ingelogd, en het lettertype komt na het laden binnen en
 * maakt elk woord een paar pixels anders. Dan klopt een meting van
 * daarvoor niet meer.
 */
let kijker: ResizeObserver | undefined;

/*
 * Na elke paginawissel opnieuw volgen.
 *
 * Deze kop blijft staan terwijl `main` wordt vervangen, dus de triggers
 * van daarvoor wijzen naar elementen die er niet meer zijn. Ze vuren dan
 * nooit meer en de markering blijft staan waar hij stond -- precies wat er
 * misging na een bezoek aan een projectpagina. `PublicLayout` roept dit op
 * het moment dat de nieuwe pagina echt in de DOM staat.
 */
const opPaginaWissel = (): void => {
    hervatVolgen();
    void nextTick(herschik);
};

onMounted(() => {
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('keydown', opToets);
    window.addEventListener('brand:pagina-gewisseld', opPaginaWissel);

    if (balk.value !== null && typeof ResizeObserver !== 'undefined') {
        kijker = new ResizeObserver(() => herschik());

        /*
         * Allebei, want ze vangen iets anders. De balk zelf verandert van
         * maat als er een item bij komt of afvalt. De rij eromheen
         * verandert mee met het venster -- en dat is ook het geval als
         * het merk ernaast inkrimpt: de balk houdt dan zijn breedte maar
         * verschuift wel, en een meting van daarvoor wijst dan naast het
         * woord.
         */
        kijker.observe(balk.value);

        if (balk.value.parentElement !== null) {
            kijker.observe(balk.value.parentElement);
        }
    }

    // Het lettertype komt na het laden binnen en maakt elk woord een paar
    // pixels anders breed. Meet je alleen daarvoor, dan staat de
    // markering vanaf dat moment net verkeerd.
    void document.fonts?.ready.then(() => herschik());

    void nextTick(() => {
        hervatVolgen();
        herschik();
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    window.removeEventListener('keydown', opToets);
    window.removeEventListener('brand:pagina-gewisseld', opPaginaWissel);

    kijker?.disconnect();
    stopVolgen?.();

    document.documentElement.style.overflow = '';
});
</script>

<template>
    <header
        class="brand-site-kopbalk"
        :data-vast="scrolled ? '' : undefined"
        :data-open="open ? '' : undefined"
    >
        <div class="brand-site-kopbalk-pil">
            <!--
                Het merkteken staat voor de naam. Op een smal scherm blijft
                alleen het teken over: dat is herkenbaar genoeg, en het
                scheelt precies de ruimte die het menu daar nodig heeft.

                Het `aria-label` op de link draagt de volle naam, en de
                tekst ernaast is `aria-hidden`. Anders leest een
                schermlezer "@T IT Advies" twee keer.
            -->
            <!--
                Buiten de voorpagina is het merk een link naar huis en geen
                anker. Dat was stuk: `#top` met een handler die voor 'top'
                altijd afbreekt en naar de bovenkant van de huidige pagina
                scrollt -- dus op /over-mij deed een klik op het logo niets,
                terwijl dat op elke site de weg naar huis is.
            -->
            <component
                :is="opDeVoorpagina ? 'a' : Link"
                :href="opDeVoorpagina ? '#top' : home().url"
                class="brand-site-merk"
                aria-label="@T IT Advies"
                @click="opDeVoorpagina && gaNaar('top', $event)"
            >
                <AppLogoIcon class="brand-site-merk-teken" />
                <span class="brand-site-merk-naam" aria-hidden="true">
                    <span class="brand-text-gradient">@T</span>
                    <span class="brand-site-merk-rest"> IT Advies</span>
                </span>
            </component>

            <!--
                De markering onder het actieve item ligt in dezelfde baan
                als de links en schuift ertussen door. Hij staat op
                breedte nul zolang je in de hero zit: daar hoort niets op
                te lichten, want de hero staat niet in het menu.
            -->
            <!--
                Eén balk voor alle publieke pagina's.

                **Hier stonden er twee**: een met het menu, en een met
                "Terug naar de website" voor het geval het menu leeg was.
                Dat tweede geval bestaat niet meer sinds de subpagina's hun
                menu meesturen -- zie Navigatie op de server -- en het was
                de oorzaak van de dubbele terugknoppen.

                Blijft het menu toch leeg, bijvoorbeeld op een foutpagina,
                dan staat er niets op deze plek. Dat is geen gat: het merk
                links is daar de weg naar huis.
            -->
            <nav
                v-if="items.length > 0"
                ref="balk"
                class="brand-site-nav"
                :data-gemeten="gemeten ? '' : undefined"
                :style="{
                    '--streep-x': `${streep.x}px`,
                    // Zonder eenheid: de markering is één pixel breed
                    // en wordt met `scale` opgerekt. Zie app.css.
                    '--streep-w': streep.breedte,
                }"
            >
                <span class="brand-site-nav-lens" aria-hidden="true" />

                <!--
                    De meetlat: alle items en "Meer" nog een keer, onzichtbaar
                    en zonder plek in de balk. Hieraan meet `pasIn()` hoeveel
                    er passen; de echte items zijn daar te wisselend voor,
                    want die staan er juist niet allemaal.
                -->
                <span class="brand-site-nav-maat" aria-hidden="true">
                    <span class="brand-site-nav-maat-rij">
                        <span
                            v-for="item in items"
                            :key="item.key"
                            data-maat-item
                            class="brand-site-nav-link"
                            >{{ item.label }}</span
                        >
                        <span data-maat-meer class="brand-site-nav-link">
                            {{ $t('Meer') }}
                            <ChevronDown class="ml-1 inline size-3.5" />
                        </span>
                    </span>
                </span>

                <component
                    :is="menuTag"
                    v-for="item in zichtbaar"
                    :key="item.key"
                    :href="anker(item.key)"
                    class="brand-site-nav-link"
                    :class="{ 'is-active': actief === item.key }"
                    :aria-current="actief === item.key ? 'true' : undefined"
                    @click="kiesItem(item.key, $event)"
                >
                    <!--
                        Het woord zit in een eigen span zodat de markering
                        eronder precies zo breed is als de tekst en niet
                        als het aanraakvlak eromheen. Zie meetStreep().
                    -->
                    <span :data-woord="item.key">{{ item.label }}</span>
                </component>

                <!--
                    Wat er niet in de balk past. Leeg zolang alles past;
                    hoeveel dat is hangt af van de schermbreedte en de
                    taal (zie `pasIn()`), dus de kop loopt nooit over,
                    hoeveel modules de klant ook aanzet.

                    De knop zelf draagt geen `data-woord`: de markering
                    eronder hoort bij een onderdeel, en "Meer" is er geen.
                    Staat er iets uit dit lijstje aan, dan kleurt de knop
                    wel mee.
                -->
                <DropdownMenu v-if="rest.length > 0">
                    <DropdownMenuTrigger
                        class="brand-site-nav-link"
                        :class="{ 'is-active': restIsActief }"
                    >
                        {{ $t('Meer') }}
                        <ChevronDown class="ml-1 inline size-3.5" />
                    </DropdownMenuTrigger>

                    <DropdownMenuContent
                        align="end"
                        class="brand-site-nav-meer"
                    >
                        <DropdownMenuItem
                            v-for="item in rest"
                            :key="item.key"
                            as-child
                        >
                            <component
                                :is="menuTag"
                                :href="anker(item.key)"
                                @click="kiesItem(item.key, $event)"
                            >
                                {{ item.label }}
                            </component>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </nav>

            <div class="brand-site-kopbalk-acties">
                <Link
                    v-if="magPortaalZien"
                    :href="portal.enter()"
                    class="brand-site-nav-portaal"
                >
                    {{ $t('Dashboard') }}
                </Link>
                <!--
                    Ook de publieke site is tweetalig. Dezelfde route als de
                    knoppen in het portaal, dus wisselt de eigenaar hier
                    terwijl hij is ingelogd, dan staat het portaal er straks
                    ook in.

                    In de kop alleen de taalcodes: daar telt elke pixel, en
                    naast een vlag is NL of EN duidelijk genoeg.
                -->
                <LocaleToggle compact />

                <!--
                    Alleen als dat onderdeel er ook staat. Zet de klant het
                    contactformulier uit, dan zou deze knop naar een anker
                    springen dat niet bestaat -- en dan gebeurt er bij het
                    klikken niets.
                -->
                <!--
                    `brand-glans-laat` zet de glans van deze knop een halve
                    cyclus achter die van de hero. Zonder dat flitsen ze
                    precies gelijk op -- ze staan allebei bovenaan in beeld
                    -- en dan leest het als een laadanimatie in plaats van
                    als een accent.
                -->
                <Button
                    v-if="heeftContact"
                    as-child
                    variant="brand"
                    class="brand-glans-laat"
                >
                    <!--
                        Dezelfde behandeling als de menu-items: op de
                        voorpagina een anker, daarbuiten een link naar de
                        voorpagina mét het anker erachter.

                        **Hier stond het anker er vast ingetikt.** Op een
                        subpagina bestaat `#contact` niet, dus `gaNaar`
                        stapte eruit zonder de klik tegen te houden en de
                        browser had niets om heen te gaan -- de knop deed
                        helemaal niets. De menu-items waren hier al op
                        overgezet; deze knop was overgeslagen.

                        `SiteNavigatieTest` bewaakt dat het zo blijft, en
                        toetst daarvoor op dit bestand: deze tests
                        renderen geen Vue, dus in de opgeleverde HTML is
                        er niets te zien.
                    -->
                    <component
                        :is="menuTag"
                        :href="anker('contact')"
                        @click="gaNaar('contact', $event)"
                    >
                        {{ $t('Neem contact op') }}
                    </component>
                </Button>
            </div>

            <button
                type="button"
                ref="knop"
                class="brand-site-menuknop"
                :aria-expanded="open"
                aria-controls="site-menu"
                aria-label="Menu"
                @click="open = !open"
            >
                <X v-if="open" class="size-6" />
                <Menu v-else class="size-6" />
            </button>
        </div>

        <div
            v-if="open"
            id="site-menu"
            ref="paneel"
            class="brand-site-menu"
            data-lenis-prevent
        >
            <nav class="brand-site-menu-lijst">
                <!--
                    Dezelfde items als op de voorpagina, ook buiten de
                    voorpagina. De terugregel die hier stond is weg: die
                    bracht je naar de bovenkant van de voorpagina, terwijl
                    deze regels je bij het onderdeel brengen dat je wilde.
                -->
                <component
                    :is="menuTag"
                    v-for="item in items"
                    :key="item.key"
                    data-menu-regel
                    :href="anker(item.key)"
                    class="brand-site-menu-link"
                    :class="{ 'is-active': actief === item.key }"
                    @click="kiesItem(item.key, $event)"
                >
                    {{ item.label }}
                </component>
                <Link
                    v-if="magPortaalZien"
                    data-menu-regel
                    :href="portal.enter()"
                    class="brand-site-menu-link"
                    @click="open = false"
                >
                    {{ $t('Dashboard') }}
                </Link>

                <!--
                    In het uitklapmenu is er ruimte zat, dus daar staan de
                    volledige taalnamen.
                -->
                <LocaleToggle data-menu-regel class="brand-site-menu-taal" />
            </nav>
        </div>
    </header>
</template>
