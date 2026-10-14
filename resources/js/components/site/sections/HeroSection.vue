<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, useTemplateRef } from 'vue';
import LinkedinMerk from '@/components/site/LinkedinMerk.vue';
import { Button } from '@/components/ui/button';
import { naDeIntro } from '@/lib/intro';
import {
    gsap,
    irisOpen,
    kopOpkomst,
    opEenTelefoon,
    prefersReducedMotion,
    scrollNaar,
    tekenRing,
    volgDeMuis,
} from '@/lib/motion';
import type { SiteKop } from '@/types/ervaring';

/**
 * De kop van de landingspagina: "Iris".
 *
 * Staat altijd bovenaan en is niet te verslepen; dat is geen instelling
 * maar wat dit onderdeel is. Zie App\Enums\PageSectionKey. Hij heeft
 * bewust geen `id`: het houvast bij een taalwissel zoekt `main section[id]`,
 * en in de hero valt niets vast te houden.
 *
 * **Het merk is een oog, en deze scène speelt daarop.** Het portret staat in
 * een iris van ringen. Bij binnenkomst gaat die open als een diafragma, de
 * achtergrond stelt scherp en de kop komt regel voor regel op. Bij het
 * scrollen zakt de scène in drie lagen weg -- achtergrond het langzaamst,
 * tekst het snelst -- en trekt de iris iets samen, alsof het oog
 * scherpstelt op wat eronder komt.
 *
 * Het scrollen hangt aan de positie (`scrub`), en dat mag hier: het drukt
 * uit wáár je bent en geen waarde. Zie de les over `scrub` in
 * docs/architecture/frontend-en-animatie.md. Er zit geen `pin` op; de hero
 * groeit gewoon mee met een lange kop of inleiding.
 */
const props = defineProps<{
    /**
     * Welke onderdelen er verder op de pagina staan.
     *
     * Alleen om te bepalen of de knoppen ergens heen gaan. Zet de klant
     * contact of de diensten uit, dan zou die knop naar een anker springen
     * dat niet bestaat -- en dan gebeurt er bij het klikken niets, wat er
     * van buiten uitziet als een kapotte website.
     */
    sections: string[];

    /**
     * De drie teksten, uit de database. De keuze tussen Nederlands en
     * Engels is op de server al gemaakt; zie App\Models\SectionHeading.
     */
    heading: SiteKop;
}>();

// Vier maten van dezelfde achtergrond; de browser kiest op schermbreedte en
// pixeldichtheid. Zie docs/architecture/frontend-en-animatie.md.
const heroSrcset = [
    '/images/hero-achtergrond-960.webp 960w',
    '/images/hero-achtergrond-1600.webp 1600w',
    '/images/hero-achtergrond-2560.webp 2560w',
    '/images/hero-achtergrond-3840.webp 3840w',
].join(', ');

/*
 * Het portret: de WebP-varianten van het medaillon, met de doorzichtige
 * achtergrond intact. De bron-PNG staat in de map, maar komt niet op de
 * website; LandingBeeldTest bewaakt dat.
 */
const portretSrcset = [
    '/images/persoon-medaillon-320.webp 320w',
    '/images/persoon-medaillon-480.webp 480w',
    '/images/persoon-medaillon-640.webp 640w',
    '/images/persoon-medaillon-960.webp 960w',
].join(', ');

const page = usePage();

/**
 * De naam op het visitekaartje, uit dezelfde bron als het portaalaccount
 * (`config/security.php`, gedeeld als `eigenaar`). Hij stond hier eerst
 * hardgecodeerd. Leeg is geen probleem: dan toont het kaartje alleen de
 * foto en de functie, en ook dat oogt af.
 */
const naam = computed(() => page.props.eigenaar ?? '');

/**
 * Het LinkedIn-adres, uit dezelfde bron als het blok verderop. Gedeeld via
 * Inertia, zodat twee knoppen naar hetzelfde profiel niet uit elkaar
 * kunnen lopen. Zie config/site.php.
 */
