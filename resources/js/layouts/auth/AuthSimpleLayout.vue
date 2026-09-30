<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LocaleToggle from '@/components/site/LocaleToggle.vue';
import { home } from '@/routes';

/**
 * De schil om de schermen die het hele venster vullen.
 *
 * Er zijn twee varianten, en het verschil zit in wie ernaar kijkt:
 *
 * - **merk** (standaard) is de voordeur. Inloggen, wachtwoord vergeten, een
 *   nieuw wachtwoord kiezen, 2FA instellen: allemaal momenten waarop je nog
 *   niet aan het werk bent. Die staan altijd in de huisstijl, met het
 *   logo en de aurora, ongeacht de themavoorkeur -- net als de landing.
 * - **portaal** is voor een onderbreking terwijl je al aan het werk bent:
 *   je klikt op Beveiliging en er wordt eerst om je wachtwoord gevraagd.
 *   Die schermen volgen wél de themavoorkeur, want anders klap je vanuit een
 *   licht portaal ineens in een donkere pagina.
 *
 * Het brede logo staat alleen in de merkvariant. Het is getekend voor een
 * donkere ondergrond -- zilver en blauw met donkere contouren -- en verliest
 * op wit zijn contrast. In de portaalvariant staat daarom het vierkante
 * merkteken, dat ook in de zijbalk op licht werkt.
 *
 * Zie docs/architecture/huisstijl-en-kleuren.md.
 */
withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        variant?: 'merk' | 'portaal';
    }>(),
    { variant: 'merk' },
);
</script>

<template>
    <div
        class="relative isolate flex min-h-svh flex-col items-center justify-center overflow-hidden bg-background p-6 text-foreground md:p-10"
        :class="variant === 'merk' ? 'brand-dark-page dark' : ''"
    >
        <div
            v-if="variant === 'merk'"
            class="brand-aurora -z-10"
            aria-hidden="true"
        >
            <span />
            <span />
            <span />
        </div>

        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-6">
                    <!--
                        Maat op breedte en niet op hoogte: het logo is bijna
                        drie keer zo breed als hoog, dus met een vaste hoogte
                        bepaalt de breedte of het past. Zo kan het op een
                        smal scherm nooit buiten de rand vallen.

                        width en height staan erbij zodat de browser de
                        ruimte al reserveert voordat het bestand binnen is;
                        anders springt de hele kaart omlaag zodra het laadt.
                    -->
                    <Link
                        v-if="variant === 'merk'"
                        :href="home()"
                        class="block w-full"
                    >
                        <img
                            src="/images/logo-breed.webp"
                            alt="@T IT Advies"
                            width="640"
                            height="213"
                            class="mx-auto h-auto w-60 max-w-full drop-shadow-[0_0_30px_rgb(7_135_232_/_0.4)] sm:w-80"
                            decoding="async"
                        />
                    </Link>

                    <!--
                        Het merkteken op een eigen tegel met het donkere
                        merkverloop. Zonder die tegel valt het in het lichte
                        thema half weg: het teken is donkerblauw met fijne
                        contouren en heeft een donkere ondergrond nodig om
                        te lezen. Zo klopt het in allebei de thema's, en het
                        leest meteen als een merk in plaats van als een los
                        plaatje.
                    -->
                    <div
                        v-else
                        class="flex size-14 items-center justify-center rounded-2xl brand-surface-dark ring-1 brand-glow ring-brand-line/40"
                    >
                        <AppLogoIcon class="size-8" width="128" height="128" />
                    </div>

                    <!--
                        De titel en de omschrijving komen uit `defineOptions`
                        van de pagina, en dat draait in de moduleruimte --
                        daar is nog geen taal. Ze worden daarom hier
                        vertaald, op het moment dat ze getekend worden. De
                        Nederlandse zin is tegelijk de sleutel. Zie
                        docs/architecture/vertalingen.md.
                    -->
                    <div class="space-y-2 text-center">
                        <h1
                            class="text-xl font-medium"
                            :class="
                                variant === 'merk'
                                    ? 'text-white'
                                    : 'text-foreground'
                            "
                        >
                            {{ $t(title ?? '') }}
                        </h1>
                        <p class="text-sm text-balance text-muted-foreground">
                            {{ $t(description ?? '') }}
                        </p>
                    </div>
                </div>

                <!--
                    Een eigen vlak onder het formulier. In de merkvariant is
                    dat half doorzichtig met een vervaging erachter, want
                    zonder die laag zweven de velden los over de bewegende
                    aurora. Zonder aurora hoeft dat niet en staat een
                    gewone kaart rustiger.
                -->
                <div
                    class="rounded-2xl border border-border p-6"
                    :class="
                        variant === 'merk'
                            ? 'bg-card/70 shadow-xl backdrop-blur-xl'
                            : 'bg-card shadow-sm'
                    "
                >
                    <slot />
                </div>

                <!--
                    De taalkeuze hoort op de voordeur al te staan en niet pas
                    achter het inloggen: wie hem in de verkeerde taal krijgt
                    kan hem nog nergens omzetten. Binnen het portaal staat
                    die knop al in het accountmenu, dus daar is hij dubbelop.
                -->
                <div
                    v-if="variant === 'merk'"
                    class="flex flex-col items-center gap-4"
                >
                    <LocaleToggle />

                    <p class="text-center text-xs text-muted-foreground">
                        {{ $t('Alleen voor de beheerder van deze website.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
