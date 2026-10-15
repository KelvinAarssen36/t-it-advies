<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, useTemplateRef } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LinkedinMerk from '@/components/site/LinkedinMerk.vue';
import {
    groeiLijn,
    gsap,
    prefersReducedMotion,
    scrollNaar,
    stapelBinnen,
} from '@/lib/motion';
import { useSectieLink } from '@/lib/sectielink';
import { privacy } from '@/routes';
import type { VoetOpDeSite } from '@/types/voettekst';

/**
 * De voet van de publieke site. Geen inloglink: zie SiteHeader.vue.
 *
 * **De links komen van de server**, uit dezelfde `navigation` als de kop.
 * Ze stonden hier hardgecodeerd, en dat was dezelfde fout als boven in de
 * balk: zette de klant een onderdeel uit, dan bleef hier een link staan
 * naar een anker dat niet bestond -- en klikken deed dan niets. Versleept
 * hij de onderdelen, dan schuift deze lijst nu mee.
 *
 * **En ze dóen nu ook iets, want dat deden ze niet.** Hier stond een anker
 * met `@click.prevent` erbij en een aanroep van `scrollNaar('#diensten')`
 * -- met hekje, terwijl die functie `getElementById` doet. Dus vond het
 * scrollen niets én mocht de browser de link niet volgen: deze links deden
 * helemaal niets, ook niet op de voorpagina. Nu komt de vorm uit
 * [`sectielink`](../../lib/sectielink.ts), dezelfde als in de kop, en krijgt
 * `scrollNaar` de sleutel zonder hekje.
 *
 * **Drie kolommen, en elke kolom heeft een andere bron.** Dat is meteen de
 * uitleg van deze module:
 *
 * | Kolom     | Waar het vandaan komt                                  |
 * | --------- | ------------------------------------------------------ |
 * | Het merk  | De naam ligt vast; de zin eronder zet de eigenaar       |
 * | Op de site| `navigation`, dus zijn eigen indeling                   |
 * | Contact   | Het adres uit de config, de rest uit `footer_settings`  |
 *
 * **Wat leeg is, staat er niet.** De server stuurt `null` voor elk veld dat
 * de eigenaar niet heeft ingevuld, dus hier staat nergens een regel te
 * wachten op een waarde die nooit komt.
 *
 * **De credit onderaan ligt vast en is geen instelling.** Zie daar.
 *
 * **Het slotbeeld.** Achter de kolommen ligt, groot en sterk gedempt, het
 * oog uit de hero: de pagina begint en eindigt bij hetzelfde beeld. Het is
 * een sfeerbeeld met een lege `alt`, en het laadt pas als je in de buurt
 * komt. De naam staat er groot boven: de vaste naam van het bedrijf, geen
 * tekst die iemand hoeft te beheren.
 *
 * Zie docs/architecture/modules/voettekst.md.
 */
const page = usePage();

/**
 * Wat de eigenaar zelf in zijn voet heeft gezet.
 *
 * Komt per pagina mee en niet uit de gedeelde props: het is een query, en
 * die hoort niet op elk portaalverzoek te drukken. Zie FooterController.
 */
const voet = computed<VoetOpDeSite>(
    () =>
        (page.props.voet as VoetOpDeSite | undefined) ?? {
            tagline: null,
            telefoon: null,
            plaats: null,
            kvk: null,
            btw: null,
        },
);

/** Het publieke adres en de LinkedIn-link liggen vast in de config. */
const email = computed(() => (page.props.email as string | undefined) ?? '');
const linkedin = computed(
    () => (page.props.linkedin as string | undefined) ?? '',
);

const { opDeVoorpagina, anker, secties: items, tag } = useSectieLink();

/**
 * Een klik op een regel in de voettekst.
 *
 * Op de voorpagina onderscheppen we hem en scrollen we; daarbuiten laten we
 * de link zijn werk doen. **Niet blind `prevent`**: dat was precies waarom
 * deze links niets deden.
 */
const kies = (sleutel: string, gebeurtenis: MouseEvent): void => {
    if (!opDeVoorpagina.value) {
        return;
    }

    // Een middelklik of ctrl-klik hoort een nieuw tabblad te openen.
    if (gebeurtenis.metaKey || gebeurtenis.ctrlKey || gebeurtenis.shiftKey) {
        return;
    }

    if (scrollNaar(sleutel)) {
        gebeurtenis.preventDefault();
    }
};