const linkedin = computed(
    () => (page.props.linkedin as string | undefined) ?? '',
);

/**
 * De maat van de kop, op lengte.
 *
 * De klant typt hem zelf, tot ruim tachtig tekens. Een maat die bij vijf
 * woorden indrukwekkend is, maakt van tachtig tekens een muur die de
 * knoppen onder de vouw duwt. De grenzen hier, de maten in
 * resources/css/site/hero.css.
 */
const lengte = computed(() => {
    const tekens = props.heading.titel.trim().length;

    if (tekens <= 42) {
        return 'kort';
    }

    return tekens <= 80 ? 'middel' : 'lang';
});

/** Een lange inleiding krijgt een kleinere letter, om dezelfde reden. */
const langeInleiding = computed(
    () => (props.heading.inleiding ?? '').trim().length > 180,
);

const scene = useTemplateRef<HTMLElement>('scene');
const beeld = useTemplateRef<HTMLElement>('beeld');
const gloed = useTemplateRef<HTMLElement>('gloed');
const tekst = useTemplateRef<HTMLElement>('tekst');
const kop = useTemplateRef<HTMLElement>('kop');
const iris = useTemplateRef<HTMLElement>('iris');
const pupil = useTemplateRef<HTMLElement>('pupil');
const ring = useTemplateRef<SVGCircleElement>('ring');
const baan = useTemplateRef<SVGSVGElement>('baan');
const schaal = useTemplateRef<SVGCircleElement>('schaal');

let intro: gsap.core.Timeline | undefined;
const opruimers: Array<() => void> = [];

/**
 * Een knop in de hero naar een onderdeel verderop: glijden en niet
 * springen. Hetzelfde als het menu: het adres krijgt het anker via
 * `replaceState` (een terugknop hoort je niet van anker naar anker te
 * sturen), en Ctrl- of middelklik gaat gewoon door naar de browser.
 */
const gaNaar = (sleutel: string, gebeurtenis: MouseEvent): void => {
    if (
        gebeurtenis.metaKey ||
        gebeurtenis.ctrlKey ||
        gebeurtenis.shiftKey ||
        gebeurtenis.button !== 0
    ) {
        return;
    }

    if (document.getElementById(sleutel) === null) {
        return;
    }

    gebeurtenis.preventDefault();
    history.replaceState(history.state, '', `#${sleutel}`);
    scrollNaar(sleutel);
};

