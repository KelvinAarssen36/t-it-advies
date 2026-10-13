<script setup lang="ts">
import { PencilLine, Plus, ShieldAlert, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Switch } from '@/components/ui/switch';
import {
    aantalStappen,
    bevestigAnnuleer,
    bevestigStaat,
    bevestigVerder,
} from '@/lib/bevestiging';

/**
 * Het venster dat bij `bevestig()` uit lib/bevestiging.ts hoort.
 *
 * Hij hangt één keer in de layout van het portaal en staat er verder
 * altijd: welk scherm ook iets vraagt, het venster is er al. Alle teksten
 * staan hier, want dit is de plek waar `$t()` bestaat.
 *
 * **De tweede stap bij bewerken is geen dubbele vraag om het dubbele.** Wat
 * de eigenaar hier aanpast staat meteen op de website die zijn klanten
 * bezoeken; er zit geen concept of publicatieknop tussen. De tweede vraag
 * zegt dat ook letterlijk, en juist dáárom staat die tekst vast en niet bij
 * de aanroeper -- anders verwatert hij tot "weet je het zeker?" en vraag je
 * twee keer hetzelfde.
 *
 * Aanmaken en verwijderen vragen één keer. Verwijderen is onomkeerbaar maar
 * ook onmiskenbaar: je klikt op een prullenbak. Een tweede vraag maakt daar
 * een reflex van, en een reflex leest niemand meer.
 *
 * De animaties zijn pure CSS, om dezelfde reden als in WelcomeDialog: GSAP
 * zit in de chunk van de publieke site.
 *
 * Zie docs/architecture/meldingen.md.
 */

const vraag = computed(() => bevestigStaat.vraag);

const open = computed({
    get: () => vraag.value !== null,
    set: (waarde: boolean) => {
        // Alleen sluiten kan van buitenaf: Escape, of naast het venster
        // klikken. Dat telt als annuleren.
        if (!waarde) {
            bevestigAnnuleer();
        }
    },
});

/** Bij bewerken is de tweede stap de zwaarste, en die kleurt dus anders. */
const laatsteStap = computed(
    () =>
        vraag.value !== null &&
        bevestigStaat.stap === aantalStappen(vraag.value.soort),
);

const tweedeStapVanBewerken = computed(
    () => vraag.value?.soort === 'bewerken' && bevestigStaat.stap === 2,
);

const pictogram = computed(() => {
    if (tweedeStapVanBewerken.value) {
        return ShieldAlert;
    }

    return {
        aanmaken: Plus,
        bewerken: PencilLine,
        verwijderen: Trash2,
    }[vraag.value?.soort ?? 'bewerken'];
});

/**
 * De accentkleur is die van de handeling zelf: blauw voor aanmaken, oker
 * voor bewerken, rood voor verwijderen. Precies dezelfde drie kleuren als
 * de knop waarop je zojuist klikte, zodat het venster zichtbaar bij die
 * knop hoort.
 *
 * Hier stond eerder groen bij aanmaken, geleend van de melding achteraf.
 * Dat was een andere vraag: groen zegt "gelukt", en dat weet je op dit
 * moment nog niet.
 */
const accent = computed(
    () =>
        ({
            aanmaken: 'var(--primary)',
            bewerken: 'var(--bewerken)',
            verwijderen: 'var(--destructive)',
        })[vraag.value?.soort ?? 'bewerken'],
);

/**
 * De bevestigknop draagt de volle vorm van zijn eigen handeling. Dit is de
 * knop die het écht doet, dus hier geen zachte variant.
 */
const knopVariant = computed(
    () =>
        ({
            aanmaken: 'aanmaken',
            bewerken: 'bewerken',
            verwijderen: 'verwijderen',
        })[vraag.value?.soort ?? 'bewerken'] as
            | 'aanmaken'
            | 'bewerken'
            | 'verwijderen',
);

/**
 * Een sleutel die verandert zodra er iets anders in het venster hoort te
 * staan. Vue hangt daar de overgang aan: zonder deze sleutel wisselt de
 * tekst van stap 1 naar stap 2 zonder dat je ziet dat er iets veranderd is,
 * en dat is precies het moment waarop je juist wél moet opletten.
 */
