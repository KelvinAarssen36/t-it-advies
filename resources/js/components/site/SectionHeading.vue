<script setup lang="ts">
import { computed } from 'vue';

/**
 * De kop van een sectie: een klein opschrift, een titel en een inleiding.
 *
 * Het opschrift is een `<p>` en geen kop-element. Het ziet eruit als een
 * label, maar in de koppenstructuur van de pagina hoort het niet thuis --
 * een schermlezer zou anders een niveau tegenkomen dat nergens heen leidt.
 *
 * **De titel kiest zijn maat op lengte.** De klant typt de koppen zelf, en
 * een maat die bij "Diensten" past, maakt van een kop van zeventig tekens
 * vijf regels die de hele sectie wegduwen. Drie standen is genoeg; de
 * grenzen staan hieronder en de maten in resources/css/site/basis.css.
 *
 * **En op rol (`rol`).** Elf koppen in exact dezelfde opbouw achter elkaar
 * maakten het ritme van de pagina vlak: elke sectie woog even zwaar. Een
 * verhalend onderdeel (Diensten, Projecten) krijgt een grotere titel en op
 * een breed scherm een redactionele opbouw, met de inleiding rechts op de
 * hoogte van de titel. Een feitelijk onderdeel (Kerngegevens, Statistieken,
 * Certificaten) krijgt een compacte kop, zodat de feiten zelf het gewicht
 * dragen. De rol hoort bij de module en niet bij zijn plek, dus het werkt
 * bij elke volgorde. De koppenstructuur verandert niet: het blijft één
 * `h2` per sectie.
 */
const props = withDefaults(
    defineProps<{
        eyebrow?: string;
        title: string;
        intro?: string;
        level?: 'h1' | 'h2';
        /** Midden voor een kop die boven een gecentreerde compositie staat. */
        align?: 'start' | 'midden';
        /** Verhalend (meer nadruk) of feitelijk (compact); zie hierboven. */
        rol?: 'verhaal' | 'feit';
    }>(),
    {
        level: 'h2',
        align: 'start',
        eyebrow: undefined,
        intro: undefined,
        rol: undefined,
    },
);

const lengte = computed(() => {
    const tekens = props.title.trim().length;

    if (tekens <= 26) {
        return 'kort';
    }

    return tekens <= 60 ? 'middel' : 'lang';
});
</script>

<template>
    <div
        class="brand-site-kop"
        :data-uitlijning="props.align"
        :data-rol="props.rol"
    >
        <p v-if="eyebrow" data-reveal class="brand-site-opschrift opacity-0">
            {{ eyebrow }}
        </p>

        <!--
            De titel splitst per regel en stelt daarbij scherp; het opschrift
            en de inleiding niet. SplitText meet en hersplitst bij elke
            maatverandering, en dat is werk dat je op een kop van een paar
            woorden wilt doen en niet op elke alinea op de pagina.
        -->
        <component
            :is="level"
            data-split
            class="brand-site-kop-titel opacity-0"
            :data-lengte="lengte"
        >
            {{ title }}
        </component>

        <p v-if="intro" data-reveal class="brand-site-kop-inleiding opacity-0">
            {{ intro }}
        </p>
    </div>
</template>
