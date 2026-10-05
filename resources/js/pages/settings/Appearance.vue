<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, ExternalLink, Pencil } from '@lucide/vue';
import { ref, watch } from 'vue';
import AppearanceToggle from '@/components/AppearanceToggle.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { edit, mail, mailOnderdelen, mailStijl } from '@/routes/appearance';

/**
 * De mailstijl die nu geldt, en de twee keuzes.
 *
 * Dit scherm was een `Route::inertia` zonder props. Sinds de mailstijl een
 * instelling is komt hij uit de database; zie WeergaveController.
 */
const props = defineProps<{
    mailStijl: string;
    mailStijlen: Array<{ value: string; label: string; omschrijving: string }>;
}>();

/**
 * Wat er nu gekozen staat.
 *
 * Een eigen `ref` die de prop volgt: zo springt de kaart meteen om als je
 * erop klikt, zonder te wachten op de server. Gaat het opslaan mis, dan
 * zet de nieuwe prop hem terug.
 */
const stijl = ref(props.mailStijl);

watch(
    () => props.mailStijl,
    (nieuw) => {
        stijl.value = nieuw;
    },
);

const bezig = ref(false);

const kies = (waarde: string): void => {
    if (waarde === stijl.value || bezig.value) {
        return;
    }

    stijl.value = waarde;
    bezig.value = true;

    router.put(
        mailStijl().url,
        { stijl: waarde },
        {
            preserveScroll: true,
            onFinish: () => {
                bezig.value = false;
            },
        },
    );
};
import contact from '@/routes/website/contact';

/**
 * **De brede kolom, net als Veiligheid en Documentatie.**
 *
 * De instellingen staan standaard smal, en dat is goed: een formulier van
 * 900 pixels breed leest niet prettiger dan een van 600. Maar dit scherm is
 * geen formulier meer sinds de mailvoorbeelden erop staan -- het is een
 * pagina met voorbeelden, net als die twee andere.
 *
 * En er zat een echte fout onder. De smalle kolom is 576 pixels, en de mail
 * zet zichzelf onder 600 pixels om naar zijn smalle vorm:
 * `@media (max-width: 600px) { .inner-body { width: 100% !important } }` in
 * de maillayout. Het `iframe` is zijn eigen venster, dus die grens gold
 * voor het voorbeeld -- de eigenaar keek naar hoe zijn mail op een telefoon
 * staat en kon dat niet weten. In de brede kolom (768) staat de mail in
 * zijn gewone vorm.
 */
defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Weergave',
                href: edit(),
            },
        ],
        breed: true,
    },
});
</script>

