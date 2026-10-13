<script setup lang="ts">
import { ArrowLeft, ArrowUpRight, ChevronRight, X } from '@lucide/vue';
import {
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { nextTick, ref, watch } from 'vue';
import ErvaringIcoon from '@/components/site/ErvaringIcoon.vue';
import { gsap, prefersReducedMotion } from '@/lib/motion';
import type { ErvaringOpDeSite } from '@/types/ervaring';

/**
 * Het venster van de tijdlijn, met twee gezichten.
 *
 * | Geopend met  | Wat je ziet                                        |
 * | ------------ | -------------------------------------------------- |
 * | een ervaring | Die ene, helemaal uitgeschreven.                   |
 * | niets        | De hele loopbaan als lijst, om in door te klikken. |
 *
 * **Waarom dat één venster is en geen twee.** Vanuit de lijst wil je op
 * een ervaring kunnen drukken, en dan zou er een tweede venster over het
 * eerste komen. Twee vensters over elkaar is precies waar dit project
 * eerder op is stukgelopen: ze zetten `pointer-events` op de body, en wie
 * er als laatste sluit zet het terug -- waarna het onderste venster wel
 * te zien maar niet meer aan te klikken is. Zie
 * docs/architecture/pagina-indeling.md.
 *
 * In plaats daarvan verandert dít venster van inhoud. Je zakt erin en
 * komt met de pijl terug. Dat is meteen wat je op een telefoon verwacht
 * van een blad dat van onderen komt.
 *
 * **Alles wat leeg mag zijn, is hier ook gewoon weg.** Geen kopje boven
 * een lege beschrijving, geen streepje waar de plaats had moeten staan.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
const props = defineProps<{
    /** De hele loopbaan, voor de lijstweergave. */
    items: ErvaringOpDeSite[];
    /** Open hiermee één ervaring; `null` opent de lijst. */
    item: ErvaringOpDeSite | null;
}>();

const open = defineModel<boolean>('open', { required: true });

/** Welke ervaring er nu op het scherm staat; null is de lijst. */
const getoond = ref<ErvaringOpDeSite | null>(null);

/**
 * Of er een weg terug is.
 *
 * Alleen als je via de lijst binnenkwam. Open je meteen één ervaring --
 * door op de tijdlijn te klikken -- dan is er niets om naar terug te
 * gaan, en dan is een terugpijl een belofte die nergens heen leidt.
 */
const uitLijst = ref(false);

const paneel = ref<HTMLElement | null>(null);

/**
 * De inhoud komt regel voor regel binnen, net nadat het venster er is.
 *
 * Het paneel zelf schuift met CSS -- dat is een overgang die de browser
 * zelf kan doen. Deze getrapte beweging is wat het venster het gevoel
 * geeft dat het zich opent in plaats van dat het er ineens staat, en dat
 * is precies het soort ding waar GSAP beter in is dan een reeks
 * vertraagde CSS-regels.
 */
const onthul = (): void => {
    if (prefersReducedMotion() || paneel.value === null) {
        return;
    }

    const rijen = paneel.value.querySelectorAll('[data-rij]');

    if (rijen.length === 0) {
        return;
    }

    gsap.fromTo(
        rijen,
        { opacity: 0, y: 14 },
        {
            opacity: 1,
            y: 0,
            duration: 0.45,
            // Bij een lange lijst zou een stagger van 0,06 seconden per
            // regel een halve minuut duren. Hij loopt daarom sneller naar
            // mate er meer regels zijn, met een bodem zodat het bij twee
            // of drie nog steeds als een golf leest.
            stagger: Math.min(0.06, 1.2 / Math.max(rijen.length, 1)),
            ease: 'power3.out',
            // Even wachten tot het paneel op zijn plek staat; anders
            // bewegen de regels mee met iets dat zelf nog schuift.
            delay: 0.1,
            clearProps: 'opacity,transform',
        },
    );
};

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    getoond.value = props.item;
    uitLijst.value = props.item === null;

    nextTick(onthul);
});

/** Vanuit de lijst één ervaring opendoen. */
const kies = (item: ErvaringOpDeSite): void => {
    getoond.value = item;

    nextTick(onthul);
};

const terug = (): void => {
    getoond.value = null;

    nextTick(onthul);
};

/**
 * Sluiten gaat één stap terug, niet meteen helemaal dicht.
 *
 * Zat je in een ervaring die je vanuit de lijst had opengedaan, dan
 * brengt sluiten je terug naar die lijst -- met Escape, met een klik
 * naast het venster, en met de pijl. Pas vanaf de lijst gaat het venster
 * echt dicht.
 *
 * **Dat is de reden dat `open` hier met de hand wordt afgehandeld** en
 * niet met een `v-model` op DialogRoot: anders sluit reka-ui bij Escape
 * het hele venster, en ben je in één toetsaanslag allebei je plekken
 * kwijt.
 */
const wisselOpen = (wil: boolean): void => {
    if (wil) {
        open.value = true;

        return;
    }

    if (uitLijst.value && getoond.value !== null) {
        terug();

        return;
    }

    open.value = false;
};
</script>

