<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted } from 'vue';
import SiteKruimels from '@/components/site/SiteKruimels.vue';
import SiteTerug from '@/components/site/SiteTerug.vue';
import { t } from '@/lib/i18n';
import { projecten } from '@/routes';
import type { ProjectHerkomst, ProjectOpDePagina } from '@/types/projecten';
import { beeldKapot, meldKapot } from '@/lib/beelden';
import {
    opProjectKlik,
    vliegTerugBijGeschiedenis,
} from '@/lib/projectOvergang';

/**
 * De pagina van één project.
 *
 * **Deze pagina bestaat omdat je hem kunt delen.** Elders op deze site
 * opent "meer over dit item" een overlay zonder adres; voor een project
 * is dat niet genoeg -- het is precies het soort ding dat de eigenaar in
 * een mail of op LinkedIn stuurt. Zie PublicProjectController voor de
 * volledige afweging.
 *
 * **De terugknop brengt je terug waar je vandaan kwam.** Deze pagina is
 * de enige op de site met twee bovenliggende plekken: de voorpagina en
 * `/projecten`. Eén vaste knop zou in de helft van de gevallen de
 * verkeerde zijn -- je klikt op de voorpagina een project aan en krijgt
 * onderaan "Terug naar alle projecten", een lijst waar je nooit bent
 * geweest. De server zegt in `van` waar je vandaan komt; is dat niets,
 * dan is de lijst het juiste antwoord.
 *
 * De tekstvelden staan met `white-space: pre-line` in de CSS, dus
 * witregels van de eigenaar worden alinea's -- dezelfde afspraak als bij
 * een dienst en bij een antwoord in de vragenlijst. Geen opmaakbalkje,
 * dus niets dat scheef kan staan.
 *
 * Zie docs/architecture/modules/projecten.md.
 */
const props = withDefaults(
    defineProps<{
        project: ProjectOpDePagina;
        /** Zie het blok hierboven. `null` = via de lijst of een link. */
        van?: ProjectHerkomst;
    }>(),
    { van: null },
);

/**
 * De bestemming van de eerste knop, of `null` als `SiteTerug` hem zelf
 * mag bepalen.
 *
 * Kwam je van de voorpagina, dan is het onderdeel "Projecten" daar het
 * juiste antwoord -- en dat weet `SiteTerug` zelf, inclusief of dat
 * onderdeel nog aanstaat. Kwam je van de lijst, dan is die lijst het
 * antwoord en zeggen we het hier.
 */
/**
 * Een lange titel krijgt een kleinere maat, net als in de casus op de
 * voorpagina; de eigenaar typt hem zelf.
 */
const lengte = computed(() =>
    props.project.titel.trim().length > 60 ? 'lang' : 'gewoon',
);

/**
 * De feiten onder de kop. Alle vier in dezelfde letter: in de mono werden
 * periode en duur twee keer zo zwaar als de naam ernaast, terwijl ze niet
 * belangrijker zijn.
 */
const feiten = computed(() =>
    [
        { label: t('Organisatie'), waarde: props.project.organisatie },
        { label: t('Rol'), waarde: props.project.rol },
        { label: t('Periode'), waarde: props.project.periode },
        { label: t('Duur'), waarde: props.project.duur },
    ].filter((feit) => feit.waarde.trim() !== ''),
);

const terug = computed(() =>
    props.van === 'start'
        ? null
        : { adres: projecten().url, tekst: t('Terug naar alle projecten') },
);

/*
 * De terugknop van de browser: het beeld vliegt terug naar zijn kaart. Een
 * eigen luisteraar per bezoek, en weg bij het verlaten: anders vliegt een
 * oud project mee bij de volgende terugknop.
 */
let stopTerug: (() => void) | undefined;

onMounted(() => {
    stopTerug = vliegTerugBijGeschiedenis(props.project.slug);
});

onBeforeUnmount(() => stopTerug?.());
</script>

