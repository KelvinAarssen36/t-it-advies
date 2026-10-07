<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { CheckCheck, Send, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import BrandSelect from '@/components/BrandSelect.vue';
import HoneypotFields from '@/components/HoneypotFields.vue';
import InputError from '@/components/InputError.vue';
import TurnstileWidget from '@/components/TurnstileWidget.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import { gsap, prefersReducedMotion } from '@/lib/motion';
import { store as contactStore } from '@/routes/contact';
import type { OnderwerpOpDeSite, VeldOpDeSite } from '@/types/contact';

/**
 * Het contactformulier zelf.
 *
 * **Eén component voor twee plekken**: de sectie onderaan de
 * landingspagina en de aparte contactpagina. De eigenaar kiest welke van
 * de twee hij gebruikt, en het formulier hoeft daar niets van te weten.
 *
 * **De velden komen van de server.** De eigenaar bepaalt welke er staan en
 * welke verplicht zijn, en diezelfde lijst bouwt de validatieregels op.
 * Zou dit component zijn eigen velden tekenen, dan is "verplicht" hier
 * vroeg of laat iets anders dan `required` daar -- en krijgt een bezoeker
 * een foutmelding bij een veld dat er niet staat. Zie
 * App\Support\Contact\Contactformulier.
 *
 * Zie docs/architecture/modules/contact.md.
 */
const props = defineProps<{
    velden: VeldOpDeSite[];
    onderwerpen: OnderwerpOpDeSite[];
    eigenOnderwerpToegestaan: boolean;
    /** Vingerafdruk van het formulier; zie ContactRequest::after(). */
    instellingen: string;
    /**
     * Het publieke adres, als uitweg onder het formulier.
     *
     * **Niet alleen een vriendelijkheid.** Het tokenveld van Turnstile is
     * verplicht, dus laadt Cloudflare niet, dan is dit formulier niet te
     * versturen. Dat is de bedoeling -- liever dicht dan onbeschermd --
     * maar dan moet er wél een andere manier zijn om iemand te bereiken.
     */
    email: string;
}>();

const page = usePage();
const status = computed(() => page.props.flash?.status);

/* --- Het onderwerp ----------------------------------------------------- */

/**
 * De keuze "anders, namelijk...".
 *
 * Een lege waarde en geen `-1`: het keuzeveld werkt met tekst, en een lege
 * string is wat een niet-gekozen optie van zichzelf al is.
 */
const EIGEN = '';

const gekozenOnderwerp = ref<string>('');

/** De opties voor de keuzelijst, met "anders" eronder als dat mag. */
const onderwerpOpties = computed(() => {
    const opties = props.onderwerpen.map((onderwerp) => ({
        value: String(onderwerp.id),
        label: onderwerp.naam,
    }));

    if (props.eigenOnderwerpToegestaan) {
        opties.push({ value: EIGEN, label: t('Anders, namelijk...') });
    }

    return opties;
});

/**
 * Of er een keuzelijst komt of gewoon een tekstveld.
 *
 * Zonder onderwerpen is een lijst met één optie "anders" een lijst die
 * niets kiest -- dan is een tekstveld eerlijker én sneller.
 */
const heeftLijst = computed(() => props.onderwerpen.length > 0);

/** Of het eigen tekstveld open moet staan. */
const eigenOnderwerp = computed(
    () => !heeftLijst.value || gekozenOnderwerp.value === EIGEN,
);

/*
 * **Hier stond een snelkeuze voor uitgelichte onderwerpen, en die is
 * weggehaald.** Uitlichten gaat niet over het formulier maar over het
 * scherm Aanvragen: de eigenaar wil een bínnengekomen aanvraag met zo'n
 * onderwerp beter zien, niet een bezoeker ergens naartoe duwen. Het
 * formulier is voor de bezoeker en dat hoort een neutrale lijst te
 * blijven. Zie Aanvragen.vue.
 */

/* --- De bevestiging ---------------------------------------------------- */

/**
 * De bevestiging komt binnen met een animatie, en het formulier verdwijnt.
 *
 * **Dat laatste is de hele reden dat dit beter werkt dan eerst.** Toen
 * bleef het lege formulier staan met een regel erboven, en dat leest
 * alsof er niets is gebeurd -- je hebt net iets verstuurd en je kijkt
 * naar hetzelfde scherm. Nu neemt de bevestiging zijn plek in.
 *
 * **Vanuit de overgang en niet uit een `watch` op de status, en dat is een
 * correctie.** Die `watch` draaide op het moment dat de status binnenkwam
 * en zocht het vak met een selector. Sinds het formulier netjes wegschuift
 * voordat de bevestiging komt, bestaat dat vak op dat moment nog niet --
 * dan had de animatie niets om op te spelen. Nu geeft Vue het element mee
 * zodra het er echt is.
 *
 * Twee bewegingen, en de tweede is de hele pointe. Het vak komt rustig
 * omhoog, en het vinkje ploft er met een overshoot in: dat laatste is wat
 * "het is gelukt" zegt. Zonder die overshoot leest het als nog een blok
 * tekst dat verschijnt.
 */
/**
 * Hoe de inzending verstuurd moet worden.
 *
 * **Via `options` en niet als losse attributen op `<Form>`, en dat is een
 * correctie die me een ronde heeft gekost.** Inertia's `<Form>` kent geen
 * `preserveScroll` of `preserveState` als prop -- alleen `<Link>` heeft
 * die. Zet je ze er toch op, dan rendert Vue ze als gewoon HTML-attribuut
 * op het `<form>`-element en gebeurt er niets. Wat de `Form` wél
 * doorgeeft aan het verzoek is `...props.options`, en dat spreidt hij als
 * laatste uit over zijn eigen opties.
 *
 * **Wat de twee doen, en waarom ze allebei moeten:**
 *
 * - `preserveScroll`: anders springt de bezoeker na het versturen naar de
 *   bovenkant van de pagina en kijkt hij naar de kop terwijl zijn
 *   bevestiging onderaan staat.
 * - `preserveState`: zonder dit bouwt Inertia het component opnieuw op.
 *   Dan bestaat het bevestigingsvak al bij de eerste tekening, is er geen
 *   wissel, en draait er geen enkele animatie.
 *
 * Als constante en niet als letterlijk object in het sjabloon: dat laatste
 * is bij elke tekening een nieuw object, en dan ziet de `Form` elke keer
 * een gewijzigde prop.
 */
const VERSTUUROPTIES = {
    preserveScroll: true,
    preserveState: true,
} as const;

/**
 * Of er een inzending onderweg is.
 *
 * **Van de gebeurtenissen van het formulier en niet uit de slot.** `Form`
 * geeft `processing` in zijn slot, maar dat is binnen de slot en niet op
 * het `<form>`-element zelf -- en het dimmen gaat juist over dat element.
 * `start` en `finish` zijn dezelfde toestand, een laag hoger.
 *
 * `finish` en niet alleen `error`: bij een mislukte verbinding komt er geen
 * van de twee andere, en dan zou het formulier gedimd en onaanklikbaar
 * blijven staan.
 */
const bezig = ref(false);

const animeerBevestiging = (el: Element): void => {
    /*
     * Deze haak vuurt voor allebei de takken van de wissel, en met `appear`
     * ook bij de eerste tekening. Het formulier hoort hier niets mee te
     * doen -- alleen het bevestigingsvak, en dat is te herkennen aan zijn
     * eigen merkje.
     *
     * `appear` staat erop als verzekering: zou Inertia het component ooit
     * tóch opnieuw opbouwen in plaats van het te laten staan, dan is er
     * geen wissel en dus geen gewone enter -- en dan ploft de bevestiging
     * er zonder animatie in. Dát was de oude toestand.
     */
    if (!(el instanceof HTMLElement) || el.dataset.bevestiging === undefined) {
        return;
    }

    if (prefersReducedMotion()) {
        return;
    }

    gsap.fromTo(
        el,
        { opacity: 0, y: 16, scale: 0.98 },
        { opacity: 1, y: 0, scale: 1, duration: 0.5, ease: 'power3.out' },
    );

    const vink = el.querySelector('[data-bevestiging-vink]');

    if (vink === null) {
        return;
    }

    gsap.fromTo(
        vink,
        { scale: 0, rotate: -25 },
        {
            scale: 1,
            rotate: 0,
            duration: 0.5,
            delay: 0.18,
            ease: 'back.out(2.2)',
        },
    );
};

/* --- Turnstile ---------------------------------------------------------- */

const widget = ref<InstanceType<typeof TurnstileWidget> | null>(null);

/**
 * Het token opnieuw ophalen na een inzending.
 *
 * **Dit was een echte fout die hier zat.** Een Turnstile-token werkt één
 * keer. Verstuurde iemand twee berichten zonder de pagina te verversen,
 * dan faalde de tweede op de verificatie -- met een melding over een
 * controle waar hij niets aan kon doen. `TurnstileWidget` had hier altijd
 * een `reset()` voor; die werd alleen nooit aangeroepen.
 *
 * **En hij hing eerst alleen aan `@success`, waar hij niets doet.** Bij
 * succes neemt het bevestigingsvak de plek van het formulier in, dus de
 * widget wordt opgeruimd -- resetten van iets dat verdwijnt helpt
 * niemand. Het geval dat hem écht nodig heeft is een mislukte validatie:
 * het token is dan al verbruikt bij Cloudflare, het formulier blijft
 * staan, en de tweede poging struikelt over de verificatie terwijl de
 * bezoeker alleen een te kort bericht had. Vandaar `@error`.
 *
 * `@success` blijft eraan hangen: het kost niets, en zodra er ooit een
 * "stuur nog een bericht" bij komt is hij daar nodig.
 */
const opnieuw = (): void => {
    widget.value?.reset();
};

const invoertype = (soort: string): string => {
    if (soort === 'email') {
        return 'email';
    }

    return soort === 'telefoon' ? 'tel' : 'text';
};
</script>

<template>
    <!--
        Van formulier naar bevestiging, in één beweging.

        **Hier zat een harde knip.** Het formulier verdween op het moment
        dat het antwoord binnenkwam en de bevestiging kwam los daarvan op.
        Je hebt net op Versturen gedrukt, en het ding waar je op drukte is
        er in één beeldje niet meer -- dat leest als een storing, niet als
        een bevestiging.

        Nu schuift het formulier eerst weg (200 ms, iets omhoog en
        wegvallend) en komt de bevestiging daarna op. `out-in` dus, want
        allebei tegelijk is twee blokken over elkaar met verschillende
        hoogtes.

        Het komen zelf staat niet in CSS maar in `animeerBevestiging`: dat
        vinkje hoort met een overshoot in te ploffen, en dat is wat GSAP
        beter doet dan een overgang.
    -->
    <Transition
        name="brand-contactwissel"
        mode="out-in"
        appear
        @enter="animeerBevestiging"
    >
        <div v-if="status" data-bevestiging class="brand-contactgelukt">
            <span class="brand-contactgelukt-vink" aria-hidden="true">
                <CheckCheck data-bevestiging-vink class="size-6" />
            </span>

            <h3 class="text-lg font-semibold">{{ $t('Aanvraag gelukt') }}</h3>

            <p class="max-w-prose text-pretty text-muted-foreground">
                {{ status }}
            </p>
        </div>

        <Form
            v-else
            v-bind="contactStore.form()"
            reset-on-success
            :options="VERSTUUROPTIES"
            v-slot="{ errors, processing }"
            class="brand-contactformulier"
            :data-bezig="bezig ? '' : undefined"
            @start="bezig = true"
            @finish="bezig = false"
            @success="opnieuw"
            @error="opnieuw"
        >
            <HoneypotFields />

            <!--
            De vingerafdruk van de instellingen; zie ContactRequest::after().

            Na een foutantwoord tekent Inertia deze pagina opnieuw met verse
            props, dus hier staat dan meteen de nieuwe vingerafdruk -- en
            daarom lukt opnieuw versturen wél.
        -->
            <input
                type="hidden"
                name="instellingen"
                :value="props.instellingen"
            />

            <!--
            Het formulier is onderweg gewijzigd.

            **Een waarschuwing en geen succes, en dat was precies de fout.**
            Deze melding kwam eerst binnen als `flash.status`, hetzelfde
            kanaal als een geslaagde inzending -- dus kreeg de bezoeker een
            groen vinkje met "Aanvraag gelukt" te zien terwijl er niets was
            opgeslagen, en verdween het formulier met zijn tekst erin.

            Nu is het een validatiefout. Die hoort bovenaan en niet bij een
            veld: er is geen veld dat de bezoeker kan verbeteren.
        -->
            <p
                v-if="errors.instellingen"
                class="brand-contactwaarschuwing"
                role="alert"
            >
                <TriangleAlert
                    class="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <span>{{ errors.instellingen }}</span>
            </p>

            <!--
            De velden in de volgorde die de eigenaar heeft gekozen. Naam en
            e-mailadres staan naast elkaar vanaf 40rem; het onderwerp en
            het bericht over de volle breedte. Een formulier dat één kolom
            blijft terwijl er ruimte is, leest als een enquête.
        -->
            <div class="brand-contactvelden">
                <template v-for="veld in props.velden" :key="veld.naam">
                    <!-- Het onderwerp: een keuzelijst, een tekstveld, of beide. -->
                    <div
                        v-if="veld.type === 'onderwerp'"
                        class="brand-contactveld-vak is-breed"
                    >
                        <Label
                            :for="`contact-${veld.naam}`"
                            :verplicht="veld.verplicht"
                        >
                            {{ veld.label }}
                        </Label>

                        <BrandSelect
                            v-if="heeftLijst"
                            :id="`contact-${veld.naam}`"
                            v-model="gekozenOnderwerp"
                            :options="onderwerpOpties"
                            :placeholder="$t('Kies een onderwerp')"
                        />

                        <!--
                        Het id gaat als verborgen veld mee: met een
                        `BrandSelect` is er geen `<select name>` om mee te
                        sturen, en dit is het veld waarop de server
                        valideert.
                    -->
                        <input
                            v-if="heeftLijst && !eigenOnderwerp"
                            type="hidden"
                            name="subject_id"
                            :value="gekozenOnderwerp"
                        />

                        <Input
                            v-if="eigenOnderwerp"
                            :id="
                                heeftLijst ? undefined : `contact-${veld.naam}`
                            "
                            name="subject_text"
                            :maxlength="veld.maximum"
                            :placeholder="
                                heeftLijst
                                    ? $t('Waar gaat het over?')
                                    : undefined
                            "
                            :aria-label="heeftLijst ? veld.label : undefined"
                        />

                        <InputError :message="errors.subject_id" />
                        <InputError :message="errors.subject_text" />
                    </div>

                    <!-- Het bericht: altijd over de volle breedte. -->
                    <div
                        v-else-if="veld.type === 'tekstvak'"
                        class="brand-contactveld-vak is-breed"
                    >
                        <Label
                            :for="`contact-${veld.naam}`"
                            :verplicht="veld.verplicht"
                        >
                            {{ veld.label }}
                        </Label>
                        <Textarea
                            :id="`contact-${veld.naam}`"
                            :name="veld.naam"
                            :maxlength="veld.maximum"
                            rows="6"
                        />
                        <InputError :message="errors[veld.naam]" />
                    </div>

                    <!-- De gewone velden. -->
                    <div v-else class="brand-contactveld-vak">
                        <Label
                            :for="`contact-${veld.naam}`"
                            :verplicht="veld.verplicht"
                        >
                            {{ veld.label }}
                        </Label>
                        <Input
                            :id="`contact-${veld.naam}`"
                            :name="veld.naam"
                            :type="invoertype(veld.type)"
                            :maxlength="veld.maximum"
                            :autocomplete="veld.autocomplete ?? undefined"
                        />
                        <InputError :message="errors[veld.naam]" />
                    </div>
                </template>
            </div>

            <TurnstileWidget ref="widget" />
            <InputError :message="errors['cf-turnstile-response']" />

            <div class="flex flex-wrap items-center gap-x-4 gap-y-3">
                <Button
                    :disabled="processing"
                    variant="brand"
                    class="w-full sm:w-auto"
                >
                    <Spinner v-if="processing" />
                    <Send v-else class="size-4" />
                    {{
                        processing
                            ? $t('Bezig met versturen...')
                            : $t('Versturen')
                    }}
                </Button>

                <!--
                De uitweg. Zie de prop `email`: zonder Turnstile is dit
                formulier bewust niet te versturen, en dan mag een bezoeker
                niet met lege handen staan.
            -->
                <p class="text-sm text-pretty text-muted-foreground">
                    {{ $t('Of mail rechtstreeks:') }}
                    <a :href="`mailto:${props.email}`" class="brand-sitemail">
                        {{ props.email }}
                    </a>
                </p>
            </div>
        </Form>
    </Transition>
</template>
