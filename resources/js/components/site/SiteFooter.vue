<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, useTemplateRef } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LinkedinMerk from '@/components/site/LinkedinMerk.vue';
import { groeiLijn, scrollNaar } from '@/lib/motion';
import { useSectieLink } from '@/lib/sectielink';
import { privacy } from '@/routes';
import type { VoetOpDeSite } from '@/types/voettekst';

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
 *
 * **Drie kolommen, en elke kolom heeft een andere bron.** Dat is meteen de
 * uitleg van deze module:
 *
 * | Kolom     | Waar het vandaan komt                                  |
 * | --------- | ------------------------------------------------------ |
 * | Het merk  | De naam ligt vast; de zin eronder zet de eigenaar       |
 * | Op de site| `navigation`, dus zijn eigen indeling                   |
 * | Contact   | Het adres uit de config, de rest uit `footer_settings`  |
 *
 * **Wat leeg is, staat er niet.** De server stuurt `null` voor elk veld dat
 * de eigenaar niet heeft ingevuld, dus hier staat nergens een regel te
 * wachten op een waarde die nooit komt.
 *
 * **De credit onderaan ligt vast en is geen instelling.** Zie daar.
 *
 * Zie docs/architecture/modules/voettekst.md.
 */
type NavItem = { key: string; label: string };

const page = usePage();

const items = computed<NavItem[]>(
    () => (page.props.navigation as NavItem[] | undefined) ?? [],
);

/**
 * Wat de eigenaar zelf in zijn voet heeft gezet.
 *
 * Komt per pagina mee en niet uit de gedeelde props: het is een query, en
 * die hoort niet op elk portaalverzoek te drukken. Zie FooterController.
 */
const voet = computed<VoetOpDeSite>(
    () =>
        (page.props.voet as VoetOpDeSite | undefined) ?? {
            tagline: null,
            telefoon: null,
            plaats: null,
            kvk: null,
            btw: null,
        },
);