<template>
    <Head :title="props.project.titel" />

    <!--
        Een klik op een link terug (kruimelpad, de knoppen onderaan) laat het
        grote beeld terugvliegen naar zijn kaart, als die er staat; de
        terugknop van de browser ook. Zie lib/projectOvergang.ts.
    -->
    <div
        class="brand-site-projectpagina mx-auto max-w-5xl px-6 py-16 sm:py-24"
        @click="opProjectKlik($event, $event.currentTarget, props.project.slug)"
    >
        <SiteKruimels :titel="'Projecten'" />

        <!--
            De kop: het beeld (of de letter van de organisatie, net als in
            de casus op de voorpagina) naast het soort werk, de titel en de
            samenvatting. De kolom van het beeld is dezelfde als die van de
            kopjes verderop, zodat de pagina één lijn heeft.
        -->
        <header
            class="brand-site-projectpagina-kop"
            :data-zonder-beeld="props.project.beeld ? undefined : ''"
        >
            <figure
                v-if="props.project.beeld"
                class="brand-site-projectpagina-beeld"
                :data-project-beeld="props.project.slug"
                :data-kapot="beeldKapot(props.project.beeld) ? '' : undefined"
            >
                <img
                    :src="props.project.beeld"
                    :alt="
                        $t('Beeld bij :titel', { titel: props.project.titel })
                    "
                    width="640"
                    height="640"
                    decoding="async"
                    @error="meldKapot(props.project.beeld)"
                />
                <span
                    v-if="beeldKapot(props.project.beeld)"
                    class="brand-site-beeld-terugval brand-site-data"
                    aria-hidden="true"
                    >{{ props.project.letter }}</span
                >
            </figure>

            <figure
                v-else
                class="brand-site-projectpagina-beeld brand-site-projectpagina-letter brand-site-data"
                :data-project-beeld="props.project.slug"
                aria-hidden="true"
            >
                {{ props.project.letter }}
            </figure>

            <div class="min-w-0">
                <p class="brand-site-project-badge">{{ props.project.type }}</p>

                <h1
                    class="brand-site-projectpagina-titel"
                    :data-lengte="lengte"
                >
                    {{ props.project.titel }}
                </h1>

                <p
                    v-if="props.project.samenvatting"
                    class="brand-site-projectpagina-inleiding"
                >
                    {{ props.project.samenvatting }}
                </p>
            </div>
        </header>

        <!--
            De feiten in één strook: voor wie, als wat, wanneer en hoe
            lang. Een label boven elke waarde, zodat je niet hoeft te raden
            wat "9 maanden" betekent. Een leeg feit valt weg in plaats van
            een streepje te tonen.
        -->
        <dl data-reveal class="brand-site-projectpagina-feiten opacity-0">
            <div v-for="feit in feiten" :key="feit.label">
                <dt>{{ feit.label }}</dt>
                <dd>{{ feit.waarde }}</dd>
            </div>
        </dl>

        <!--
            De twee lange teksten, elk met zijn kopje in de kolom van het
            beeld erboven. Staat er geen van beide, dan staat hier niets.
        -->
        <div
            v-if="props.project.omschrijving || props.project.resultaat"
            class="brand-site-projectpagina-blad"
        >
            <section
                v-if="props.project.omschrijving"
                data-reveal
                class="brand-site-projectpagina-deel opacity-0"
            >
                <h2 class="brand-site-projectpagina-kopje">
                    {{ $t('Wat ik heb gedaan') }}
                </h2>
                <p class="brand-site-projectpagina-tekst">
                    {{ props.project.omschrijving }}
                </p>
            </section>

            <section
                v-if="props.project.resultaat"
                data-reveal
                class="brand-site-projectpagina-deel opacity-0"
            >
                <h2 class="brand-site-projectpagina-kopje">
                    {{ $t('Wat het opleverde') }}
                </h2>
                <p class="brand-site-projectpagina-tekst">
                    {{ props.project.resultaat }}
                </p>
            </section>
        </div>

        <!--
            De eerste knop wijst terug naar de plek waar je vandaan kwam.

            Kwam je van de voorpagina, dan stuurt die `?van=start` mee en
            laten we `SiteTerug` zelf het anker van het onderdeel pakken --
            dan kom je terug bij de projecten in plaats van bovenaan. In
            alle andere gevallen -- via `/projecten`, via een gedeelde
            link, uit een zoekresultaat -- is de lijst het juiste antwoord,
            en die geven we mee. Zie `PublicProjectController` voor waarom
            dit via het adres gaat en niet via de verwijzer van de browser.
        -->
        <SiteTerug
            :sleutel="props.van === 'start' ? 'projecten' : null"
            :terug="terug"
        />
    </div>
</template>
