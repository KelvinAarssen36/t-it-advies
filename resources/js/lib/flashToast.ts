import { router } from '@inertiajs/vue3';
import { CirclePlus, Info, Pencil, TriangleAlert, Trash2 } from '@lucide/vue';
import type { Component } from 'vue';
import { h } from 'vue';
import { toast } from 'vue-sonner';
import type { FlashToast, FlashToastType } from '@/types/ui';

/**
 * De meldingen die rechtsonder verschijnen nadat je iets hebt gedaan.
 *
 * Elke soort heeft een eigen pictogram en een eigen kleur, zodat je zonder
 * te lezen ziet wát er is gebeurd. Dat is het hele punt: "verwijderd" hoort
 * er anders uit te zien dan "toegevoegd", ook als je net wegkeek.
 *
 * De server bepaalt de soort; zie App\Support\Toast en
 * docs/architecture/meldingen.md.
 */
type Soort = {
    icoon: Component;
    klasse: string;
    /** Hoelang hij blijft staan, in milliseconden. */
    duur: number;
};

const soorten: Record<FlashToastType, Soort> = {
    aangemaakt: {
        icoon: CirclePlus,
        klasse: 'brand-toast-aangemaakt',
        duur: 4500,
    },
    bijgewerkt: { icoon: Pencil, klasse: 'brand-toast-bijgewerkt', duur: 4500 },
    verwijderd: { icoon: Trash2, klasse: 'brand-toast-verwijderd', duur: 6000 },
    melding: { icoon: Info, klasse: 'brand-toast-melding', duur: 4500 },
    /*
     * Een fout blijft langer staan. De andere meldingen bevestigen iets dat
     * je zelf net deed en die mag je missen; een fout moet je lezen, en
     * misschien twee keer.
     */
    fout: { icoon: TriangleAlert, klasse: 'brand-toast-fout', duur: 9000 },
};

/**
 * De oude namen uit de starter kit, zodat een bestaande aanroep blijft
 * werken in plaats van stilletjes niets te doen.
 */
const oudeNamen: Record<string, FlashToastType> = {
    success: 'melding',
    info: 'melding',
    warning: 'fout',
    error: 'fout',
};

export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data?.message) {
            return;
        }

        const naam = soorten[data.type] ? data.type : oudeNamen[data.type];
        const soort = soorten[naam] ?? soorten.melding;

        toast(data.message, {
            description: data.description,
            icon: h(soort.icoon, { class: 'size-4' }),
            class: `brand-toast ${soort.klasse}`,
            duration: soort.duur,
        });
    });
}
