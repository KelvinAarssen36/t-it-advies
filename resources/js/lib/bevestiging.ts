import type { Ref } from 'vue';
import { shallowReactive } from 'vue';

/**
 * Het bevestigingsvenster, als functie die je kunt awaiten.
 *
 *     if (!(await bevestigBewerken({ titel: 'De volgorde aanpassen?' }))) {
 *         return;
 *     }
 *
 * **Waarom dit een module is en geen component dat je zelf plaatst.** Er
 * hoort maar één venster tegelijk op het scherm te staan, en de aanroeper
 * wil een antwoord -- geen `v-model` die hij zelf moet uitlezen, met de
 * afhandeling in een aparte functie. Met een belofte staat de vraag en wat
 * erna gebeurt op dezelfde plek in de code, en dat is precies waar je het
 * later weer zoekt.
 *
 * Het venster zelf staat in ConfirmDialog.vue en hangt één keer in de
 * layout van het portaal. Alle teksten staan daar, want daar is `$t()`
 * beschikbaar; deze module bevat geen woord Nederlands.
 *
 * **Dit is geen beveiliging.** Een bevestiging die in de browser staat, kun
 * je in de browser overslaan. Wat echt niet mag, hoort op de server te
 * worden tegengehouden. Zie docs/architecture/meldingen.md.
 */

export type BevestigSoort = 'aanmaken' | 'bewerken' | 'verwijderen';

/**
 * Een schuifje in het bevestigingsvenster: één beslissing die bij de
 * handeling hoort en niet in het formulier ervoor thuishoort.
 *
 * Het antwoord komt in `model` te staan, een ref van de aanroeper. Zo
 * blijft de belofte gewoon een `boolean` -- ja of nee op de vraag -- en
 * hoeft geen enkele bestaande aanroep mee te veranderen.
 *
 * Alleen op de **eerste** stap zichtbaar. Bij bewerken is de tweede stap er
 * om te bevestigen wat je al besloten hebt; daar hoort geen nieuwe keuze
 * meer bij.
 */
export type BevestigKeuze = {
    model: Ref<boolean>;
    /** Het woord naast het schuifje. */
    label: string;
    /** Eén regel uitleg eronder. Optioneel. */
    tekst?: string;
};

export type BevestigVraag = {
    /**
     * Bepaalt de kleur, het pictogram en het **aantal stappen**: bewerken
     * vraagt twee keer, aanmaken en verwijderen één keer. Die afspraak
     * staat hier en niet bij de aanroeper, zodat niemand hem per ongeluk
     * anders invult.
     */
    soort: BevestigSoort;
    /** De vraag zelf, als hele zin met een vraagteken. */
    titel: string;
    /** Eén regel context: wát er precies verandert. Optioneel. */
    tekst?: string;
    /** Het woord op de knop. Standaard kiest het venster er zelf een. */
    knop?: string;
    /** Een extra beslissing die bij deze handeling hoort. Optioneel. */
    keuze?: BevestigKeuze;
};

type Staat = {
    vraag: BevestigVraag | null;
    /** 1 of 2. Alleen bij bewerken komt stap 2 aan de beurt. */
    stap: number;
};

/**
 * `shallowReactive` en niet `reactive`: de vraag wordt in zijn geheel
 * vervangen en nooit van binnen gewijzigd, dus diep reactief maken levert
 * niets op. Het kost wél iets -- een diep reactief object pakt elke `Ref`
 * erin automatisch uit, en juist de `model` van een keuze moet een `Ref`
 * blijven, want dat is de draad terug naar de aanroeper.
 */
export const bevestigStaat = shallowReactive<Staat>({ vraag: null, stap: 1 });

/** Hoe vaak er gevraagd wordt. Bewerken is de enige met twee stappen. */
export function aantalStappen(soort: BevestigSoort): number {
    return soort === 'bewerken' ? 2 : 1;
}

let beantwoord: ((akkoord: boolean) => void) | null = null;

/**
 * Stel de vraag en wacht op het antwoord.
 *
 * Staat er al een venster open, dan komt er geen tweede overheen: de
 * nieuwe vraag wordt met `false` beantwoord. Dat kan alleen bij een fout in
 * de aanroepende code -- het venster houdt de rest van het scherm tegen --
 * en dan is niets doen het veiligste antwoord.
 */
export function bevestig(vraag: BevestigVraag): Promise<boolean> {
    if (bevestigStaat.vraag !== null) {
        return Promise.resolve(false);
    }

    bevestigStaat.vraag = vraag;
    bevestigStaat.stap = 1;

    return new Promise<boolean>((resolve) => {
        beantwoord = resolve;
    });
}

/**
 * De gebruiker klikte op de bevestigende knop.
 *
 * Bij bewerken is dat de eerste van twee keer; dan schuift alleen de stap
 * op en blijft het venster staan.
 */
export function bevestigVerder(): void {
    const vraag = bevestigStaat.vraag;

    if (vraag === null) {
        return;
    }

    if (bevestigStaat.stap < aantalStappen(vraag.soort)) {
        bevestigStaat.stap += 1;

        return;
    }

    sluit(true);
}

/** Afgebroken: met de knop, met Escape, of door naast het venster te klikken. */
export function bevestigAnnuleer(): void {
    sluit(false);
}

function sluit(akkoord: boolean): void {
    const antwoord = beantwoord;

    beantwoord = null;
    bevestigStaat.vraag = null;
    bevestigStaat.stap = 1;

    antwoord?.(akkoord);
}

/**
 * Twee keer vragen, want dit staat meteen live op de website.
 */
export function bevestigBewerken(
    vraag: Omit<BevestigVraag, 'soort'>,
): Promise<boolean> {
    return bevestig({ ...vraag, soort: 'bewerken' });
}

export function bevestigAanmaken(
    vraag: Omit<BevestigVraag, 'soort'>,
): Promise<boolean> {
    return bevestig({ ...vraag, soort: 'aanmaken' });
}

export function bevestigVerwijderen(
    vraag: Omit<BevestigVraag, 'soort'>,
): Promise<boolean> {
    return bevestig({ ...vraag, soort: 'verwijderen' });
}