<template>
    <Head :title="$t('Weergave')" />

    <h1 class="sr-only">{{ $t('Weergave') }}</h1>

    <div class="space-y-8">
        <Heading
            variant="small"
            :title="$t('Weergave')"
            :description="
                $t(
                    'De huisstijl is de basis. Licht is er voor wie dat prettiger werkt.',
                )
            "
        />

        <AppearanceToggle />

        <p class="max-w-prose text-sm text-pretty text-muted-foreground">
            {{
                $t(
                    'De huisstijl is hetzelfde donkere palet als de website zelf, en dat is waar alles op gebouwd wordt. Deze keuze geldt alleen voor het beheerportaal; de website blijft altijd in de huisstijl staan.',
                )
            }}
        </p>

        <!--
            Het voorbeeld van de mailstijl.

            **Dit is de échte mail en geen nabootsing**: hetzelfde sjabloon
            en hetzelfde thema als wat een bezoeker krijgt, met verzonnen
            gegevens erin. Zou het een eigen stukje HTML zijn, dan klopt het
            tot de dag dat iemand het thema aanpast en hier niet aan denkt.

            In een `iframe`, want een mail heeft zijn opmaak in de tags zelf
            en die zou met het portaal vechten.
        -->
        <section class="space-y-3">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <div class="space-y-1">
                    <h2 class="text-sm font-medium">
                        {{ $t('Hoe je mail eruitziet') }}
                    </h2>
                    <p
                        class="max-w-prose text-sm text-pretty text-muted-foreground"
                    >
                        {{
                            $t(
                                'Elke mail die je website verstuurt gebruikt deze stijl: de bevestiging aan een bezoeker, maar ook de meldingen aan jou. Dit is geen plaatje -- het is de echte mail, met een verzonnen naam erin.',
                            )
                        }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="contact.index()">
                            <Pencil class="size-4" />
                            {{ $t('Tekst aanpassen') }}
                        </Link>
                    </Button>

                    <!--
                        In een nieuw tabblad en met het tekentje erbij: dit
                        is geen scherm van het portaal maar een los stukje
                        HTML, en daar hoort de gewone navigatie niet bij.
                    -->
                    <Button variant="outline" size="sm" as-child>
                        <a :href="mail().url" target="_blank" rel="noopener">
                            <ExternalLink class="size-4" />
                            {{ $t('Groot bekijken') }}
                        </a>
                    </Button>
                </div>
            </div>

            <!--
                De keuze tussen de twee stijlen.

                **Twee kaarten en geen keuzelijst.** Dit is een keuze die je
                één keer maakt en die je wil kunnen zien: de omschrijving
                hoort erbij, want het verschil is niet alleen smaak. Een
                lijstje met twee woorden erin zou je laten gokken.

                Het voorbeeld eronder verandert mee zodra je opslaat -- het
                rendert de échte mail, dus wat je daar ziet is wat er
                uitgaat.
            -->
            <div class="brand-mailstijl">
                <button
                    v-for="optie in props.mailStijlen"
                    :key="optie.value"
                    type="button"
                    class="brand-mailstijl-kaart"
                    :data-gekozen="stijl === optie.value ? '' : undefined"
                    :disabled="bezig"
                    :aria-pressed="stijl === optie.value"
                    @click="kies(optie.value)"
                >
                    <span class="brand-mailstijl-vink">
                        <Check
                            v-if="stijl === optie.value"
                            class="size-3.5"
                            aria-hidden="true"
                        />
                    </span>

                    <span class="min-w-0 space-y-1 text-left">
                        <span class="block text-sm font-medium">
                            {{ optie.label }}
                        </span>
                        <span
                            class="block text-xs text-pretty text-muted-foreground"
                        >
                            {{ optie.omschrijving }}
                        </span>
                    </span>
                </button>
            </div>

            <div class="brand-mailvoorbeeld" :data-stijl="props.mailStijl">
                <!--
                    **`:key` op de stijl, en dat is geen truc maar de
                    oplossing van een echte fout.**

                    Een `iframe` haalt zijn pagina één keer op. Verandert
                    de stijl, dan blijft `src` precies hetzelfde -- het is
                    hetzelfde adres -- dus doet de browser niets en bleef
                    het voorbeeld in de oude stijl staan tot je de pagina
                    ververste. `Cache-Control: no-store` helpt daar niet
                    tegen: er wordt niet opnieuw gevraagd, dus er is niets
                    om niet te bewaren.

                    Met een sleutel op de stijl gooit Vue het element weg en
                    maakt een nieuw aan, en een nieuw `iframe` haalt zijn
                    pagina vers op.

                    **De sleutel hangt aan `props.mailStijl` en niet aan de
                    `stijl` hiernaast.** Die eerste is wat er is opgeslagen,
                    die tweede wat je net hebt aangetikt. Het voorbeeld hoort
                    te tonen wat er écht uitgaat; zou het meteen omslaan,
                    dan laat het bij een mislukte opslag iets zien dat niet
                    bewaard is.
                -->
                <iframe
                    :key="props.mailStijl"
                    :src="mail().url"
                    :title="$t('Voorbeeld van je mailstijl')"
                    class="brand-mailvoorbeeld-venster"
                    loading="lazy"
                />
            </div>

            <p class="max-w-prose text-xs text-pretty text-muted-foreground">
                {{
                    $t(
                        'Elk mailprogramma tekent een mail een beetje anders -- Outlook doet het net niet zoals Gmail. Dit komt dicht in de buurt, maar het is geen garantie tot op de pixel.',
                    )
                }}
            </p>
        </section>

        <!--
            En de bouwstenen.

            **Een tweede voorbeeld en geen knop in het eerste.** De
            bevestiging hierboven heeft geen knop -- daar valt niets te
            openen -- maar je beveiligingsmeldingen wel. Zonder dit vak zag
            de eigenaar dus nooit hoe een knop in zijn huisstijl staat, en
            een knop in de bevestiging plakken zou een mail tonen die niet
            bestaat.

            De kop zegt daarom expliciet dat dit geen echte mail is.
        -->
        <section class="space-y-3">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <div class="space-y-1">
                    <h2 class="text-sm font-medium">
                        {{ $t('De onderdelen van een mail') }}
                    </h2>
                    <p
                        class="max-w-prose text-sm text-pretty text-muted-foreground"
                    >
                        {{
                            $t(
                                'Dit is geen mail die iemand krijgt, maar een voorbeeld van de bouwstenen: een knop, een uitgelicht vak en een tabel. Die komen voor in de meldingen die jij van je website krijgt. Zo zie je hoe ze eruitzien zonder te wachten tot er een binnenkomt.',
                            )
                        }}
                    </p>
                </div>

                <Button variant="outline" size="sm" as-child>
                    <a
                        :href="mailOnderdelen().url"
                        target="_blank"
                        rel="noopener"
                    >
                        <ExternalLink class="size-4" />
                        {{ $t('Groot bekijken') }}
                    </a>
                </Button>
            </div>

            <div class="brand-mailvoorbeeld" :data-stijl="props.mailStijl">
                <!-- Ook hier een sleutel: dit voorbeeld draagt hetzelfde
                     thema, dus het verandert mee. Zie hierboven. -->
                <iframe
                    :key="props.mailStijl"
                    :src="mailOnderdelen().url"
                    :title="$t('Voorbeeld van de onderdelen van een mail')"
                    class="brand-mailvoorbeeld-venster"
                    loading="lazy"
                />
            </div>
        </section>
    </div>
</template>
