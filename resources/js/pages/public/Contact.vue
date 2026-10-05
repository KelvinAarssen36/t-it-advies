<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ArrowUpRight, Clock, Mail } from '@lucide/vue';
import { computed } from 'vue';
import ContactFormulier from '@/components/site/ContactFormulier.vue';
import { home } from '@/routes';
import type { OnderwerpOpDeSite, VeldOpDeSite } from '@/types/contact';
import type { SectieKop } from '@/types/secties';

/**
 * De aparte contactpagina.
 *
 * Bestaat alleen als de eigenaar daarvoor heeft gekozen; zie
 * PublicContactController, die anders een 404 geeft.
 *
 * **Het formulier zelf staat in een eigen component**, want het staat op
 * twee plekken: hier en in de sectie onderaan de landingspagina. Eén
 * formulier op twee plekken is één plek om aan te passen -- en vooral: één
 * plek waar de velden uit de server worden getekend.
 *
 * **Deze pagina was eerst onaf, en dat was aan drie dingen te zien.**
 *
 * 1. Er stond geen kop. Het type dat hier was opgeschreven noemde de velden
 *    `eyebrow`, `title` en `intro`, maar `SectionHeading::voorDeSite()`
 *    stuurt `opschrift`, `titel` en `inleiding`. Alle drie waren dus
 *    `undefined`: geen foutmelding, alleen een lege plek waar de kop hoort.
 *    Vandaar nu het gedeelde type `SectieKop` en niet een eigen lijstje.
 * 2. Er zat geen omhulsel om de pagina, dus het formulier liep van rand tot
 *    rand en er was geen ruimte boven en onder. `/privacy` doet het goed met
 *    `mx-auto max-w-* px-6 py-16`; dat patroon staat nu ook hier.
 * 3. Een formulier alleen op een lege pagina is een invulbriefje. Er staat
 *    nu bij naar wíe je schrijft -- met de foto en de naam van de eigenaar,
 *    zijn adres en zijn LinkedIn -- want dat is het verschil tussen een
 *    contactpagina en een veldenlijst.
 *
 * De layout komt automatisch: alles in `pages/public/` krijgt
 * `PublicLayout`; zie resources/js/app.ts.
 *
 * Zie docs/architecture/modules/contact.md.
 */
const props = defineProps<{
    kop: SectieKop;
    velden: VeldOpDeSite[];
    onderwerpen: OnderwerpOpDeSite[];
    eigenOnderwerpToegestaan: boolean;
    instellingen: string;
    email: string;
}>();

const page = usePage();

/** De naam van de eigenaar, uit de gedeelde props. */
const eigenaar = computed(
    () => (page.props.eigenaar as string | undefined) ?? '',
);

/** Zijn LinkedIn, als die is ingesteld. */
const linkedin = computed(
    () => (page.props.linkedin as string | undefined) ?? '',
);
</script>

<template>
    <Head :title="props.kop.titel" />

    <section class="mx-auto max-w-6xl px-6 py-16 sm:py-24">
        <!--
            De weg terug, bovenaan. De kop van de site laat op deze pagina
            geen onderdelen zien -- die ankers bestaan hier niet -- dus
            zonder deze link is de enige uitweg de terugknop van de browser.

            **Eén keer en niet twee.** De privacyverklaring heeft hem boven
            én onder omdat die pagina lang is; deze past op één schermhoogte,
            en dan is twee keer dezelfde link ruis.
        -->
        <Link :href="home()" class="brand-terug">
            <ArrowLeft class="size-4" />
            {{ $t('Terug naar de website') }}
        </Link>

        <header class="mt-8 max-w-2xl space-y-3" data-reveal>
            <p v-if="props.kop.opschrift" class="brand-contactpagina-opschrift">
                {{ props.kop.opschrift }}
            </p>
            <h1 class="brand-contactpagina-titel">{{ props.kop.titel }}</h1>
            <p
                v-if="props.kop.inleiding"
                class="text-pretty text-muted-foreground"
            >
                {{ props.kop.inleiding }}
            </p>
        </header>

        <!--
            Het formulier krijgt de brede kolom, want dat is waarvoor
            iemand hier komt. Het visitekaartje staat ernaast en niet
            erboven: zo zie je tegelijk naar wie je schrijft en wat je moet
            invullen. Onder `lg` staat het eronder -- op een telefoon wil je
            eerst het formulier zien.
        -->
        <div
            class="mt-10 grid gap-8 lg:mt-12 lg:grid-cols-[1fr_20rem] lg:gap-12"
        >
            <div class="brand-contactpagina-formulier" data-reveal>
                <ContactFormulier
                    :velden="props.velden"
                    :onderwerpen="props.onderwerpen"
                    :eigen-onderwerp-toegestaan="props.eigenOnderwerpToegestaan"
                    :instellingen="props.instellingen"
                    :email="props.email"
                />
            </div>

            <aside class="brand-contactkaart" data-reveal>
                <!--
                    Dezelfde foto als het medaillon in de kop van de site,
                    in dezelfde maten. Eén beeld voor beide plekken: dat
                    scheelt een download en het is hetzelfde gezicht.
                -->
                <img
                    class="brand-contactkaart-foto"
                    src="/images/persoon-medaillon-320.webp"
                    srcset="
                        /images/persoon-medaillon-320.webp 320w,
                        /images/persoon-medaillon-480.webp 480w
                    "
                    sizes="96px"
                    alt=""
                    width="320"
                    height="320"
                    loading="lazy"
                    decoding="async"
                />

                <p v-if="eigenaar" class="brand-contactkaart-naam">
                    {{ eigenaar }}
                </p>
                <p class="brand-contactkaart-rol">
                    {{ $t('IT-advies en realisatie') }}
                </p>

                <hr class="brand-contactkaart-streep" />

                <ul class="brand-contactkaart-lijst">
                    <li>
                        <Mail class="size-4 shrink-0" aria-hidden="true" />
                        <a
                            :href="`mailto:${props.email}`"
                            class="brand-sitemail"
                        >
                            {{ props.email }}
                        </a>
                    </li>
                    <li v-if="linkedin">
                        <ArrowUpRight
                            class="size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <!--
                            Buiten de site, dus een nieuw tabblad met het
                            tekentje erbij. Zie docs/architecture/overzicht.md.
                        -->
                        <a
                            :href="linkedin"
                            target="_blank"
                            rel="noopener"
                            class="brand-sitemail"
                        >
                            {{ $t('LinkedIn') }}
                        </a>
                    </li>
                    <li>
                        <Clock class="size-4 shrink-0" aria-hidden="true" />
                        <span>{{
                            $t('Meestal binnen een werkdag antwoord')
                        }}</span>
                    </li>
                </ul>
            </aside>
        </div>
    </section>
</template>
