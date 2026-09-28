import type { Ref } from 'vue';
import { onMounted, ref } from 'vue';
import type { Appearance } from '@/types';

export type { Appearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    updateAppearance: (value: Appearance) => void;
};

/**
 * Het thema van het portaal.
 *
 * Twee waarden, niet drie. De starter kit kent ook 'system', maar dat is
 * hier weggehaald: het betekent dat het portaal er anders uitziet naargelang
 * een instelling die ergens anders staat, en dat je niet kunt zien welke van
 * de twee je eigenlijk hebt gekozen.
 *
 * De donkere stand is de huisstijl en daarmee de basis: de landing staat er
 * altijd in, en het portaal sluit daarop aan. In het scherm heet hij daarom
 * "Huisstijl" en niet "Donker"; de opgeslagen waarde blijft `dark`, want die
 * stuurt de klasse op <html> aan.
 *
 * Zie docs/architecture/huisstijl-en-kleuren.md.
 */
export const STANDAARD: Appearance = 'dark';

/**
 * Leest een opgeslagen waarde uit en maakt er iets geldigs van.
 *
 * Een oude 'system' uit een eerdere versie komt hier binnen, en die wordt
 * gewoon de standaard. Hetzelfde geldt voor onzin: de waarde komt uit
 * localStorage en een cookie, en dat is allebei iets wat een bezoeker zelf
 * kan zetten.
 */
export function leesThema(waarde: string | null | undefined): Appearance {
    return waarde === 'light' ? 'light' : STANDAARD;
}

export function updateTheme(value: Appearance): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.classList.toggle('dark', value === 'dark');
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getStoredAppearance = (): Appearance => {
    if (typeof window === 'undefined') {
        return STANDAARD;
    }

    try {
        return leesThema(localStorage.getItem('appearance'));
    } catch {
        // In een privévenster kan localStorage gooien in plaats van leeg
        // terug te komen. Dan maar de standaard.
        return STANDAARD;
    }
};

export function initializeTheme(): void {
    updateTheme(getStoredAppearance());
}

const appearance = ref<Appearance>(STANDAARD);

export function useAppearance(): UseAppearanceReturn {
    onMounted(() => {
        appearance.value = getStoredAppearance();
    });

    function updateAppearance(value: Appearance) {
        appearance.value = value;

        try {
            localStorage.setItem('appearance', value);
        } catch {
            // Zie hierboven: niet kunnen opslaan mag het wisselen zelf niet
            // in de weg zitten.
        }

        // Ook in een cookie, want de server zet `dark` al op <html> voordat
        // er JavaScript draait. Zonder dat zie je bij elke paginalading een
        // flits van het verkeerde thema.
        setCookie('appearance', value);

        updateTheme(value);
    }

    return {
        appearance,
        updateAppearance,
    };
}
