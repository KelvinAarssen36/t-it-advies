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

type NavItem = { key: string; label: string };

const page = usePage();

/**
 * De onderdelen die op de pagina staan, op volgorde.
 *
 * Leeg op een pagina die geen landing is -- een foutpagina bijvoorbeeld.
 * Dan toont de kop alleen het merk en de taalkeuze, want een anker naar
 * een onderdeel dat hier niet staat is een link die niets doet.
 */
const items = computed<NavItem[]>(
    () => (page.props.navigation as NavItem[] | undefined) ?? [],
);

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
const { opDeVoorpagina, anker, tag: menuTag } = useSectieLink();

/**
 * Hoeveel onderdelen er los in de balk passen.
 *
 * Er zijn er nu vier, en dan doet dit getal niets. Het staat er voor
 * straks: elke module die de klant erbij krijgt komt hier vanzelf in, en
 * bij acht of tien loopt de balk over de taalknop en de contactknop heen.
 * Wat er niet in past gaat achter "Meer", en dan houdt de kop dezelfde
 * hoogte hoeveel modules er ook bijkomen.
 *
 * Vijf en niet drie: onder de vijf is een uitklaplijstje meer werk voor
 * de bezoeker dan een link, en boven de zes wordt de balk te vol.
 */
const MAX_IN_DE_BALK = 5;

/** De onderdelen die los in de balk staan. */
const zichtbaar = computed(() =>
    items.value.length <= MAX_IN_DE_BALK
        ? items.value
        : items.value.slice(0, MAX_IN_DE_BALK - 1),
);

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

    window.history.replaceState(
        null,
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

/* --- Het uitklapmenu op mobiel ---------------------------------------- */

const paneel = useTemplateRef<HTMLElement>('paneel');

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

    gsap.fromTo(
        paneel.value,
        { height: 0, opacity: 0 },
        { height: 'auto', opacity: 1, duration: 0.35, ease: 'power3.out' },
    );

    gsap.fromTo(
        paneel.value.querySelectorAll('[data-menu-regel]'),
        { opacity: 0, y: -8 },
        {
            opacity: 1,
            y: 0,
            duration: 0.3,
            stagger: 0.05,
            delay: 0.08,
            ease: 'power2.out',
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
watch(items, () => void nextTick(hervatVolgen), { deep: true });

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

onMounted(() => {
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('keydown', opToets);

    if (balk.value !== null && typeof ResizeObserver !== 'undefined') {
        kijker = new ResizeObserver(() => meetStreep());

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
    void document.fonts?.ready.then(() => meetStreep());

    void nextTick(hervatVolgen);
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    window.removeEventListener('keydown', opToets);

    kijker?.disconnect();
    stopVolgen?.();

    document.documentElement.style.overflow = '';
});
</script>

<template>
    <header class="brand-sitekop" :data-vast="scrolled ? '' : undefined">
        <div
            class="mx-auto flex h-16 max-w-6xl items-center justify-between px-6"
        >
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
                class="brand-merk"
                aria-label="@T IT Advies"
                @click="opDeVoorpagina && gaNaar('top', $event)"
            >
                <AppLogoIcon class="brand-merk-teken" />
                <span class="brand-merk-naam" aria-hidden="true">
                    <span class="brand-text-gradient">@T</span>
                    <span class="text-white"> IT Advies</span>
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
                class="brand-navbalk hidden tablet:flex"
                :style="{
                    '--streep-x': `${streep.x}px`,
                    // Zonder eenheid: de markering is één pixel breed
                    // en wordt met `scale` opgerekt. Zie app.css.
                    '--streep-w': streep.breedte,
                }"
            >
                <span class="brand-navstreep" aria-hidden="true" />

                <component
                    :is="menuTag"
                    v-for="item in zichtbaar"
                    :key="item.key"
                    :href="anker(item.key)"
                    class="brand-navlink"
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
                    Wat er niet in de balk past. Leeg zolang er vijf of
                    minder onderdelen zijn, dus nu zie je hier niets --
                    maar zodra de klant zijn zesde module krijgt, loopt de
                    kop niet over.

                    De knop zelf draagt geen `data-woord`: de markering
                    eronder hoort bij een onderdeel, en "Meer" is er geen.
                    Staat er iets uit dit lijstje aan, dan kleurt de knop
                    wel mee.
                -->
                <DropdownMenu v-if="rest.length > 0">
                    <DropdownMenuTrigger
                        class="brand-navlink"
                        :class="{ 'is-active': restIsActief }"
                    >
                        {{ $t('Meer') }}
                        <ChevronDown class="ml-1 inline size-3.5" />
                    </DropdownMenuTrigger>

                    <DropdownMenuContent align="end">
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

            <div class="hidden items-center gap-3 tablet:flex">
                <Link
                    v-if="magPortaalZien"
                    :href="portal.enter()"
                    class="text-sm text-muted-foreground transition-colors hover:text-brand-cyan"
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
                    as="a"
                    href="#contact"
                    variant="brand"
                    class="brand-glans-laat"
                    @click="gaNaar('contact', $event)"
                >
                    {{ $t('Neem contact op') }}
                </Button>
            </div>

            <button
                type="button"
                class="text-foreground tablet:hidden"
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
            class="overflow-hidden border-t border-border bg-background tablet:hidden"
        >
            <nav class="mx-auto flex max-w-6xl flex-col gap-1 px-6 py-4">
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
                    class="brand-navlink-mobiel"
                    :class="{ 'is-active': actief === item.key }"
                    @click="kiesItem(item.key, $event)"
                >
                    {{ item.label }}
                </component>
                <Link
                    v-if="magPortaalZien"
                    data-menu-regel
                    :href="portal.enter()"
                    class="brand-navlink-mobiel"
                    @click="open = false"
                >
                    {{ $t('Dashboard') }}
                </Link>

                <!--
                    In het uitklapmenu is er ruimte zat, dus daar staan de
                    volledige taalnamen.
                -->
                <LocaleToggle data-menu-regel class="mt-2 self-start" />
            </nav>
        </div>
    </header>
</template>
