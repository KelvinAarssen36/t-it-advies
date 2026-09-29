<script setup lang="ts">
import {
    ImagePlus,
    Loader2,
    Maximize2,
    RotateCcw,
    Sparkles,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import { SliderRange, SliderRoot, SliderThumb, SliderTrack } from 'reka-ui';
import { computed, onBeforeUnmount, ref, useTemplateRef, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { keurBeeldmerk } from '@/lib/beeldmerk';
import { t } from '@/lib/i18n';

/**
 * Een beeldmerk kiezen en zelf in het ronde vakje zetten.
 *
 * **Waarom de klant het zelf bijsnijdt.** Wat er binnenkomt is niet te
 * voorspellen: een liggend logo met de bedrijfsnaam ernaast, een vierkant
 * beeldmerk met veel lucht eromheen, of gewoon een foto. Elke
 * automatische regel gaat bij een van die drie mis -- bijsnijden knipt de
 * naam eraf, passend maken laat een foto met witranden staan. Wie het
 * beeld voor zich ziet, kiest in twee seconden wat wij niet kunnen raden.
 *
 * **Het voorbeeld hier is de waarheid.** De server rekent met precies
 * dezelfde verhoudingen: bij zoom 1 past de langste zijde in het vierkant,
 * en `x` en `y` verschuiven in halve vierkanten. Lopen die twee uiteen,
 * dan krijgt de klant iets anders te zien dan hij instelde -- en dat merkt
 * hij pas op zijn website. Zie App\Support\Media\Logo::verklein.
 *
 * **Een te groot bestand wordt hier verkleind en niet afgekeurd.** De
 * server accepteert hoogstens twee megabyte, maar dat hoorde de klant pas
 * nadat hij de hele foto had geüpload en op Opslaan had gedrukt -- op een
 * telefoon een minuut wachten op een nee. En het was een nee die nergens
 * voor nodig was: wat er bewaard wordt is een vierkantje van 256 bij 256.
 * Zie lib/beeldmerk.ts.
 *
 * **De witte ondergrond wordt in het bestand gebakken.** Dat scheelt de
 * website een uitzondering: elk opgeslagen beeldmerk is daarna een gewoon
 * vierkant plaatje dat overal hetzelfde getoond kan worden. Zonder die
 * keuze zou een donker logo met doorzichtige achtergrond op de donkere
 * site verdwijnen, en zou elk scherm dat apart moeten opvangen.
 *
 * Zie docs/architecture/formulieren-en-schuifbalken.md.
 */
const props = defineProps<{
    /** Het logo dat er al staat, als adres. Null betekent: nog geen. */
    bestaand: string | null;
}>();

/** Het gekozen bestand; null betekent dat er niets nieuws bij is gekomen. */
const bestand = defineModel<File | null>('bestand', { required: true });

/** Expliciet weggooien van het logo dat er al stond. */
const weghalen = defineModel<boolean>('weghalen', { required: true });

const zoom = defineModel<number>('zoom', { required: true });
const x = defineModel<number>('x', { required: true });
const y = defineModel<number>('y', { required: true });
const plaat = defineModel<boolean>('plaat', { required: true });

/* --- Het gekozen bestand --------------------------------------------- */

/**
 * Het voorbeeld van het gekozen bestand.
 *
 * Dit is een `blob:`-adres dat de browser bijhoudt tot je het teruggeeft.
 * Vergeet je dat, dan blijft het bestand in het geheugen staan zolang het
 * tabblad open is -- en bij een formulier waarin je een paar plaatjes
 * achter elkaar uitprobeert, loopt dat op.
 */
const blobAdres = ref<string | null>(null);

/** De echte afmetingen van het gekozen beeld, nodig voor de rekensom. */
const breedte = ref(0);
const hoogte = ref(0);

const geefTerug = (): void => {
    if (blobAdres.value !== null) {
        URL.revokeObjectURL(blobAdres.value);
        blobAdres.value = null;
    }
};

onBeforeUnmount(geefTerug);

const veld = useTemplateRef<HTMLInputElement>('veld');

/** Waarom dit bestand niet kan. Null zolang er niets mis is. */
const fout = ref<string | null>(null);

/** Wat we voor de klant hebben opgelost. Geen fout, wel het vermelden waard. */
const melding = ref<string | null>(null);

/** Het openen en verkleinen duurt bij een grote foto even. */
const bezig = ref(false);

/** 1258291 wordt "1,2 MB". */
const inMb = (bytes: number): string =>
    `${new Intl.NumberFormat(undefined, { maximumFractionDigits: 1 }).format(
        bytes / (1024 * 1024),
    )} MB`;

const kies = async (gebeurtenis: Event): Promise<void> => {
    const invoer = gebeurtenis.target as HTMLInputElement;
    const gekozen = invoer.files?.[0] ?? null;

    if (gekozen === null) {
        return;
    }

    fout.value = null;
    melding.value = null;
    bezig.value = true;

    try {
        const keuring = await keurBeeldmerk(gekozen);

        if (keuring.soort === 'fout') {
            /*
             * Het veld leegmaken, anders kan de klant hetzelfde bestand
             * niet nog eens kiezen: de browser stuurt geen `change` als de
             * waarde niet verandert, en dan lijkt de knop stuk.
             */
            invoer.value = '';

            fout.value = {
                onleesbaar: t(
                    'Dit bestand kunnen we niet als afbeelding openen. Kies een JPG, PNG of WebP.',
                ),
                'te-klein': t(
                    'Dit beeld is te klein. Gebruik er een van minstens 48 bij 48 pixels; kleiner wordt op je website een vlek.',
                ),
                'te-smal': t(
                    'Dit beeld is te langgerekt voor het vierkante vakje op je website. Snijd het eerst zelf bij tot iets wat dichter bij een vierkant komt.',
                ),
                'niet-gelukt': t(
                    'Het is niet gelukt om dit beeld kleiner te maken. Verklein het zelf tot onder de 1,5 MB en probeer het opnieuw.',
                ),
            }[keuring.reden];

            return;
        }

        geefTerug();

        bestand.value = keuring.bestand;
        weghalen.value = false;
        blobAdres.value = URL.createObjectURL(keuring.bestand);

        herstel();

        if (keuring.soort === 'verkleind') {
            melding.value = t(
                'Dit beeld was groot, dus we hebben het verkleind van :van naar :naar. Op je website is er niets van te zien.',
                { van: inMb(keuring.van), naar: inMb(keuring.naar) },
            );
        }
    } finally {
        bezig.value = false;
    }
};

const gemeten = (gebeurtenis: Event): void => {
    const beeld = gebeurtenis.target as HTMLImageElement;

    breedte.value = beeld.naturalWidth;
    hoogte.value = beeld.naturalHeight;
};

const verwijder = (): void => {
    geefTerug();

    fout.value = null;
    melding.value = null;
    bestand.value = null;
    // Alleen iets weg te gooien als er al iets stond. Bij een nieuwe
    // ervaring is dit gewoon "toch maar niet".
    weghalen.value = props.bestaand !== null;
    breedte.value = 0;
    hoogte.value = 0;

    if (veld.value !== null) {
        veld.value.value = '';
    }
};

/* --- Bijsnijden ------------------------------------------------------ */

/** De maat van het voorbeeld in beeldpunten; alleen voor de rekensom. */
const VAK = 176;

const herstel = (): void => {
    zoom.value = 1;
    x.value = 0;
    y.value = 0;
};

/**
 * De zoom waarbij het beeld het vakje precies vult.
 *
 * Bij zoom 1 past de lángste zijde; om te vullen moet de kórtste zijde
 * passen, en dat scheelt precies die verhouding.
 */
const vullendeZoom = computed(() => {
    if (breedte.value === 0 || hoogte.value === 0) {
        return 1;
    }

    return Math.min(
        5,
        Math.max(breedte.value, hoogte.value) /
            Math.min(breedte.value, hoogte.value),
    );
});

const vullend = (): void => {
    zoom.value = Number(vullendeZoom.value.toFixed(3));
    x.value = 0;
    y.value = 0;
};

/** Hoe het beeld in het voorbeeld staat, in beeldpunten. */
const stand = computed(() => {
    if (breedte.value === 0 || hoogte.value === 0) {
        return null;
    }

    const schaal = (VAK / Math.max(breedte.value, hoogte.value)) * zoom.value;

    const getekendeBreedte = breedte.value * schaal;
    const getekendeHoogte = hoogte.value * schaal;

    return {
        width: `${getekendeBreedte}px`,
        height: `${getekendeHoogte}px`,
        left: `${(VAK - getekendeBreedte) / 2 + (x.value * VAK) / 2}px`,
        top: `${(VAK - getekendeHoogte) / 2 + (y.value * VAK) / 2}px`,
    };
});

/* --- Slepen ---------------------------------------------------------- */

let sleept = false;
let vanafX = 0;
let vanafY = 0;

const begin = (gebeurtenis: PointerEvent): void => {
    if (blobAdres.value === null) {
        return;
    }

    sleept = true;
    vanafX = gebeurtenis.clientX;
    vanafY = gebeurtenis.clientY;

    // De aanwijzer vastzetten, anders raak je het beeld kwijt zodra je
    // buiten het vakje komt -- en blijft het slepen halverwege hangen.
    (gebeurtenis.target as HTMLElement).setPointerCapture(
        gebeurtenis.pointerId,
    );
};

const beweeg = (gebeurtenis: PointerEvent): void => {
    if (!sleept) {
        return;
    }

    // Twee beeldpunten verschuiving is één eenheid op de halve zijde.
    x.value = begrens(x.value + ((gebeurtenis.clientX - vanafX) * 2) / VAK);
    y.value = begrens(y.value + ((gebeurtenis.clientY - vanafY) * 2) / VAK);

    vanafX = gebeurtenis.clientX;
    vanafY = gebeurtenis.clientY;
};

const stop = (): void => {
    sleept = false;
};

const begrens = (waarde: number): number =>
    Math.max(-1, Math.min(1, Number(waarde.toFixed(4))));

/* --- De schuif ------------------------------------------------------- */

const zoomSchuif = computed<number[]>({
    get: () => [zoom.value * 100],
    set: ([waarde]) => {
        zoom.value = Number(((waarde ?? 100) / 100).toFixed(3));
    },
});

/** Er valt alleen iets bij te snijden als er een nieuw bestand ligt. */
const bewerkbaar = computed(() => blobAdres.value !== null);

/** Wat er in het rondje staat als er niets bij te snijden valt. */
const stilstaand = computed(() =>
    weghalen.value ? null : (props.bestaand ?? null),
);

/**
 * Zet de aanroeper het bestand op null, dan gaat het voorbeeld ook weg.
 *
 * Dat gebeurt bij elke keer dat het formulier opnieuw wordt gevuld -- je
 * bewerkt een andere ervaring. **Zonder deze regel bleef het `blob:`-adres
 * van het vorige bestand staan**, en zag je bij de volgende ervaring nog
 * het plaatje van de vorige in het rondje. Erger nog: dat adres werd nooit
 * teruggegeven, dus het bestand bleef in het geheugen staan.
 */
watch(bestand, (nieuw) => {
    if (nieuw !== null) {
        return;
    }

    geefTerug();

    breedte.value = 0;
    hoogte.value = 0;

    if (veld.value !== null) {
        veld.value.value = '';
    }
});
</script>

<template>
    <div class="brand-logo-kiezer">
        <!--
            Links het ronde voorbeeld. Ligt er een nieuw bestand, dan kun
            je erin slepen; anders staat er wat er al was, of niets.
        -->
        <div class="brand-uitsnede">
            <div
                class="brand-uitsnede-vak"
                :data-plaat="plaat ? '' : undefined"
                :data-sleepbaar="bewerkbaar ? '' : undefined"
                @pointerdown="begin"
                @pointermove="beweeg"
                @pointerup="stop"
                @pointercancel="stop"
            >
                <img
                    v-if="blobAdres"
                    :src="blobAdres"
                    alt=""
                    draggable="false"
                    :style="stand ?? undefined"
                    @load="gemeten"
                />
                <img v-else-if="stilstaand" :src="stilstaand" alt="" />
                <span v-else class="brand-uitsnede-leeg">
                    {{ $t('Nog geen beeldmerk') }}
                </span>
            </div>

            <p v-if="bewerkbaar" class="brand-uitsnede-hint">
                {{ $t('Sleep om te verschuiven') }}
            </p>
        </div>

        <div class="flex min-w-0 flex-1 flex-col gap-3">
            <div class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="gap-2"
                    :disabled="bezig"
                    @click="veld?.click()"
                >
                    <Loader2 v-if="bezig" class="size-4 animate-spin" />
                    <ImagePlus v-else class="size-4" />
                    {{
                        bezig
                            ? $t('Bezig met klaarmaken')
                            : blobAdres || stilstaand
                              ? $t('Ander beeld')
                              : $t('Beeld uploaden')
                    }}
                </Button>

                <Button
                    v-if="blobAdres || stilstaand"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="gap-2"
                    :disabled="bezig"
                    @click="verwijder"
                >
                    <Trash2 class="size-4" />
                    {{ $t('Weghalen') }}
                </Button>
            </div>

            <!--
                Wat er met het gekozen bestand aan de hand is. Dit staat
                hier en niet bij de foutmelding van de server onderaan het
                veld: het gaat over de keuze die je zojuist maakte, en dan
                hoort het antwoord naast de knop te staan waarop je drukte.

                `role="status"` en niet `role="alert"` bij de melding: het
                is goed nieuws, en het hoeft niemand te onderbreken.
            -->
            <p v-if="fout" class="brand-beeld-fout" role="alert">
                <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                <span>{{ fout }}</span>
            </p>

            <p v-else-if="melding" class="brand-beeld-melding" role="status">
                <Sparkles class="mt-0.5 size-4 shrink-0" />
                <span>{{ melding }}</span>
            </p>

            <!--
                Het echte bestandsveld blijft verborgen: de knop erboven
                staat wél in de huisstijl. Niet met `display: none` -- dan
                kun je er met het toetsenbord niet meer bij.
            -->
            <input
                ref="veld"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="sr-only"
                @change="kies"
            />

            <template v-if="bewerkbaar">
                <div class="grid gap-1.5">
                    <div
                        class="flex items-center justify-between text-xs text-muted-foreground"
                    >
                        <span>{{ $t('Inzoomen') }}</span>
                        <span class="tabular-nums">
                            {{ Math.round(zoom * 100) }}%
                        </span>
                    </div>

                    <SliderRoot
                        v-model="zoomSchuif"
                        :min="100"
                        :max="500"
                        :step="1"
                        class="brand-schuif"
                        :aria-label="$t('Inzoomen')"
                    >
                        <SliderTrack class="brand-schuif-spoor">
                            <SliderRange class="brand-schuif-bereik" />
                        </SliderTrack>
                        <SliderThumb class="brand-schuif-greep" />
                    </SliderRoot>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="gap-2"
                        @click="vullend"
                    >
                        <Maximize2 class="size-4" />
                        {{ $t('Vullend maken') }}
                    </Button>

                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="gap-2"
                        @click="herstel"
                    >
                        <RotateCcw class="size-4" />
                        {{ $t('Hele beeld') }}
                    </Button>
                </div>

                <!--
                    De achtergrond wordt in het bestand gebakken. Daardoor
                    hoeft de website later niet te weten of dit een logo
                    met doorzichtige randen was of een gewone foto.
                -->
                <label class="flex items-start gap-2 text-sm">
                    <Switch
                        v-model="plaat"
                        :aria-label="$t('Witte ondergrond')"
                    />
                    <span class="min-w-0">
                        <span class="block">{{ $t('Witte ondergrond') }}</span>
                        <span
                            class="mt-0.5 block text-xs text-pretty text-muted-foreground"
                        >
                            {{
                                $t(
                                    'Aanzetten bij een logo met doorzichtige randen; anders valt het weg op de donkere website.',
                                )
                            }}
                        </span>
                    </span>
                </label>
            </template>

            <p class="text-xs text-pretty text-muted-foreground">
                {{
                    $t(
                        'JPG, PNG of WebP. Is je beeld te groot, dan verkleinen we het zelf. Wat je hier instelt is precies wat er op je website komt te staan.',
                    )
                }}
            </p>
        </div>
    </div>
</template>
