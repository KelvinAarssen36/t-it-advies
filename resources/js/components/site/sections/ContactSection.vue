<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import HoneypotFields from '@/components/HoneypotFields.vue';
import InputError from '@/components/InputError.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import TurnstileWidget from '@/components/TurnstileWidget.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
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
</script>

<template>
    <SiteSection id="contact" width="narrow" :tone="tone" :divided="divided">
        <SectionHeading
            eyebrow="Contact"
            title="Laat een bericht achter"
            intro="We reageren doorgaans binnen een werkdag."
        />

        <p
            v-if="status"
            class="mt-8 rounded-md border border-border bg-muted px-4 py-3 text-sm"
        >
            {{ status }}
        </p>

        <Form
            v-bind="contactStore.form()"
            reset-on-success
            v-slot="{ errors, processing }"
            class="mt-10 space-y-5"
        >
            <HoneypotFields />

            <div class="grid gap-2">
                <Label for="name" verplicht>Naam</Label>
                <Input id="name" name="name" required autocomplete="name" />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email" verplicht>E-mailadres</Label>
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
                <Label for="subject" verplicht>Onderwerp</Label>
                <Input id="subject" name="subject" required />
                <InputError :message="errors.subject" />
            </div>

            <div class="grid gap-2">
                <Label for="message" verplicht>Bericht</Label>
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
                Versturen
            </Button>
        </Form>
    </SiteSection>
</template>
