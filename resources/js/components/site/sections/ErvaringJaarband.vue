<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useTemplateRef,
    watch,
} from 'vue';
import { prefersReducedMotion } from '@/lib/motion';
import type { ErvaringOpDeSite } from '@/types/ervaring';

/**
 * De jaarband: de loopbaan als één regel jaartallen, en de bediening ervan.
 *
 * **Eerst was dit een beeld, nu is het een knoppenrij.** De band schoof met
 * de scroll van links naar rechts, los van alles eronder: hij bewoog, maar
 * je kon er niets mee, en de carrousel had daarnaast een eigen rail met
 * jaartallen. Twee keer dezelfde tijdlijn, en de opvallendste van de twee
 * deed niets. Nu is de band de jaarbediening van Ervaring: het jaar van de
 * ervaring die je ziet licht op, en een klik op een jaar brengt je bij die
 * ervaring. De rail in de carrousel is weg.
 *
 * **De indeling is per beginjaar**, van oud naar nieuw, net als de groepen
 * in de lijst. Daarvoor is gekozen en niet voor elk jaar van de loopbaan:
 * met vijfendertig jaar ervaring en zevenentwintig functies zou een band met
 * elk jaar vooral lege jaartallen tonen, en een jaartal waar je op klikt
 * hoort je ergens heen te brengen.
 *
 * - **Meer ervaringen die in hetzelfde jaar beginnen** krijgen een teller
 *   ("×2"). Een klik brengt je naar de nieuwste van dat jaar; nog een klik op
 *   hetzelfde jaar naar de volgende in dat jaar. Zo is elke ervaring met de
 *   band te bereiken, ook als er drie in één jaar beginnen.
 * - **Een ervaring die meer jaren beslaat** vult zijn beginjaar en licht de
 *   jaren van zijn periode zachter op (`data-in-periode`): je ziet hoe lang
 *   hij liep, en waar hij begon.
 *
 * **Echte bediening, ook zonder muis.** Een `toolbar` met één tabstop: de
 * pijltjes, Home en End gaan van jaar naar jaar en brengen de ervaring mee,
 * Enter of spatie op het actieve jaar gaat naar de volgende in dat jaar.
 * Elke knop zegt hoeveel ervaringen erachter zitten.
 *
 * **Op een aanraakscherm veeg je de band zelf**, met gewone, native
 * horizontale scroll en `scroll-snap`. Geen eigen sleepcode: die zou met
 * verticaal scrollen gaan vechten. `data-lenis-prevent-horizontal` laat een
 * zijwaartse veeg aan de band; een verticale gaat gewoon naar de pagina.
 *
 * **De band beweegt alleen nog door bediening**: als het actieve jaar
 * wisselt, schuift hij er kort naartoe (en onder reduced motion meteen).
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
const props = defineProps<{
    /** Van nieuw naar oud, zoals de carrousel en de lijst ze tonen. */
    items: ErvaringOpDeSite[];
    /** De ervaring die nu zichtbaar is (index in `items`), of niets. */
    actief: number | null;
}>();

/**
 * `focus` is waar of niet de focus mee moet naar die ervaring. Bij een klik
 * of Enter wel -- dan wil je hem lezen; bij de pijltjes niet, want dan blader
 * je door de jaren en hoort de focus in de band te blijven.
 */
const emit = defineEmits<{ naar: [index: number, focus: boolean] }>();

type Stap = {
    jaar: string;
    /** De ervaringen die in dit jaar begonnen, in de volgorde van `items`. */
    indexen: number[];
    organisatie: string;
};

/** Eén stap per beginjaar, van oud naar nieuw. */
const stappen = computed<Stap[]>(() => {
    const perJaar = new Map<string, Stap>();

    props.items.forEach((item, index) => {
        if (item.jaar === '') {
            return;
        }

        const stap = perJaar.get(item.jaar);

        if (stap === undefined) {
            perJaar.set(item.jaar, {
                jaar: item.jaar,
                indexen: [index],
                organisatie: item.organisatie,
            });
        } else {
            stap.indexen.push(index);
        }
    });

    return [...perJaar.values()].sort((a, b) => a.jaar.localeCompare(b.jaar));
});

const huidigItem = computed(() =>
    props.actief === null ? undefined : props.items[props.actief],
);

const actieveStap = computed(() =>
    stappen.value.findIndex((stap) => stap.jaar === huidigItem.value?.jaar),
);

/** Valt dit jaar binnen de periode van de zichtbare ervaring? */
const inPeriode = (stap: Stap): boolean => {
    const item = huidigItem.value;

    if (item === undefined || stap.jaar === item.jaar) {
        return false;
    }

    return stap.jaar > item.jaar && stap.jaar <= (item.totJaar ?? item.jaar);
};

/** Welke van de ervaringen in dit jaar nu zichtbaar is (1-based), of 0. */
const plekInJaar = (stap: Stap): number =>
    props.actief === null ? 0 : stap.indexen.indexOf(props.actief) + 1;

/** Kies een jaar: de nieuwste ervaring ervan, of de volgende als je er al bent. */
const kies = (stap: Stap): void => {
    const plek = plekInJaar(stap);
    const volgende =
        plek === 0 ? stap.indexen[0] : stap.indexen[plek % stap.indexen.length];

    if (volgende !== undefined) {
        emit('naar', volgende, true);
    }
};

/* --- Toetsen: één tabstop, de pijltjes binnen de band ---------------- */

const focusStap = ref(0);

watch(
    actieveStap,
    (nu) => {
        if (nu >= 0) {
            focusStap.value = nu;
        }
    },
    { immediate: true },
);

const knoppen = (): HTMLButtonElement[] =>
    Array.from(
        band.value?.querySelectorAll<HTMLButtonElement>('[data-stap]') ?? [],
    );

