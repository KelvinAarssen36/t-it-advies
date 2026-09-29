<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    ChevronLeft,
    ExternalLink,
    Languages,
    Pencil,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import { ref } from 'vue';
import ErvaringIcoon from '@/components/site/ErvaringIcoon.vue';
import LocaleFlag from '@/components/LocaleFlag.vue';
import ErvaringDialoog from '@/components/website/ErvaringDialoog.vue';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { bevestigBewerken, bevestigVerwijderen } from '@/lib/bevestiging';
import { t } from '@/lib/i18n';
import website from '@/routes/website';
import ervaringRoutes from '@/routes/website/ervaring';
import type {
    ErvaringBuren,
    ErvaringDetail,
    ErvaringOpties,
} from '@/types/ervaring';

/**
 * Eén ervaring, helemaal uitgeschreven.
 *
 * Dit is de pagina waar de klant controleert of het klopt. Daarom staan de
 * twee talen hier **naast elkaar** en niet onder elkaar: zo zie je in één
 * blik of de Engelse tekst bij de Nederlandse hoort, en waar er nog niets
 * staat. In het overzicht zou die vergelijking niet passen; daar gaat het
 * om terugvinden.
 *
 * Onderaan staan de buren op de tijdlijn. Wie zijn Engels nakijkt loopt de
 * loopbaan van voren naar achteren door, en hoeft daarvoor niet telkens
 * terug naar de lijst.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */

const props = defineProps<{
    item: ErvaringDetail;
    buren: ErvaringBuren;
    opties: ErvaringOpties;
    kanVertalen: boolean;
}>();

/**
 * Het kruimelpad, met de ervaring zelf als laatste stap.
 *
 * **Daarom is dit een functie en geen vaste lijst.** Inertia roept hem
 * aan met de props van de pagina, dus hier kan de naam van de ervaring
 * in. Stond die er niet, dan was "Ervaring" de laatste kruimel -- en die
 * laatste is per definitie de pagina waar je al bent, dus niet
 * aanklikbaar. Je kon vanaf deze pagina dus niet terug naar het
 * overzicht, en dat is precies waar een kruimelpad voor is.
 *
 * De titels zijn Nederlandse zinnen en tegelijk de vertaalsleutel; zie
 * de toelichting in Breadcrumbs.vue. De naam van een ervaring staat
 * uiteraard in geen enkele woordenlijst en komt er ongewijzigd doorheen.
 */
defineOptions({
    layout: (props: { item: ErvaringDetail }) => ({
        breadcrumbs: [
            { title: 'Website', href: website.index() },
            { title: 'Ervaring', href: ervaringRoutes.index() },
            {
                title: props.item.role_nl,
                href: ervaringRoutes.show(props.item.id),
            },
        ],
    }),
});

const venster = ref(false);

const verwijder = async (): Promise<void> => {
    const akkoord = await bevestigVerwijderen({
        titel: t('":naam" verwijderen?', {
            naam: `${props.item.role_nl} bij ${props.item.organisation}`,
        }),
        tekst: t('Dit kan niet ongedaan worden gemaakt.'),
    });

    if (!akkoord) {
        return;
    }

    // De server stuurt hierna naar het overzicht: deze pagina bestaat
    // straks niet meer. Zie ExperienceController::naHetVerwijderen.
    router.delete(ervaringRoutes.destroy(props.item.id).url);
};

