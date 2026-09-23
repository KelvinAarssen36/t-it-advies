/**
 * Props die op elke pagina beschikbaar zijn.
 *
 * Deze komen uit HandleInertiaRequests::share(). Voeg je daar iets toe, voeg
 * het hier dan ook toe -- anders ziet TypeScript het niet.
 */

export type HoneypotProps = {
    enabled: boolean;
    nameFieldName: string;
    unrandomizedNameFieldName: string;
    validFromFieldName: string;
    encryptedValidFrom: string;
    withCsp: boolean;
};

export type FlashProps = {
    status?: string | null;
};
