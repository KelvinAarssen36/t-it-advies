<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, useTemplateRef } from 'vue';
import {
    gsap,
    heeftMuis,
    opEenBreedScherm,
    prefersReducedMotion,
    spotlicht,
} from '@/lib/motion';
import { project } from '@/routes';
import type { ProjectHerkomst, ProjectOpDeSite } from '@/types/projecten';
import { beeldKapot, meldKapot } from '@/lib/beelden';
import { opProjectKlik } from '@/lib/projectOvergang';

/**
 * Eén project als kaart.
 *
 * Gebruikt op de voorpagina én op `/projecten`, zodat de twee niet uiteen
 * kunnen lopen. Alles wat erop staat komt al opgemaakt van de server: de
 * periode, het woord op de badge, de taal.
 *
 * **De hele kaart is de link en niet alleen de knop onderaan.** Een kaart
 * met één klikbaar zinnetje is een doelwit van tien bij honderd pixels;
 * op een telefoon mis je dat. "Bekijk project" onderaan is er voor de
 * blik, niet als enige ingang.
 *
 * **Waar het beeld staat, hangt af van de plek.** Een grote kaart (`groot`,
 * de eerste van drie op de voorpagina) toont het beeld bovenaan. Een
 * gewone kaart toont het klein, als tegeltje naast het soort -- en met een
 * muis zweeft er bij het aanwijzen een grotere versie mee met de cursor.
 * Zo blijft een rij kaarten rustig en compact, en zie je het beeld precies
 * op het moment dat je ernaar kijkt. Op een telefoon is er geen cursor, en
 * daar staat het beeld dus altijd bovenaan.
 *
 * **Zonder beeld staat er een letter en geen leeg vlak.** De eerste letter
 * van de organisatie, van de server -- `titel[0]` in het sjabloon zou bij
 * een naam met een accent het verkeerde teken pakken.
 *
 * Zie docs/architecture/modules/projecten.md.
 */
const props = withDefaults(
    defineProps<{
        item: ProjectOpDeSite;
        /**
         * Waar deze kaart staat.
         *
         * Op de voorpagina geven we `'start'` mee; dat belandt als
         * `?van=start` in het adres en zorgt dat de knop onderaan de
         * detailpagina terugwijst naar de voorpagina in plaats van naar
         * een lijst waar de bezoeker nooit is geweest. Op `/projecten`
         * laten we hem leeg, want daar klopt de lijst wél.
         */
        herkomst?: ProjectHerkomst;
        /** De grote kaart: het beeld (of de letter) staat bovenaan. */
        groot?: boolean;
        /**
         * Hoe de kaart binnenkomt. `reveal` doet het zelf, met de gewone
         * scan van PublicLayout (op /projecten). `groep` laat het aan de
         * ouder: op de voorpagina komen de kaarten samen binnen met
         * `stapelBinnen`, en dan mag de kaart zelf geen `data-reveal`
         * dragen -- twee systemen die dezelfde doorzichtigheid schrijven,
         * laten hem knipperen.
         */
        binnenkomst?: 'reveal' | 'groep';
    }>(),
    { herkomst: null, groot: false, binnenkomst: 'reveal' },
);

const adres = computed(() =>
    props.herkomst
        ? project(props.item.slug, { query: { van: props.herkomst } })
        : project(props.item.slug),
);

const kaart = useTemplateRef<HTMLElement>('kaart');
const zweef = useTemplateRef<HTMLElement>('zweef');

const opruimers: Array<() => void> = [];

/**
 * Het zwevende beeld volgt de cursor, met een klein beetje vertraging.
 *
 * `quickTo` en geen directe positie: een beeld dat als een sticker aan de
 * cursor vastzit oogt goedkoop; een beeld dat er een fractie achteraan
 * komt oogt als iets met gewicht. Alleen `x` en `y`, dus alleen een
 * transform. Of hij zichtbaar is regelt de CSS met `:hover`; dit zegt
 * alleen wáár.
 *
 * Niet op een telefoon, niet zonder muis en niet onder reduced motion: dan
 * staat het beeld gewoon in de kaart.
 */
function volgCursor(element: HTMLElement, beeld: HTMLElement): () => void {
    const naarX = gsap.quickTo(beeld, 'x', {
        duration: 0.55,
        ease: 'power3.out',
    });
    const naarY = gsap.quickTo(beeld, 'y', {
        duration: 0.55,
        ease: 'power3.out',
    });

    const opBeweging = (gebeurtenis: PointerEvent): void => {
        const vlak = element.getBoundingClientRect();

        naarX(gebeurtenis.clientX - vlak.left);
        naarY(gebeurtenis.clientY - vlak.top);
    };

    /*
     * Bij binnenkomst eerst zonder vertraging naar de cursor, anders
     * vliegt het beeld vanuit de hoek waar hij de vorige keer stond.
     */
    const opBinnenkomst = (gebeurtenis: PointerEvent): void => {
        const vlak = element.getBoundingClientRect();

        gsap.set(beeld, {
            x: gebeurtenis.clientX - vlak.left,
            y: gebeurtenis.clientY - vlak.top,
        });
    };

    element.addEventListener('pointerenter', opBinnenkomst);
    element.addEventListener('pointermove', opBeweging, { passive: true });

    return () => {
        element.removeEventListener('pointerenter', opBinnenkomst);
        element.removeEventListener('pointermove', opBeweging);
        gsap.killTweensOf(beeld);
    };
}

