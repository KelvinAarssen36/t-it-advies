<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Globe, Heading, Lock, MousePointerClick } from '@lucide/vue';
import { ref } from 'vue';
import CopyButton from '@/components/CopyButton.vue';
import VerlaatPortaal from '@/components/VerlaatPortaal.vue';
import KoptekstDialoog from '@/components/website/KoptekstDialoog.vue';
import LinkedinKnopDialoog from '@/components/website/LinkedinKnopDialoog.vue';
import { Button } from '@/components/ui/button';
import site from '@/routes/site';
import website from '@/routes/website';
import linkedin from '@/routes/website/linkedin';
import type {
    LinkedinInstelling,
    LinkedinOpties,
    LinkedinStandaardKnop,
} from '@/types/linkedin';
import type { KoptekstRij } from '@/types/secties';

/**
 * Het LinkedIn-blok op de website.
 *
 * Twee dingen zijn hier te beheren, elk met een eigen venster: de kop
 * (opschrift, titel en de zin eronder, dezelfde als bij elk onderdeel) en
 * de tekst op de knop. **De link zelf niet**: die ligt vast in de
 * configuratie, en dit scherm laat hem zien met een slotje, zodat de
 * eigenaar weet waar de knop heen gaat zonder te zoeken naar een veld dat
 * er bewust niet is.
 *
 * Zie docs/architecture/modules/linkedin.md.
 */
const props = defineProps<{
    kop: KoptekstRij;
    instelling: LinkedinInstelling;
    adres: string;
    standaardKnop: LinkedinStandaardKnop;
    opties: LinkedinOpties;
    kanVertalen: boolean;
}>();

/*
 * De kruimel staat er in gewoon Nederlands; Breadcrumbs.vue vertaalt hem.
 * Zie Voettekst.vue voor waarom hier geen `$t()` kan.
 */
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Website', href: website.index() },
            { title: 'LinkedIn' },
        ],
    },
});

const kopVenster = ref(false);
const knopVenster = ref(false);
</script>