const wisselOnline = async (): Promise<void> => {
    const aan = !props.item.published;

    const akkoord = await bevestigBewerken({
        titel: aan
            ? t('":naam" op je website zetten?', { naam: props.item.role_nl })
            : t('":naam" van je website halen?', { naam: props.item.role_nl }),
        tekst: aan
            ? undefined
            : t(
                  'Hij blijft hier gewoon staan; bezoekers zien hem alleen niet meer.',
              ),
    });

    if (!akkoord) {
        return;
    }

    router.patch(
        ervaringRoutes.online(props.item.id).url,
        { published: aan },
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head :title="props.item.role_nl" />

    <div class="flex flex-col gap-6 p-4">
        <Link
            :href="ervaringRoutes.index()"
            class="inline-flex w-fit items-center gap-1.5 text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
        >
            <ChevronLeft class="size-4" />
            {{ $t('Alle ervaringen') }}
        </Link>

        <!-- ------------------------- De kop ------------------------- -->
        <div class="brand-detail-kop">
            <span class="brand-logo-rondje is-groot">
                <img v-if="props.item.logo" :src="props.item.logo" alt="" />
                <ErvaringIcoon v-else :icoon="props.item.icon" class="size-7" />
            </span>

            <div class="min-w-0 flex-1 space-y-2">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                    <h1
                        class="text-xl font-semibold tracking-tight text-balance"
                    >
                        {{ props.item.role_nl }}
                    </h1>

                    <span
                        class="brand-ervaring-merk"
                        :data-nu="props.item.published ? '' : undefined"
                        :data-uit="props.item.published ? undefined : ''"
                    >
                        {{
                            props.item.published
                                ? $t('Staat op je website')
                                : $t('Staat niet op je website')
                        }}
                    </span>

                    <span
                        v-if="props.item.loopt"
                        class="brand-ervaring-merk"
                        data-nu
                    >
                        {{ $t('Loopt nu') }}
                    </span>
                </div>

                <p class="text-sm text-muted-foreground">
                    <!--
                        De naam van de organisatie is alleen een link als er
                        een veilig webadres bij staat. Die controle staat op
                        de server; zie App\Models\Experience::website.
                    -->
                    <a
                        v-if="props.item.website"
                        :href="props.item.website"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 font-medium text-foreground underline-offset-4 hover:underline"
                    >
                        {{ props.item.organisation }}
                        <ExternalLink class="size-3.5" />
                    </a>
                    <span v-else class="font-medium text-foreground">
                        {{ props.item.organisation }}
                    </span>

                    <span class="mx-2">·</span>
                    {{ props.item.periode }}
                    <span class="mx-2">·</span>
                    {{ props.item.duur }}
                </p>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <label class="flex items-center gap-2 text-sm">
                    <Switch
                        :model-value="props.item.published"
                        :aria-label="$t('Op de website zetten')"
                        @update:model-value="wisselOnline"
                    />
                    {{ $t('Online') }}
                </label>

                <Button
                    variant="bewerken"
                    class="gap-2"
                    @click="venster = true"
                >
                    <Pencil class="size-4" />
                    {{ $t('Bewerken') }}
                </Button>

                <Button
                    variant="verwijderen-zacht"
                    size="icon"
                    :aria-label="$t('Verwijderen')"
                    @click="verwijder"
                >
                    <Trash2 class="size-4" />
                </Button>
            </div>
        </div>

        <!-- ---------------------- De twee talen ---------------------- -->
        <div class="grid gap-4 lg:grid-cols-2">
            <section class="brand-detail-vak">
                <h2 class="brand-detail-taal">
                    <LocaleFlag locale="nl" size="md" />
                    {{ $t('Nederlands') }}
                </h2>

                <dl class="brand-detail-lijst">
                    <div>
                        <dt>{{ $t('Functie') }}</dt>
                        <dd>{{ props.item.role_nl }}</dd>
                    </div>
                    <div>
                        <dt>{{ $t('Plaats') }}</dt>
                        <dd v-if="props.item.location_nl">
                            {{ props.item.location_nl }}
                        </dd>
                        <dd v-else class="is-leeg">
                            {{ $t('Niet ingevuld') }}
                        </dd>
                    </div>
                    <div>
                        <dt>{{ $t('Beschrijving') }}</dt>
                        <dd
                            v-if="props.item.description_nl"
                            class="whitespace-pre-line"
                        >
                            {{ props.item.description_nl }}
                        </dd>
                        <dd v-else class="is-leeg">
                            {{ $t('Niet ingevuld') }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="brand-detail-vak">
                <h2 class="brand-detail-taal">
                    <LocaleFlag locale="en" size="md" />
                    {{ $t('Engels') }}

                    <span
                        v-if="!props.item.vertaald"
                        class="brand-ervaring-merk"
                        data-let-op
                    >
                        <TriangleAlert class="size-3" />
                        {{ $t('Nog niet vertaald') }}
                    </span>
                    <!--
                        De datum staat erbij omdat hij het merkje bruikbaar
                        maakt: "automatisch vertaald" zegt weinig, "en dat
                        was in maart" vertelt je of het nog over de tekst
                        gaat die er nu staat.
                    -->
                    <span
                        v-else-if="props.item.automatisch_vertaald"
                        class="brand-ervaring-merk"
                        data-automatisch
                    >
                        <Languages class="size-3" />
                        {{
                            props.item.vertaald_op
                                ? $t('Automatisch vertaald op :datum', {
                                      datum: props.item.vertaald_op,
                                  })
                                : $t('Automatisch vertaald')
                        }}
                    </span>
                </h2>

                <dl class="brand-detail-lijst">
                    <div>
                        <dt>{{ $t('Functie') }}</dt>
                        <dd v-if="props.item.role_en">
                            {{ props.item.role_en }}
                        </dd>
                        <!--
                            De functietitel is het enige veld dat terugvalt
                            op het Nederlands: een kaart zonder functietitel
                            is stuk. Bij de andere twee is leeg gewoon leeg.
                            Zie App\Models\Experience.
                        -->
                        <dd v-else class="is-leeg">
                            {{ $t('Leeg; de Nederlandse titel wordt getoond') }}
                        </dd>
                    </div>
                    <div>
                        <dt>{{ $t('Plaats') }}</dt>
                        <dd v-if="props.item.location_en">
                            {{ props.item.location_en }}
                        </dd>
                        <dd v-else class="is-leeg">
                            {{ $t('Leeg; blijft weg op de Engelse site') }}
                        </dd>
                    </div>
                    <div>
                        <dt>{{ $t('Beschrijving') }}</dt>
                        <dd
                            v-if="props.item.description_en"
                            class="whitespace-pre-line"
                        >
                            {{ props.item.description_en }}
                        </dd>
                        <dd v-else class="is-leeg">
                            {{ $t('Leeg; blijft weg op de Engelse site') }}
                        </dd>
                    </div>
                </dl>
            </section>
        </div>

        <!-- ------------------------ Gegevens ------------------------ -->
        <section class="brand-detail-vak">
            <h2 class="brand-detail-taal">{{ $t('Gegevens') }}</h2>

            <dl class="brand-detail-lijst sm:grid sm:grid-cols-2 sm:gap-x-8">
                <div>
                    <dt>{{ $t('Dienstverband') }}</dt>
                    <dd v-if="props.item.employment_label">
                        {{ props.item.employment_label }}
                    </dd>
                    <dd v-else class="is-leeg">{{ $t('Niet opgegeven') }}</dd>
                </div>
                <div>
                    <dt>{{ $t('Werkvorm') }}</dt>
                    <dd v-if="props.item.workplace_label">
                        {{ props.item.workplace_label }}
                    </dd>
                    <dd v-else class="is-leeg">{{ $t('Niet opgegeven') }}</dd>
                </div>
                <div>
                    <dt>{{ $t('Beeldmerk') }}</dt>
                    <dd>
                        {{
                            props.item.logo
                                ? $t('Eigen logo')
                                : props.item.icon_label
                        }}
                    </dd>
                </div>
                <div>
                    <dt>{{ $t('Laatst gewijzigd') }}</dt>
                    <dd>{{ props.item.gewijzigd ?? '-' }}</dd>
                </div>
            </dl>
        </section>

        <!-- ------------------------- De buren ------------------------ -->
        <!--
            Twee volwaardige knoppen naast elkaar, en op een telefoon
            onder elkaar. Ze dragen de functie, de organisatie én de
            periode: bij iemand die drie keer "Service Manager" is
            geweest zegt alleen een titel niets over waar je heen gaat.

            De lege plek blijft staan als er maar één buur is, zodat
            "ouder" altijd rechts zit en "nieuwer" altijd links. Springt
            die knop van kant, dan moet je elke keer opnieuw kijken.
        -->
        <nav
            v-if="props.buren.vorige || props.buren.volgende"
            class="brand-detail-buren"
            :aria-label="$t('Andere ervaringen')"
        >
            <Link
                v-if="props.buren.vorige"
                :href="ervaringRoutes.show(props.buren.vorige.id)"
                class="brand-detail-buur"
            >
                <span class="brand-detail-buur-pijl">
                    <ArrowLeft class="size-4" />
                </span>

                <span class="min-w-0 flex-1">
                    <span class="brand-detail-buur-kop">
                        {{ $t('Nieuwer') }}
                    </span>
                    <span class="brand-detail-buur-titel">
                        {{ props.buren.vorige.functie }}
                    </span>
                    <span class="brand-detail-buur-meta">
                        {{ props.buren.vorige.organisatie }}
                        <span aria-hidden="true">·</span>
                        {{ props.buren.vorige.periode }}
                    </span>
                </span>
            </Link>
            <span v-else class="hidden sm:block" />

            <Link
                v-if="props.buren.volgende"
                :href="ervaringRoutes.show(props.buren.volgende.id)"
                class="brand-detail-buur is-rechts"
            >
                <span class="min-w-0 flex-1">
                    <span class="brand-detail-buur-kop">
                        {{ $t('Ouder') }}
                    </span>
                    <span class="brand-detail-buur-titel">
                        {{ props.buren.volgende.functie }}
                    </span>
                    <span class="brand-detail-buur-meta">
                        {{ props.buren.volgende.organisatie }}
                        <span aria-hidden="true">·</span>
                        {{ props.buren.volgende.periode }}
                    </span>
                </span>

                <span class="brand-detail-buur-pijl">
                    <ArrowRight class="size-4" />
                </span>
            </Link>
        </nav>
    </div>

    <ErvaringDialoog
        v-model:open="venster"
        :item="props.item"
        :opties="props.opties"
        :kan-vertalen="props.kanVertalen"
    />
</template>