<template>
    <DialogRoot :open="open" @update:open="wisselOpen">
        <DialogPortal>
            <DialogOverlay class="brand-blad-waas" />

            <!--
                `data-lenis-prevent` is hier geen versiering. De publieke
                site draait op Lenis, en die vangt het muiswiel af om de
                pagina zelf soepel te laten scrollen -- óók als je met je
                muis boven dit venster hangt. Het gevolg was dat een lange
                tekst niet te scrollen viel: je verschoof de pagina
                erachter. Met dit attribuut laat Lenis het wiel met rust
                zodra het binnen dit venster gebeurt.
            -->
            <DialogContent class="brand-blad" data-lenis-prevent>
                <div ref="paneel" class="brand-blad-inhoud">
                    <!-- ------------------- Eén ervaring ------------------ -->
                    <template v-if="getoond">
                        <div data-rij class="flex items-start gap-3 sm:gap-4">
                            <button
                                v-if="uitLijst"
                                type="button"
                                class="brand-blad-terug"
                                :aria-label="$t('Terug naar alle ervaringen')"
                                @click="terug"
                            >
                                <ArrowLeft class="size-4" />
                            </button>

                            <!--
                                Hetzelfde beeldmerkslot als overal: een
                                geüpload beeld of een pictogram, altijd op
                                dezelfde plek en in dezelfde maat.
                            -->
                            <span class="brand-blad-bel" aria-hidden="true">
                                <img
                                    v-if="getoond.logo"
                                    :src="getoond.logo"
                                    alt=""
                                />
                                <ErvaringIcoon v-else :icoon="getoond.icon" />
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="brand-blad-periode">
                                    {{ getoond.periode }}
                                    <span
                                        v-if="getoond.loopt"
                                        class="brand-tijdlijn-nu ml-2"
                                    >
                                        {{ $t('Nu') }}
                                    </span>
                                </p>

                                <DialogTitle class="brand-blad-titel">
                                    {{ getoond.functie }}
                                </DialogTitle>
                            </div>

                            <!--
                                Kwam je via de lijst binnen, dan staat er
                                links al een pijl terug en hoort er geen
                                kruisje naast. Twee knoppen die allebei
                                "weg hier" betekenen maar iets anders doen,
                                is precies waar je op misklikt.
                            -->
                            <button
                                v-if="!uitLijst"
                                type="button"
                                class="brand-blad-sluit"
                                :aria-label="$t('Sluiten')"
                                @click="wisselOpen(false)"
                            >
                                <X class="size-4" />
                            </button>
                        </div>

                        <DialogDescription data-rij class="brand-tijdlijn-meta">
                            <span class="brand-tijdlijn-org">
                                <a
                                    v-if="getoond.website"
                                    :href="getoond.website"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 hover:underline"
                                >
                                    {{ getoond.organisatie }}
                                    <ArrowUpRight class="size-3.5" />
                                </a>
                                <template v-else>
                                    {{ getoond.organisatie }}
                                </template>
                            </span>

                            <span>{{ getoond.duur }}</span>

                            <span v-for="deel in getoond.meta" :key="deel">
                                {{ deel }}
                            </span>
                        </DialogDescription>

                        <!-- Geen kopje boven niets: wat er niet is, staat er niet. -->
                        <div
                            v-if="getoond.beschrijving"
                            data-rij
                            class="brand-blad-tekst brand-scrollbar"
                        >
                            <p>{{ getoond.beschrijving }}</p>
                        </div>
                    </template>

                    <!-- ------------------ De hele loopbaan ---------------- -->
                    <template v-else>
                        <div data-rij class="flex items-start gap-3">
                            <div class="min-w-0 flex-1">
                                <DialogTitle class="brand-blad-titel">
                                    {{ $t('De hele loopbaan') }}
                                </DialogTitle>
                                <DialogDescription
                                    class="mt-1 text-sm text-muted-foreground"
                                >
                                    {{
                                        $t(
                                            ':aantal functies, van nu naar toen. Klik er een aan om verder te lezen.',
                                            { aantal: props.items.length },
                                        )
                                    }}
                                </DialogDescription>
                            </div>

                            <button
                                type="button"
                                class="brand-blad-sluit"
                                :aria-label="$t('Sluiten')"
                                @click="wisselOpen(false)"
                            >
                                <X class="size-4" />
                            </button>
                        </div>

                        <div class="brand-blad-lijst">
                            <button
                                v-for="item in props.items"
                                :key="item.id"
                                data-rij
                                type="button"
                                class="brand-blad-rij"
                                @click="kies(item)"
                            >
                                <span
                                    class="brand-blad-bel is-klein"
                                    aria-hidden="true"
                                >
                                    <img
                                        v-if="item.logo"
                                        :src="item.logo"
                                        alt=""
                                    />
                                    <ErvaringIcoon v-else :icoon="item.icon" />
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span
                                        class="flex flex-wrap items-center gap-x-2 gap-y-1"
                                    >
                                        <span class="font-medium text-white">
                                            {{ item.functie }}
                                        </span>
                                        <span
                                            v-if="item.loopt"
                                            class="brand-tijdlijn-nu"
                                        >
                                            {{ $t('Nu') }}
                                        </span>
                                    </span>

                                    <span class="brand-blad-rij-meta">
                                        {{ item.organisatie }}
                                    </span>

                                    <span class="brand-blad-rij-tijd">
                                        {{ item.periode }} · {{ item.duur }}
                                    </span>
                                </span>

                                <ChevronRight
                                    class="brand-tijdlijn-pijl"
                                    aria-hidden="true"
                                />
                            </button>
                        </div>
                    </template>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
