<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Globe, Lock, Pencil } from '@lucide/vue';
import { computed, ref } from 'vue';
import SortableList from '@/components/SortableList.vue';
import VerlaatPortaal from '@/components/VerlaatPortaal.vue';
import SectionRow from '@/components/website/SectionRow.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { bevestigBewerken } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import site from '@/routes/site';
import website from '@/routes/website';
import type { SectieRij } from '@/types/secties';

/**
 * De indeling van de website: alle onderdelen op volgorde.
 *
 * Dit is het startpunt van het Website-gedeelte. Van hieruit gaat de
 * eigenaar naar het scherm waar hij de inhoud van een onderdeel bijhoudt,
 * en hier ziet hij in één oogopslag wat er wél en niet op zijn site staat.
 *
 * **Het overzicht staat in een venster met een adresbalk.** Dat is geen
 * versiering: het zegt zonder woorden dat je naar een wébsite kijkt en niet
 * naar een instellingenlijst. Samen met de schetsjes per onderdeel zie je de
 * vorm van je pagina van boven naar beneden.
 *
 * **Bewerken gebeurt in een eigen venster.** De pagina zelf verandert dus
 * niet onder je handen. Dat is het verschil tussen "ik kijk" en "ik ben aan
 * het wijzigen", en dat verschil hoort zichtbaar te zijn als alles wat je
 * doet direct live staat.
 *
 * Zie docs/architecture/pagina-indeling.md.
 */

const props = defineProps<{ sections: SectieRij[]; host: string }>();

/*
 * De kruimel staat er in gewoon Nederlands; Breadcrumbs.vue vertaalt hem.
 * Hier `$t()` gebruiken zou niet werken: defineOptions draait bij het laden
 * van de module, en dan zijn de vertalingen er nog niet.
 */
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Website', href: website.index() }],
    },
});

const kop = computed(() => props.sections.find((rij) => rij.fixed) ?? null);

const voet = computed(
    () => [...props.sections].reverse().find((rij) => rij.fixed) ?? null,
);

/** Wat de klant mag verslepen: alles tussen de kop en de voettekst. */
const verplaatsbaar = computed(() =>
    props.sections.filter((rij) => !rij.fixed),
);

const opDeWebsite = computed(
    () => verplaatsbaar.value.filter((rij) => rij.live).length,
);

const nogLeeg = computed(
    () =>
        verplaatsbaar.value.filter((rij) => rij.visible && !rij.filled).length,
);

const bewerken = ref(false);
const bezig = ref(false);

/**
 * De werkkopie.
 *
 * Elke regel wordt gekopieerd en niet doorgegeven: annuleren moet echt
 * annuleren, ook nadat er een schuifje om is. Zou hier de originele lijst
 * staan, dan zou een omgezet schuifje al zichtbaar zijn in het overzicht
 * erachter voordat er iets is opgeslagen -- en dan laat het scherm iets zien
 * dat de website niet doet.
 */
const concept = ref<SectieRij[]>([]);

const beginBewerken = (): void => {
    concept.value = verplaatsbaar.value.map((rij) => ({ ...rij }));
    bewerken.value = true;
};

/** Wat er straks naar de server gaat, in deze volgorde. */
const alsPayload = (lijst: SectieRij[]) =>
    lijst.map((rij) => ({ key: rij.key, visible: rij.visible }));

const veranderd = computed(
    () =>
        JSON.stringify(alsPayload(concept.value)) !==
        JSON.stringify(alsPayload(verplaatsbaar.value)),
);

/**
 * Even wachten tot een venster is weggeanimeerd voordat het volgende komt.
 *
 * Twee vensters die elkaar overlappen zien er niet alleen rommelig uit: de
 * dialooglaag zet tijdens het openen de rest van de pagina op
 * `pointer-events: none`, en twee die tegelijk openen en sluiten kunnen dat
 * op elkaar achterlaten. Dan is de pagina daarna niet meer aanklikbaar.
 */
const adem = (): Promise<void> =>
    new Promise((klaar) => setTimeout(klaar, 200));

const opslaan = async (): Promise<void> => {
    // Niets veranderd is geen opslag waard: geen vraag, geen verzoek, geen
    // melding. De server weigert hem ook, maar dan heeft de eigenaar al
    // twee keer "ja" gezegd tegen niets.
    if (!veranderd.value) {
        bewerken.value = false;

        return;
    }

    /*
     * Het bewerkvenster gaat dicht vóór de bevestiging, en niet eronder
     * liggen. Zegt de eigenaar "nee", dan komt het gewoon weer open met
     * alles er nog in -- `concept` staat op deze pagina en niet in het
     * venster, dus het overleeft het dichtgaan.
     */
    bewerken.value = false;
    await adem();

    const akkoord = await bevestigBewerken({
        titel: t('De indeling van je website aanpassen?'),
        tekst: t(
            'De onderdelen komen in deze volgorde te staan, en wat je hebt uitgezet verdwijnt van de site.',
        ),
    });

    if (!akkoord) {
        await adem();
        bewerken.value = true;

        return;
    }

    bezig.value = true;

    router.put(
        website.update().url,
        { sections: alsPayload(concept.value) },
        {
            preserveScroll: true,
            onFinish: () => {
                bezig.value = false;
            },
        },
    );
};
</script>