onMounted(() => {
    const opkomers =
        scene.value?.querySelectorAll<HTMLElement>('[data-intro]') ?? [];

    /*
     * Bij reduced motion staat alles meteen in zijn eindtoestand: de iris
     * open, de kop zichtbaar, de achtergrond scherp en stil. De animatie
     * overslaan zou de `opacity-0` laten staan; zie motion.ts.
     */
    if (prefersReducedMotion()) {
        gsap.set([...opkomers, kop.value, pupil.value, beeld.value], {
            opacity: 1,
            y: 0,
        });

        if (ring.value !== null) {
            opruimers.push(tekenRing(ring.value, baan.value));
        }

        return;
    }

    /*
     * De hero begint als de welkomstanimatie hem het stokje geeft: op het
     * moment dat de lens landt en het diafragma opengaat, of meteen als er
     * geen entree speelt of de bezoeker hem overslaat. `naDeIntro` wacht
     * hoogstens 2,6 seconde; zie lib/intro.ts. Tot dan staan de kop, de
     * tekst en het portret op hun begintoestand (`opacity-0` in het
     * sjabloon), onder de laag van de entree.
     *
     * Op een telefoon loopt hij sneller: anderhalve seconde opkomst op een
     * klein scherm leest als een lege site, niet als een statige.
     */
    const smal = opEenTelefoon();

    const begin = (): void => {
        intro = gsap.timeline({ defaults: { ease: 'expo.out' } });

        /*
         * De achtergrond komt op: van iets te dichtbij naar zijn plek.
         *
         * **Zonder vervaging, en dat is gemeten.** Hier stelde de foto
         * scherp van een blur naar scherp. Een filter over het hele scherm
         * laat de browser elk beeldje de volle foto opnieuw berekenen, en
         * dat viel precies samen met het diafragma van de entree en de kop
         * die opkomt: op de gebouwde site was een kwart tot een derde van
         * de beeldjes in de hero te laat, zonder de blur nul tot drie
         * procent. Het scherpstellen doet de lens van de entree al; hier
         * schuift de foto alleen nog op zijn plek.
         */
        if (beeld.value !== null) {
            intro.fromTo(
                beeld.value,
                smal ? { opacity: 0 } : { opacity: 0, scale: 1.16 },
                smal
                    ? { opacity: 1, duration: 1 }
                    : {
                          opacity: 1,
                          scale: 1.06,
                          duration: 1.6,
                      },
                0,
            );
        }

        intro.fromTo(
            opkomers,
            { opacity: 0, y: 24 },
            { opacity: 1, y: 0, duration: 1, stagger: 0.1 },
            0.3,
        );

        if (smal) {
            intro.timeScale(1.4);
        }

        /*
         * De kop, de iris en de ring hebben elk hun eigen opruimfunctie en
         * begintoestand, en staan dus los van de tijdlijn. De kop draagt
         * daarom geen `data-intro`: twee systemen die om beurten in
         * dezelfde doorzichtigheid schrijven, is precies hoe je een
         * knipperende kop krijgt.
         */
        if (kop.value !== null) {
            opruimers.push(kopOpkomst(kop.value, { wachten: 0.1 }));
        }

        if (pupil.value !== null) {
            opruimers.push(
                irisOpen(pupil.value, {
                    direct: true,
                    wachten: 0.1,
                    duur: 1.4,
                }),
            );
        }

        if (ring.value !== null) {
            opruimers.push(
                tekenRing(ring.value, baan.value, { wachten: 0.15 }),
            );
        }
    };

    opruimers.push(naDeIntro(begin));

    if (gloed.value !== null) {
        opruimers.push(volgDeMuis(gloed.value));
    }

    /*
     * De diepte bij het scrollen, alleen op een breed scherm.
     *
     * Een element dat permanent met de scroll meebeweegt, staat permanent
     * in een bewegende compositielaag, en die rastert de browser in lagere
     * resolutie -- bij het portret zag je dat als een foto die de hele tijd
     * wazig is. Op een telefoon kost het dus scherpte en levert het op dat
     * formaat nauwelijks diepte op. `gsap.matchMedia` draait het netjes
     * terug als je je scherm draait.
     */
    const mm = gsap.matchMedia();

    mm.add('(min-width: 50rem)', () => {
        if (scene.value === null) {
            return;
        }

        const diepte = gsap.timeline({
            defaults: { ease: 'none' },
            scrollTrigger: {
                trigger: scene.value,
                start: 'top top',
                end: 'bottom top',
                scrub: true,
                invalidateOnRefresh: true,
            },
        });

        if (beeld.value !== null) {
            diepte.to(beeld.value, { yPercent: 14, scale: 1.14 }, 0);
        }

        if (iris.value !== null) {
            diepte.to(iris.value, { y: 90, scale: 0.84 }, 0);
        }

        /*
         * De maatverdeling draait mee terwijl je wegscrolt, als de ring van
         * een lens die je scherpstelt. Eén cirkel met streepjes, gedraaid
         * om zijn eigen midden: dat kost niets en het is de beweging die
         * het oog-motief het duidelijkst maakt.
         */
        if (schaal.value !== null) {
            diepte.to(schaal.value, { rotation: 50, svgOrigin: '50 50' }, 0);
        }

        if (tekst.value !== null) {
            diepte.to(tekst.value, { y: -70, opacity: 0.35 }, 0);
        }

        return () => {
            diepte.scrollTrigger?.kill();
            diepte.kill();
        };
    });

    opruimers.push(() => mm.revert());
});

