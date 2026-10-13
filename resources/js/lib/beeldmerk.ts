/**
 * Een gekozen beeldmerk nakijken, en te grote beelden meteen verkleinen.
 *
 * **Waarom dit in de browser gebeurt.** De server keurt een logo af dat
 * groter is dan twee megabyte, maar dat hoort de klant pas nadat hij het
 * hele bestand heeft geüpload, het formulier heeft ingevuld en op Opslaan
 * heeft gedrukt. Op een telefoon met een foto van acht megabyte is dat een
 * minuut wachten op een nee.
 *
 * En het is een nee die nergens voor nodig is. Wat er uiteindelijk wordt
 * bewaard is een vierkantje van 256 bij 256 (zie `App\Support\Media\Logo`),
 * dus van die acht megabyte blijft sowieso niets over. Daarom verkleinen we
 * hier, vóór het versturen: de klant merkt er alleen aan dat het snel gaat,
 * en krijgt één regel te lezen waarin staat wat er gebeurd is.
 *
 * **De grenzen hieronder zijn een kopie, geen bron.** De echte staan in
 * `App\Http\Requests\Website\ExperienceRequest`. Wat hier gebeurt is een
 * gemak; wie deze code omzeilt loopt alsnog tegen de server aan. Wijzig je
 * daar een grens, wijzig hem dan hier mee -- anders verkleinen we naar een
 * maat die de server nog steeds afkeurt.
 *
 * Er staat met opzet geen Nederlandse tekst in deze module. Welke zin de
 * klant bij welke uitkomst te zien krijgt, bepaalt
 * `components/LogoKiezer.vue`; daar bestaat `$t()`.
 *
 * Zie docs/architecture/formulieren-en-schuifbalken.md.
 */

/** Anderhalve megabyte, net als `media.logo.max_kb` op de server. */
export const LOGO_MAX_BYTES = 1536 * 1024;

/** De kortste zijde die de server accepteert. */
export const LOGO_MIN_ZIJDE = 48;

/** De langste zijde die de server accepteert. */
export const LOGO_MAX_ZIJDE = 3000;

/**
 * Waar we naartoe verkleinen.
 *
 * Het bewaarde vierkantje is 256 pixels en de klant mag vijf keer
 * inzoomen, dus bij de uiterste stand komt 256 / 5 ≈ 52 pixel van het
 * origineel in beeld. Met 1600 pixels op de langste zijde is er dus ruim
 * genoeg over om ook volledig ingezoomd scherp te blijven, en scheelt het
 * tegelijk een veelvoud aan bytes.
 */
const DOELMATEN = [1600, 1200, 900] as const;

/** Van scherp naar zuinig. Alleen zinvol bij JPEG en WebP. */
const KWALITEITEN = [0.9, 0.78, 0.65] as const;

export type Keuring =
    /** Het bestand kan zo mee. */
    | { soort: 'goed'; bestand: File }
    /** Het was te groot en is hier verkleind. */
    | { soort: 'verkleind'; bestand: File; van: number; naar: number }
    /**
     * Onbruikbaar, met de reden erbij:
     *
     * - `onleesbaar` -- geen beeld dat de browser kan openen.
     * - `te-klein` -- de kortste zijde haalt de 48 pixels niet. Dat valt
     *   niet op te lossen door te verkleinen.
     * - `te-smal` -- zo langgerekt dat hij niet tegelijk onder de 5000 en
     *   boven de 48 pixels te krijgen is.
     * - `niet-gelukt` -- de browser kon er geen kleiner bestand van maken.
     */
    | {
          soort: 'fout';
          reden: 'onleesbaar' | 'te-klein' | 'te-smal' | 'niet-gelukt';
      };

type Geopend = {
    bron: CanvasImageSource;
    breedte: number;
    hoogte: number;
    sluit: () => void;
};

/**
 * Het bestand openen als beeld.
 *
 * Eerst `createImageBitmap`, want dat decodeert buiten de hoofddraad en
 * laat de pagina dus niet haperen bij een grote foto. Lukt dat niet -- een
 * oudere browser, of een formaat dat hij niet los kan decoderen -- dan de
 * gewone weg via een `<img>`.
 */
async function open(bestand: File): Promise<Geopend | null> {
    if (typeof createImageBitmap === 'function') {
        try {
            const bitmap = await createImageBitmap(bestand);

            return {
                bron: bitmap,
                breedte: bitmap.width,
                hoogte: bitmap.height,
                sluit: () => bitmap.close(),
            };
        } catch {
            // Door naar de <img>-weg hieronder.
        }
    }

    const adres = URL.createObjectURL(bestand);

    try {
        const beeld = await new Promise<HTMLImageElement>((klaar, mis) => {
            const element = new Image();

            element.onload = () => klaar(element);
            element.onerror = () => mis(new Error('onleesbaar'));
            element.src = adres;
        });

        return {
            bron: beeld,
            breedte: beeld.naturalWidth,
            hoogte: beeld.naturalHeight,
            // Pas teruggeven als er niet meer uit getekend wordt.
            sluit: () => URL.revokeObjectURL(adres),
        };
    } catch {
        URL.revokeObjectURL(adres);

        return null;
    }
}