const year = new Date().getFullYear();

/** Het oog uit de hero, in twee maten; zie HeroSection. */
const oogSrcset = [
    '/images/hero-achtergrond-960.webp 960w',
    '/images/hero-achtergrond-1600.webp 1600w',
].join(', ');

const voetElement = useTemplateRef<HTMLElement>('voetElement');

const oog = useTemplateRef<HTMLElement>('oog');
const lensSchaal = useTemplateRef<SVGSVGElement>('lensSchaal');
const lensRing = useTemplateRef<SVGCircleElement>('lensRing');
const lensTeken = useTemplateRef<{ $el: HTMLElement }>('lensTeken');
const naam = useTemplateRef<HTMLElement>('naam');

const opruimers: Array<() => void> = [];

/**
 * De voet als slotbeeld, ook in beweging.
 *
 * - **De lijn bovenin** groeit vanuit het midden open (`groeiLijn`).
 * - **De drie blokken komen gestaffeld op**, merk eerst, dan de twee
 *   lijstjes (`stapelBinnen`). Gebundeld: wat tegelijk in beeld komt,
 *   komt ook samen binnen.
 * - **Het oog en het woordmerk schuiven langzaam omhoog** terwijl de voet
 *   binnenkomt, het oog trager dan de naam. Dat is positie en geen waarde
 *   (`scrub`), en alleen op een breed scherm: op een telefoon kost een
 *   meebewegende laag met een foto erin vooral scherpte.
 *
 * De voet blijft bij een navigatie staan, dus dit draait één keer; bij een
 * andere pagina rekent ScrollTrigger de posities opnieuw uit
 * (`hermeetScroll` in PublicLayout).
 */
onMounted(() => {
    opruimers.push(groeiLijn('[data-groeilijn]', voetElement.value));

    const blokken =
        voetElement.value?.querySelectorAll<HTMLElement>('[data-voet-blok]') ??
        [];

    opruimers.push(
        stapelBinnen([...blokken], {
            van: { opacity: 0, y: 32 },
            stagger: 0.09,
        }),
    );

    if (prefersReducedMotion() || voetElement.value === null) {
        return;
    }

    const voet = voetElement.value;

    opruimers.push(sluitDeLens(voet));

    const mm = gsap.matchMedia();

    mm.add('(min-width: 50rem)', () => {
        const diepte = gsap.timeline({
            defaults: { ease: 'none' },
            scrollTrigger: {
                trigger: voet,
                start: 'top bottom',
                end: 'bottom bottom',
                scrub: true,
                invalidateOnRefresh: true,
            },
        });

        if (oog.value !== null) {
            diepte.fromTo(oog.value, { yPercent: 18 }, { yPercent: 0 }, 0);
        }

        if (naam.value !== null) {
            diepte.fromTo(naam.value, { y: 40 }, { y: 0 }, 0);
        }

        return () => {
            diepte.scrollTrigger?.kill();
            diepte.kill();
        };
    });

    opruimers.push(() => mm.revert());
});

/**
 * De lens sluit: het slot van de welkomstanimatie.
 *
 * De entree begint met een lens die zich om het merkteken tekent en opengaat
 * de site in. Hier, aan het eind, tekent dezelfde ring zich om het teken in
 * de voet terwijl de voet binnenschuift, de schaalring draait dicht en het
 * oog stelt scherp. Onderaan de pagina is de lens rond; scrol je terug, dan
 * gaat hij weer open.
 *
 * **Gekoppeld aan de voet zelf** (`top bottom` tot `bottom bottom`), dus het
 * werkt welk onderdeel er ook als laatste staat, en er komt geen scrollruimte
 * bij: de afstand is de hoogte van de voet. **Alleen transform, opacity en de
 * streep van één kleine cirkel.** De schaalring is een eigen `<svg>` die als
 * geheel draait (een CSS-transform; zie de les van de hero-ring in
 * docs/architecture/prestaties.md). Op een telefoon alleen de ring, zonder
 * draai en zonder naloop. Onder reduced motion staat de lens meteen rond: dat is de stand in
 * de CSS, en hier gebeurt dan niets.
 */
