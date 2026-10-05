<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import ContactFormulier from '@/components/site/ContactFormulier.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import { Button } from '@/components/ui/button';
import { contact as contactPagina } from '@/routes';
import type { OnderwerpOpDeSite, VeldOpDeSite } from '@/types/contact';
import type { SectieKop, SectieProps } from '@/types/secties';

/**
 * Het contactonderdeel op de landingspagina.
 *
 * Dit onderdeel is smaller dan de rest -- een formulier over de volle
 * breedte leest slecht -- en die keuze staat hier en niet bij de
 * aanroeper: hij hoort bij wat dit onderdeel is en niet bij waar het staat.
 *
 * **Twee verschijningsvormen, en de eigenaar kiest.** Het formulier hier,
 * of alleen een knop naar `/contact`. Dat tweede houdt de landing korter
 * en levert een adres op dat hij kan delen; het eerste is de kortste weg
 * naar een bericht. Zie App\Enums\ContactWeergave.
 *
 * Het formulier zelf staat in `ContactFormulier` -- dat staat ook op de
 * aparte pagina, en één formulier op twee plekken is één plek om aan te
 * passen.
 *
 * Zie docs/architecture/modules/contact.md.
 */
defineProps<SectieProps>();

const page = usePage();

/**
 * Het blok zoals de server het meestuurt.
 *
 * Uit de gedeelde props en niet als eigen prop, net als bij de andere
 * secties: `Welcome.vue` geeft elk onderdeel alleen `tone` en `divided`
 * mee, en de rest haalt het zelf op. Zie StatistiekenSection.
 */
type Contactblok = {
    /*
     * **Het gedeelde type en niet een eigen lijstje.**
     *
     * Hier stond `{ eyebrow, title, intro }`, en dat waren de verkeerde
     * namen: `SectionHeading::voorDeSite()` stuurt `opschrift`, `titel` en
     * `inleiding`. Alle drie waren dus `undefined` en stond er op de site
     * geen kop boven het contactformulier -- zonder foutmelding, want een
     * ontbrekende prop is in Vue gewoon leeg. Elke andere sectie gebruikt
     * `SectieKop`; deze nu ook.
     */
    kop: SectieKop | null;
    eigenPagina: boolean;
    velden: VeldOpDeSite[];
    onderwerpen: OnderwerpOpDeSite[];
    eigenOnderwerpToegestaan: boolean;
    instellingen: string;
    email: string;
};

const blok = computed<Contactblok | null>(
    () => (page.props.contact as Contactblok | undefined) ?? null,
);
</script>

<template>
    <SiteSection
        v-if="blok"
        id="contact"
        width="narrow"
        :tone="tone"
        :divided="divided"
    >
        <SectionHeading
            v-if="blok.kop"
            :eyebrow="blok.kop.opschrift ?? undefined"
            :title="blok.kop.titel"
            :intro="blok.kop.inleiding ?? undefined"
        />

        <!--
            De eigenaar heeft gekozen voor een eigen contactpagina. Dan
            staat hier een uitnodiging met een knop en geen formulier --
            ook zodat er niet twee Turnstile-widgets op één site staan.
        -->
        <div v-if="blok.eigenPagina" class="brand-contactknop-vak">
            <!--
                **Hier stond een vaste uitnodigende zin.** Die is eruit:
                de kop hierboven heeft al een `intro` die de eigenaar zelf
                beheert, dus dit was een tweede inleiding naast een
                inleiding -- en die tweede kon hij niet wijzigen.
            -->
            <Button variant="brand" as-child>
                <a :href="contactPagina().url">
                    {{ $t('Neem contact op') }}
                    <ArrowRight class="size-4" />
                </a>
            </Button>

            <!-- Dezelfde uitweg als onder het formulier zelf. -->
            <p class="text-sm text-pretty text-muted-foreground">
                {{ $t('Of mail rechtstreeks:') }}
                <a :href="`mailto:${blok.email}`" class="brand-sitemail">
                    {{ blok.email }}
                </a>
            </p>
        </div>

        <ContactFormulier
            v-else
            :velden="blok.velden"
            :onderwerpen="blok.onderwerpen"
            :eigen-onderwerp-toegestaan="blok.eigenOnderwerpToegestaan"
            :instellingen="blok.instellingen"
            :email="blok.email"
        />
    </SiteSection>
</template>
