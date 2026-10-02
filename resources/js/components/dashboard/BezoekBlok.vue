<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, Eye } from '@lucide/vue';
import { computed } from 'vue';
import visitors from '@/routes/admin/visitors';

/**
 * Het blok linksboven op het dashboard: wat de website doet.
 *
 * **Het grote getal is het totaal van altijd.** Dat is een keuze: een
 * dashboardblok dat elke maand op nul begint voelt als iets dat je
 * kwijtraakt, en dit cijfer hoeft nooit opgeruimd te worden -- het gaat
 * over niemand in het bijzonder. Het loopt dus door zolang de site bestaat.
 *
 * Daaronder staat wat er **vandaag** gebeurt, want zonder dat is het een
 * monument en geen dashboard.
 *
 * **Het hele blok is een link**, en niet een knop in een hoek. Je kijkt
 * hiernaar en denkt "hoeveel waren het er deze week" -- dan hoort de hele
 * kaart je daarheen te brengen.
 *
 * De staafjes zijn dezelfde taal als de grafiek op het scherm Bezoekers,
 * maar zonder cijfers en zonder as: hier zijn ze de vorm van de laatste
 * twee weken en niet iets om uit af te lezen.
 *
 * Zie docs/architecture/dashboard.md.
 */
const props = defineProps<{
    weergavenTotaal: number;
    bezoekersVandaag: number;
    weergavenVandaag: number;
    /** De weergaven per dag, oudste eerst. */
    reeks: number[];
    /** Of er al iets gemeten is. */
    meet: boolean;
}>();

const hoogste = computed(() => Math.max(1, ...props.reeks));

const hoogte = (waarde: number): string =>
    `${Math.round((waarde / hoogste.value) * 100)}%`;

/** Of er deze twee weken überhaupt iets te zien is in de staafjes. */
const beweging = computed(() => props.reeks.some((dag) => dag > 0));
</script>

<template>
    <Link :href="visitors.index()" class="brand-bezoekblok">
        <p class="brand-bezoekblok-kop">
            <Eye class="size-4" />
            {{ $t('Je website') }}
            <ArrowUpRight class="brand-bezoekblok-pijl size-4" />
        </p>

        <!--
            Nog niets gemeten. Een nul zou hier zeggen "er komt niemand",
            en dat is iets anders dan "we zijn pas net begonnen".
        -->
        <template v-if="!props.meet">
            <p class="brand-bezoekblok-leeg">
                {{ $t('Nog geen bezoek gemeten') }}
            </p>
            <p class="brand-bezoekblok-regel">
                {{ $t('Zodra de eerste bezoeker langskomt, staat hij hier.') }}
            </p>
        </template>

        <template v-else>
            <!--
                Twee cijfers die makkelijk door elkaar te halen zijn, dus
                ze staan onder een eigen opschrift. Zonder die twee
                woordjes is "1.284" een getal zonder tijdvak, en dan leest
                iemand het als "deze maand" -- precies wat het niet is.
            -->
            <div class="brand-bezoekblok-totaal">
                <p class="brand-bezoekblok-label">
                    {{ $t('Totaal sinds de start') }}
                </p>
                <p class="brand-bezoekblok-getal">
                    <span class="brand-bezoekblok-cijfer brand-text-gradient">
                        {{ props.weergavenTotaal.toLocaleString('nl-NL') }}
                    </span>
                    <span class="brand-bezoekblok-eenheid">
                        {{ $t('weergaven') }}
                    </span>
                </p>
            </div>

            <div class="brand-bezoekblok-vandaag">
                <p class="brand-bezoekblok-label">{{ $t('Vandaag') }}</p>
                <p class="brand-bezoekblok-regel">
                    <template v-if="props.bezoekersVandaag === 0">
                        {{ $t('Nog niemand langs geweest') }}
                    </template>
                    <template v-else>
                        <span class="brand-bezoekblok-nadruk">
                            <template v-if="props.bezoekersVandaag === 1">
                                {{ $t('1 bezoeker') }}
                            </template>
                            <template v-else>
                                {{
                                    $t(':aantal bezoekers', {
                                        aantal: props.bezoekersVandaag,
                                    })
                                }}
                            </template>
                        </span>
                        <span aria-hidden="true"> · </span>
                        <template v-if="props.weergavenVandaag === 1">
                            {{ $t('1 weergave') }}
                        </template>
                        <template v-else>
                            {{
                                $t(':aantal weergaven', {
                                    aantal: props.weergavenVandaag,
                                })
                            }}
                        </template>
                    </template>
                </p>
            </div>

            <!--
                De laatste twee weken als vorm. `aria-hidden`, want de
                getallen staan erboven en veertien losse staafjes
                voorlezen is geen informatie.
            -->
            <span
                v-if="beweging"
                class="brand-bezoekblok-staven"
                aria-hidden="true"
            >
                <span
                    v-for="(dag, index) in props.reeks"
                    :key="index"
                    class="brand-bezoekblok-staaf"
                    :data-vandaag="
                        index === props.reeks.length - 1 ? '' : undefined
                    "
                    :style="{
                        '--hoogte': hoogte(dag),
                        '--vertraging': `${index * 35}ms`,
                    }"
                />
            </span>
        </template>
    </Link>
</template>
