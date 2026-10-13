<script setup lang="ts">
import { computed } from 'vue';

/**
 * Een miniatuur van hoe een onderdeel er op de website uitziet.
 *
 * Geen schermafdruk maar een schets: een paar balkjes in de vorm van het
 * echte onderdeel. Dat is met opzet. Een echte afbeelding zou verouderen
 * zodra de klant iets aanpast, en een pictogram zegt niets over wat er op
 * die plek op zijn pagina staat. Een schets zegt precies genoeg: hier staat
 * een brede kop, daar staan drie blokken naast elkaar.
 *
 * Dit is wat het indelingsscherm van een lijst een **pagina** maakt. Je
 * ziet in één blik de vorm van je site en niet alleen de namen ervan.
 *
 * De donkere onderdelen -- de kop en de voettekst -- krijgen de merkgradient
 * die ze op de echte site ook hebben. Zo herken je ze zonder te lezen.
 */
const props = defineProps<{
    sectie: string;
    /** Bepaalt of de schets gewoon, gedempt of doorzichtig-gestippeld is. */
    staat: 'live' | 'uit' | 'leeg';
}>();

const donker = computed(
    () => props.sectie === 'hero' || props.sectie === 'footer',
);
</script>

<template>
    <div
        class="brand-schets"
        :class="donker ? 'brand-surface-dark' : ''"
        :data-staat="props.staat"
        aria-hidden="true"
    >
        <!-- De kop: een bovenschrift, een grote titel en twee knoppen. -->
        <template v-if="sectie === 'hero'">
            <span class="brand-schets-balk w-1/3 bg-brand-cyan/70" />
            <span class="brand-schets-balk h-1.5 w-4/5 bg-white/75" />
            <span class="brand-schets-balk w-3/5 bg-white/30" />
            <div class="mt-auto flex gap-1">
                <span class="brand-schets-knop bg-brand-blue/80" />
                <span class="brand-schets-knop bg-white/25" />
            </div>
        </template>

        <!-- De diensten: drie blokken naast elkaar. -->
        <template v-else-if="sectie === 'diensten'">
            <span class="brand-schets-balk w-1/2" />
            <div class="mt-auto flex h-1/2 gap-1">
                <span v-for="n in 3" :key="n" class="brand-schets-blok" />
            </div>
        </template>

        <!-- De werkwijze: vier genummerde stappen. -->
        <template v-else-if="sectie === 'werkwijze'">
            <span class="brand-schets-balk w-2/5" />
            <div class="mt-auto flex h-1/2 items-end gap-1">
                <div v-for="n in 4" :key="n" class="flex-1 space-y-0.5">
                    <span class="brand-schets-stip" />
                    <span class="brand-schets-balk w-full" />
                    <span class="brand-schets-balk w-2/3" />
                </div>
            </div>
        </template>

        <!-- Het contactformulier: een kop, twee velden en een knop. -->
        <template v-else-if="sectie === 'contact'">
            <span class="brand-schets-balk w-1/2" />
            <span class="brand-schets-veld" />
            <span class="brand-schets-veld" />
            <span class="brand-schets-knop mt-auto bg-brand-blue/70" />
        </template>

        <!--
            LinkedIn: een blokje met het merkteken en een knop ernaast,
            met de stippellijnen van het netwerk erboven.
        -->
        <template v-else-if="sectie === 'linkedin'">
            <div class="flex w-full items-center gap-1">
                <span class="brand-schets-bel bg-brand-blue/70" />
                <span class="brand-schets-balk w-1/3" />
            </div>
            <span class="brand-schets-balk h-px w-2/3 bg-brand-cyan/50" />
            <span class="brand-schets-knop mt-auto w-1/2 bg-brand-cyan/60" />
        </template>

        <!--
            De projecten: één groot vlak met een regel ernaast, en daaronder
            drie kleine kaarten. Dat is precies hoe het blok eruitziet -- het
            uitgelichte project en de rij eronder.
        -->
        <template v-else-if="sectie === 'projecten'">
            <div class="flex w-full gap-1">
                <span class="brand-schets-blok h-6 w-6 shrink-0" />
                <div class="flex min-w-0 flex-1 flex-col gap-1 pt-0.5">
                    <span class="brand-schets-balk w-1/2 bg-brand-cyan/60" />
                    <span class="brand-schets-balk w-4/5 bg-white/40" />
                </div>
            </div>
            <div class="mt-auto flex w-full gap-1">
                <span class="brand-schets-blok h-3 flex-1" />
                <span class="brand-schets-blok h-3 flex-1" />
                <span class="brand-schets-blok h-3 flex-1" />
            </div>
        </template>

        <!-- De voettekst: een accentlijn en een regel eronder. -->
        <template v-else>
            <span class="brand-schets-balk h-px w-full bg-brand-cyan/60" />
            <div class="mt-auto flex w-full items-end justify-between gap-1">
                <span class="brand-schets-balk w-1/3 bg-white/40" />
                <span class="brand-schets-balk w-1/5 bg-white/20" />
            </div>
        </template>
    </div>
</template>
