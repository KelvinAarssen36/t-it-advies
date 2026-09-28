<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ShieldAlert } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/security';

/**
 * Herinnert de ingelogde gebruiker eraan dat tweestapsverificatie uitstaat.
 *
 * Zolang 2FA uit staat is het wachtwoord het enige slot op het portaal, en
 * dat wachtwoord is bij het opzetten één keer doorgegeven. Deze balk blijft
 * daarom staan tot het aan staat: wegklikbaar maken zou betekenen dat hij
 * één keer wordt weggeklikt en daarna nooit meer terugkomt.
 *
 * De gevoelige acties in het beheergedeelte werken sowieso niet zonder 2FA;
 * zie docs/security/gevoelige-acties.md.
 */
const page = usePage();

const zichtbaar = computed(
    () => Boolean(page.props.auth?.user) && !page.props.auth?.twoFactor,
);
</script>

<template>
    <div
        v-if="zichtbaar"
        class="mx-4 mt-4 flex flex-col gap-3 rounded-xl border border-warning/40 bg-warning/10 p-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="flex gap-3">
            <ShieldAlert class="mt-0.5 size-5 shrink-0 text-warning" />
            <div class="text-sm">
                <p class="font-medium">
                    {{ $t('Tweestapsverificatie staat uit') }}
                </p>
                <p class="text-muted-foreground">
                    {{
                        $t(
                            'Zolang dit uitstaat is je wachtwoord het enige slot op dit portaal. Instellen kost een minuut en je hebt er alleen een authenticator-app voor nodig.',
                        )
                    }}
                </p>
            </div>
        </div>

        <Button as-child size="sm" class="shrink-0">
            <Link :href="edit()">{{ $t('Nu instellen') }}</Link>
        </Button>
    </div>
</template>