const sluitDeLens = (voet: HTMLElement): (() => void) => {
    const ring = lensRing.value;

    if (ring === null) {
        return () => {};
    }

    const teken = lensTeken.value?.$el ?? null;
    const breed = window.matchMedia('(min-width: 50rem)').matches;

    const tijdlijn = gsap.timeline({
        defaults: { ease: 'none' },
        scrollTrigger: {
            trigger: voet,
            start: 'top bottom',
            end: 'bottom bottom',
            /*
             * Op een telefoon direct met de scroll en zonder naloop: een
             * naloop laat GSAP ook tussen de scrollstappen door elk
             * beeldje rekenen, en op een trage telefoon gaf de voet daarmee
             * gemeten meer trage beeldjes dan de rest van de pagina.
             */
            scrub: breed ? 0.4 : true,
            invalidateOnRefresh: true,
        },
    });

    tijdlijn.fromTo(ring, { drawSVG: '0%' }, { drawSVG: '100%' }, 0);

    if (breed && lensSchaal.value !== null) {
        tijdlijn.fromTo(
            lensSchaal.value,
            { rotation: 80, opacity: 0.25 },
            { rotation: 0, opacity: 1 },
            0,
        );
    }

    if (breed && teken !== null) {
        tijdlijn.fromTo(
            teken,
            { scale: 0.82, opacity: 0.45 },
            { scale: 1, opacity: 1 },
            0.15,
        );
    }

    return () => {
        tijdlijn.scrollTrigger?.kill();
        tijdlijn.kill();
        gsap.set([ring, lensSchaal.value, teken].filter(Boolean), {
            clearProps: 'all',
        });
    };
};

onBeforeUnmount(() => opruimers.forEach((opruimen) => opruimen()));
</script>

