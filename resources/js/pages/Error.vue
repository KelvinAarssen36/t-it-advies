<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Ban,
    Clock,
    Compass,
    LayoutGrid,
    PlugZap,
    SearchX,
} from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes';

/**
 * De foutpagina van het portaal.
 *
 * Hij staat in de schil van het portaal, dus met de zijbalk en het
 * accountmenu eromheen. Dat is het hele idee: een fout is geen reden om
 * iemand uit zijn omgeving te gooien. Je ziet waar je bent, je navigatie
 * staat er nog, en er is altijd een weg terug.
 *
 * Alle kleuren komen uit de rol-tokens, behalve het tegeltje met het
 * pictogram: dat is met opzet het merkverloop met een witte vorm erop, want
 * dat leest in allebei de thema's even goed.
 *
 * De landing krijgt later een eigen variant, en die hoort er anders uit te
 * zien: daar is de bezoeker een klant van onze klant en geen beheerder.
 * Zie docs/architecture/foutpaginas.md.
 */
const props = defineProps<{ status: number }>();

/**
 * Wat er staat, per code.
 *
 * Geen technische taal: "429" zegt niemand iets. En de tekst gaat over het
 * portaal -- over je menu, je rechten, je account -- want dat is waar je
 * bent. Geen verontschuldiging bij een 404: er is meestal niets misgegaan,
 * je bent gewoon ergens waar niets staat. Bij een 500 wél, want dan ligt
 * het aan ons.
 */
const inhoud = computed(() => {
    switch (props.status) {
        case 403:
            return {
                icoon: Ban,
                kop: t('Hier mag je niet bij'),
                uitleg: t(
                    'Dit onderdeel hoort niet bij jouw account. Klopt dat niet, laat het ons dan weten — je hoeft niet opnieuw in te loggen.',
                ),
            };
        case 429:
            return {
                icoon: Clock,
                kop: t('Even rustig aan'),
                uitleg: t(
                    'Er kwamen te veel verzoeken achter elkaar binnen. Wacht een minuut, daarna werkt alles weer gewoon.',
                ),
            };
        case 503:
            return {
                icoon: PlugZap,
                kop: t('Even in onderhoud'),
                uitleg: t(
                    'Er wordt op dit moment aan de website gewerkt. Over een paar minuten kun je weer verder waar je gebleven was.',
                ),
            };
        case 500:
            return {
                icoon: PlugZap,
                kop: t('Er ging iets mis'),
                uitleg: t(
                    'Dit ligt aan ons en niet aan jou. De fout is vastgelegd en we kijken ernaar; probeer het zo nog eens.',
                ),
            };
        default:
            return {
                icoon: SearchX,
                kop: t('Deze pagina bestaat niet'),
                uitleg: t(
                    'De link is misschien verouderd, of dit onderdeel is er niet meer. Je menu links staat er gewoon nog.',
                ),
            };
    }
});

/**
 * Terug naar waar je vandaan kwam.
 *
 * Met een vangnet: kom je hier binnen via een gedeelde link of een nieuw
 * tabblad, dan is er geen geschiedenis om naar terug te gaan en zou de knop
 * niets doen. Dan maar het dashboard.
 */
const terug = () => {
    if (typeof window !== 'undefined' && window.history.length > 1) {
        window.history.back();

        return;
    }

    router.visit(dashboard().url);
};
</script>

<template>
    <Head :title="inhoud.kop" />

    <div class="flex flex-1 items-center justify-center p-6">
        <div class="w-full max-w-lg text-center">
            <!--
                Het tegeltje met het pictogram. Het merkverloop met een witte
                vorm erop: dat is het enige stukje kleur op deze pagina, en
                het leest in licht en in de huisstijl even goed.
            -->
            <div
                class="mx-auto flex size-16 brand-rise items-center justify-center rounded-2xl brand-surface text-white brand-glow"
                style="--rise-delay: 0ms"
            >
                <component :is="inhoud.icoon" class="size-7" />
            </div>

            <p
                class="mt-6 brand-rise text-sm font-medium tracking-[0.3em] text-muted-foreground tabular-nums"
                style="--rise-delay: 60ms"
            >
                {{ status }}
            </p>

            <h1
                class="mt-2 brand-rise text-3xl font-semibold text-balance text-foreground"
                style="--rise-delay: 110ms"
            >
                {{ inhoud.kop }}
            </h1>

            <p
                class="mx-auto mt-3 max-w-md brand-rise text-sm text-pretty text-muted-foreground"
                style="--rise-delay: 160ms"
            >
                {{ inhoud.uitleg }}
            </p>

            <div
                class="mx-auto mt-8 brand-rule w-24 brand-rise"
                style="--rise-delay: 210ms"
            />

            <!--
                Er is altijd een weg terug, en die staat in een eigen vlak.
                Dat is niet alleen opmaak: het maakt van "hier is niets" een
                pagina met een volgende stap erop.
            -->
            <div
                class="mt-8 brand-rise rounded-2xl border border-border bg-card p-5 text-left"
                style="--rise-delay: 260ms"
            >
                <div class="flex items-start gap-3">
                    <Compass class="mt-0.5 size-5 shrink-0 text-primary" />
                    <div class="space-y-1">
                        <p class="text-sm font-medium text-foreground">
                            {{ $t('Je bent nog gewoon in het portaal') }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{
                                $t(
                                    'Je bent niet uitgelogd en er is niets kwijt. Ga terug naar waar je was, of begin opnieuw op het dashboard.',
                                )
                            }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                    <Button as-child class="sm:flex-1">
                        <Link :href="dashboard()">
                            <LayoutGrid class="size-4" />
                            {{ $t('Naar het dashboard') }}
                        </Link>
                    </Button>

                    <Button variant="outline" class="sm:flex-1" @click="terug">
                        <ArrowLeft class="size-4" />
                        {{ $t('Terug naar de vorige pagina') }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
