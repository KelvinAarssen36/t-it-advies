<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { project } from '@/routes';
import type { ProjectHerkomst, ProjectOpDeSite } from '@/types/projecten';

/**
 * Het uitgelichte project, groot.
 *
 * **Het beeld is vierkant en staat náást de tekst, niet erboven.** Dat is
 * geen smaak maar een gevolg: `Logo::bewaar()` tekent altijd in een
 * vierkant, dus een breed hero-beeld bestaat in dit project niet. Naast
 * de tekst werkt een vierkant wél -- het leest als een tegel bij een
 * verhaal in plaats van als een afgeknipte banner.
 *
 * Op een telefoon gaat het beeld erboven, want naast elkaar zou allebei
 * te smal maken.
 *
 * **Zonder beeld vervalt de kolom en loopt de tekst door.** Geen
 * plaatsvervangend vlak van 320 pixels: dat is op deze maat geen accent
 * meer maar een gat. De kleine kaarten doen dat wél, want daar is de
 * letter klein genoeg om als merkteken te lezen.
 *
 * **Twee plekken, en daarom het schuifje `animeren`.** Op de voorpagina
 * komt dit blok op als je erlangs scrollt; in de slideshow op
 * `/projecten` mag dat juist niet. Die dia is bij het laden al in beeld
 * maar nog weggeschoven, en dan ziet de scanner hem niet aankomen --
 * waarna de tekst op nul blijft staan en de dia leeg lijkt. In de
 * slideshow zet de omlijsting zelf de beweging.
 *
 * Zie docs/architecture/modules/projecten.md.
 */
const props = withDefaults(
    defineProps<{
        item: ProjectOpDeSite;
        /** Zie het blok hierboven: uit binnen de slideshow. */
        animeren?: boolean;
        /**
         * Of dit beeld meteen moet laden.
         *
         * Standaard ja: los op de voorpagina is dit het eerste wat een
         * bezoeker van dit onderdeel ziet. In een slideshow staat hij
         * alleen op de eerste dia aan -- de rest staat weggeschoven, en
         * drie afbeeldingen tegelijk ophalen voor twee die niemand ziet
         * is zonde van de verbinding.
         */
        meteen?: boolean;
        /** Zie `ProjectKaart`: belandt als `?van=` in het adres. */
        herkomst?: ProjectHerkomst;
    }>(),
    { animeren: true, meteen: true, herkomst: null },
);

/** `''` zet het attribuut, `undefined` laat het weg. */
const merk = computed(() => (props.animeren ? '' : undefined));

/** Verborgen beginnen hoort bij de scanner; zonder scanner niet. */
const verborgen = computed(() => (props.animeren ? 'opacity-0' : ''));

const adres = computed(() =>
    props.herkomst
        ? project(props.item.slug, { query: { van: props.herkomst } })
        : project(props.item.slug),
);
</script>

<template>
    <article
        class="brand-projectuitgelicht"
        :data-zonder-beeld="item.beeld ? undefined : ''"
    >
        <figure
            v-if="item.beeld"
            :data-reveal="merk"
            class="brand-projectuitgelicht-beeld"
            :class="verborgen"
        >
            <img
                :src="item.beeld"
                :alt="$t('Beeld bij :titel', { titel: item.titel })"
                width="640"
                height="640"
                :loading="meteen ? undefined : 'lazy'"
                decoding="async"
            />
        </figure>

        <div class="brand-projectuitgelicht-tekst">
            <p
                :data-reveal="merk"
                class="brand-projectbadge"
                :class="verborgen"
            >
                {{ item.type }}
            </p>

            <h3
                :data-split="merk"
                class="brand-projectuitgelicht-titel"
                :class="verborgen"
            >
                {{ item.titel }}
            </h3>

            <p
                :data-reveal="merk"
                class="brand-projectuitgelicht-meta"
                :class="verborgen"
            >
                <span>{{ item.organisatie }}</span>
                <span>{{ item.rol }}</span>
                <span>{{ item.periode }}</span>
                <span>{{ item.duur }}</span>
            </p>

            <p
                v-if="item.samenvatting"
                :data-reveal="merk"
                class="brand-projectuitgelicht-zin"
                :class="verborgen"
            >
                {{ item.samenvatting }}
            </p>

            <div :data-reveal="merk" :class="verborgen">
                <Button variant="brand" as-child>
                    <Link :href="adres">
                        {{ $t('Bekijk project') }}
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </Button>
            </div>
        </div>
    </article>
</template>
