<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Menu, X } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import LocaleToggle from '@/components/site/LocaleToggle.vue';
import { Button } from '@/components/ui/button';
import portal from '@/routes/portal';

/**
 * De kop van de publieke site.
 *
 * De navigatie wijst nu naar ankers op de landingspagina. Zodra er echte
 * pagina's zijn, worden dit routes; de rest van dit component verandert dan
 * niet mee.
 *
 * Er staat bewust géén inlogknop op de publieke site. Er is maar één
 * gebruiker -- de eigenaar -- en die kent zijn eigen adres. Een inlogknop
 * zou bezoekers alleen maar wijzen op een deur die niet voor hen is, en
 * nodigt uit tot proberen. De link naar het portaal verschijnt alleen als
 * je al ingelogd bent.
 */

type NavLink = { label: string; href: string };

const links: NavLink[] = [
    { label: 'Diensten', href: '#diensten' },
    { label: 'Werkwijze', href: '#werkwijze' },
    { label: 'Contact', href: '#contact' },
];

const page = usePage();

/**
 * Of de link naar het portaal getoond mag worden.
 *
 * Dit staat hier als één benoemde waarde en niet als losse `v-if` per plek,
 * zodat er maar één regel is om te vergeten. De link staat op twee plekken
 * (de kop op desktop en het uitklapmenu op mobiel); komt er een derde bij,
 * dan hoort die deze waarde te gebruiken en niet zijn eigen controle te
 * verzinnen.
 *
 * De optionele ketting is geen sierraad: `auth` is een gedeelde prop, en bij
 * een gedeeltelijke herlading die hem niet opvraagt is hij er even niet.
 * Zonder `?.` zou dat een fout geven; mét `?.` valt hij terug op "niet
 * tonen", en dat is de veilige kant.
 *
 * Wat je hier ziet is bovendien alleen de nette weergave. De echte grendel
 * zit op de server: /dashboard staat achter 'auth', 'verified' en
 * 'two-factor.required', dus wie het adres raadt komt er evengoed niet in.
 * Zie routes/web.php.
 */
const magPortaalZien = computed(() => Boolean(page.props.auth?.user));

// Boven aan de pagina zweeft de kop over de hero heen; zodra je scrollt komt
// er een achtergrond onder, anders loopt de tekst door het beeld.
const scrolled = ref(false);
const open = ref(false);

const onScroll = () => {
    scrolled.value = window.scrollY > 8;
};

onMounted(() => {
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
});
</script>

<template>
    <header
        class="sticky top-0 z-50 transition-colors duration-300"
        :class="
            scrolled
                ? 'border-b border-border bg-background/85 backdrop-blur-md'
                : 'border-b border-transparent'
        "
    >
        <div
            class="mx-auto flex h-16 max-w-6xl items-center justify-between px-6"
        >
            <a href="#top" class="text-lg font-semibold tracking-tight">
                <span class="brand-text-gradient">@T</span>
                <span class="text-white"> IT Advies</span>
            </a>

            <nav class="hidden items-center gap-8 md:flex">
                <a
                    v-for="link in links"
                    :key="link.href"
                    :href="link.href"
                    class="text-sm text-foreground transition-colors hover:text-brand-cyan"
                >
                    {{ link.label }}
                </a>
            </nav>

            <div class="hidden items-center gap-3 md:flex">
                <Link
                    v-if="magPortaalZien"
                    :href="portal.enter()"
                    class="text-sm text-muted-foreground transition-colors hover:text-brand-cyan"
                >
                    Dashboard
                </Link>
                <!--
                    Ook de publieke site is tweetalig. Dezelfde route als de
                    knoppen in het portaal, dus wisselt de eigenaar hier
                    terwijl hij is ingelogd, dan staat het portaal er straks
                    ook in.

                    In de kop alleen de taalcodes: daar telt elke pixel, en
                    naast een vlag is NL of EN duidelijk genoeg.
                -->
                <LocaleToggle compact />

                <Button as="a" href="#contact" variant="brand">
                    Neem contact op
                </Button>
            </div>

            <button
                type="button"
                class="text-foreground md:hidden"
                :aria-expanded="open"
                aria-controls="site-menu"
                aria-label="Menu"
                @click="open = !open"
            >
                <X v-if="open" class="size-6" />
                <Menu v-else class="size-6" />
            </button>
        </div>

        <div
            v-if="open"
            id="site-menu"
            class="border-t border-border bg-background md:hidden"
        >
            <nav class="mx-auto flex max-w-6xl flex-col gap-1 px-6 py-4">
                <a
                    v-for="link in links"
                    :key="link.href"
                    :href="link.href"
                    class="py-2 text-foreground"
                    @click="open = false"
                >
                    {{ link.label }}
                </a>
                <Link
                    v-if="magPortaalZien"
                    :href="portal.enter()"
                    class="py-2 text-muted-foreground"
                >
                    Dashboard
                </Link>

                <!--
                    In het uitklapmenu is er ruimte zat, dus daar staan de
                    volledige taalnamen.
                -->
                <LocaleToggle class="mt-2 self-start" />
            </nav>
        </div>
    </header>
</template>
