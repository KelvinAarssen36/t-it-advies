/**
 * Het thema van het portaal. Twee waarden en niet drie: 'system' uit de
 * starter kit is weggehaald. Zie resources/js/composables/useAppearance.ts.
 */
export type Appearance = 'light' | 'dark';

export type AppVariant = 'header' | 'sidebar';

/**
 * De soorten meldingen. Vijf, en dat zijn precies de dingen die je van
 * elkaar moet kunnen onderscheiden zonder te lezen. Zie
 * docs/architecture/meldingen.md.
 */
export type FlashToastType =
    | 'aangemaakt'
    | 'bijgewerkt'
    | 'verwijderd'
    | 'melding'
    | 'fout';

export type FlashToast = {
    type: FlashToastType;
    message: string;
    /** Optioneel, en bedoeld om iets toe te voegen -- niet te herhalen. */
    description?: string;
};