<template>
    <footer ref="voetElement" class="brand-site-voet">
        <!--
            De accentlijn trekt zich open vanuit het midden zodra je de voet
            bereikt. Een eigen klasse en niet `brand-rule`: die wordt ook
            door de foutpagina en het welkomstvenster gebruikt, en daar
            hoort niets te bewegen. Zie `groeiLijn()` in motion.ts.
        -->
        <div data-groeilijn class="brand-site-voet-lijn" />

        <!--
            Het oog uit de hero, als slotbeeld. Sfeer en geen inhoud, dus
            een lege `alt` en `aria-hidden`; `lazy`, want je ziet het pas
            onderaan de pagina.
        -->
        <div ref="oog" class="brand-site-voet-oog" aria-hidden="true">
            <img
                src="/images/hero-achtergrond-960.webp"
                :srcset="oogSrcset"
                sizes="(min-width: 50rem) 70vw, 140vw"
                alt=""
                width="1600"
                height="900"
                loading="lazy"
                decoding="async"
            />
        </div>

        <div class="brand-site-raam brand-site-voet-binnen">
            <div class="brand-site-voet-raster">
                <!-- Het merk. De naam ligt vast, de zin eronder niet. -->
                <div data-voet-blok class="brand-site-voet-merk">
                    <!--
                        Hetzelfde merkteken als in de kop, via hetzelfde
                        component. De naam staat er direct naast en wordt
                        één keer voorgelezen.
                    -->
                    <p ref="naam" class="brand-site-voet-naam">
                        <!--
                            De lens die de pagina afsluit: dezelfde ringen
                            als de welkomstanimatie, om het merkteken. Zie
                            `sluitDeLens` hierboven.
                        -->
                        <span class="brand-site-voet-lens" aria-hidden="true">
                            <svg
                                ref="lensSchaal"
                                class="brand-site-voet-schaal"
                                viewBox="0 0 100 100"
                            >
                                <circle
                                    cx="50"
                                    cy="50"
                                    r="48.5"
                                    pathLength="360"
                                    vector-effect="non-scaling-stroke"
                                />
                            </svg>
                            <svg
                                class="brand-site-voet-ring"
                                viewBox="0 0 100 100"
                            >
                                <circle
                                    ref="lensRing"
                                    cx="50"
                                    cy="50"
                                    r="45"
                                    vector-effect="non-scaling-stroke"
                                />
                            </svg>
                            <AppLogoIcon
                                ref="lensTeken"
                                class="brand-site-voet-teken"
                            />
                        </span>
                        <span>
                            <span class="brand-text-gradient">@T</span>
                            <span> IT Advies</span>
                        </span>
                    </p>

                    <p v-if="voet.tagline" class="brand-site-voet-zin">
                        {{ voet.tagline }}
                    </p>

                    <a
                        v-if="linkedin"
                        :href="linkedin"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="brand-site-voet-linkedin"
                    >
                        <LinkedinMerk class="brand-site-voet-linkedin-teken" />
                        <span>{{ $t('Volg op LinkedIn') }}</span>
                    </a>
                </div>

                <!--
                    Niets tonen als er geen onderdelen zijn. Dat is het
                    geval op een foutpagina: de voet staat er wel, maar de
                    ankers hebben daar niets om heen te wijzen.

                    `aria-label` omdat dit de tweede navigatie op de pagina
                    is. Zonder naam kondigt een schermlezer er twee aan die
                    allebei "navigatie" heten.
                -->
                <nav
                    v-if="items.length > 0"
                    data-voet-blok
                    class="brand-site-voet-kolom"
                    :aria-label="$t('Onderdelen van de site')"
                >
                    <p class="brand-site-voet-kop">{{ $t('Op de site') }}</p>

                    <!--
                        Een eigen omhulsel om de links: de lijst loopt in
                        twee kolommen en het kopje erboven niet.
                    -->
                    <div class="brand-site-voet-lijst">
                        <component
                            :is="tag"
                            v-for="item in items"
                            :key="item.key"
                            :href="anker(item.key)"
                            class="brand-site-voet-link"
                            @click="kies(item.key, $event)"
                        >
                            {{ item.label }}
                        </component>
                    </div>
                </nav>

                <div data-voet-blok class="brand-site-voet-kolom">
                    <p class="brand-site-voet-kop">{{ $t('Contact') }}</p>

                    <a
                        v-if="email"
                        :href="`mailto:${email}`"
                        class="brand-site-voet-link brand-site-voet-mail"
                    >
                        {{ email }}
                    </a>

                    <a
                        v-if="voet.telefoon"
                        :href="`tel:${voet.telefoon.link}`"
                        class="brand-site-voet-link brand-site-data"
                    >
                        {{ voet.telefoon.tekst }}
                    </a>

                    <!--
                        Geen link: een werkgebied is geen adres, en een
                        kaartlink naar "Noord-Brabant" helpt niemand.
                    -->
                    <p v-if="voet.plaats" class="brand-site-voet-regel">
                        {{ voet.plaats }}
                    </p>
                </div>
            </div>

            <!--
                Onder de streep het zakelijke. De privacyverklaring hoort
                hier en niet in de navigatie erboven: die gaat over de
                onderdelen van de pagina, en dit is een eigen pagina.
                Onderaan is ook waar bezoekers hem zoeken.

                Geen puntjes tussen de onderdelen maar ruimte: de regel
                breekt op een telefoon toch, en dan blijft er anders een
                puntje aan het begin van een regel hangen.
            -->
            <div class="brand-site-voet-balk">
                <p class="brand-site-voet-zakelijk">
                    <span>&copy; {{ year }} @T IT Advies</span>

                    <span v-if="voet.kvk" class="brand-site-data">
                        {{ $t('KvK') }} {{ voet.kvk }}
                    </span>

                    <span v-if="voet.btw" class="brand-site-data">
                        {{ $t('btw') }} {{ voet.btw }}
                    </span>

                    <Link :href="privacy()" class="brand-site-voet-link">
                        {{ $t('Privacyverklaring') }}
                    </Link>
                </p>

                <!--
                    **De credit, en die is geen instelling.**

                    Hij staat hier hardgecodeerd en niet in `config/` of in
                    `footer_settings`. Een configwaarde is een env-waarde en
                    een instelling is een schuifje; allebei maken er iets
                    van dat uitgezet kan worden, en dan is het geen credit
                    meer.

                    De naam komt níet uit `page.props.eigenaar`. Die bevat
                    Erik Aarssen, de eigenaar van deze site. Kelvin Aarssen
                    heeft hem gebouwd. Dat scheelt één letter en het is
                    niet dezelfde persoon.

                    Alleen het label gaat door `$t()`: een naam vertaal je
                    niet.
                -->
                <p class="brand-site-voet-credit">
                    {{ $t('Gemaakt door') }} Kelvin Aarssen
                </p>
            </div>
        </div>
    </footer>
</template>
