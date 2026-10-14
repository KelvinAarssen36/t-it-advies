<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Mail } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, useTemplateRef } from 'vue';
import ContactFormulier from '@/components/site/ContactFormulier.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import { Button } from '@/components/ui/button';
import { gsap, prefersReducedMotion } from '@/lib/motion';
import { contact as contactPagina } from '@/routes';
import type { OnderwerpOpDeSite, VeldOpDeSite } from '@/types/contact';
import type { SectieKop, SectieProps } from '@/types/secties';

/**
 * Het contactonderdeel op de landingspagina.
 *
 * **De slotscène.** Vanaf een tablet staat links de kop met de weg naar
 * het mailadres, en rechts het formulier op een glazen plaat. Het formulier
 * zelf blijft smal -- een invoerveld over de volle breedte leest slecht --
 * maar de sectie niet: zo is dit het einde van het verhaal en niet een
 * smal kolommetje onderaan. Op een telefoon staat alles onder elkaar, de
 * kop eerst.
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
 * **De slotscène beweegt mee met het scrollen.** De kop komt regel voor
 * regel op (`data-split`, zoals elke sectiekop), de lijn boven het
 * mailadres tekent zich van links naar rechts, en de plaat met het
 * formulier komt uit de diepte omhoog terwijl je hem binnenscrolt. Dat
 * laatste alleen als deze sectie een open veld is: staat contact op een
 * oneven plek, dan is de hele sectie al een plaat en doet SiteSection die
 * beweging (`plaatOpkomst`). Twee lagen die allebei omhoogkomen lezen als
 * één schokkende beweging.
 *
 * Zie docs/architecture/modules/contact.md.
 */
const props = defineProps<SectieProps>();

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

const diepte = useTemplateRef<HTMLElement>('diepte');
const opruimers: Array<() => void> = [];

onMounted(() => {
    if (
        props.tone === 'raised' ||
        diepte.value === null ||
        prefersReducedMotion()
    ) {
        return;
    }

    /*
     * Alleen op een breed scherm. Op een telefoon staat de plaat onder de
     * kop en komt hij gewoon op met zijn reveal; een plaat die over de
     * volle breedte omhoogschuift kost daar alleen scherpte.
     */
    const mm = gsap.matchMedia();
    const omhulsel = diepte.value;

    mm.add('(min-width: 50rem)', () => {
        const tween = gsap.fromTo(
            omhulsel,
            { y: 72, scale: 0.955, transformOrigin: '50% 0%' },
            {
                y: 0,
                scale: 1,
                ease: 'none',
                scrollTrigger: {
                    trigger: omhulsel,
                    start: 'top bottom',
                    end: 'top 55%',
                    scrub: true,
                    invalidateOnRefresh: true,
                    // Op zijn plek: de transform eraf, zodat het omhulsel
                    // niet het kader wordt van het keuzelijstje erin.
                    onUpdate: (zelf) => {
                        if (zelf.progress >= 1) {
                            omhulsel.style.transform = '';
                        }
                    },
                },
            },
        );

        return () => {
            tween.scrollTrigger?.kill();
            tween.kill();
            gsap.set(omhulsel, { clearProps: 'transform' });
        };
    });

    opruimers.push(() => mm.revert());
});

onBeforeUnmount(() => opruimers.forEach((opruimen) => opruimen()));
</script>

<template>
    <SiteSection v-if="blok" id="contact" :tone="tone" :divided="divided">
        <div class="brand-site-contact">
            <div class="brand-site-contact-kant">
                <SectionHeading
                    v-if="blok.kop"
                    :eyebrow="blok.kop.opschrift ?? undefined"
                    :title="blok.kop.titel"
                    :intro="blok.kop.inleiding ?? undefined"
                />

                <!--
                    De weg buiten het formulier om, naast het formulier en
                    niet eronder. **Hier en niet ook nog onder de knop**:
                    twee keer hetzelfde adres in één beeld is één keer te
                    veel. Het formulier laat zijn eigen regel daarom weg
                    (`toon-mail`); bij een eigen contactpagina staat die
                    regel gewoon onder de knop.

                    Het blijft een uitweg en geen versiering: laadt
                    Turnstile niet, dan is het formulier bewust niet te
                    versturen. Zie de prop `email` van ContactFormulier.
                -->
                <p
                    v-if="!blok.eigenPagina"
                    data-reveal
                    class="brand-site-contact-mail opacity-0"
                >
                    <span class="brand-site-contact-mail-label">
                        {{ $t('Liever mailen?') }}
                    </span>
                    <a
                        :href="`mailto:${blok.email}`"
                        class="brand-site-contact-mail-adres"
                    >
                        <Mail class="size-5 shrink-0" aria-hidden="true" />
                        <span class="min-w-0">{{ blok.email }}</span>
                    </a>
                </p>
            </div>

            <!--
                Het omhulsel voor de beweging uit de diepte; zie het script.
            -->
            <div ref="diepte" class="brand-site-contact-diepte">
                <!--
                    De eigenaar heeft gekozen voor een eigen contactpagina. Dan
                    staat hier een uitnodiging met een knop en geen formulier --
                    ook zodat er niet twee Turnstile-widgets op één site staan.
                -->
                <div
                    v-if="blok.eigenPagina"
                    data-reveal
                    class="brand-site-contact-uitnodiging opacity-0"
                >
                    <!--
                        **Hier stond een vaste uitnodigende zin.** Die is eruit:
                        de kop heeft al een `intro` die de eigenaar zelf
                        beheert, dus dit was een tweede inleiding naast een
                        inleiding -- en die tweede kon hij niet wijzigen.

                        **Een Inertia-link en geen kale `<a>`.** Die laatste
                        herlaadde de hele site, intro en al, voor een pagina die
                        in dezelfde layout staat. Turnstile laadt zijn script
                        zelf zodra het formulier daar verschijnt, dus er is niets
                        dat een volledige herlaad nodig heeft.
                    -->
                    <Button variant="brand" size="lg" as-child>
                        <Link
                            :href="contactPagina()"
                            class="brand-site-contact-grote-knop"
                        >
                            {{ $t('Neem contact op') }}
                            <ArrowRight class="size-5" />
                        </Link>
                    </Button>

                    <!-- Dezelfde uitweg als onder het formulier zelf. -->
                    <p class="text-sm text-pretty text-muted-foreground">
                        {{ $t('Of mail rechtstreeks:') }}
                        <a
                            :href="`mailto:${blok.email}`"
                            class="brand-sitemail"
                        >
                            {{ blok.email }}
                        </a>
                    </p>
                </div>

                <div
                    v-else
                    data-reveal
                    class="brand-site-contact-plaat opacity-0"
                >
                    <ContactFormulier
                        :velden="blok.velden"
                        :onderwerpen="blok.onderwerpen"
                        :eigen-onderwerp-toegestaan="
                            blok.eigenOnderwerpToegestaan
                        "
                        :instellingen="blok.instellingen"
                        :email="blok.email"
                        :toon-mail="false"
                    />
                </div>
            </div>
        </div>
    </SiteSection>
</template>
