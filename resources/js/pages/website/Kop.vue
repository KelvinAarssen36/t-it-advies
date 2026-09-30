<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Globe, Languages, Pencil } from '@lucide/vue';
import { computed, ref } from 'vue';
import LocaleFlag from '@/components/LocaleFlag.vue';
import VerlaatPortaal from '@/components/VerlaatPortaal.vue';
import KoptekstDialoog from '@/components/website/KoptekstDialoog.vue';
import { Button } from '@/components/ui/button';
import site from '@/routes/site';
import website from '@/routes/website';
// `kopRoutes` en niet `kop`: dit scherm heeft een prop die zo heet, en
// in het sjabloon zouden die twee elkaar in de weg zitten.
import kopRoutes from '@/routes/website/kop';
import type { SiteKopRij } from '@/types/ervaring';

/**
 * De kop van de landingspagina.
 *
 * **Het kleinste beheerscherm van de website, en dat is de bedoeling.**
 * Er is één kop, dus er valt niets aan te maken en niets te verwijderen:
 * geen tabel, geen zoekveld, geen detailpagina. Alleen wat er nu staat,
 * en één knop om het te wijzigen.
 *
 * Wat je hier ziet is een **voorbeeld van het echte blok**, in dezelfde
 * opbouw als op de site: het opschrift klein en in hoofdletters, de titel
 * groot eronder, de zin daar weer onder. Dat is dezelfde keuze als bij de
 * cijfers boven de tijdlijn -- een lijst met veldnamen zegt wat er in de
 * database staat, een voorbeeld zegt wat de bezoeker ziet.
 *
 * Zie docs/architecture/modules/kop.md.
 */
const props = defineProps<{
    kop: SiteKopRij;
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
            { title: 'Kop' },
        ],
    },
});

const venster = ref(false);

/**
 * Is het Engels compleet?
 *
 * Alleen de velden die in het Nederlands ook gevuld zijn tellen mee: een
 * lege zin eronder hoeft geen Engelse vertaling te krijgen, want er staat
 * dan in geen van beide talen iets.
 */
const engelsCompleet = computed(() => {
    const paren: Array<[string | null, string | null]> = [
        [props.kop.eyebrow_nl, props.kop.eyebrow_en],
        [props.kop.title_nl, props.kop.title_en],
        [props.kop.intro_nl, props.kop.intro_en],
    ];

    return paren.every(([nl, en]) => !nl?.trim() || !!en?.trim());
});
</script>

<template>
    <Head :title="$t('Kop')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <header class="max-w-xl space-y-1">
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ $t('Kop') }}
                </h1>
                <p class="text-sm text-pretty text-muted-foreground">
                    {{
                        $t(
                            'Het eerste dat een bezoeker leest, bovenaan je website. Drie teksten, en meer is het niet.',
                        )
                    }}
                </p>
            </header>

            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" as-child class="gap-2">
                    <Link :href="site.enter()">
                        <Globe class="size-4" />
                        {{ $t('Bekijk het resultaat') }}
                        <VerlaatPortaal />
                    </Link>
                </Button>

                <Button
                    variant="bewerken"
                    class="gap-2"
                    @click="venster = true"
                >
                    <Pencil class="size-4" />
                    {{ $t('Tekst aanpassen') }}
                </Button>
            </div>
        </div>

        <!--
            Het voorbeeld, in twee talen naast elkaar. Naast elkaar en niet
            onder elkaar met een tabje ertussen: zo zie je in één oogopslag
            of het Engels achterloopt op het Nederlands, en dat is precies
            de vraag die je hier hebt.
        -->
        <div class="grid gap-4 tablet:grid-cols-2">
            <section class="brand-kopvoorbeeld">
                <p class="brand-kopvoorbeeld-taal">
                    <LocaleFlag locale="nl" size="sm" />
                    {{ $t('Nederlands') }}
                </p>

                <p class="brand-kopvoorbeeld-opschrift">
                    {{ props.kop.eyebrow_nl }}
                </p>
                <h2 class="brand-kopvoorbeeld-titel">
                    {{ props.kop.title_nl }}
                </h2>
                <p v-if="props.kop.intro_nl" class="brand-kopvoorbeeld-zin">
                    {{ props.kop.intro_nl }}
                </p>
                <p v-else class="brand-kopvoorbeeld-leeg">
                    {{ $t('Geen zin eronder.') }}
                </p>
            </section>

            <section class="brand-kopvoorbeeld">
                <p class="brand-kopvoorbeeld-taal">
                    <LocaleFlag locale="en" size="sm" />
                    {{ $t('Engels') }}
                </p>

                <!--
                    Ontbreekt het Engels, dan staat er wat de bezoeker dán
                    ziet: het Nederlands. Dat is hoe de website het doet
                    voor het opschrift en de titel, en het is eerlijker dan
                    een leeg vak dat suggereert dat er niets staat.
                -->
                <p
                    class="brand-kopvoorbeeld-opschrift"
                    :class="{ 'is-terugval': !props.kop.eyebrow_en }"
                >
                    {{ props.kop.eyebrow_en || props.kop.eyebrow_nl }}
                </p>
                <h2
                    class="brand-kopvoorbeeld-titel"
                    :class="{ 'is-terugval': !props.kop.title_en }"
                >
                    {{ props.kop.title_en || props.kop.title_nl }}
                </h2>

                <!--
                    De zin eronder valt níet terug: half Nederlands op een
                    Engelse pagina is slordiger dan geen zin. Zie
                    App\Models\SectionHeading.
                -->
                <p v-if="props.kop.intro_en" class="brand-kopvoorbeeld-zin">
                    {{ props.kop.intro_en }}
                </p>
                <p v-else class="brand-kopvoorbeeld-leeg">
                    {{
                        props.kop.intro_nl
                            ? $t('Nog niet vertaald; er komt dan geen zin.')
                            : $t('Geen zin eronder.')
                    }}
                </p>
            </section>
        </div>

        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
            <p v-if="!engelsCompleet" class="brand-kop-melding">
                <Languages class="size-4 shrink-0" />
                <span>
                    {{
                        $t(
                            'Het Engels is nog niet compleet. Je bezoeker ziet dan het Nederlands.',
                        )
                    }}
                </span>
            </p>

            <p
                v-if="props.kop.automatisch_vertaald"
                class="text-muted-foreground"
            >
                {{
                    $t(
                        'Het Engels komt van de vertaaldienst en is nog niet nagelezen.',
                    )
                }}
            </p>
        </div>
    </div>

    <KoptekstDialoog
        v-model:open="venster"
        :kop="props.kop"
        :actie="kopRoutes.update().url"
        :titel="$t('De kop van je landingspagina')"
        :uitleg="
            $t(
                'Het opschrift, de titel en de zin eronder. Dit is het eerste dat een bezoeker leest.',
            )
        "
        :bevestiging="$t('De kop van je landingspagina aanpassen?')"
        sleutel="hero"
        :kan-vertalen="props.kanVertalen"
    />
</template>
