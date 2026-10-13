<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

/**
 * Het tussenscherm dat je ziet terwijl de andere helft wordt klaargezet.
 *
 * Landing en portaal zijn twee verschillende bundels: de publieke site trekt
 * de hele animatielaag mee, het portaal heeft zijn eigen schil. Stap je van
 * de een naar de ander, dan moet die bundel eerst binnenkomen -- en zonder
 * tussenscherm kijk je die tel tegen een pagina aan die zich nog aan het
 * opbouwen is.
 *
 * **Het scherm laat zien waar je heen gaat, niet waar je vandaan komt.**
 * Naar het portaal krijg je het skelet van het portaal: de zijbalk, de kop
 * en de vlakken, in jouw eigen thema. Wat er daarna komt staat op precies
 * dezelfde plek, dus het voelt alsof de pagina zich vult in plaats van dat
 * hij wordt vervangen. Naar de website krijg je het merk op donker, want dat
 * is wat daar op je wacht.
 *
 * Zie docs/architecture/frontend-en-animatie.md.
 */
const props = defineProps<{
    /** Waar je heen gaat. Al aan de serverkant gecontroleerd. */
    destination: string;
    /** Hoelang het scherm minimaal blijft staan, in milliseconden. */
    duration: number;
    /** Welke kant je op gaat, en dus wat je te zien krijgt. */
    variant: 'portaal' | 'website';
    message: string;
    /** De tekst van het vangnet voor wie geen JavaScript heeft. */
    continueLabel: string;
}>();

let timer: ReturnType<typeof setTimeout> | undefined;

const reducedMotion = (): boolean =>
    typeof window !== 'undefined' &&
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;

onMounted(() => {
    /*
     * Alvast ophalen terwijl het scherm staat. Dat is het hele punt van dit
     * scherm: de tijd die je toch wacht wordt gebruikt om de volgende
     * pagina en zijn bundel binnen te halen, zodat de overgang daarna
     * meteen is in plaats van dat het wachten dán pas begint.
     *
     * In een try, want dit is een extraatje: gaat het mis, dan duurt de
     * overgang gewoon zo lang als hij duurt.
     */
    try {
        router.prefetch(props.destination, {}, { cacheFor: '30s' });
    } catch {
        // Niets aan de hand; de bezoeker merkt hooguit een tel vertraging.
    }

    // Ondergrens bij minder beweging: de animatie staat dan uit, maar het
    // scherm moet nog wel te lezen zijn. Tekst die na een fractie verdwijnt
    // is geen tekst.
    const wait = reducedMotion()
        ? Math.max(600, props.duration)
        : Math.max(200, props.duration);

    timer = setTimeout(() => {
        // `replace`, want anders kom je met de terugknop op dit laadscherm
        // terecht -- dat je dan meteen weer doorstuurt. Een val waar je
        // niet uit komt.
        router.visit(props.destination, { replace: true });
    }, wait);
});

onBeforeUnmount(() => {
    clearTimeout(timer);
});
</script>

