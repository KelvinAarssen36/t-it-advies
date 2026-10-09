<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Building2, Globe, Lock, Pencil, Phone, Type } from '@lucide/vue';
import { ref } from 'vue';
import VerlaatPortaal from '@/components/VerlaatPortaal.vue';
import VoettekstBedrijfDialoog from '@/components/website/VoettekstBedrijfDialoog.vue';
import VoettekstContactDialoog from '@/components/website/VoettekstContactDialoog.vue';
import VoettekstTekstDialoog from '@/components/website/VoettekstTekstDialoog.vue';
import { Button } from '@/components/ui/button';
import site from '@/routes/site';
import website from '@/routes/website';
import type { VoetInstelling, VoetOpties } from '@/types/voettekst';

/**
 * De voet van de website.
 *
 * **Dit scherm toont ook wat je níet kunt aanpassen, en dat is de
 * bedoeling.** Een voet zit vol dingen die vastliggen -- het jaartal, de
 * privacylink, de sitemap, de credit -- en zonder dat lijstje gaat de
 * eigenaar ernaar zoeken. Liever één blok dat zegt "deze regelt zichzelf"
 * dan een half uur zoeken naar een knop die er niet is.
 *
 * Drie blokken met elk een eigen venster, want ze slaan elk hun eigen
 * velden op. Zie FooterController voor waarom dat niet één formulier is.
 *
 * Zie docs/architecture/modules/voettekst.md.
 */
const props = defineProps<{
    instelling: VoetInstelling;
    email: string;
    opties: VoetOpties;
    kanVertalen: boolean;
}>();

/*
 * De kruimel staat er in gewoon Nederlands; Breadcrumbs.vue vertaalt hem.
 * Hier `$t()` gebruiken zou niet werken: defineOptions draait bij het
 * laden van de module, en dan zijn de vertalingen er nog niet.
 */
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Website', href: website.index() },
            { title: 'Voettekst' },
        ],
    },
});

const tekstVenster = ref(false);
const contactVenster = ref(false);
const bedrijfVenster = ref(false);
</script>

