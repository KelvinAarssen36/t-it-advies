<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import { project } from '@/routes';
import type { ProjectHerkomst, ProjectOpDeSite } from '@/types/projecten';

/**
 * Eén project als regel in de lijst op `/projecten`.
 *
 * **Alle regels zijn gelijk.** Dat is een correctie op twee eerdere
 * versies. Eerst was dit een lichte regel tussen de kaarten, daarna een
 * brede kaart afgewisseld met smalle -- allebei riepen dezelfde vraag op:
 * waarom is die ene anders dan de andere? Daar was geen goed antwoord op,
 * want het antwoord was "omdat hij toevallig op plek één van de ronde
 * staat". Nu is elke regel hetzelfde en gaat het verschil alleen nog over
 * de inhoud.
 *
 * **Compact, want de lijst heeft geen grens.** Er staan er net zo veel
 * als de eigenaar heeft, dus elke regel die hoger is dan nodig maakt de
 * pagina meteen een stuk langer. Daarom een klein beeld, één regel met de
 * gegevens en hoogstens twee regels samenvatting -- wie meer wil weten
 * klikt, en dat is precies waar de detailpagina voor is.
 *
 * **De hele regel is de link.** Het pijltje is er voor de blik, niet als
 * enige ingang; een regel met één klikbaar zinnetje is op een telefoon
 * niet te raken.
 *
 * Zie docs/architecture/modules/projecten.md.
 */
const props = withDefaults(
    defineProps<{
        item: ProjectOpDeSite;
        /** Zie `ProjectKaart`: belandt als `?van=` in het adres. */
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
    <article data-reveal class="brand-projectbreed opacity-0">
        <Link :href="adres" class="brand-projectbreed-link">
            <span class="brand-projectbreed-beeld">
                <img
                    v-if="item.beeld"
                    :src="item.beeld"
                    :alt="$t('Beeld bij :titel', { titel: item.titel })"
                    width="640"
                    height="640"
                    loading="lazy"
                    decoding="async"
                />

                <!--
                    Zonder beeld de eerste letter van de organisatie, net
                    als op een kaart. Een leeg vlak zou lezen alsof er iets
                    ontbreekt in plaats van alsof er niets is.
                -->
                <span v-else aria-hidden="true">{{ item.letter }}</span>
            </span>

            <span class="brand-projectbreed-tekst">
                <span class="brand-projectbreed-kop">
                    <span class="brand-projectbadge">{{ item.type }}</span>

                    <!--
                        De periode staat naast het soort en niet op een
                        eigen regel: dat scheelt hoogte, en samen zeggen ze
                        in één oogopslag wát het was en wannéér.
                    -->
                    <span class="brand-projectbreed-wanneer">
                        {{ item.periode }}
                    </span>
                </span>

                <h3 class="brand-projectbreed-titel">{{ item.titel }}</h3>

                <!--
                    De puntjes tussen de delen zet de CSS, zodat er geen
                    losse punt overblijft als een deel ooit wegvalt.
                -->
                <span class="brand-projectbreed-meta">
                    <span>{{ item.organisatie }}</span>
                    <span>{{ item.rol }}</span>
                    <span>{{ item.duur }}</span>
                </span>

                <span v-if="item.samenvatting" class="brand-projectbreed-zin">
                    {{ item.samenvatting }}
                </span>
            </span>

            <!--
                Het pijltje staat rechts en op de volle hoogte van de
                regel, zodat de rij er aan de rechterkant netjes uitziet
                hoe lang een titel ook is. Op een telefoon valt hij weg:
                daar is de regel al duidelijk genoeg aantikbaar.
            -->
            <span class="brand-projectbreed-pijl" aria-hidden="true">
                <ArrowRight class="size-5" />
            </span>
        </Link>
    </article>
</template>