<template>
    <!--
        aria-busy en een live region: wie met een schermlezer werkt hoort
        niet naar een stil scherm te luisteren dat vanzelf verspringt.
    -->
    <div aria-busy="true">
        <span class="sr-only" role="status">{{ message }}</span>

        <!--
            De balk bovenaan het venster, zoals een browser hem tekent. Dat
            is het enige element dat in allebei de varianten hetzelfde is,
            want het is het enige dat "er wordt geladen" betekent zonder
            uitleg.
        -->
        <div class="brand-loader-bar" aria-hidden="true">
            <span />
        </div>

        <!-- ─────────────────────────────  naar het portaal  ───────────── -->
        <!--
            `bg-sidebar` op de buitenrand en het werkvlak als eigen kaart
            erin: dat is precies hoe de echte zijbalk staat, met
            variant="inset". Zonder die kaart zie je bij de overgang alles
            een centimeter verspringen.
        -->
        <div
            v-if="variant === 'portaal'"
            class="flex min-h-svh bg-sidebar text-foreground"
        >
            <!--
                Het skelet van de zijbalk, met dezelfde maten als de echte:
                16rem breed, een logo van 32 pixels, en vijf menuregels. Komt
                het portaal binnen, dan staat alles op zijn plek.
            -->
            <aside class="hidden w-64 shrink-0 flex-col gap-6 p-4 md:flex">
                <div class="flex items-center gap-2 px-1 py-1.5">
                    <AppLogoIcon
                        class="size-8 shrink-0 rounded-lg opacity-60"
                        width="128"
                        height="128"
                    />
                    <div class="brand-skeleton h-4 w-28 rounded" />
                </div>

                <div class="space-y-4">
                    <div class="space-y-1.5">
                        <div
                            v-for="n in 1"
                            :key="`start-${n}`"
                            class="brand-skeleton h-8 rounded-md"
                            :style="{ '--skeleton-delay': `${n * 60}ms` }"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <div
                            class="brand-skeleton h-3 w-20 rounded"
                            style="--skeleton-delay: 120ms"
                        />
                        <div
                            v-for="n in 2"
                            :key="`web-${n}`"
                            class="brand-skeleton h-8 rounded-md"
                            :style="{
                                '--skeleton-delay': `${140 + n * 60}ms`,
                            }"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <div
                            class="brand-skeleton h-3 w-16 rounded"
                            style="--skeleton-delay: 300ms"
                        />
                        <div
                            v-for="n in 5"
                            :key="`beheer-${n}`"
                            class="brand-skeleton h-8 rounded-md"
                            :style="{
                                '--skeleton-delay': `${320 + n * 60}ms`,
                            }"
                        />
                    </div>
                </div>
            </aside>

            <div
                class="flex min-w-0 flex-1 flex-col bg-background md:m-2 md:ml-0 md:rounded-xl md:shadow-sm"
            >
                <header class="flex h-16 shrink-0 items-center gap-3 px-6">
                    <div class="brand-skeleton size-5 rounded" />
                    <div class="brand-skeleton h-4 w-32 rounded" />
                </header>

                <!--
                    Dezelfde indeling als het dashboard: drie vlakken bovenin
                    en één groot daaronder.
                -->
                <main class="flex flex-1 flex-col gap-4 p-4">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div
                            v-for="n in 3"
                            :key="`kaart-${n}`"
                            class="brand-skeleton aspect-video rounded-xl"
                            :style="{ '--skeleton-delay': `${n * 90}ms` }"
                        />
                    </div>

                    <div
                        class="brand-skeleton min-h-64 flex-1 rounded-xl"
                        style="--skeleton-delay: 360ms"
                    />
                </main>

                <p
                    class="pb-6 text-center text-xs text-muted-foreground"
                    aria-hidden="true"
                >
                    {{ message }}
                    <Link
                        :href="destination"
                        replace
                        class="underline-offset-4 transition-colors hover:text-brand-cyan hover:underline"
                    >
                        {{ continueLabel }}
                    </Link>
                </p>
            </div>
        </div>

        <!-- ─────────────────────────────  naar de website  ────────────── -->
        <div
            v-else
            class="brand-dark-page dark relative isolate flex min-h-svh flex-col items-center justify-center overflow-hidden bg-background p-6 text-foreground"
        >
            <div class="brand-aurora -z-10" aria-hidden="true">
                <span />
                <span />
                <span />
            </div>

            <div class="flex w-full max-w-xs flex-col items-center gap-8">
                <img
                    src="/images/logo-breed.webp"
                    alt="@T IT Advies"
                    width="640"
                    height="213"
                    class="brand-loader-logo h-auto w-56 max-w-full"
                    decoding="async"
                />

                <div class="w-full space-y-3">
                    <div class="brand-loader-track">
                        <span />
                    </div>

                    <p
                        class="text-center text-sm text-muted-foreground"
                        aria-hidden="true"
                    >
                        {{ message }}
                    </p>
                </div>

                <!--
                    Vangnet: werkt JavaScript niet, dan is dit scherm geen
                    doodlopende weg maar een pagina met een knop.
                -->
                <Link
                    :href="destination"
                    replace
                    class="text-xs text-muted-foreground underline-offset-4 transition-colors hover:text-brand-cyan hover:underline"
                >
                    {{ continueLabel }}
                </Link>
            </div>
        </div>
    </div>
</template>
