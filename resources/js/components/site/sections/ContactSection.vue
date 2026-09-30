<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { CheckCheck } from '@lucide/vue';
import { computed, nextTick, watch } from 'vue';
import HoneypotFields from '@/components/HoneypotFields.vue';
import InputError from '@/components/InputError.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import TurnstileWidget from '@/components/TurnstileWidget.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { gsap, prefersReducedMotion } from '@/lib/motion';
import { store as contactStore } from '@/routes/contact';
import type { SectieProps } from '@/types/secties';

/**
 * Het contactformulier.
 *
 * Dit onderdeel is smaller dan de rest -- een formulier over de volle
 * breedte leest slecht -- en die keuze staat hier en niet bij de
 * aanroeper: hij hoort bij wat dit onderdeel is en niet bij waar het staat.
 *
 * De drie beschermingslagen (rate limiting, honeypot, Turnstile) zitten op
 * de route; zie routes/web.php.
 */
defineProps<SectieProps>();

const page = usePage();
const status = computed(() => page.props.flash?.status);

/**
 * De bevestiging komt binnen met een animatie.
 *
 * Dit is het enige moment op de hele landing waarop de bezoeker iets
 * heeft gedaan en een antwoord terugkrijgt. Een regel die er zonder meer
 * staat, mist hij -- het formulier is dan leeggelopen en er is verder
 * niets veranderd. Daarom zwelt hij op en vinkt het vinkje zich af.
 *
 * `prefersReducedMotion` wordt hier niet apart afgevangen: de melding
 * staat er sowieso, ook als de animatie niets doet. Dat is het verschil
 * met een reveal, waar het element op doorzichtigheid nul begint.
 */
watch(status, (nieuw) => {
    if (!nieuw || prefersReducedMotion()) {
        return;
    }

    void nextTick(() => {
        gsap.fromTo(
            '[data-bevestiging]',
            { opacity: 0, y: 12, scale: 0.98 },
            { opacity: 1, y: 0, scale: 1, duration: 0.5, ease: 'power3.out' },
        );

        gsap.fromTo(
            '[data-bevestiging-vink]',
            { scale: 0, rotate: -25 },
            {
                scale: 1,
                rotate: 0,
                duration: 0.5,
                delay: 0.12,
                ease: 'back.out(2.2)',
            },
        );
    });
});
</script>

<template>
    <SiteSection id="contact" width="narrow" :tone="tone" :divided="divided">
        <SectionHeading
            :eyebrow="$t('Contact')"
            :title="$t('Laat een bericht achter')"
            :intro="$t('We reageren doorgaans binnen een werkdag.')"
        />

        <p v-if="status" data-bevestiging class="brand-bevestiging mt-8">
            <CheckCheck data-bevestiging-vink class="mt-0.5 size-4 shrink-0" />
            <span>{{ status }}</span>
        </p>

        <Form
            v-bind="contactStore.form()"
            reset-on-success
            v-slot="{ errors, processing }"
            class="mt-10 space-y-5"
        >
            <HoneypotFields />

            <div class="grid gap-2">
                <Label for="name" verplicht>{{ $t('Naam') }}</Label>
                <Input id="name" name="name" required autocomplete="name" />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email" verplicht>{{ $t('E-mailadres') }}</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    required
                    autocomplete="email"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="subject" verplicht>{{ $t('Onderwerp') }}</Label>
                <Input id="subject" name="subject" required />
                <InputError :message="errors.subject" />
            </div>

            <div class="grid gap-2">
                <Label for="message" verplicht>{{ $t('Bericht') }}</Label>
                <textarea
                    id="message"
                    name="message"
                    rows="6"
                    required
                    class="rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                />
                <InputError :message="errors.message" />
            </div>

            <TurnstileWidget />
            <InputError :message="errors['cf-turnstile-response']" />

            <Button :disabled="processing" variant="brand">
                <Spinner v-if="processing" />
                {{ $t('Versturen') }}
            </Button>
        </Form>
    </SiteSection>
</template>
