<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import { project } from '@/routes';
import type { ProjectHerkomst, ProjectOpDeSite } from '@/types/projecten';

/**
 * Eén project als kaart.
 *
 * Gebruikt op de voorpagina én op `/projecten`, zodat de twee niet uiteen
 * kunnen lopen. Alles wat erop staat komt al opgemaakt van de server: de
 * periode, het woord op de badge, de taal.
 *
 * **De hele kaart is de link en niet alleen de knop onderaan.** Een kaart
 * met één klikbaar zinnetje is een doelwit van tien bij honderd pixels;
 * op een telefoon mis je dat. Het pijltje eronder is er voor de blik, niet
 * als enige ingang.
 *
 * **Zonder beeld staat er een letter en geen leeg vlak.** Dat is de eerste
 * letter van de organisatie, van de server -- `titel[0]` in het sjabloon
 * zou bij een naam met een accent het verkeerde teken pakken. Een project
 * zonder afbeelding hoort er gewoon te staan.
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
    }>(),
    { herkomst: null },
);

const adres = computed(() =>
    props.herkomst
        ? project(props.item.slug, { query: { van: props.herkomst } })
        : project(props.item.slug),
);
</script>

<template>
    <article data-reveal class="brand-projectkaart opacity-0">
        <Link :href="adres" class="brand-projectkaart-link">
            <span class="brand-projectkaart-beeld">
                <img
                    v-if="item.beeld"
                    :src="item.beeld"
                    :alt="$t('Beeld bij :titel', { titel: item.titel })"
                    width="640"
                    height="640"
                    loading="lazy"
                    decoding="async"
                />
                <span
                    v-else
                    class="brand-projectkaart-letter"
                    aria-hidden="true"
                >
                    {{ item.letter }}
                </span>
            </span>

            <span class="brand-projectkaart-tekst">
                <span class="brand-projectbadge">{{ item.type }}</span>

                <h3 class="brand-projectkaart-titel">{{ item.titel }}</h3>

                <!--
                    De organisatie en de rol op één regel met een puntje
                    ertussen, gezet door de CSS. Zou het puntje in de tekst
                    staan, dan blijft er eentje over als een van de twee
                    ooit wegvalt.
                -->
                <span class="brand-projectkaart-meta">
                    <span>{{ item.organisatie }}</span>
                    <span>{{ item.rol }}</span>
                </span>

                <!--
                    De periode en hoe lang het duurde, met hetzelfde
                    puntje uit de CSS. "mrt. 2023 – nov. 2023" zegt
                    wannéér, "9 maanden" zegt hoe gróót -- en dat tweede
                    is vaak waar iemand op afgaat.
                -->
                <span class="brand-projectkaart-periode">
                    <span>{{ item.periode }}</span>
                    <span>{{ item.duur }}</span>
                </span>

                <span v-if="item.samenvatting" class="brand-projectkaart-zin">
                    {{ item.samenvatting }}
                </span>

                <span class="brand-projectkaart-meer">
                    {{ $t('Bekijk project') }}
                    <ArrowRight class="size-4" aria-hidden="true" />
                </span>
            </span>
        </Link>
    </article>
</template>
