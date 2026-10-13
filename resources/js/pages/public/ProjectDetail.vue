<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import SiteKruimels from '@/components/site/SiteKruimels.vue';
import SiteTerug from '@/components/site/SiteTerug.vue';
import { t } from '@/lib/i18n';
import { projecten } from '@/routes';
import type { ProjectHerkomst, ProjectOpDePagina } from '@/types/projecten';

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
const terug = computed(() =>
    props.van === 'start'
        ? null
        : { adres: projecten().url, tekst: t('Terug naar alle projecten') },
);
</script>

<template>
    <Head :title="props.project.titel" />

    <div class="mx-auto max-w-5xl px-6 py-16 sm:py-24">
        <SiteKruimels :titel="'Projecten'" />

        <header
            class="brand-projectpagina-kop"
            :data-zonder-beeld="props.project.beeld ? undefined : ''"
        >
            <figure
                v-if="props.project.beeld"
                class="brand-projectpagina-beeld"
            >
                <img
                    :src="props.project.beeld"
                    :alt="
                        $t('Beeld bij :titel', { titel: props.project.titel })
                    "
                    width="640"
                    height="640"
                    decoding="async"
                />
            </figure>

            <div class="min-w-0">
                <p class="brand-projectbadge">{{ props.project.type }}</p>

                <h1 class="brand-projectpagina-titel">
                    {{ props.project.titel }}
                </h1>

                <!--
                    De puntjes tussen de delen zet de CSS, zodat er geen
                    losse punt overblijft als een deel ooit wegvalt.
                -->
                <p class="brand-projectpagina-meta">
                    <span>{{ props.project.organisatie }}</span>
                    <span>{{ props.project.rol }}</span>
                </p>

                <p class="brand-projectpagina-periode">
                    <span>{{ props.project.periode }}</span>
                    <span>{{ props.project.duur }}</span>
                </p>

                <p
                    v-if="props.project.samenvatting"
                    class="brand-projectpagina-inleiding"
                >
                    {{ props.project.samenvatting }}
                </p>
            </div>
        </header>

        <div class="brand-projectpagina-blad">
            <section v-if="props.project.omschrijving">
                <h2 class="brand-projectpagina-kopje">
                    {{ $t('Wat ik heb gedaan') }}
                </h2>
                <p class="brand-projectpagina-tekst">
                    {{ props.project.omschrijving }}
                </p>
            </section>

            <section v-if="props.project.resultaat">
                <h2 class="brand-projectpagina-kopje">
                    {{ $t('Wat het opleverde') }}
                </h2>
                <p class="brand-projectpagina-tekst">
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