/** Het publieke adres en de LinkedIn-link liggen vast in de config. */
const email = computed(() => (page.props.email as string | undefined) ?? '');
const linkedin = computed(
    () => (page.props.linkedin as string | undefined) ?? '',
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

const voetElement = useTemplateRef<HTMLElement>('voetElement');

let stop: (() => void) | undefined;

onMounted(() => {
    stop = groeiLijn('[data-groeilijn]', voetElement.value);
});

onBeforeUnmount(() => stop?.());
</script>

<template>
    <footer ref="voetElement" class="brand-footer">
        <!--
            De accentlijn trekt zich open vanuit het midden zodra je de voet
            bereikt. Een eigen klasse en niet `brand-rule`: die wordt ook
            door de foutpagina en het welkomstvenster gebruikt, en daar
            hoort niets te bewegen.
        -->
        <div data-groeilijn class="brand-footer-lijn" />

        <div class="brand-footer-binnen">
            <div class="brand-footer-kolommen">
                <!-- Het merk. De naam ligt vast, de zin eronder niet. -->
                <div data-reveal class="brand-footer-merk opacity-0">
                    <!--
                        Hetzelfde merkteken als in de kop, via hetzelfde
                        component. Het draagt een lege `alt`: de naam staat
                        er direct naast, en die twee keer voorlezen helpt
                        niemand.
                    -->
                    <p class="brand-footer-naam">
                        <AppLogoIcon class="brand-footer-teken" />
                        <span>
                            <span class="brand-text-gradient">@T</span>
                            <span class="text-white"> IT Advies</span>
                        </span>
                    </p>

                    <p v-if="voet.tagline" class="brand-footer-zin">
                        {{ voet.tagline }}
                    </p>

                    <a
                        v-if="linkedin"
                        :href="linkedin"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="brand-footer-linkedin"
                    >
                        <LinkedinMerk class="brand-footer-linkedin-teken" />
                        <span>{{ $t('Volg op LinkedIn') }}</span>
                    </a>
                </div>

                <!--
                    Niets tonen als er geen onderdelen zijn. Dat is het
                    geval op een foutpagina: de voet staat er wel, maar de
                    ankers hebben daar niets om heen te wijzen.
                -->
                <!--
                    `aria-label` omdat dit de tweede navigatie op de pagina
                    is. Zonder naam kondigt een schermlezer er twee aan die
                    allebei "navigatie" heten, en dan weet je pas waar je
                    zit als je erdoorheen bent gelopen.
                -->
                <nav
                    v-if="items.length > 0"
                    data-reveal
                    class="brand-footer-kolom opacity-0"
                    :aria-label="$t('Onderdelen van de site')"
                >
                    <p class="brand-footer-kop">{{ $t('Op de site') }}</p>

                    <!--
                        Een eigen omhulsel om de links, want de lijst loopt
                        op een breed scherm in twee kolommen en het kopje
                        erboven niet. Zie `.brand-footer-lijst` in app.css.
                    -->
                    <div class="brand-footer-lijst">
                        <component
                            :is="tag"
                            v-for="item in items"
                            :key="item.key"
                            :href="anker(item.key)"
                            class="brand-footer-link"
                            @click="kies(item.key, $event)"
                        >
                            {{ item.label }}
                        </component>
                    </div>
                </nav>

                <div data-reveal class="brand-footer-kolom opacity-0">
                    <p class="brand-footer-kop">{{ $t('Contact') }}</p>

                    <a
                        v-if="email"
                        :href="`mailto:${email}`"
                        class="brand-footer-link"
                    >
                        {{ email }}
                    </a>

                    <a
                        v-if="voet.telefoon"
                        :href="`tel:${voet.telefoon.link}`"
                        class="brand-footer-link"
                    >
                        {{ voet.telefoon.tekst }}
                    </a>

                    <!--
                        Geen link: een werkgebied is geen adres, en een
                        kaartlink naar "Noord-Brabant" helpt niemand.
                    -->
                    <p v-if="voet.plaats" class="brand-footer-regel">
                        {{ voet.plaats }}
                    </p>
                </div>
            </div>

            <!--
                Onder de streep het zakelijke. De privacyverklaring hoort
                hier en niet in de navigatie erboven: die gaat over de
                onderdelen van de pagina, en dit is een eigen pagina.
                Onderaan is ook waar bezoekers hem zoeken.
            -->
            <div class="brand-footer-balk">
                <p class="brand-footer-zakelijk">
                    <span>&copy; {{ year }} @T IT Advies</span>

                    <template v-if="voet.kvk">
                        <span aria-hidden="true">·</span>
                        <span>{{ $t('KvK') }} {{ voet.kvk }}</span>
                    </template>

                    <template v-if="voet.btw">
                        <span aria-hidden="true">·</span>
                        <span>{{ $t('btw') }} {{ voet.btw }}</span>
                    </template>

                    <span aria-hidden="true">·</span>
                    <Link :href="privacy()" class="brand-footer-link">
                        {{ $t('Privacyverklaring') }}
                    </Link>
                </p>

                <!--
                    **De credit, en die is geen instelling.**

                    Hij staat hier hardgecodeerd en niet in `config/` of in
                    `footer_settings`. Een configwaarde is een env-waarde en
                    een instelling is een schuifje; allebei maken er iets
                    van dat uitgezet kan worden, en dan is het geen credit
                    meer.

                    De naam komt níet uit `page.props.eigenaar`. Die bevat
                    Erik Aarssen, de eigenaar van deze site. Kelvin Aarssen
                    heeft hem gebouwd. Dat scheelt één letter en het is
                    niet dezelfde persoon.

                    Alleen het label gaat door `$t()`: een naam vertaal je
                    niet.
                -->
                <p class="brand-footer-credit">
                    {{ $t('Gemaakt door') }} Kelvin Aarssen
                </p>
            </div>
        </div>
    </footer>
</template>
