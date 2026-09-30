<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, useTemplateRef } from 'vue';
import LinkedinMerk from '@/components/site/LinkedinMerk.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import {
    draaiRadar,
    kantelKaarten,
    volgDeMuis,
    zendSignaal,
} from '@/lib/motion';
import type { SectieProps } from '@/types/secties';

/**
 * De uitnodiging om verder te kijken op LinkedIn.
 *
 * **Geen module maar een blok.** Er valt niets te beheren: er is één
 * link, en die ligt vast in `config/site.php`. Wat de klant er wél mee
 * kan is hem verslepen en uitzetten, net als elk ander onderdeel.
 *
 * **Waarom dit een eigen onderdeel is en geen regeltje in de voet.** Op
 * een zakelijke site is LinkedIn waar het gesprek verdergaat: iemand die
 * je site heeft gelezen en nog niet wil mailen, wil je wel volgen. Een
 * pictogrammetje onderaan haalt dat niet; een blok dat erom vraagt wel.
 * Het staat daarom ná het contactformulier -- eerst de vraag om contact,
 * dan de zachtere uitnodiging.
 *
 * **De animatie zit op het merkteken.** Hier stond eerst een netwerkje
 * van lijnen dat zichzelf tekende achter de kaart. Dat werkte op papier
 * en niet op het scherm: het lag erachter, de lijnen waren haarfijn en
 * de helft stond buiten beeld. Nu gaan er ringen uit vanaf het
 * merkteken -- precies waar je toch al kijkt. Zie `zendSignaal` in
 * motion.ts.
 *
 * **En erachter gaat een radar rond.** Een lichtbundel die traag
 * ronddraait, met twee flauwe cirkels eromheen. Dat is bewust de
 * tegenhanger van de ringen: daar gaat iets uit, hier wordt gekeken.
 * Alleen op een breed scherm, en zo zwak dat je het pas ziet als je
 * erop let. Zie `draaiRadar`.
 *
 * De kaart zelf kantelt mee met de cursor en heeft een gloed die de muis
 * volgt -- dezelfde twee effecten als op de hero en de dienstkaarten, dus
 * het voelt als hetzelfde huis.
 *
 * Zie docs/architecture/modules/linkedin.md.
 */
defineProps<SectieProps>();

const page = usePage();

/**
 * Het adres komt van de server en staat niet in dit bestand.
 *
 * Twee redenen. Het is echte gegevens van de klant, en die horen niet in
 * een Vue-component. En straks staat er meer dan één knop naar LinkedIn;
 * dan wijzen ze allemaal naar dezelfde bron. Zie config/site.php.
 */
const adres = computed(() => (page.props.linkedin as string | undefined) ?? '');

/** De naam van de eigenaar, uit dezelfde bron als het portaalaccount. */
const naam = computed(() => (page.props.eigenaar as string | undefined) ?? '');

const sectie = useTemplateRef<HTMLElement>('sectie');
const kaart = useTemplateRef<HTMLElement>('kaart');
const gloed = useTemplateRef<HTMLElement>('gloed');
const veeg = useTemplateRef<HTMLElement>('veeg');

const opruimers: Array<() => void> = [];

onMounted(() => {
    if (sectie.value === null) {
        return;
    }

    const onderdelen = Array.from(
        sectie.value.querySelectorAll<HTMLElement>('[data-op]'),
    );
    const ringen = Array.from(
        sectie.value.querySelectorAll<HTMLElement>('[data-ring]'),
    );

    opruimers.push(zendSignaal(onderdelen, ringen));

    if (veeg.value !== null) {
        opruimers.push(draaiRadar(veeg.value));
    }

    if (kaart.value !== null) {
        // Dezelfde kanteling als bij de dienstkaarten, maar zachter: dit
        // is één groot vlak, en vier graden voelt daarop als scheefzakken.
        opruimers.push(kantelKaarten([kaart.value], { graden: 2.5 }));
    }

    if (gloed.value !== null) {
        opruimers.push(volgDeMuis(gloed.value));
    }
});

onBeforeUnmount(() => {
    opruimers.forEach((opruimen) => opruimen());
});
</script>

<template>
    <SiteSection id="linkedin" :tone="tone" :divided="divided">
        <div ref="sectie" class="brand-linkedin">
            <!--
                De radar achter de kaart: twee flauwe cirkels en een
                bundel die traag ronddraait. Puur decoratie, dus
                `aria-hidden`, en het vangt niets af.

                Hij staat er ook op een telefoon in de opmaak, maar de
                CSS zet hem daar op `display: none` en `draaiRadar` laat
                hem daar niet draaien. Twee sloten op dezelfde deur,
                want een laag die eeuwig beweegt kost daar accu.
            -->
            <div class="brand-linkedin-radar" aria-hidden="true">
                <span class="brand-linkedin-radar-cirkel" />
                <span class="brand-linkedin-radar-cirkel" />
                <span ref="veeg" class="brand-linkedin-radar-veeg" />
            </div>

            <!--
                De gloed achter de kaart, die de cursor volgt. Ligt
                erachter en vangt niets af.
            -->
            <div
                ref="gloed"
                class="brand-linkedin-gloed pointer-events-none"
                aria-hidden="true"
            />

            <div ref="kaart" class="brand-linkedin-kaart brand-kantel">
                <!--
                    Het merkteken, met de ringen die eromheen vandaan
                    gaan. Puur decoratief, dus `aria-hidden`: wat ze
                    zeggen staat er ook in woorden onder.
                -->
                <span data-op class="brand-linkedin-merk-vak">
                    <span
                        data-ring
                        class="brand-linkedin-ring"
                        aria-hidden="true"
                    />
                    <span
                        data-ring
                        class="brand-linkedin-ring"
                        aria-hidden="true"
                    />

                    <span class="brand-linkedin-merk" aria-hidden="true">
                        <LinkedinMerk />
                    </span>
                </span>

                <p data-op class="brand-linkedin-opschrift">
                    {{ $t('LinkedIn') }}
                </p>

                <h2 data-op class="brand-linkedin-titel">
                    {{ $t('Blijf op de hoogte') }}
                </h2>

                <p data-op class="brand-linkedin-tekst">
                    {{
                        $t(
                            'Wat er speelt in het vak, waar ik aan werk en wat er af is -- dat deel ik op LinkedIn. Nog niet toe aan een bericht? Volgen mag ook.',
                        )
                    }}
                </p>

                <!--
                    Een echte link en geen knop met een klikafhandelaar:
                    zo kun je hem kopiëren, in een nieuw venster openen en
                    met het toetsenbord bereiken.

                    Hij opent wél in een nieuw tabblad, anders dan de
                    links in het portaal. Dit is een ander domein: een
                    bezoeker die doorklikt hoort zijn plek op deze pagina
                    niet kwijt te raken. Het pijltje zegt dat vooraf.
                -->
                <a
                    v-if="adres"
                    data-op
                    :href="adres"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="brand-linkedin-knop"
                >
                    <span>
                        {{
                            naam
                                ? $t('Bekijk :naam', { naam })
                                : $t('Bekijk het profiel')
                        }}
                    </span>
                    <ArrowUpRight class="size-4 shrink-0" />
                </a>
            </div>
        </div>
    </SiteSection>
</template>