<template>
    <Head :title="$t('Indeling')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <header class="max-w-xl space-y-1">
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ $t('Indeling') }}
                </h1>
                <p class="text-sm text-pretty text-muted-foreground">
                    {{
                        $t(
                            'De onderdelen van je website, van boven naar beneden. Hiervandaan pas je ook de inhoud van een onderdeel aan.',
                        )
                    }}
                </p>
            </header>

            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" as-child class="gap-2">
                    <Link :href="site.enter()">
                        <Globe class="size-4" />
                        {{ $t('Bekijk het resultaat') }}
                        <VerlaatPortaal />
                    </Link>
                </Button>

                <Button variant="bewerken" class="gap-2" @click="beginBewerken">
                    <Pencil class="size-4" />
                    {{ $t('Bewerk volgorde') }}
                </Button>
            </div>
        </div>

        <!--
            Het venster met de adresbalk. De regels erin staan tegen elkaar
            aan en niet als losse kaarten: zo lezen ze als één pagina van
            boven naar beneden, wat ze op de website ook zijn.
        -->
        <div class="brand-venster">
            <div class="brand-venster-balk">
                <span class="brand-venster-stip" aria-hidden="true" />
                <span class="brand-venster-stip" aria-hidden="true" />
                <span class="brand-venster-stip" aria-hidden="true" />
                <span class="brand-venster-adres">{{ props.host }}</span>
            </div>

            <div class="brand-venster-inhoud">
                <SectionRow v-if="kop" :rij="kop" />

                <SectionRow
                    v-for="rij in verplaatsbaar"
                    :key="rij.key"
                    :rij="rij"
                />

                <SectionRow v-if="voet" :rij="voet" />
            </div>

            <p class="brand-venster-voet">
                <span>
                    {{
                        $t(
                            ':aantal van de :totaal onderdelen staan op je website.',
                            {
                                aantal: opDeWebsite,
                                totaal: verplaatsbaar.length,
                            },
                        )
                    }}
                </span>
                <!--
                    Enkelvoud en meervoud als twee losse zinnen. De
                    vertaalhulp kent geen meervoudsvormen, en ":aantal
                    onderdeel(en)" is geen Nederlands.
                -->
                <span v-if="nogLeeg === 1" class="brand-venster-voet-let-op">
                    {{ $t('Eén ervan staat aan maar is nog leeg.') }}
                </span>
                <span v-else-if="nogLeeg > 1" class="brand-venster-voet-let-op">
                    {{
                        $t(':aantal ervan staan aan maar zijn nog leeg.', {
                            aantal: nogLeeg,
                        })
                    }}
                </span>
            </p>
        </div>
    </div>

    <Dialog v-model:open="bewerken">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{ $t('Volgorde en zichtbaarheid') }}</DialogTitle>
                <DialogDescription>
                    {{
                        $t(
                            'Sleep aan de greep of gebruik de pijltjes. Met het schuifje zet je een onderdeel aan of uit. Er wordt pas iets opgeslagen als je op Opslaan klikt.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="brand-bewerk-lijst brand-scrollbar">
                <!--
                    De kop en de voettekst staan buiten de sorteerlijst. Dat
                    is geen presentatietruc: ze zitten er ook echt niet in,
                    zodat er geen sleepbeweging bestaat die ze zou kunnen
                    raken. Ze springen precies zoveel in als de greep en de
                    pijltjes breed zijn, zodat alle kaders op één lijn staan.
                -->
                <div v-if="kop" class="brand-sorteer-rij">
                    <span class="brand-sorteer-greep-vast" aria-hidden="true">
                        <Lock class="size-3.5" />
                    </span>
                    <SectionRow
                        :rij="kop"
                        compact
                        kaart
                        class="min-w-0 flex-1"
                    />
                    <span class="brand-sorteer-ruimte" aria-hidden="true" />
                </div>

                <SortableList
                    v-model="concept"
                    :label="$t('De volgorde van de onderdelen')"
                >
                    <template #default="{ item }">
                        <SectionRow
                            :rij="item"
                            bewerken
                            compact
                            kaart
                            @update:zichtbaar="item.visible = $event"
                        />
                    </template>
                </SortableList>

                <div v-if="voet" class="brand-sorteer-rij">
                    <span class="brand-sorteer-greep-vast" aria-hidden="true">
                        <Lock class="size-3.5" />
                    </span>
                    <SectionRow
                        :rij="voet"
                        compact
                        kaart
                        class="min-w-0 flex-1"
                    />
                    <span class="brand-sorteer-ruimte" aria-hidden="true" />
                </div>
            </div>

            <DialogFooter class="gap-2 sm:gap-2">
                <Button
                    variant="ghost"
                    :disabled="bezig"
                    @click="bewerken = false"
                >
                    {{ $t('Annuleren') }}
                </Button>
                <Button variant="bewerken" :disabled="bezig" @click="opslaan">
                    {{ $t('Opslaan') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
