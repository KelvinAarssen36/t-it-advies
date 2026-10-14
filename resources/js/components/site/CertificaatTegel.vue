<script setup lang="ts">
import { ArrowUpRight, Award } from '@lucide/vue';
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
    <!--
        Een glazen plaat in de credentialwand. `brand-site-spot` geeft de
        rand een licht dat de cursor volgt; de plek komt van
        `kantelKaarten` in CertificatenSection.
    -->
    <component
        :is="props.item.details ? 'button' : 'div'"
        data-kaart
        class="brand-site-certificaten-tegel brand-site-spot"
        :data-klikbaar="props.item.details ? '' : undefined"
        :type="props.item.details ? 'button' : undefined"
        @click="props.item.details ? $emit('openen') : undefined"
    >
        <!--
            Het logo in een eigen plaat, zodat een donker merkteken op
            een donkere achtergrond niet verdwijnt. Dezelfde behandeling
            als de logo's in de tijdlijn.
        -->
        <span class="brand-site-certificaten-plaat" aria-hidden="true">
            <img
                v-if="props.item.logo"
                :src="props.item.logo"
                alt=""
                loading="lazy"
                decoding="async"
                class="brand-site-certificaten-logo"
            />
            <Award v-else class="brand-site-certificaten-teken" />
        </span>

        <!--
            Alleen op een tegel met iets te lezen: het teken dat er een
            venster achter zit. Een tegel zonder details krijgt het niet,
            want dan belooft hij iets wat er niet is.
        -->
        <ArrowUpRight
            v-if="props.item.details"
            class="brand-site-certificaten-meer"
            aria-hidden="true"
        />

        <span class="brand-site-certificaten-naam">{{ props.item.naam }}</span>

        <span class="brand-site-certificaten-uitgever">
            <span class="brand-site-certificaten-uitgever-naam">
                {{ props.item.uitgever }}
            </span>
            <span class="brand-site-data">{{ props.item.jaar }}</span>
        </span>
    </component>
</template>
