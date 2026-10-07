<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

/**
 * De weg terug onder aan een subpagina.
 *
 * **Eén knop en geen tekstlink, en onderaan en niet bovenaan.** Boven aan
 * de pagina staat het kruimelpad en in de kop het menu; daar is al gezegd
 * waar je bent en waar je heen kunt. Hier ben je klaar met lezen, en dan is
 * "ik wil weg" een handeling. Een knop leest als een handeling, een
 * tekstlink als een voetnoot.
 *
 * **Op elke subpagina precies één keer.** De privacyverklaring had hem
 * boven én onder, met als reden dat je na een lange tekst onderaan staat.
 * Dat klopte niet: `.brand-sitekop` is `position: sticky`, dus de
 * navigatie is daar ook. Wat er echt ontbrak is dit: een duidelijk einde
 * aan de pagina.
 */
</script>

<template>
    <div class="brand-siteterug">
        <Button variant="outline" as-child>
            <Link :href="home()">
                <ArrowLeft class="size-4" />
                {{ $t('Terug naar de voorpagina') }}
            </Link>
        </Button>
    </div>
</template>