const opToets = (gebeurtenis: KeyboardEvent): void => {
    const laatste = stappen.value.length - 1;
    const naar: Record<string, number> = {
        ArrowRight: Math.min(focusStap.value + 1, laatste),
        ArrowLeft: Math.max(focusStap.value - 1, 0),
        Home: 0,
        End: laatste,
    };
    const doel = naar[gebeurtenis.key];

    if (doel === undefined) {
        return;
    }

    gebeurtenis.preventDefault();
    focusStap.value = doel;
    knoppen()[doel]?.focus({ preventScroll: true });

    const stap = stappen.value[doel];

    if (stap !== undefined && stap.indexen[0] !== undefined) {
        emit('naar', stap.indexen[0], false);
    }
};

/* --- Meeschuiven naar het actieve jaar -------------------------------- */

const band = useTemplateRef<HTMLElement>('band');
const kanTerug = ref(false);
const kanVerder = ref(false);

/**
 * Of er links of rechts nog jaren buiten beeld staan. Alleen schrijven als
 * het verandert: dit draait bij elke scrollstap van de band.
 */
const meetRanden = (): void => {
    const vak = band.value;

    if (vak === null) {
        return;
    }

    const terug = vak.scrollLeft > 4;
    const verder = vak.scrollLeft + vak.clientWidth < vak.scrollWidth - 4;

    if (terug !== kanTerug.value) {
        kanTerug.value = terug;
    }

    if (verder !== kanVerder.value) {
        kanVerder.value = verder;
    }
};

/**
 * Het actieve jaar naar het midden van de band schuiven -- alleen
 * zijwaarts, `scrollTo` op de band zelf en geen `scrollIntoView`: dat zou
 * ook de pagina verticaal laten springen als de band net buiten beeld staat.
 */
const schuifNaarActief = (zacht: boolean): void => {
    const vak = band.value;
    const knop = knoppen()[actieveStap.value];

    if (vak === null || knop === undefined) {
        return;
    }

    const links = knop.offsetLeft - (vak.clientWidth - knop.offsetWidth) / 2;

    vak.scrollTo({
        left: Math.max(0, links),
        behavior: zacht && !prefersReducedMotion() ? 'smooth' : 'auto',
    });
};

watch(actieveStap, () => void nextTick(() => schuifNaarActief(true)));

/** De pijlknoppen aan de randen: een stuk verder, voor wie een muis heeft. */
const blader = (richting: 1 | -1): void => {
    band.value?.scrollBy({
        left: richting * band.value.clientWidth * 0.7,
        behavior: prefersReducedMotion() ? 'auto' : 'smooth',
    });
};

let toezicht: ResizeObserver | undefined;

onMounted(() => {
    schuifNaarActief(false);
    meetRanden();

    if (band.value !== null && typeof ResizeObserver !== 'undefined') {
        toezicht = new ResizeObserver(() => {
            schuifNaarActief(false);
            meetRanden();
        });
        toezicht.observe(band.value);
    }
});

onBeforeUnmount(() => toezicht?.disconnect());
</script>

<template>
    <div
        v-if="stappen.length > 1"
        class="brand-site-ervaring-band"
        :data-terug="kanTerug ? '' : undefined"
        :data-verder="kanVerder ? '' : undefined"
    >
        <button
            type="button"
            class="brand-site-ervaring-band-blader"
            data-kant="terug"
            :aria-label="$t('Eerdere jaren')"
            tabindex="-1"
            @click="blader(-1)"
        >
            <ChevronLeft class="size-4" />
        </button>

        <div
            ref="band"
            class="brand-site-ervaring-band-spoor"
            role="toolbar"
            :aria-label="$t('Jaren van de loopbaan')"
            data-lenis-prevent-horizontal
            @keydown="opToets"
            @scroll.passive="meetRanden"
        >
            <button
                v-for="(stap, plek) in stappen"
                :key="stap.jaar"
                type="button"
                data-stap
                class="brand-site-ervaring-band-stap"
                :tabindex="plek === focusStap ? 0 : -1"
                :aria-current="plek === actieveStap ? 'true' : undefined"
                :data-in-periode="inPeriode(stap) ? '' : undefined"
                :aria-label="
                    stap.indexen.length === 1
                        ? $t(':jaar, 1 ervaring: :organisatie', {
                              jaar: stap.jaar,
                              organisatie: stap.organisatie,
                          })
                        : $t(':jaar, :aantal ervaringen', {
                              jaar: stap.jaar,
                              aantal: stap.indexen.length,
                          })
                "
                @click="kies(stap)"
                @focus="focusStap = plek"
            >
                <span class="brand-site-ervaring-band-jaar" aria-hidden="true">
                    {{ stap.jaar }}
                </span>
                <span class="brand-site-ervaring-band-org" aria-hidden="true">
                    {{ stap.organisatie }}
                </span>
                <!--
                    Meer ervaringen in hetzelfde jaar: hoeveel, en welke je
                    nu ziet. "2/3" zegt dat er na deze nog een is.
                -->
                <span
                    v-if="stap.indexen.length > 1"
                    class="brand-site-ervaring-band-aantal brand-site-data"
                    aria-hidden="true"
                >
                    {{
                        plekInJaar(stap) > 0
                            ? `${plekInJaar(stap)}/${stap.indexen.length}`
                            : `×${stap.indexen.length}`
                    }}
                </span>
            </button>
        </div>

        <button
            type="button"
            class="brand-site-ervaring-band-blader"
            data-kant="verder"
            :aria-label="$t('Latere jaren')"
            tabindex="-1"
            @click="blader(1)"
        >
            <ChevronRight class="size-4" />
        </button>
    </div>
</template>
