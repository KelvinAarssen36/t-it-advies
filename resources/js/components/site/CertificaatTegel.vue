<script setup lang="ts">
import { Award } from '@lucide/vue';
import type { CertificaatOpDeSite } from '@/types/certificaten';

/**
 * Eén certificaat als tegel in het raster.
 *
 * **Het logo draagt de tegel.** Een bezoeker scant logo's die hij
 * herkent -- Microsoft, Cisco, CompTIA -- en leest pas daarna de naam
 * eronder. Daarom staat het bovenaan en groot, en is er geen keuzelijst
 * met pictogrammen zoals bij een dienst: het logo ís het pictogram.
 * Staat er geen, dan komt er één vast badge-teken.
 *
 * **Een knop of een vlak, en dat hangt van de inhoud af.** Is er een
 * toelichting of een certificaatnummer, dan gaat er een venster open en
 * is dit een echte `<button>` -- dus ook met het toetsenbord te
 * bereiken. Is er geen van beide, dan is het gewoon een tegel. Dezelfde
 * regel als bij een dienst zonder lang verhaal: een venster dat opengaat
 * met niets erin is erger dan geen venster.
 *
 * Aanwijzen doen ze wél allebei hetzelfde. In een raster van negen zag
 * je anders vier tegels bewegen en vijf niet, en dat leest als kapot.
 *
 * **Er staat nooit "verlopen" op.** Hier stond dat wel, en het is eruit
 * gehaald: een bezoeker die op een etalage kijkt hoeft niet te weten dat
 * één papiertje aan vernieuwing toe is. In het beheerscherm staat het
 * nadrukkelijk wél, want daar is het iets om over te beslissen. Zie
 * docs/architecture/modules/certificaten.md.
 *
 * Zie docs/architecture/modules/certificaten.md.
 */
const props = defineProps<{ item: CertificaatOpDeSite }>();

defineEmits<{ openen: [] }>();
</script>

<template>
    <component
        :is="props.item.details ? 'button' : 'div'"
        data-kaart
        class="brand-certificaat"
        :class="{ 'is-klikbaar': props.item.details }"
        :type="props.item.details ? 'button' : undefined"
        @click="props.item.details ? $emit('openen') : undefined"
    >
        <!--
            Het logo in een lichte plaat, zodat een donker merkteken op
            een donkere achtergrond niet verdwijnt. Dezelfde behandeling
            als de logo's in de tijdlijn.
        -->
        <span class="brand-certificaat-plaat" aria-hidden="true">
            <img
                v-if="props.item.logo"
                :src="props.item.logo"
                alt=""
                loading="lazy"
                decoding="async"
                class="brand-certificaat-logo"
            />
            <Award v-else class="brand-certificaat-teken" />
        </span>

        <span class="brand-certificaat-naam">{{ props.item.naam }}</span>

        <span class="brand-certificaat-uitgever">
            {{ props.item.uitgever }}
            <span aria-hidden="true">·</span>
            {{ props.item.jaar }}
        </span>
    </component>
</template>
