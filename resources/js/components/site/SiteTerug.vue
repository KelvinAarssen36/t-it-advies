<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, House } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import { useSectieLink } from '@/lib/sectielink';
import { home } from '@/routes';

/**
 * De weg terug onder aan een subpagina.
 *
 * **Knoppen en geen tekstlinks, en onderaan en niet bovenaan.** Boven aan
 * de pagina staat het kruimelpad en in de kop het menu; daar is al gezegd
 * waar je bent en waar je heen kunt. Hier ben je klaar met lezen, en dan is
 * "ik wil weg" een handeling. Een knop leest als een handeling, een
 * tekstlink als een voetnoot.
 *
 * **Op elke subpagina precies één keer.** De privacyverklaring had dit
 * boven én onder, met als reden dat je na een lange tekst onderaan staat.
 * Dat klopte niet: `.brand-sitekop` is `position: sticky`, dus de navigatie
 * is daar ook. Wat er echt ontbrak is dit: een duidelijk einde aan de
 * pagina.
 *
 * **Eén knop was er een te weinig.** Hier stond alleen "Terug naar de
 * voorpagina". Wie van de voorpagina een project aanklikt en daarop drukt,
 * komt bovenaan uit en mag de halve pagina opnieuw afscrollen naar waar hij
 * was. De knop deed precies wat hij beloofde, en toch was het verkeerd.
 *
 * Daarom staan er nu twee, met een duidelijke eerste keus:
 *
 * - **Terug naar het onderdeel.** Dat is in verreweg de meeste gevallen wat
 *   je wilt, dus die krijgt de nadruk. Hij wijst naar het anker op de
 *   voorpagina, zodat je terugkomt waar je weg ging.
 * - **Voorpagina.** Rustiger ernaast, voor wie opnieuw wil beginnen. Dat
 *   woord is met opzet hetzelfde als in het kruimelpad; zie `SiteKruimels`.
 *
 * **Het onderdeel moet wel bestaan.** De eigenaar kan elk onderdeel
 * uitzetten, en dan wijst een anker naar iets dat er niet is -- je landt
 * dan alsnog bovenaan, zonder dat iets dat zegt. We kijken daarom in de
 * lijst met onderdelen uit `useSectieLink`, dezelfde waar de kop en de
 * voettekst hun menu uit halen: staat het er niet in, dan blijft alleen de
 * knop naar de voorpagina over. Zie `Navigatie::sleutels()`.
 *
 * De privacyverklaring is het geval waarin dat altijd zo is: die hoort bij
 * de voettekst en is geen onderdeel van de voorpagina. Daar staat dus één
 * knop, en dat is geen vergeten tweede maar het eerlijke antwoord.
 *
 * Zie docs/architecture/pagina-indeling.md.
 */
const props = withDefaults(
    defineProps<{
        /**
         * Het onderdeel op de voorpagina waar deze pagina bij hoort.
         * `null` betekent: er is er geen, dus alleen de voorpagina.
         */
        sleutel?: string | null;

        /**
         * Een eigen bestemming die vóór `sleutel` gaat.
         *
         * Dit is er voor het ene geval waarin "terug" niet de voorpagina is
         * maar een andere subpagina: een project dat je via de lijst hebt
         * geopend hoort terug te gaan naar die lijst. Zie
         * `ProjectDetail.vue`.
         */
        terug?: { adres: string; tekst: string } | null;
    }>(),
    { sleutel: null, terug: null },
);

const { anker, secties } = useSectieLink();

/** Het onderdeel, maar alleen als het echt op de voorpagina staat. */
const onderdeel = computed(() =>
    props.sleutel === null
        ? null
        : (secties.value.find((item) => item.key === props.sleutel) ?? null),
);

/** De knop met de nadruk, of niets als er nergens anders heen te gaan is. */
const eerste = computed(() => {
    if (props.terug !== null) {
        return props.terug;
    }

    if (onderdeel.value === null) {
        return null;
    }

    return {
        adres: anker(onderdeel.value.key),
        tekst: t('Terug naar :onderdeel', { onderdeel: onderdeel.value.label }),
    };
});
</script>

<template>
    <div class="brand-siteterug">
        <Button v-if="eerste" as-child class="brand-siteterug-eerst">
            <Link :href="eerste.adres">
                <ArrowLeft class="size-4" />
                {{ eerste.tekst }}
            </Link>
        </Button>

        <!--
            De tweede is `outline` en niet `ghost`: hij staat naast een volle
            knop op een verder lege regel, en een ghost-knop ziet er daar uit
            als tekst die per ongeluk klikbaar is. Staat hij er alleen -- op
            de privacyverklaring -- dan is `outline` ook wat er eerst stond.
        -->
        <Button variant="outline" as-child>
            <Link :href="home()">
                <House class="size-4" />
                {{ $t('Voorpagina') }}
            </Link>
        </Button>
    </div>
</template>
