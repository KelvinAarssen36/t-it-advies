<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted } from 'vue';
import HoneypotFields from '@/components/HoneypotFields.vue';
import InputError from '@/components/InputError.vue';
import FeatureCard from '@/components/site/FeatureCard.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import TurnstileWidget from '@/components/TurnstileWidget.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { gsap, prefersReducedMotion } from '@/lib/motion';
import { store as contactStore } from '@/routes/contact';

/**
 * De publieke landingspagina.
 *
 * De structuur, de huisstijl en het beveiligde contactformulier staan; de
 * teksten zijn nog die van een opzet. Vervang ze, maar houd de secties:
 * kop, diensten, werkwijze, contact.
 *
 * Smooth scrolling en de scroll-reveals zitten in PublicLayout. Alleen de
 * binnenkomst van de hero staat hier, want die hoort bij deze pagina.
 */

const page = usePage();
const status = computed(() => page.props.flash?.status);

const services = [
    {
        eyebrow: '01',
        title: 'Advies',
        body: 'Meedenken over wat er nodig is en wat niet. Een keuze die je over drie jaar nog kunt uitleggen.',
    },
    {
        eyebrow: '02',
        title: 'Realisatie',
        body: 'Bouwen wat er is afgesproken, met documentatie die meegroeit met de code.',
    },
    {
        eyebrow: '03',
        title: 'Beheer',
        body: 'Draaiend houden, in de gaten houden en bijsturen voordat het een storing wordt.',
    },
];

const steps = [
    {
        title: 'Kennismaken',
        body: 'Wat speelt er, wat is er al, en waar loopt het vast.',
    },
    {
        title: 'Voorstel',
        body: 'Een plan met een prijs, en wat er buiten valt.',
    },
    {
        title: 'Bouwen',
        body: 'In korte stappen, zodat je onderweg kunt bijsturen.',
    },
    {
        title: 'Overdragen',
        body: 'Werkende software plus de documentatie om er zelf mee verder te kunnen.',
    },
];

let intro: gsap.core.Timeline | undefined;

onMounted(() => {
    // Bij reduced motion zetten we de elementen meteen op hun eindtoestand.
    // De animatie overslaan zou ze onzichtbaar laten; zie motion.ts.
    if (prefersReducedMotion()) {
        gsap.set('[data-intro]', { opacity: 1, y: 0 });

        return;
    }

    intro = gsap
        .timeline({ defaults: { ease: 'power3.out' } })
        .fromTo(
            '[data-intro]',
            { opacity: 0, y: 28 },
            { opacity: 1, y: 0, duration: 0.9, stagger: 0.12 },
        );
});

onBeforeUnmount(() => {
    intro?.kill();
});
</script>

<template>
    <Head title="IT-advies dat blijft staan" />

    <SiteSection
        tone="gradient"
        image="/images/hero-achtergrond.webp"
        image-mobile="/images/hero-achtergrond-mobiel.webp"
        priority
    >
        <div class="py-10 sm:py-16">
            <p
                data-intro
                class="mb-4 text-sm tracking-[0.2em] text-brand-cyan uppercase opacity-0"
            >
                IT-advies en realisatie
            </p>

            <h1
                data-intro
                class="max-w-3xl text-4xl leading-[1.05] font-semibold tracking-tight text-balance text-white opacity-0 sm:text-6xl"
            >
                Techniek die doet wat je bedrijf nodig heeft.
            </h1>

            <p
                data-intro
                class="mt-6 max-w-xl text-lg text-pretty text-muted-foreground opacity-0"
            >
                Van advies tot bouw en beheer. Zonder ruis, zonder
                afhankelijkheid van één leverancier.
            </p>

            <div data-intro class="mt-10 flex flex-wrap gap-3 opacity-0">
                <Button as="a" href="#contact" size="lg" variant="brand">
                    Neem contact op
                </Button>
                <Button
                    as="a"
                    href="#diensten"
                    size="lg"
                    variant="brand-outline"
                >
                    Bekijk de diensten
                </Button>
            </div>
        </div>
    </SiteSection>

    <SiteSection id="diensten" divided>
        <SectionHeading
            eyebrow="Diensten"
            title="Wat we doen"
            intro="Drie dingen, en die goed. De rest besteden we liever uit dan half te doen."
        />

        <div class="mt-12 grid gap-6 sm:grid-cols-3">
            <FeatureCard
                v-for="service in services"
                :key="service.title"
                :eyebrow="service.eyebrow"
                :title="service.title"
            >
                {{ service.body }}
            </FeatureCard>
        </div>
    </SiteSection>

    <SiteSection id="werkwijze" tone="raised" divided>
        <SectionHeading
            eyebrow="Werkwijze"
            title="Hoe het gaat"
            intro="Geen verrassingen achteraf. Je weet van tevoren wat er gebeurt en wat het kost."
        />

        <ol class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
            <li
                v-for="(step, index) in steps"
                :key="step.title"
                data-reveal
                class="opacity-0"
            >
                <span class="font-mono text-sm text-brand-cyan">
                    {{ String(index + 1).padStart(2, '0') }}
                </span>
                <h3 class="mt-2 text-lg font-medium text-white">
                    {{ step.title }}
                </h3>
                <p class="mt-1 text-muted-foreground">{{ step.body }}</p>
            </li>
        </ol>
    </SiteSection>

    <SiteSection id="contact" width="narrow" divided>
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
                <Label for="name">Naam</Label>
                <Input id="name" name="name" required autocomplete="name" />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">E-mailadres</Label>
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
                <Label for="subject">Onderwerp</Label>
                <Input id="subject" name="subject" required />
                <InputError :message="errors.subject" />
            </div>

            <div class="grid gap-2">
                <Label for="message">Bericht</Label>
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
