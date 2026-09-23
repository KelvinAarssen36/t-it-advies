<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import HoneypotFields from '@/components/HoneypotFields.vue';
import InputError from '@/components/InputError.vue';
import TurnstileWidget from '@/components/TurnstileWidget.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    gsap,
    prefersReducedMotion,
    revealOnScroll,
    startSmoothScroll,
} from '@/lib/motion';
import { store as contactStore } from '@/routes/contact';
import { dashboard, login } from '@/routes';

/**
 * De publieke landingspagina.
 *
 * Dit is bewust een skelet, geen af ontwerp: de structuur, de animatielaag en
 * het beveiligde contactformulier staan er, zodat er een echte pagina
 * overheen gebouwd kan worden zonder de techniek opnieuw uit te vinden.
 */

const page = usePage();
const status = computed(() => page.props.flash?.status);

const root = ref<HTMLElement | null>(null);

let stopScroll: (() => void) | undefined;
let stopReveal: (() => void) | undefined;
let intro: gsap.core.Timeline | undefined;

onMounted(() => {
    stopScroll = startSmoothScroll();
    stopReveal = revealOnScroll('[data-reveal]', root.value);

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
    stopReveal?.();
    stopScroll?.();
});
</script>

<template>
    <Head title="IT-advies dat blijft staan" />

    <div ref="root" class="min-h-screen bg-background text-foreground">
        <header
            class="mx-auto flex max-w-6xl items-center justify-between px-6 py-6"
        >
            <span class="text-lg font-semibold tracking-tight"
                >@T IT Advies</span
            >

            <nav class="flex items-center gap-4 text-sm">
                <Link
                    v-if="page.props.auth.user"
                    :href="dashboard()"
                    class="hover:text-foreground/70"
                >
                    Dashboard
                </Link>
                <Link v-else :href="login()" class="hover:text-foreground/70">
                    Inloggen
                </Link>
            </nav>
        </header>

        <main>
            <section class="mx-auto max-w-6xl px-6 py-24 sm:py-32">
                <p
                    data-intro
                    class="mb-4 text-sm tracking-[0.2em] text-muted-foreground uppercase"
                >
                    IT-advies en realisatie
                </p>
                <h1
                    data-intro
                    class="max-w-3xl text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-6xl"
                >
                    Techniek die doet wat je bedrijf nodig heeft.
                </h1>
                <p
                    data-intro
                    class="mt-6 max-w-xl text-lg text-pretty text-muted-foreground"
                >
                    Van advies tot bouw en beheer. Zonder ruis, zonder
                    afhankelijkheid van één leverancier.
                </p>
                <div data-intro class="mt-10 flex gap-3">
                    <Button as="a" href="#contact" size="lg"
                        >Neem contact op</Button
                    >
                </div>
            </section>

            <section class="border-t border-border">
                <div
                    class="mx-auto grid max-w-6xl gap-10 px-6 py-24 sm:grid-cols-3"
                >
                    <article
                        v-for="n in 3"
                        :key="n"
                        data-reveal
                        class="opacity-0"
                    >
                        <h2 class="mb-2 text-lg font-medium">Dienst {{ n }}</h2>
                        <p class="text-muted-foreground">
                            Korte omschrijving van wat je hier levert en wat de
                            klant eraan heeft.
                        </p>
                    </article>
                </div>
            </section>

            <section id="contact" class="border-t border-border">
                <div class="mx-auto max-w-2xl px-6 py-24">
                    <h2
                        data-reveal
                        class="mb-2 text-3xl font-semibold opacity-0"
                    >
                        Contact
                    </h2>
                    <p data-reveal class="mb-8 text-muted-foreground opacity-0">
                        Laat een bericht achter, we reageren doorgaans binnen
                        een werkdag.
                    </p>

                    <p
                        v-if="status"
                        class="mb-6 rounded-md border border-border bg-muted px-4 py-3 text-sm"
                    >
                        {{ status }}
                    </p>

                    <Form
                        v-bind="contactStore.form()"
                        reset-on-success
                        v-slot="{ errors, processing }"
                        class="space-y-5"
                    >
                        <HoneypotFields />

                        <div class="grid gap-2">
                            <Label for="name">Naam</Label>
                            <Input
                                id="name"
                                name="name"
                                required
                                autocomplete="name"
                            />
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
                        <InputError
                            :message="errors['cf-turnstile-response']"
                        />

                        <Button :disabled="processing">
                            <Spinner v-if="processing" />
                            Versturen
                        </Button>
                    </Form>
                </div>
            </section>
        </main>

        <footer
            class="border-t border-border px-6 py-10 text-sm text-muted-foreground"
        >
            <div class="mx-auto max-w-6xl">
                &copy; {{ new Date().getFullYear() }} @T IT Advies
            </div>
        </footer>
    </div>
</template>