const stapSleutel = computed(
    () => `${vraag.value?.soort}-${bevestigStaat.stap}`,
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="brand-confirm overflow-hidden sm:max-w-md"
            :show-close-button="false"
            :style="{ '--confirm-accent': accent }"
        >
            <Transition name="brand-confirm-stap" mode="out-in">
                <div :key="stapSleutel" class="flex gap-4">
                    <div
                        class="brand-confirm-badge flex size-11 shrink-0 items-center justify-center rounded-full"
                        aria-hidden="true"
                    >
                        <component :is="pictogram" class="size-5" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <!--
                            De kop is de vraag zelf. Hij staat als <h2> in de
                            DOM zodat een schermlezer hem voorleest zodra het
                            venster opent; reka-ui koppelt hem via aria.
                        -->
                        <h2 class="text-base font-semibold text-balance">
                            {{
                                tweedeStapVanBewerken
                                    ? $t('Weet je het 100% zeker?')
                                    : vraag?.titel
                            }}
                        </h2>

                        <p
                            v-if="tweedeStapVanBewerken"
                            class="mt-1.5 text-sm text-pretty text-muted-foreground"
                        >
                            {{ $t('Dit staat direct live op de website.') }}
                        </p>
                        <p
                            v-else-if="vraag?.tekst"
                            class="mt-1.5 text-sm text-pretty text-muted-foreground"
                        >
                            {{ vraag.tekst }}
                        </p>

                        <!--
                            Een beslissing die bij de handeling hoort en niet
                            in het formulier ervoor: "wil je hem ook meteen
                            online zetten?". Alleen op de eerste stap -- de
                            tweede vraagt om te bevestigen wat je hier hebt
                            gekozen, en dan hoort de keuze vast te staan.
                        -->
                        <label
                            v-if="vraag?.keuze && bevestigStaat.stap === 1"
                            class="brand-confirm-keuze mt-4"
                        >
                            <Switch
                                v-model="vraag.keuze.model.value"
                                :aria-label="vraag.keuze.label"
                            />
                            <span class="min-w-0">
                                <span class="block text-sm font-medium">
                                    {{ vraag.keuze.label }}
                                </span>
                                <span
                                    v-if="vraag.keuze.tekst"
                                    class="mt-0.5 block text-xs text-pretty text-muted-foreground"
                                >
                                    {{ vraag.keuze.tekst }}
                                </span>
                            </span>
                        </label>

                        <div
                            class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                        >
                            <Button variant="ghost" @click="bevestigAnnuleer">
                                {{ $t('Annuleren') }}
                            </Button>

                            <Button
                                :variant="knopVariant"
                                autofocus
                                @click="bevestigVerder"
                            >
                                {{
                                    tweedeStapVanBewerken
                                        ? $t('Ja, 100% zeker')
                                        : (vraag?.knop ??
                                          {
                                              aanmaken: $t('Toevoegen'),
                                              bewerken: $t('Ja, aanpassen'),
                                              verwijderen: $t('Verwijderen'),
                                          }[vraag?.soort ?? 'bewerken'])
                                }}
                            </Button>
                        </div>
                    </div>
                </div>
            </Transition>

            <!--
                Twee stipjes bij bewerken, zodat je ziet dat er nog een vraag
                komt en dat de eerste klik dus niets live zet. Bij één stap
                staat er niets: een voortgangsbalk van één stap is ruis.
            -->
            <div
                v-if="vraag && aantalStappen(vraag.soort) > 1"
                class="flex justify-center gap-1.5"
                aria-hidden="true"
            >
                <span
                    v-for="stap in aantalStappen(vraag.soort)"
                    :key="stap"
                    class="brand-confirm-stip"
                    :data-actief="stap <= bevestigStaat.stap ? '' : undefined"
                />
            </div>

            <div
                v-if="laatsteStap"
                class="brand-confirm-gloed"
                aria-hidden="true"
            />
        </DialogContent>
    </Dialog>
</template>
