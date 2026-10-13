<script setup lang="ts">
import { ArrowUpRight } from '@lucide/vue';

/**
 * Het tekentje achter een link die het portaal verlaat.
 *
 * "Bekijk de website" en "Bekijk het resultaat" brengen je naar de publieke
 * site. Dat is geen scherm van het portaal: de zijbalk is weg, het thema
 * staat vast op donker, en terugkomen doe je met de terugknop van de
 * browser. Dat hoort te blijken vóórdat je klikt, en niet pas daarna.
 *
 * **Het opent geen nieuw tabblad.** Het pijltje is alleen een markering.
 * Dat is een bewuste keuze: een link die ongevraagd een tabblad opent neemt
 * een beslissing over die persoon zijn browser, en na drie keer kijken op
 * de site heeft hij er vier tabbladen bij staan.
 *
 * Gebruik hem als laatste kind van de link of de knop, na de tekst.
 *
 * Zie docs/architecture/frontend-en-animatie.md.
 */
</script>

<template>
    <span class="brand-verlaat" :title="$t('Je verlaat het portaal')">
        <ArrowUpRight class="size-3.5" aria-hidden="true" />
        <span class="sr-only">{{ $t('Je verlaat het portaal') }}</span>
    </span>
</template>
