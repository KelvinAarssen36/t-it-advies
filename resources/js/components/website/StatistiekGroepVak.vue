<script setup lang="ts">
import {
    ChevronDown,
    ChevronUp,
    Languages,
    Loader2,
    Trash2,
} from '@lucide/vue';
import { computed } from 'vue';
import BrandSelect from '@/components/BrandSelect.vue';
import InputError from '@/components/InputError.vue';
import LocaleFlag from '@/components/LocaleFlag.vue';
import SortableList from '@/components/SortableList.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import type { StatistiekRij, StatistiekVak } from '@/types/statistieken';

/**
 * Eén groep in het indelingsvenster: een vak met een naam en een lijst.
 *
 * **Het vak is de groep.** Ligt een statistiek hierin, dan hoort hij bij
 * deze groep -- er is geen veld dat dat nog eens apart bijhoudt. Dat is
 * precies waarom er een vak is en geen regeltje per item: slepen naar een
 * ander vak is verhuizen, en dat zie je gebeuren.
 *
 * **De naam staat altijd in een invoerveld en nooit in een bewerkmodus.**
 * Je ziet dus meteen dat je hem kunt aanpassen, en een nieuwe groep is
 * hetzelfde vak met een leeg veld. Twee velden, want de klant voert zijn
 * teksten in dit project twee keer in: Nederlands en Engels naast
 * elkaar.
 *
 * Alle cijfers in dit vak krijgen bij het opslaan deze twee namen. Daarmee
 * kan een groep niet meer twee Engelse koppen hebben -- wat eerder kon,
 * omdat elk item zijn eigen groepsnaam bewaarde.
 *
 * **De indeling van de kop is bewust hetzelfde voor allebei de soorten
 * vakken**: links wat het is en hoeveel erin zit, rechts de knoppen, dan
 * een streep en dan de lijst. Het vak van de losse cijfers heeft geen
 * naamvelden en geen knoppen, maar leest verder precies gelijk -- anders
 * lijken het twee verschillende dingen terwijl het er twee van dezelfde
 * zijn.
 *
 * Zie docs/architecture/modules/statistieken.md.
 */
const props = defineProps<{
    vak: StatistiekVak;

    /** Het eerste vak is dat van de losse cijfers: geen naam, blijft staan. */
    naamloos: boolean;

    /** Voor de pijltjes waarmee hele groepen van plek wisselen. */
    eerste: boolean;
    laatste: boolean;

    /** De vakken waar een regel naartoe kan, voor wie niet sleept. */
    keuzes: Array<{ value: string; label: string }>;

    /**
     * Of het vertaalknopje naast het Engelse veld hoort te staan.
     *
     * De aanroeper beslist dat; zie `magVertalen` in
     * StatistiekIndelingDialoog. Kort gezegd: alleen als er een
     * Nederlandse naam staat en het Engels daar niet meer bij kan kloppen.
     */
    kanVertalen: boolean;

    /** Of de vertaaldienst op dit moment met dít vak bezig is. */
    vertaalt: boolean;

    /**
     * Of er érgens een vertaling loopt.
     *
     * Er kan er maar één tegelijk, want het antwoord zegt alleen
     * "groep_en" en niet voor welk vak het is. Dan hoort het knopje van
     * de andere vakken op slot te staan en niet stil niets te doen als
     * je erop drukt.
     */
    vertaalWacht: boolean;

    /** Wat er mis is met de naam van dit vak, als er iets mis is. */
    fout?: string;
}>();

const emit = defineEmits<{
    'update:items': [StatistiekRij[]];
    'update:naam': [string];
    'update:naamEn': [string];
    verhuis: [number, string];
    vertaal: [];
    omhoog: [];
    omlaag: [];
    verwijder: [];
}>();

/**
 * Hoeveel cijfers er in dit vak liggen.
 *
 * Het staat erbij omdat een vak anders alleen te lezen is door te tellen,
 * en dat is juist wat je wil weten bij een groep: is dit er een met één
 * cijfer of met zeven?
 */
const aantal = computed(() =>
    props.vak.items.length === 1
        ? t('1 cijfer')
        : t(':aantal cijfers', { aantal: props.vak.items.length }),
);
</script>

