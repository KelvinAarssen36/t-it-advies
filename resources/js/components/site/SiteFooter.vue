<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { scrollNaar } from '@/lib/motion';
import { useSectieLink } from '@/lib/sectielink';
import { privacy } from '@/routes';

/**
 * De voet van de publieke site. Geen inloglink: zie SiteHeader.vue.
 *
 * **De links komen van de server**, uit dezelfde `navigation` als de kop.
 * Ze stonden hier hardgecodeerd, en dat was dezelfde fout als boven in de
 * balk: zette de klant een onderdeel uit, dan bleef hier een link staan
 * naar een anker dat niet bestond -- en klikken deed dan niets. Versleept
 * hij de onderdelen, dan schuift deze lijst nu mee.
 *
 * **En ze dóen nu ook iets, want dat deden ze niet.** Hier stond een anker
 * met `@click.prevent` erbij en een aanroep van `scrollNaar('#diensten')`
 * -- met hekje, terwijl die functie `getElementById` doet. Dus vond het
 * scrollen niets én mocht de browser de link niet volgen: deze links deden
 * helemaal niets, ook niet op de voorpagina. Nu komt de vorm uit
 * [`sectielink`](../../lib/sectielink.ts), dezelfde als in de kop, en krijgt
 * `scrollNaar` de sleutel zonder hekje.
 */
type NavItem = { key: string; label: string };

const page = usePage();

const items = computed<NavItem[]>(
    () => (page.props.navigation as NavItem[] | undefined) ?? [],
);

const { opDeVoorpagina, anker, tag } = useSectieLink();

/**
 * Een klik op een regel in de voettekst.
 *
 * Op de voorpagina onderscheppen we hem en scrollen we; daarbuiten laten we
 * de link zijn werk doen. **Niet blind `prevent`**: dat was precies waarom
 * deze links niets deden.
 */
const kies = (sleutel: string, gebeurtenis: MouseEvent): void => {
    if (!opDeVoorpagina.value) {
        return;
    }

    // Een middelklik of ctrl-klik hoort een nieuw tabblad te openen.
    if (gebeurtenis.metaKey || gebeurtenis.ctrlKey || gebeurtenis.shiftKey) {
        return;
    }

    if (scrollNaar(sleutel)) {
        gebeurtenis.preventDefault();
    }
};

const year = new Date().getFullYear();
</script>

<template>
    <footer class="bg-brand-navy">
        <div class="brand-rule" />

        <div class="mx-auto max-w-6xl px-6 py-14">
            <div class="flex flex-col justify-between gap-10 sm:flex-row">
                <div class="max-w-sm">
                    <p class="text-lg font-semibold tracking-tight">
                        <span class="brand-text-gradient">@T</span>
                        <span class="text-white"> IT Advies</span>
                    </p>
                    <p class="mt-3 text-sm text-muted-foreground">
                        {{
                            $t(
                                'IT-advies en realisatie. Van advies tot bouw en beheer.',
                            )
                        }}
                    </p>
                </div>

                <!--
                    Niets tonen als er geen onderdelen zijn. Dat is het
                    geval op een foutpagina: de voet staat er wel, maar de
                    ankers hebben daar niets om heen te wijzen.
                -->
                <nav
                    v-if="items.length > 0"
                    class="flex flex-col gap-2 text-sm"
                >
                    <component
                        :is="tag"
                        v-for="item in items"
                        :key="item.key"
                        :href="anker(item.key)"
                        class="text-muted-foreground transition-colors hover:text-brand-cyan"
                        @click="kies(item.key, $event)"
                    >
                        {{ item.label }}
                    </component>
                </nav>
            </div>

            <!--
                De privacyverklaring hoort hier en niet in de navigatie
                erboven: die gaat over de onderdelen van de pagina, en dit
                is een eigen pagina. Onderaan is ook waar bezoekers hem
                zoeken.
            -->
            <p
                class="mt-12 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground"
            >
                <span>&copy; {{ year }} @T IT Advies</span>
                <span aria-hidden="true">·</span>
                <Link
                    :href="privacy()"
                    class="transition-colors hover:text-brand-cyan"
                >
                    {{ $t('Privacyverklaring') }}
                </Link>
            </p>
        </div>
    </footer>
</template>
