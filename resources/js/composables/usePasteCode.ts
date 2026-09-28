import { ref } from 'vue';

/**
 * Plakt een code uit het klembord in een invoerveld van zes cijfers.
 *
 * Bedoeld voor wie zijn code uit een wachtwoordmanager kopieert: die
 * plakt dan vaak iets als "123 456" of een hele regel tekst. We vissen er
 * de cijfers uit in plaats van te eisen dat de gebruiker precies zes
 * tekens op het klembord heeft staan.
 *
 * Niet elke browser laat een pagina het klembord lézen -- Firefox doet dat
 * zonder extensie niet. Daarom `supported`: is het er niet, dan tonen we de
 * knop helemaal niet in plaats van een knop die niets doet.
 */
export type UsePasteCodeReturn = {
    supported: boolean;
    failed: ReturnType<typeof ref<boolean>>;
    paste: () => Promise<void>;
};

export function usePasteCode(
    apply: (code: string) => void,
    length = 6,
): UsePasteCodeReturn {
    const supported =
        typeof navigator !== 'undefined' &&
        typeof navigator.clipboard?.readText === 'function';

    const failed = ref(false);

    const paste = async (): Promise<void> => {
        failed.value = false;

        try {
            const text = await navigator.clipboard.readText();
            const digits = text.replace(/\D/g, '').slice(0, length);

            if (digits.length < length) {
                failed.value = true;

                return;
            }

            apply(digits);
        } catch {
            // Geweigerde toestemming of een leeg klembord. Geen foutmelding
            // in de console: de gebruiker kan gewoon met de hand typen.
            failed.value = true;
        }
    };

    return { supported, failed, paste };
}