/**
 * Of deze browser WebP kan wegschrijven.
 *
 * Kan hij het niet, dan geeft `toBlob` stilletjes een PNG terug in plaats
 * van een foutmelding -- en dan sta je met een bestand waarvan de naam en
 * het type niet kloppen. Daarom vooraf vragen in plaats van achteraf.
 */
function kanWebp(): boolean {
    try {
        const doek = document.createElement('canvas');

        doek.width = 1;
        doek.height = 1;

        return doek.toDataURL('image/webp').startsWith('data:image/webp');
    } catch {
        return false;
    }
}

/**
 * Het formaat om naartoe te schrijven.
 *
 * WebP als het kan: dat is het kleinst en houdt doorzichtigheid. Anders
 * blijft een PNG een PNG -- de klant mag de witte ondergrond uitzetten, en
 * met JPEG zou die doorzichtigheid hier al verdwijnen. Al het overige gaat
 * naar JPEG, want een foto als PNG wordt juist groter.
 */
function kiesFormaat(bronType: string): { mime: string; extensie: string } {
    if (kanWebp()) {
        return { mime: 'image/webp', extensie: 'webp' };
    }

    if (bronType === 'image/png') {
        return { mime: 'image/png', extensie: 'png' };
    }

    return { mime: 'image/jpeg', extensie: 'jpg' };
}

function naarBlob(
    doek: HTMLCanvasElement,
    mime: string,
    kwaliteit: number,
): Promise<Blob | null> {
    return new Promise((klaar) => doek.toBlob(klaar, mime, kwaliteit));
}

/** `logo van de zaak.png` wordt `logo van de zaak.webp`. */
function hernoem(naam: string, extensie: string): string {
    const kaal = naam.replace(/\.[^./\\]+$/, '');

    return `${kaal === '' ? 'logo' : kaal}.${extensie}`;
}

export async function keurBeeldmerk(bestand: File): Promise<Keuring> {
    const geopend = await open(bestand);

    if (geopend === null) {
        return { soort: 'fout', reden: 'onleesbaar' };
    }

    try {
        const { bron, breedte, hoogte } = geopend;

        const kort = Math.min(breedte, hoogte);
        const lang = Math.max(breedte, hoogte);

        // Te klein is niet op te lossen: verkleinen maakt het erger, en
        // oprekken maakt van een logo een vlek.
        if (kort < LOGO_MIN_ZIJDE || kort === 0) {
            return { soort: 'fout', reden: 'te-klein' };
        }

        if (bestand.size <= LOGO_MAX_BYTES && lang <= LOGO_MAX_ZIJDE) {
            return { soort: 'goed', bestand };
        }

        /*
         * De kortste zijde mag niet ónder de 48 zakken door het
         * verkleinen. Bij een strook van 6000 bij 60 is dat een echte
         * botsing: hem onder de 5000 krijgen kost de korte zijde zijn
         * minimum. Dat is geen logo meer, en daar is geen maat voor die
         * werkt.
         */
        const ondergrens = LOGO_MIN_ZIJDE / kort;

        if (lang * ondergrens > LOGO_MAX_ZIJDE) {
            return { soort: 'fout', reden: 'te-smal' };
        }

        const formaat = kiesFormaat(bestand.type);

        for (const doelmaat of DOELMATEN) {
            const schaal = Math.min(1, Math.max(doelmaat / lang, ondergrens));

            const doek = document.createElement('canvas');

            doek.width = Math.max(LOGO_MIN_ZIJDE, Math.round(breedte * schaal));
            doek.height = Math.max(LOGO_MIN_ZIJDE, Math.round(hoogte * schaal));

            const penseel = doek.getContext('2d');

            if (penseel === null) {
                return { soort: 'fout', reden: 'niet-gelukt' };
            }

            penseel.imageSmoothingQuality = 'high';
            penseel.drawImage(bron, 0, 0, doek.width, doek.height);

            for (const kwaliteit of KWALITEITEN) {
                const blob = await naarBlob(doek, formaat.mime, kwaliteit);

                if (blob === null) {
                    return { soort: 'fout', reden: 'niet-gelukt' };
                }

                if (blob.size <= LOGO_MAX_BYTES) {
                    return {
                        soort: 'verkleind',
                        bestand: new File(
                            [blob],
                            hernoem(bestand.name, formaat.extensie),
                            { type: formaat.mime },
                        ),
                        van: bestand.size,
                        naar: blob.size,
                    };
                }

                // Bij PNG doet de kwaliteit niets, dus verder draaien is
                // drie keer hetzelfde bestand maken. Meteen kleiner dan.
                if (formaat.mime === 'image/png') {
                    break;
                }
            }
        }

        return { soort: 'fout', reden: 'niet-gelukt' };
    } finally {
        geopend.sluit();
    }
}