<template>
    <Head :title="$t('LinkedIn')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <header class="max-w-xl space-y-1">
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ $t('LinkedIn') }}
                </h1>
                <p class="text-sm text-pretty text-muted-foreground">
                    {{
                        $t(
                            'De uitnodiging om je op LinkedIn te volgen. Jij bepaalt wat er staat; de link zelf ligt vast.',
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

        <!-- ===== De kop ============================================== -->

        <section class="brand-voetblok">
            <header class="brand-voetblok-kop">
                <div class="min-w-0 flex-1">
                    <h2 class="brand-voetblok-titel">
                        {{ $t('De tekst van het blok') }}
                    </h2>
                    <p class="brand-voetblok-uitleg">
                        {{
                            $t(
                                'Het opschrift, de titel en de zin die erboven staan.',
                            )
                        }}
                    </p>
                </div>

                <Button
                    variant="bewerken"
                    class="gap-2"
                    @click="kopVenster = true"
                >
                    <Heading class="size-4" />
                    {{ $t('Aanpassen') }}
                </Button>
            </header>

            <div class="grid gap-2">
                <p class="brand-voetwaarde">
                    <span class="brand-voetwaarde-etiket">
                        {{ $t('Opschrift') }}
                    </span>
                    <span v-if="props.kop.eyebrow_nl">
                        {{ props.kop.eyebrow_nl }}
                    </span>
                    <span v-else class="brand-voetleeg">
                        {{ $t('Niet ingevuld') }}
                    </span>
                </p>

                <p class="brand-voetwaarde">
                    <span class="brand-voetwaarde-etiket">
                        {{ $t('Titel') }}
                    </span>
                    <span>{{ props.kop.title_nl }}</span>
                </p>

                <p class="brand-voetwaarde">
                    <span class="brand-voetwaarde-etiket">
                        {{ $t('Zin eronder') }}
                    </span>
                    <span v-if="props.kop.intro_nl">
                        {{ props.kop.intro_nl }}
                    </span>
                    <span v-else class="brand-voetleeg">
                        {{ $t('Niet ingevuld') }}
                    </span>
                </p>

                <p class="brand-voetwaarde">
                    <span class="brand-voetwaarde-etiket">
                        {{ $t('Titel (Engels)') }}
                    </span>
                    <span v-if="props.kop.title_en">
                        {{ props.kop.title_en }}
                    </span>
                    <span v-else class="brand-voetleeg">
                        {{
                            $t(
                                'Niet ingevuld; het Nederlands komt er dan te staan',
                            )
                        }}
                    </span>
                </p>
            </div>

            <p
                v-if="props.kop.automatisch_vertaald"
                class="text-xs text-muted-foreground"
            >
                {{
                    $t(
                        'Het Engels komt van de vertaaldienst en is nog niet nagelezen.',
                    )
                }}
            </p>
        </section>

        <!-- ===== De knop ============================================= -->

        <section class="brand-voetblok">
            <header class="brand-voetblok-kop">
                <div class="min-w-0 flex-1">
                    <h2 class="brand-voetblok-titel">
                        {{ $t('De knop') }}
                    </h2>
                    <p class="brand-voetblok-uitleg">
                        {{
                            $t(
                                'Wat er op de knop naar je profiel staat. Leeg is de standaardtekst.',
                            )
                        }}
                    </p>
                </div>

                <Button
                    variant="bewerken"
                    class="gap-2"
                    @click="knopVenster = true"
                >
                    <MousePointerClick class="size-4" />
                    {{ $t('Aanpassen') }}
                </Button>
            </header>

            <div class="grid gap-2">
                <p class="brand-voetwaarde">
                    <span class="brand-voetwaarde-etiket">
                        {{ $t('Nederlands') }}
                    </span>
                    <span v-if="props.instelling.button_nl">
                        {{ props.instelling.button_nl }}
                    </span>
                    <span v-else class="brand-voetleeg">
                        {{
                            $t('Standaard: :tekst', {
                                tekst: props.standaardKnop.nl,
                            })
                        }}
                    </span>
                </p>

                <p class="brand-voetwaarde">
                    <span class="brand-voetwaarde-etiket">
                        {{ $t('Engels') }}
                    </span>
                    <span v-if="props.instelling.button_en">
                        {{ props.instelling.button_en }}
                    </span>
                    <span v-else class="brand-voetleeg">
                        {{
                            $t('Standaard: :tekst', {
                                tekst: props.standaardKnop.en,
                            })
                        }}
                    </span>
                </p>

                <!--
                    Het adres ter informatie, met een slotje: het ligt vast
                    in de configuratie, en een tikfout in een link naar
                    buiten is een dode knop op de voorpagina.
                -->
                <div class="brand-voetwaarde">
                    <span class="brand-voetwaarde-etiket">
                        {{ $t('Link') }}
                    </span>
                    <span
                        v-if="props.adres"
                        class="flex min-w-0 items-center gap-1.5"
                    >
                        <span class="truncate">{{ props.adres }}</span>
                        <Lock class="size-3 shrink-0 text-muted-foreground" />
                        <CopyButton :value="props.adres" />
                    </span>
                    <span v-else class="brand-voetleeg">
                        {{
                            $t(
                                'Geen link ingesteld; het blok staat er dan zonder knop',
                            )
                        }}
                    </span>
                </div>
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
    </div>

    <KoptekstDialoog
        v-model:open="kopVenster"
        :kop="props.kop"
        :actie="linkedin.kop().url"
        :titel="$t('De tekst van je LinkedIn-blok')"
        :uitleg="
            $t(
                'Het opschrift, de titel en de zin erboven. Zeg in die zin wat iemand op je profiel vindt -- dan is volgen een reden en geen beleefdheid.',
            )
        "
        :bevestiging="$t('De tekst van je LinkedIn-blok aanpassen?')"
        sleutel="linkedin"
        :kan-vertalen="props.kanVertalen"
    />

    <LinkedinKnopDialoog
        v-model:open="knopVenster"
        :instelling="props.instelling"
        :standaard="props.standaardKnop"
        :opties="props.opties"
        :kan-vertalen="props.kanVertalen"
    />
</template>
