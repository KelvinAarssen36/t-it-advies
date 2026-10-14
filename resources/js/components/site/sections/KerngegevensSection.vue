<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, useTemplateRef } from 'vue';
import KerngegevenIcoon from '@/components/site/KerngegevenIcoon.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import SiteSection from '@/components/site/SiteSection.vue';
import {
    gsap,
    prefersReducedMotion,
    rolbord,
    stapelBinnen,
} from '@/lib/motion';
import type { KerngegevenOpDeSite } from '@/types/kerngegevens';
import type { SectieKop, SectieProps } from '@/types/secties';

/**
 * De kerngegevens: de praktische feiten in één overzicht.
 *
 * **Een lijst in twee kolommen, en geen strook met gelijke vakken.** De
 * strook die hier stond gaf elk vak de hoogte van het langste in zijn rij:
 * één lange waarde, en de drie buren kregen een leeg vlak onder hun tekst.
 * Nu is elke rij zo hoog als zijn eigen inhoud. Per feit staat het teken in
 * een vak links, het label klein erboven en de waarde in de maat van een
 * kopje -- de waarde is waar het om gaat.
 *
 * Eén feit staat alleen. Bij een oneven aantal krijgt het laatste de hele
 * breedte, zodat er geen half lege rij overblijft. Het blijft een `<dl>`:
 * termen met hun omschrijving.
 *
 * **De beweging komt van twee kanten.** Op een breed scherm schuift de
 * linkerkolom van links en de rechterkolom van rechts naar zijn plek,
 * gekoppeld aan de scroll: je schuift de feiten zelf in elkaar, en
 * omhoogscrollen haalt ze weer uiteen. Ze staan stil voordat de strook het
 * midden van het scherm bereikt, dus je leest nooit iets wat beweegt. Dan
 * rollen de waarden zich vast als een rolbord. Op een telefoon komen ze
 * gewoon één voor één op; onder reduced motion staat alles er meteen.
 *
 * Zie docs/architecture/modules/kerngegevens.md.
 */
defineProps<SectieProps>();

const page = usePage();

const gegevens = computed<KerngegevenOpDeSite[]>(
    () => (page.props.coreFacts as KerngegevenOpDeSite[] | undefined) ?? [],
);

const kop = computed<SectieKop | null>(
    () => (page.props.coreFactsHeading as SectieKop | undefined) ?? null,
);

const lijst = useTemplateRef<HTMLElement>('lijst');
const opruimers: Array<() => void> = [];

/**
 * De twee kolommen schuiven in elkaar, met de scroll mee.
 *
 * De kant komt uit de plek in de lijst (even links, oneven rechts) -- dat
 * is ook de kolom waarin het feit staat. De afstand is een deel van de
 * breedte van de lijst zelf, en wordt bij elke verversing opnieuw gemeten
 * (`invalidateOnRefresh`): na een andere taal of een andere schermbreedte
 * klopt hij nog steeds.
 */
const schuifInElkaar = (): (() => void) => {
    const vlak = lijst.value;

    if (vlak === null || prefersReducedMotion()) {
        return () => {};
    }

    const mm = gsap.matchMedia();

    mm.add('(min-width: 50rem)', () => {
        const feiten = Array.from(
            vlak.querySelectorAll<HTMLElement>('.brand-site-kern-feit'),
        );
        const afstand = (): number => Math.min(vlak.clientWidth * 0.12, 140);

        const tijdlijn = gsap.timeline({
            defaults: { ease: 'power2.out' },
            scrollTrigger: {
                trigger: vlak,
                start: 'top 95%',
                end: 'top 52%',
                scrub: 0.6,
                invalidateOnRefresh: true,
            },
        });

        tijdlijn.fromTo(
            feiten,
            {
                x: (i: number) =>
                    (i % 2 === 0 ? -1 : 1) *
                    afstand() *
                    (1 + Math.floor(i / 2) * 0.25),
                opacity: 0,
            },
            /*
             * Geen `stagger`: in een scrub met afstanden die bij een
             * verversing opnieuw worden berekend, tekent GSAP de beginstand
             * van een later element pas als zijn beurt komt -- de
             * rechterkolom stond dan eerst op zijn plek en schoot daarna weg.
             * De volgorde zit nu in de afstand: elke rij lager begint een
             * stukje verder weg.
             */
            { x: 0, opacity: 1, duration: 1 },
        );

        return () => {
            tijdlijn.scrollTrigger?.kill();
            tijdlijn.kill();
            gsap.set(feiten, { clearProps: 'transform,opacity' });
        };
    });

    /*
     * Op een telefoon is er één kolom, dus geen twee kanten: daar komen
     * de feiten één voor één op.
     */
    mm.add('(max-width: 49.999rem)', () =>
        stapelBinnen(
            Array.from(
                vlak.querySelectorAll<HTMLElement>('.brand-site-kern-feit'),
            ),
            { van: { opacity: 0, y: 18 }, stagger: 0.05 },
        ),
    );

    return () => mm.revert();
};

onMounted(() => {
    opruimers.push(schuifInElkaar());
    opruimers.push(rolbord('[data-rolbord]', lijst.value));
});

onBeforeUnmount(() => opruimers.forEach((opruimen) => opruimen()));
</script>
<template>
    <SiteSection id="kerngegevens" :tone="tone" :divided="divided">
        <SectionHeading
            v-if="kop"
            :eyebrow="kop.opschrift ?? undefined"
            :title="kop.titel"
            :intro="kop.inleiding ?? undefined"
        />

        <!--
            `data-rolbord-strook`: de waarden rollen zodra deze lijst
            binnenkomt, allemaal tegelijk, en niet elk op zijn eigen
            moment. Anders staat een feit onderaan het scherm zichtbaar
            leeg. Zie `rolbord()` in motion.ts.
        -->
        <dl
            ref="lijst"
            data-rolbord-strook
            class="brand-site-kern-lijst"
            :data-aantal="gegevens.length"
        >
            <div
                v-for="gegeven in gegevens"
                :key="gegeven.id"
                class="brand-site-kern-feit"
            >
                <span class="brand-site-kern-teken" aria-hidden="true">
                    <KerngegevenIcoon :icoon="gegeven.soort" />
                </span>

                <div class="brand-site-kern-tekst">
                    <dt class="brand-site-kern-label">
                        {{ gegeven.label }}
                    </dt>
                    <dd class="brand-site-kern-waarde">
                        <!--
                            `opacity-0` tot het rolbord hem zichtbaar zet;
                            onder reduced motion staat hij meteen goed.
                        -->
                        <span
                            data-rolbord
                            class="brand-site-kern-cijfer opacity-0"
                        >
                            {{ gegeven.waarde }}
                        </span>
                        <span
                            v-if="gegeven.notitie"
                            class="brand-site-kern-notitie"
                        >
                            {{ gegeven.notitie }}
                        </span>
                    </dd>
                </div>
            </div>
        </dl>
    </SiteSection>
</template>