<template>
    <section
        class="brand-groepvak"
        :data-naamloos="props.naamloos ? '' : undefined"
    >
        <header class="brand-groepvak-kop">
            <!--
                De bovenste regel: wat dit vak is, en de knoppen die het
                als geheel verplaatsen of opheffen.
            -->
            <div class="flex items-center justify-between gap-2">
                <p class="flex min-w-0 items-baseline gap-2">
                    <span class="brand-sorteer-groepkop">
                        <template v-if="props.naamloos">
                            {{ $t('Zonder groep') }}
                        </template>
                        <template v-else>
                            {{ $t('Groep') }}
                        </template>
                    </span>
                    <span class="shrink-0 text-xs text-muted-foreground">
                        {{ aantal }}
                    </span>
                </p>

                <!--
                    De pijltjes verplaatsen de hele groep, en niet een
                    regel erin. Daarom staan ze in de kop van het vak en
                    niet bij de regels: wie ze bij een regel zou
                    verwachten, verwacht iets anders dan ze doen.
                -->
                <div v-if="!props.naamloos" class="flex shrink-0 items-center">
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :disabled="props.eerste"
                        :aria-label="$t('Deze groep één plek omhoog')"
                        @click="emit('omhoog')"
                    >
                        <ChevronUp class="size-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :disabled="props.laatste"
                        :aria-label="$t('Deze groep één plek omlaag')"
                        @click="emit('omlaag')"
                    >
                        <ChevronDown class="size-4" />
                    </Button>
                    <Button
                        variant="verwijderen-zacht"
                        size="icon-sm"
                        class="ml-1"
                        :aria-label="$t('Deze groep opheffen')"
                        @click="emit('verwijder')"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </div>

            <!--
                Het vak van de losse cijfers heeft geen naam, want op de
                website staan deze cijfers boven het eerste kopje. Dat
                staat er met zoveel woorden bij: een vak zonder naam
                leest anders als een vak waarvan de naam nog ontbreekt.
            -->
            <p
                v-if="props.naamloos"
                class="text-xs text-pretty text-muted-foreground"
            >
                {{
                    $t(
                        'Deze cijfers staan los bovenaan, boven het eerste kopje.',
                    )
                }}
            </p>

            <div v-else class="grid gap-2 sm:grid-cols-2">
                <div class="space-y-1">
                    <Label
                        :for="`groep-nl-${props.vak.id}`"
                        verplicht
                        class="text-xs"
                    >
                        {{ $t('Naam van de groep') }}
                    </Label>
                    <Input
                        :id="`groep-nl-${props.vak.id}`"
                        :model-value="props.vak.naam ?? ''"
                        :maxlength="60"
                        :placeholder="$t('Bijvoorbeeld: Netwerk')"
                        @update:model-value="
                            emit('update:naam', String($event))
                        "
                    />
                </div>

                <div class="space-y-1">
                    <!--
                        Het vertaalknopje staat náást het label en niet
                        erin: een knop binnen een `<label for>` geeft bij
                        een klik ook de focus aan het veld, en dan
                        verspringt er iets zonder dat je daarom vroeg.
                    -->
                    <div class="flex items-center gap-1.5">
                        <Label
                            :for="`groep-en-${props.vak.id}`"
                            class="flex items-center gap-1.5 text-xs"
                        >
                            <LocaleFlag locale="en" size="sm" />
                            {{ $t('In het Engels') }}
                        </Label>

                        <button
                            v-if="props.kanVertalen"
                            type="button"
                            class="brand-vertaalknopje"
                            :disabled="props.vertaalt || props.vertaalWacht"
                            :aria-label="$t('Vertaal de naam van deze groep')"
                            :title="$t('Vertaal de naam van deze groep')"
                            @click="emit('vertaal')"
                        >
                            <Loader2
                                v-if="props.vertaalt"
                                class="size-3 animate-spin"
                            />
                            <Languages v-else class="size-3" />
                        </button>
                    </div>

                    <Input
                        :id="`groep-en-${props.vak.id}`"
                        :model-value="props.vak.naamEn ?? ''"
                        :maxlength="60"
                        :placeholder="$t('Bijvoorbeeld: Network')"
                        @update:model-value="
                            emit('update:naamEn', String($event))
                        "
                    />
                </div>
            </div>

            <InputError :message="props.fout" />
        </header>

        <SortableList
            :model-value="props.vak.items"
            groep="statistieken"
            :label="$t('De cijfers in deze groep')"
            @update:model-value="emit('update:items', $event)"
        >
            <template #default="{ item }">
                <!--
                    De regel is een kaartje en geen kale rij. Dat is niet
                    opsmuk: het is wat zichtbaar maakt dat je hem kunt
                    oppakken, en waar het oplichten tijdens het slepen op
                    aangrijpt.
                -->
                <div class="brand-sorteer-kaart">
                    <span class="brand-statistiek-soort">
                        {{ item.display_label }}
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium">
                            {{ item.label_nl }}
                        </span>
                        <span
                            v-if="!item.published"
                            class="block truncate text-xs text-muted-foreground"
                        >
                            {{ $t('Staat niet op je website') }}
                        </span>
                    </span>

                    <!--
                        Dezelfde verhuizing als slepen, maar zonder muis.
                        Slepen tussen twee vakken werkt niet met een
                        toetsenbord en de pijltjes blijven binnen hun
                        eigen vak, dus zonder deze lijst kan wie niet
                        sleept helemaal geen groep wisselen.

                        `@click.stop` omdat de regel een sleepgreep
                        heeft: zonder dit begint een klik op de lijst
                        een sleepbeweging.
                    -->
                    <span class="w-32 shrink-0" @click.stop>
                        <BrandSelect
                            :model-value="props.vak.id"
                            :options="props.keuzes"
                            :aria-label="$t('Groep')"
                            @update:model-value="
                                emit('verhuis', item.id, String($event))
                            "
                        />
                    </span>
                </div>
            </template>
        </SortableList>

        <!--
            Een leeg vak zegt wat je ermee moet. Zonder die regel is het
            een rechthoek zonder betekenis, en dat is precies het vak
            waar iemand als eerste iets in wil leggen.
        -->
        <p
            v-if="props.vak.items.length === 0"
            class="text-xs text-pretty text-muted-foreground"
        >
            <template v-if="props.naamloos">
                {{
                    $t(
                        'Sleep hier een cijfer naartoe om het uit zijn groep te halen.',
                    )
                }}
            </template>
            <template v-else>
                {{
                    $t(
                        'Sleep hier een cijfer naartoe. Een groep zonder cijfers verdwijnt zodra je opslaat.',
                    )
                }}
            </template>
        </p>
    </section>
</template>
