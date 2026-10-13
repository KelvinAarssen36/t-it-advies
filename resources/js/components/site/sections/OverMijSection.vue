<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import { Button } from '@/components/ui/button';
import type { OverMijOpDeSite } from '@/types/over-mij';
import type { SectieKop, SectieProps } from '@/types/secties';

/**
 * Het korte stuk over de eigenaar.
 *
 * **Eén alinea en een foto, en niet meer dan dat.** De lengte wordt op de
 * server afgedwongen (400 tekens); dit component vertrouwt daarop en zet er
 * geen eigen afkapping overheen. Een blok dat zijn eigen tekst inkort
 * verbergt dat de grens is overschreden, en dan merkt de eigenaar het pas
 * als een bezoeker het zegt.
 *
 * **De foto staat links op een breed scherm en bovenaan op een telefoon.**
 * Niet omgekeerd: de tekst is waar het om gaat, en op een smal scherm wil je
 * niet eerst langs een foto scrollen -- maar de foto hoort wel bij de
 * alinea, en dan is erboven duidelijker dan eronder.
 *
 * **De knop staat er alleen als de aparte pagina echt bestaat.** Dat is
 * `pagina` uit de server, en die zegt "aangezet én er staat een verhaal in"
 * -- zie AboutSetting::paginaStaatKlaar(). Een knop naar een 404 is erger
 * dan geen knop.
 *
 * Zie docs/architecture/modules/over-mij.md.
 */
defineProps<SectieProps>();

const page = usePage();

const over = computed<OverMijOpDeSite | null>(
    () => (page.props.about as OverMijOpDeSite | undefined) ?? null,
);

const kop = computed<SectieKop | null>(
    () => (page.props.aboutHeading as SectieKop | undefined) ?? null,
);
</script>

<template>
    <SiteSection id="over-mij" :tone="tone" :divided="divided">
        <div v-if="over" class="brand-overmij">
            <SectionHeading
                v-if="kop"
                :eyebrow="kop.opschrift ?? undefined"
                :title="kop.titel"
                :intro="kop.inleiding ?? undefined"
            />

            <div class="brand-overmij-rij">
                <!--
                    Het medaillon heeft vier maten en een eigen `sizes`; een
                    eigen foto is één vierkant van 640 en krijgt er geen.
                    Vandaar de `srcset` die `null` mag zijn.
                -->
                <figure class="brand-overmij-beeld">
                    <img
                        :src="over.foto.src"
                        :srcset="over.foto.srcset ?? undefined"
                        sizes="(min-width: 64rem) 16rem, 12rem"
                        :alt="$t('Foto van de eigenaar')"
                        width="640"
                        height="640"
                        loading="lazy"
                        decoding="async"
                    />
                </figure>

                <div class="brand-overmij-tekst">
                    <p class="brand-overmij-samenvatting">
                        {{ over.samenvatting }}
                    </p>

                    <Button v-if="over.pagina" variant="brand" as-child>
                        <Link href="/over-mij">
                            {{ $t('Lees meer over mij') }}
                            <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                </div>
            </div>
        </div>
    </SiteSection>
</template>
