<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Lock, LockOpen } from '@lucide/vue';
import { computed } from 'vue';
import { t } from '@/lib/i18n';

/**
 * Het slotje naast "Beveiliging" in de instellingen.
 *
 * Achter die pagina zit een wachtwoordbevestiging, en dat is precies het
 * soort drempel waar je van schrikt als je hem niet ziet aankomen: je klikt
 * op een menu-item en krijgt ineens een inlogscherm. Dit slotje vertelt
 * vooraf wat je te wachten staat -- dicht betekent dat er nog om je
 * wachtwoord wordt gevraagd, open dat je zo doorloopt.
 *
 * Het is **weergave en geen beveiliging**. Wie de gedeelde prop in zijn
 * browser omzet krijgt een open slotje te zien en verder niets: de
 * middleware `password.confirm` doet de controle opnieuw, op de sessie waar
 * de browser niet bij kan.
 *
 * Zie docs/security/gevoelige-acties.md.
 */
const page = usePage();

const open = computed(() => Boolean(page.props.auth?.passwordConfirmed));

const uitleg = computed(() =>
    open.value
        ? t('Je wachtwoord is bevestigd, je loopt zo door')
        : t('Hier wordt eerst om je wachtwoord gevraagd'),
);
</script>

<template>
    <span
        class="brand-lock"
        :class="{ 'is-open': open }"
        :title="uitleg"
        :aria-label="uitleg"
        role="img"
    >
        <!--
            mode="out-in", zodat het dichte slot eerst weg is voordat het
            open slot binnenkomt. Kruisen ze elkaar, dan zie je een tel lang
            twee sloten over elkaar en leest het als een storing.
        -->
        <Transition name="brand-lock" mode="out-in">
            <LockOpen v-if="open" key="open" class="size-3.5" />
            <Lock v-else key="dicht" class="size-3.5" />
        </Transition>
    </span>
</template>