onMounted(() => {
    if (kaart.value === null) {
        return;
    }

    opruimers.push(spotlicht([kaart.value]));

    if (
        zweef.value !== null &&
        heeftMuis() &&
        opEenBreedScherm() &&
        !prefersReducedMotion()
    ) {
        opruimers.push(volgCursor(kaart.value, zweef.value));
    }
});

onBeforeUnmount(() => opruimers.forEach((opruimen) => opruimen()));
</script>

<template>
    <article
        ref="kaart"
        :data-reveal="binnenkomst === 'reveal' ? '' : undefined"
        class="brand-site-projectkaart brand-site-spot"
        :class="{ 'opacity-0': binnenkomst === 'reveal' }"
        :data-beeld="item.beeld ? '' : undefined"
        :data-groot="groot ? '' : undefined"
        @click="opProjectKlik($event, kaart, item.slug)"
    >
        <!--
            Vooraf ophalen bij aanwijzen of indrukken: dan staat de
            projectpagina klaar op het moment van klikken, en hoeft het
            meevliegende beeld (lib/projectOvergang.ts) niet te wachten.
            Kort bewaren (anderhalve seconde, de klik volgt meteen): met de
            standaard van dertig seconden hield de cache van Inertia een
            hele voorpagina vast in het geheugen (gemeten: ±3200 knopen).
        -->
        <Link
            :href="adres"
            :prefetch="['hover', 'click']"
            :cache-for="1500"
            class="brand-site-projectkaart-link"
        >
            <!--
                Het beeld bovenaan: op een grote kaart altijd, op een
                gewone alleen op een telefoon. Zie het blok bovenaan.
            -->
            <!--
                Laadt het beeld niet, dan meldt het zich (`meldKapot`) en
                staat in hetzelfde kader de letter. Zie lib/beelden.ts.
            -->
            <span
                v-if="item.beeld"
                class="brand-site-projectkaart-banner"
                :data-project-beeld="item.slug"
                :data-kapot="beeldKapot(item.beeld) ? '' : undefined"
            >
                <img
                    :src="item.beeld"
                    :alt="$t('Beeld bij :titel', { titel: item.titel })"
                    width="640"
                    height="640"
                    loading="lazy"
                    decoding="async"
                    @error="meldKapot(item.beeld)"
                />
                <span
                    v-if="beeldKapot(item.beeld)"
                    class="brand-site-beeld-terugval brand-site-data"
                    aria-hidden="true"
                    >{{ item.letter }}</span
                >
            </span>

            <span
                v-else-if="groot"
                class="brand-site-projectkaart-letterveld brand-site-data"
                :data-project-beeld="item.slug"
                aria-hidden="true"
            >
                {{ item.letter }}
            </span>

            <span class="brand-site-projectkaart-kop">
                <!--
                    Het tegeltje: het beeld klein, of de letter. Leeg
                    `alt`: het grote beeld draagt de beschrijving al, en
                    twee keer hetzelfde voorlezen helpt niemand.
                -->
                <span
                    class="brand-site-projectkaart-tegel"
                    :data-project-beeld="item.slug"
                    aria-hidden="true"
                >
                    <img
                        v-if="item.beeld && !beeldKapot(item.beeld)"
                        :src="item.beeld"
                        alt=""
                        width="640"
                        height="640"
                        loading="lazy"
                        decoding="async"
                        @error="meldKapot(item.beeld)"
                    />
                    <span v-else class="brand-site-data">{{
                        item.letter
                    }}</span>
                </span>

                <span class="brand-site-project-badge">{{ item.type }}</span>
            </span>

            <h3 class="brand-site-projectkaart-titel">{{ item.titel }}</h3>

            <span class="brand-site-project-wie">
                <span>{{ item.organisatie }}</span>
                <span>{{ item.rol }}</span>
            </span>

            <!--
                "mrt. 2023 - nov. 2023" zegt wannéér, "9 maanden" zegt hoe
                gróót -- en dat tweede is vaak waar iemand op afgaat.
            -->
            <span class="brand-site-project-wanneer brand-site-data">
                <span>{{ item.periode }}</span>
                <span>{{ item.duur }}</span>
            </span>

            <span v-if="item.samenvatting" class="brand-site-projectkaart-zin">
                {{ item.samenvatting }}
            </span>

            <span class="brand-site-link brand-site-projectkaart-meer">
                {{ $t('Bekijk project') }}
                <ArrowRight class="size-4" aria-hidden="true" />
            </span>
        </Link>

        <!--
            Het zwevende beeld bij het aanwijzen. Buiten de link, zodat het
            nooit iets afvangt, en alleen als er een beeld is.
        -->
        <span
            v-if="item.beeld && !groot && !beeldKapot(item.beeld)"
            ref="zweef"
            class="brand-site-projectkaart-zweef"
            aria-hidden="true"
        >
            <img
                :src="item.beeld"
                alt=""
                width="640"
                height="640"
                loading="lazy"
                decoding="async"
            />
        </span>
    </article>
</template>
