import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { home } from '@/routes';

/**
 * Een link naar een onderdeel van de voorpagina, waar je ook staat.
 *
 * **Op de voorpagina is dat een anker, daarbuiten een link.** Dat verschil
 * zat eerst in `SiteHeader` en moest eruit, want de voettekst heeft precies
 * hetzelfde nodig -- en daar ging het mis. Die zette een anker neer met
 * `@click.prevent` erbij en gaf `scrollNaar` de sleutel mét hekje mee,
 * terwijl die `getElementById` doet. Het gevolg: de links in de voettekst
 * deden niets, ook niet op de voorpagina, want de browser mocht de link
 * niet volgen en het scrollen vond niets. Twee keer dezelfde logica is twee
 * keer een kans daarop.
 *
 * Wat hier staat is alleen "waar wijst dit heen en wat voor element is
 * het". Wat er verder bij een klik gebeurt -- het uitklapmenu sluiten, het
 * adres bijwerken -- blijft bij de aanroeper, want dat is per plek anders.
 *
 * Zie docs/architecture/pagina-indeling.md.
 */
export function useSectieLink() {
    const page = usePage();

    /** Of we op de voorpagina staan; daar bestaan de ankers echt. */
    const opDeVoorpagina = computed(() => page.component === 'Welcome');

    /**
     * Waar een item naartoe wijst.
     *
     * Het anker blijft in de `href` staan, ook buiten de voorpagina: zo
     * werkt de link zonder JavaScript, kun je hem kopiëren en ziet een
     * zoekmachine waar hij heen gaat.
     */
    const anker = (sleutel: string): string =>
        opDeVoorpagina.value ? `#${sleutel}` : `${home().url}#${sleutel}`;

    /**
     * Een gewoon anker op de voorpagina, een Inertia-link daarbuiten.
     *
     * Als één waarde en niet als twee takken in het sjabloon: zo staat de
     * opmaak van een item één keer en kan hij voor de twee gevallen niet
     * uiteen gaan lopen.
     */
    const tag = computed(() => (opDeVoorpagina.value ? 'a' : Link));

    return { opDeVoorpagina, anker, tag };
}
