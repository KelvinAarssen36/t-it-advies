import type { ComputedRef, Ref } from 'vue';
import { computed, reactive, ref } from 'vue';
import { t } from '@/lib/i18n';

/**
 * De bewaking op verplichte velden in een venster.
 *
 *     const nodig = useVerplicht(() => [
 *         { sleutel: 'title_nl', naam: t('Titel'), gevuld: vorm.value.title_nl.trim() !== '' },
 *     ]);
 *
 * **Waarom dit op één plek staat.** De klant beheert alles zelf en kan het
 * portaal niet stukmaken: een knop die je wél kunt indrukken terwijl er een
 * verplicht veld leeg is, levert hem een rode serverfout op nadat hij al
 * drie stappen verder dacht te zijn. Deze module draait dat om -- de knop
 * is grijs zolang er iets mist, en drukt hij toch, dan krijgt hij te horen
 * wát er mist. In vierentwintig vensters moet dat hetzelfde werken en
 * hetzelfde heten, dus staat het hier en niet vierentwintig keer apart.
 *
 * **De knop blijft indrukbaar.** Een echt uitgeschakelde knop (`disabled`)
 * doet níets als je erop drukt -- geen melding, geen uitleg, en een
 * schermlezer slaat hem over. Daarom zet `geblokkeerd` op de knop hem
 * alleen grijs en meldt hij zich via `aria-disabled`; de klik komt gewoon
 * binnen en wordt hier beantwoord. Zie components/ui/button/Button.vue.
 *
 * **De melding komt pas na een druk op de knop.** Rode tekst onder velden
 * die je nog niet eens hebt kunnen invullen leest als een fout die je al
 * gemaakt hebt. Daarom is `ontbreekt` leeg tot `meld()` is aangeroepen.
 *
 * Zie docs/architecture/formulieren-en-schuifbalken.md.
 */
export type VerplichtVeld = {
    /** De veldnaam uit het formulier; hieraan hangt de melding eronder. */
    sleutel: string;

    /** Hoe het veld op het scherm heet. Dit komt in de melding te staan. */
    naam: string;

    /** Of er iets ingevuld staat. */
    gevuld: boolean;

    /**
     * Op welke stap het veld staat. Laat dit weg in een venster zonder
     * stappen; dan telt alles als één geheel.
     */
    stap?: number;

    /**
     * Een eigen melding onder dit veld, in plaats van "Dit veld is nog
     * leeg." Gebruik hem als "leeg" niet klopt: een code van zes cijfers
     * waarvan er pas drie staan is niet leeg, maar nog niet af.
     *
     * Zet hier nooit iets in dat de klant heeft getypt. Een wachtwoord of
     * een code hoort nergens in een melding thuis.
     */
    melding?: string;
};

export type Verplicht = {
    /**
     * Of alles wat op de huidige stap moet, ingevuld is. Dit stuurt de
     * knop "Volgende"; in een venster zonder stappen is het hetzelfde als
     * `compleet`.
     */
    klaar: boolean;

    /** Of het hele formulier compleet is. Dit stuurt de knop "Opslaan". */
    compleet: boolean;

    /** Wat er nog ontbreekt, pas nadat er op een grijze knop is gedrukt. */
    ontbreekt: string[];

    /** De melding onder één veld, of `undefined` als die er niet is. */
    melding: (sleutel: string) => string | undefined;

    /** Laat zien wat er mist. Geeft `false`, zodat je hem kunt returnen. */
    meld: () => false;

    /** De eerste stap waar nog iets leegstaat, of de stap van nu. */
    eersteLegeStap: () => number;

    /** Begin opnieuw: de meldingen verdwijnen. Doe dit bij het openen. */
    herstel: () => void;
};

/**
 * @param velden  Een functie die de verplichte velden teruggeeft. Een
 *                functie en geen lijst: de waarden veranderen terwijl de
 *                klant typt, en zo leest deze module ze elke keer opnieuw.
 * @param stap    De stap waar het venster nu op staat, als het stappen
 *                heeft.
 */
export function useVerplicht(
    velden: () => VerplichtVeld[],
    stap?: Ref<number>,
): Verplicht {
    const gemeld = ref(false);

    const alle: ComputedRef<VerplichtVeld[]> = computed(velden);

    const leeg = computed(() => alle.value.filter((veld) => !veld.gevuld));

    /** Wat er op de stap van nu nog leegstaat. */
    const leegHier = computed(() =>
        stap === undefined
            ? leeg.value
            : leeg.value.filter((veld) => veld.stap === stap.value),
    );

    const klaar = computed(() => leegHier.value.length === 0);
    const compleet = computed(() => leeg.value.length === 0);

    /*
     * Staat er op deze stap nog iets leeg, dan noemen we alleen dat --
     * verderop wijzen heeft geen zin zolang je hier nog bezig bent. Is
     * deze stap rond en blijkt er bij het opslaan tóch nog iets te
     * ontbreken, dan hoort de klant te horen wat dat is, ook al staat het
     * op een stap terug.
     */
    const ontbreekt = computed(() => {
        if (!gemeld.value) {
            return [];
        }

        const mist = leegHier.value.length > 0 ? leegHier.value : leeg.value;

        return mist.map((veld) => veld.naam);
    });

    const melding = (sleutel: string): string | undefined => {
        if (!gemeld.value) {
            return undefined;
        }

        const veld = alle.value.find(
            (kandidaat) => kandidaat.sleutel === sleutel,
        );

        if (!veld || veld.gevuld) {
            return undefined;
        }

        return veld.melding ?? t('Dit veld is nog leeg.');
    };

    const meld = (): false => {
        gemeld.value = true;

        return false;
    };

    const eersteLegeStap = (): number => {
        const stappen = leeg.value
            .map((veld) => veld.stap)
            .filter((nummer): nummer is number => nummer !== undefined);

        return stappen.length > 0 ? Math.min(...stappen) : (stap?.value ?? 1);
    };

    const herstel = (): void => {
        gemeld.value = false;
    };

    return reactive({
        klaar,
        compleet,
        ontbreekt,
        melding,
        meld,
        eersteLegeStap,
        herstel,
    });
}