<template>
    <Head :title="$t('Voettekst')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <header class="max-w-xl space-y-1">
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ $t('Voettekst') }}
                </h1>
                <p class="text-sm text-pretty text-muted-foreground">
                    {{
                        $t(
                            'De afsluiting onderaan elke pagina. Het meeste regelt zichzelf; hieronder staat wat jij invult.',
                        )
                    }}
                </p>
            </header>

            <Button variant="outline" as-child class="gap-2">
                <Link :href="site.enter()">
                    <Globe class="size-4" />
                    {{ $t('Bekijk het resultaat') }}
                    <VerlaatPortaal />
                </Link>
            </Button>
        </div>

        <!-- ===== De zin onder je logo ================================= -->

        <section class="brand-voetblok">
            <header class="brand-voetblok-kop">
                <div class="min-w-0 flex-1">
                    <h2 class="brand-voetblok-titel">
                        {{ $t('De zin onder je logo') }}
                    </h2>
                    <p class="brand-voetblok-uitleg">
                        {{
                            $t(
                                'Eén regel direct onder je naam, onderaan elke pagina.',
                            )
                        }}
                    </p>
                </div>

                <Button
                    variant="bewerken"
                    class="gap-2"
                    @click="tekstVenster = true"
                >
                    <Type class="size-4" />
                    {{ $t('Aanpassen') }}
                </Button>
            </header>

            <div class="grid gap-2">
                <p class="brand-voetwaarde">
                    <span class="brand-voetwaarde-etiket">
                        {{ $t('Nederlands') }}
                    </span>
                    <span v-if="props.instelling.tagline_nl">
                        {{ props.instelling.tagline_nl }}
                    </span>
                    <span v-else class="brand-voetleeg">
                        {{ $t('Niet ingevuld') }}
                    </span>
                </p>

                <p class="brand-voetwaarde">
                    <span class="brand-voetwaarde-etiket">
                        {{ $t('Engels') }}
                    </span>
                    <span v-if="props.instelling.tagline_en">
                        {{ props.instelling.tagline_en }}
                    </span>
                    <span v-else class="brand-voetleeg">
                        {{ $t('Niet ingevuld; er komt dan geen zin') }}
                    </span>
                </p>
            </div>

            <p
                v-if="props.instelling.automatisch_vertaald"
                class="text-xs text-muted-foreground"
            >
                {{
                    $t(
                        'Het Engels komt van de vertaaldienst en is nog niet nagelezen.',
                    )
                }}
            </p>
        </section>

        <!-- ===== Contactgegevens ====================================== -->

        <section class="brand-voetblok">
            <header class="brand-voetblok-kop">
                <div class="min-w-0 flex-1">
                    <h2 class="brand-voetblok-titel">
                        {{ $t('Contactgegevens') }}
                    </h2>
                    <p class="brand-voetblok-uitleg">
                        {{
                            $t(
                                'Wat er onder "Contact" staat. Wat je niet invult, staat er niet -- er blijft geen lege regel achter.',
                            )
                        }}
                    </p>
                </div>

                <Button
                    variant="bewerken"
                    class="gap-2"
                    @click="contactVenster = true"
                >
                    <Phone class="size-4" />
                    {{ $t('Aanpassen') }}
                </Button>
            </header>

            <div class="grid gap-2">
                <p class="brand-voetwaarde">
                    <span class="brand-voetwaarde-etiket">
                        {{ $t('E-mailadres') }}
                    </span>
                    <span class="flex items-center gap-1.5">
                        {{ props.email }}
                        <Lock class="size-3 text-muted-foreground" />
                    </span>
                </p>

                <p class="brand-voetwaarde">
                    <span class="brand-voetwaarde-etiket">
                        {{ $t('Telefoonnummer') }}
                    </span>
                    <span v-if="props.instelling.phone">
                        {{ props.instelling.phone }}
                    </span>
                    <span v-else class="brand-voetleeg">
                        {{ $t('Niet ingevuld') }}
                    </span>
                </p>

                <p class="brand-voetwaarde">
                    <span class="brand-voetwaarde-etiket">
                        {{ $t('Plaats of werkgebied') }}
                    </span>
                    <span v-if="props.instelling.region_nl">
                        {{ props.instelling.region_nl }}
                    </span>
                    <span v-else class="brand-voetleeg">
                        {{ $t('Niet ingevuld') }}
                    </span>
                </p>
            </div>
        </section>

        <!-- ===== Bedrijfsgegevens ===================================== -->

        <section class="brand-voetblok">
            <header class="brand-voetblok-kop">
                <div class="min-w-0 flex-1">
                    <h2 class="brand-voetblok-titel">
                        {{ $t('Bedrijfsgegevens') }}
                    </h2>
                    <p class="brand-voetblok-uitleg">
                        {{
                            $t(
                                'Je KvK- en btw-nummer, naast je copyrightregel. Allebei mogen leeg blijven.',
                            )
                        }}
                    </p>
                </div>

                <Button
                    variant="bewerken"
                    class="gap-2"
                    @click="bedrijfVenster = true"
                >
                    <Building2 class="size-4" />
                    {{ $t('Aanpassen') }}
                </Button>
            </header>

            <div class="grid gap-2">
                <p class="brand-voetwaarde">
                    <span class="brand-voetwaarde-etiket">
                        {{ $t('KvK-nummer') }}
                    </span>
                    <span v-if="props.instelling.kvk">
                        {{ props.instelling.kvk }}
                    </span>
                    <span v-else class="brand-voetleeg">
                        {{ $t('Niet ingevuld') }}
                    </span>
                </p>

                <p class="brand-voetwaarde">
                    <span class="brand-voetwaarde-etiket">
                        {{ $t('Btw-nummer') }}
                    </span>
                    <span v-if="props.instelling.vat">
                        {{ props.instelling.vat }}
                    </span>
                    <span v-else class="brand-voetleeg">
                        {{ $t('Niet ingevuld') }}
                    </span>
                </p>
            </div>
        </section>

        <!-- ===== Wat zichzelf regelt ================================== -->

        <!--
            **Dit blok heeft geen knop, en dat is precies waarom het er
            staat.** Zonder deze opsomming gaat de eigenaar zoeken naar een
            plek om zijn jaartal bij te werken of zijn menu in de voet aan
            te passen. Nu weet hij dat dat niet hoeft.
        -->
        <section class="brand-voetblok">
            <header class="brand-voetblok-kop">
                <div class="min-w-0 flex-1">
                    <h2 class="brand-voetblok-titel">
                        {{ $t('Dit regelt zichzelf') }}
                    </h2>
                    <p class="brand-voetblok-uitleg">
                        {{
                            $t(
                                'Niet alles in een voet hoort aanpasbaar te zijn. Deze dingen staan er automatisch goed, dus je hoeft er nooit naar om te kijken.',
                            )
                        }}
                    </p>
                </div>
            </header>

            <ul class="grid gap-2 text-sm text-muted-foreground">
                <li class="brand-voetwaarde">
                    <span class="brand-voetwaarde-etiket">
                        {{ $t('Het jaartal') }}
                    </span>
                    <span>
                        {{ $t('Telt elk jaar zichzelf op.') }}
                    </span>
                </li>
                <li class="brand-voetwaarde">
                    <span class="brand-voetwaarde-etiket">
                        {{ $t('Het rijtje "Op de site"') }}
                    </span>
                    <span>
                        {{
                            $t(
                                'Volgt je indeling. Zet je een onderdeel uit, dan verdwijnt de regel ook hier.',
                            )
                        }}
                    </span>
                </li>
                <li class="brand-voetwaarde">
                    <span class="brand-voetwaarde-etiket">
                        {{ $t('De privacyverklaring') }}
                    </span>
                    <span>{{ $t('Staat er altijd; dat hoort zo.') }}</span>
                </li>
                <li class="brand-voetwaarde">
                    <span class="brand-voetwaarde-etiket">
                        {{ $t('LinkedIn') }}
                    </span>
                    <span>
                        {{ $t('Dezelfde link als bovenaan je website.') }}
                    </span>
                </li>
            </ul>
        </section>
    </div>

    <VoettekstTekstDialoog
        v-model:open="tekstVenster"
        :instelling="props.instelling"
        :opties="props.opties"
        :kan-vertalen="props.kanVertalen"
    />

    <VoettekstContactDialoog
        v-model:open="contactVenster"
        :instelling="props.instelling"
        :opties="props.opties"
        :email="props.email"
        :kan-vertalen="props.kanVertalen"
    />

    <VoettekstBedrijfDialoog
        v-model:open="bedrijfVenster"
        :instelling="props.instelling"
        :opties="props.opties"
    />
</template>
