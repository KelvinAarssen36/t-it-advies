<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BezoekBlok from '@/components/dashboard/BezoekBlok.vue';
import DigitaleKlok from '@/components/dashboard/DigitaleKlok.vue';
import LeegVak from '@/components/dashboard/LeegVak.vue';
import { dashboard } from '@/routes';

/**
 * Het dashboard van het portaal.
 *
 * De indeling van het standaard Laravel-dashboard blijft staan: drie
 * kleine vlakken bovenin en één groot eronder. In het middelste kleine
 * vlak staat de klok.
 *
 * **Op een telefoon staat de klok bovenaan en is het vlak een dunne
 * strook.** Dat is één klasse op dit vlak -- `order-first md:order-none`
 * -- en bewust niets op de andere twee: die hoeven niet aangepast te
 * worden om de klok vooraan te krijgen, en wat je niet aanraakt kan ook
 * niet stuk. De hoogte van die strook zit in `brand-klok` in app.css.
 *
 * Links van de klok staat wat de website doet; zie BezoekBlok.vue. Het
 * derde vlak en het grote blok eronder zijn nog leeg, en dat blijft zo tot
 * er iets is afgesproken om erin te zetten -- een vlak vullen met een getal
 * dat niemand heeft gevraagd maakt een dashboard niet nuttiger.
 *
 * Zie docs/architecture/dashboard.md.
 */
const props = defineProps<{
    tijdzone: string;
    bezoek: {
        weergavenTotaal: number;
        bezoekersVandaag: number;
        weergavenVandaag: number;
        reeks: number[];
        meet: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="$t('Dashboard')" />

    <div
        class="brand-schuif-x flex h-full flex-1 brand-scrollbar flex-col gap-4 rounded-xl p-4"
    >
        <!--
            De bovenste rij.

            **Een vaste hoogte en niet `aspect-video`**, zoals de starter
            het had. Een beeldverhouding van 16:9 klinkt netjes, maar hij
            rekent met de breedte: hoe breder het scherm, hoe hoger deze
            drie vakken worden. Op een groot scherm werden het daardoor
            drie hoge vlakken met weinig in, en stond alles eronder onnodig
            laag.

            `h-32` op een telefoon en `h-36` daarboven. Dat is hoog genoeg
            voor het cijferblok: de twee cijfers staan daar náást elkaar in
            plaats van gestapeld, precies zodat deze strook plat kan zijn.
            Maak hem niet lager zonder naar BezoekBlok.vue te kijken, en
            draai er `npm run build` achteraan -- anders zie je de oude
            hoogte.
        -->
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <!--
                Wat de website doet. Dit vlak heeft geen eigen rand meer:
                de kaart erin is zelf een link en tekent zijn eigen rand,
                zodat je kunt zien dat hij reageert als je erover gaat.
            -->
            <div class="relative h-32 md:h-36">
                <BezoekBlok
                    :weergaven-totaal="props.bezoek.weergavenTotaal"
                    :bezoekers-vandaag="props.bezoek.bezoekersVandaag"
                    :weergaven-vandaag="props.bezoek.weergavenVandaag"
                    :reeks="props.bezoek.reeks"
                    :meet="props.bezoek.meet"
                />
            </div>

            <!--
                De klok. `order-first` haalt hem op een telefoon naar
                boven; vanaf `md` staat hij weer op zijn eigen plek in de
                rij, en dat is de middelste. Geen vaste hoogte op een
                telefoon: daar hoort dit een dunne strook te zijn en geen
                vlak zo hoog als de andere twee.
            -->
            <div
                class="brand-dashboardvak relative order-first overflow-hidden md:order-none md:h-36"
            >
                <DigitaleKlok :tijdzone="props.tijdzone" />
            </div>
            <div class="relative h-32 md:h-36">
                <LeegVak />
            </div>
        </div>

        <!--
            Het grote vlak. `min-h-56` op een telefoon en niet de
            `min-h-[100vh]` van de starter: een leeg vlak van een hele
            schermhoogte betekende dat je moest scrollen langs niets om bij
            de onderkant van je eigen dashboard te komen.
        -->
        <div class="relative min-h-56 flex-1 md:min-h-min">
            <LeegVak :tekst="$t('Ruimte voor wat er later bij komt')" />
        </div>
    </div>
</template>