onBeforeUnmount(() => {
    intro?.kill();
    opruimers.forEach((opruimen) => opruimen());
});
</script>

<template>
    <section ref="scene" class="brand-site-hero">
        <!--
            De diepste laag: de achtergrond, met een waas eroverheen die de
            tekst zijn contrast geeft. Een sfeerbeeld, dus leeg `alt`.
        -->
        <div class="brand-site-hero-diepte" aria-hidden="true">
            <img
                ref="beeld"
                class="brand-site-hero-beeld"
                src="/images/hero-achtergrond-1600.webp"
                :srcset="heroSrcset"
                sizes="100vw"
                alt=""
                loading="eager"
                fetchpriority="high"
                decoding="async"
            />
            <div class="brand-site-hero-waas" />
            <!--
                De gloed volgt de cursor. De verschuiving staat in CSS en
                leest `--muis-x` en `--muis-y`; JavaScript bepaalt alleen
                waar de muis is.
            -->
            <div ref="gloed" class="brand-site-hero-gloed" />
        </div>

        <div class="brand-site-raam brand-site-hero-raster">
            <div ref="tekst" class="brand-site-hero-tekst">
                <!--
                    Op een telefoon staat het opschrift in het visitekaartje
                    onder de titel, dus daar zou het zich hier herhalen.
                -->
                <p
                    v-if="props.heading.opschrift"
                    data-intro
                    class="brand-site-opschrift brand-site-hero-opschrift opacity-0"
                >
                    {{ props.heading.opschrift }}
                </p>

                <h1
                    ref="kop"
                    class="brand-site-hero-titel opacity-0"
                    :data-lengte="lengte"
                >
                    {{ props.heading.titel }}
                </h1>

                <!--
                    Het visitekaartje, alleen op een telefoon. Daar staat de
                    iris niet naast de tekst, en zo krijgt het gezicht toch
                    een plek: als wie het doet, in een strook in plaats van
                    een blok. Vanaf een tablet is hij weg; daar zou hij
                    hetzelfde gezicht twee keer tonen.
                -->
                <div data-intro class="brand-site-visitekaartje opacity-0">
                    <span class="brand-site-visitekaartje-oog">
                        <img
                            src="/images/persoon-medaillon-320.webp"
                            alt=""
                            width="320"
                            height="320"
                            decoding="async"
                            fetchpriority="high"
                        />
                    </span>

                    <span class="min-w-0">
                        <span v-if="naam" class="brand-site-visitekaartje-naam">
                            {{ naam }}
                        </span>
                        <span
                            v-if="props.heading.opschrift"
                            class="brand-site-visitekaartje-functie"
                        >
                            {{ props.heading.opschrift }}
                        </span>
                    </span>
                </div>

                <!--
                    De zin eronder is optioneel. Laat de klant hem leeg, dan
                    staat er niets in plaats van een lege regel die de
                    knoppen omlaag duwt.
                -->
                <p
                    v-if="props.heading.inleiding"
                    data-intro
                    class="brand-site-hero-inleiding opacity-0"
                    :data-lang="langeInleiding ? '' : undefined"
                >
                    {{ props.heading.inleiding }}
                </p>

                <div data-intro class="brand-site-hero-knoppen opacity-0">
                    <Button
                        v-if="props.sections.includes('contact')"
                        as="a"
                        href="#contact"
                        variant="brand"
                        @click="gaNaar('contact', $event)"
                    >
                        {{ $t('Neem contact op') }}
                    </Button>
                    <Button
                        v-if="props.sections.includes('diensten')"
                        as="a"
                        href="#diensten"
                        variant="brand-outline"
                        @click="gaNaar('diensten', $event)"
                    >
                        {{ $t('Bekijk de diensten') }}
                    </Button>

                    <!--
                        Het LinkedIn-knopje: alleen het merkteken, met de
                        naam in het `aria-label`. Twee knoppen met woorden
                        plus een derde met woorden is geen keuze meer maar
                        een menu. Nieuw tabblad: een ander domein, en de
                        bezoeker hoort zijn plek hier niet kwijt te raken.
                    -->
                    <a
                        v-if="linkedin"
                        :href="linkedin"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="brand-merkknop"
                        :aria-label="$t('Bekijk het LinkedIn-profiel')"
                    >
                        <LinkedinMerk />
                    </a>
                </div>
            </div>

            <!--
                De iris: het portret in een stelsel van ringen.

                Het portret is één samengesteld beeld -- het oog-embleem uit
                de huisstijl met de uitgeknipte persoon erop -- zodat zijn
                hoofd op elke breedte voor het oog staat. De pupil gaat open
                als een diafragma (`irisOpen`), de buitenste ring tekent
                zich (`tekenRing`) en een lichtboog blijft er langzaam
                omheen lopen.

                Leeg `alt`: de naam en de functie staan er al in tekst, en
                een schermlezer heeft aan "een man met een bril" niets.
            -->
            <div class="brand-site-hero-oog" aria-hidden="true">
                <div ref="iris" class="brand-site-hero-iris">
                    <svg class="brand-site-hero-ringen" viewBox="0 0 100 100">
                        <defs>
                            <linearGradient
                                id="hero-iris-verloop"
                                x1="0"
                                y1="0"
                                x2="1"
                                y2="1"
                            >
                                <stop
                                    offset="0%"
                                    style="stop-color: var(--brand-cyan)"
                                />
                                <stop
                                    offset="55%"
                                    style="stop-color: var(--brand-blue)"
                                />
                                <stop
                                    offset="100%"
                                    style="
                                        stop-color: var(--brand-cyan);
                                        stop-opacity: 0.2;
                                    "
                                />
                            </linearGradient>
                        </defs>

                        <!-- De maatstreepjes: een vaste schaal, zoals op een lens. -->
                        <circle
                            ref="schaal"
                            class="brand-site-hero-schaal"
                            cx="50"
                            cy="50"
                            r="49.4"
                            pathLength="360"
                            vector-effect="non-scaling-stroke"
                        />

                        <!-- De ring die zich één keer tekent. -->
                        <circle
                            ref="ring"
                            cx="50"
                            cy="50"
                            r="47"
                            fill="none"
                            stroke="url(#hero-iris-verloop)"
                            stroke-width="1"
                            vector-effect="non-scaling-stroke"
                        />
                    </svg>

                    <!--
                        De lichtboog die blijft rondgaan, in een eigen
                        tekening over de ringen heen. Die tekening draait als
                        geheel (een CSS-transform), zodat de browser de ringen
                        niet elk beeldje opnieuw hoeft te tekenen; zie
                        `tekenRing` in motion.ts.
                    -->
                    <svg
                        ref="baan"
                        class="brand-site-hero-ringen brand-site-hero-baan"
                        viewBox="0 0 100 100"
                    >
                        <circle
                            cx="50"
                            cy="50"
                            r="47"
                            fill="none"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-dasharray="22 274"
                            vector-effect="non-scaling-stroke"
                        />
                        <circle cx="50" cy="3" r="1.4" />
                    </svg>

                    <div ref="pupil" class="brand-site-hero-pupil opacity-0">
                        <img
                            src="/images/persoon-medaillon-480.webp"
                            :srcset="portretSrcset"
                            sizes="(min-width: 80rem) 30rem, (min-width: 50rem) 38vw, 16rem"
                            alt=""
                            width="960"
                            height="960"
                            decoding="async"
                            fetchpriority="high"
                        />
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
