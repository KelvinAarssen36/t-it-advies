<script setup lang="ts">
import { X } from '@lucide/vue';
import {
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { nextTick, ref, watch } from 'vue';
import DienstIcoon from '@/components/site/DienstIcoon.vue';
import { gsap, prefersReducedMotion } from '@/lib/motion';
import type { DienstOpDeSite } from '@/types/diensten';

/**
 * Het hele verhaal achter één dienst.
 *
 * Eenvoudiger dan ErvaringVenster: daar kan het venster van inhoud
 * wisselen omdat je er ook een lijst in kunt openen. Hier is er altijd
 * precies één dienst, dus `v-model:open` op DialogRoot volstaat en is
 * Escape gewoon sluiten.
 *
 * **Alles wat leeg mag zijn, is hier ook weg.** Geen kopje boven een
 * leeg lijstje, geen streepje waar de tekst had moeten staan.
 *
 * Zie docs/architecture/modules/diensten.md.
 */
const props = defineProps<{ item: DienstOpDeSite | null }>();

const open = defineModel<boolean>('open', { required: true });

const paneel = ref<HTMLElement | null>(null);

/**
 * De inhoud komt regel voor regel binnen, net nadat het venster er is.
 *
 * Het paneel zelf schuift met CSS -- dat is een overgang die de browser
 * zelf kan doen. Deze getrapte beweging is wat het venster het gevoel
 * geeft dat het zich opent in plaats van dat het er ineens staat.
 * Zelfde aanpak als in ErvaringVenster.
 */
const onthul = (): void => {
    if (prefersReducedMotion() || paneel.value === null) {
        return;
    }

    const rijen = paneel.value.querySelectorAll('[data-rij]');

    if (rijen.length === 0) {
        return;
    }

    gsap.fromTo(
        rijen,
        { opacity: 0, y: 14 },
        {
            opacity: 1,
            y: 0,
            duration: 0.45,
            stagger: 0.06,
            ease: 'power3.out',
            // Even wachten tot het paneel op zijn plek staat; anders
            // bewegen de regels mee met iets dat zelf nog schuift.
            delay: 0.1,
            clearProps: 'opacity,transform',
        },
    );
};

watch(open, (isOpen) => {
    if (isOpen) {
        void nextTick(onthul);
    }
});
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay class="brand-blad-waas" />

            <!--
                `data-lenis-prevent` is hier geen versiering. De publieke
                site draait op Lenis, en die vangt het muiswiel af om de
                pagina zelf soepel te laten scrollen -- óók als je met je
                muis boven dit venster hangt. Zonder dit attribuut is een
                lange tekst niet te scrollen: je verschuift de pagina
                erachter.
            -->
            <DialogContent class="brand-blad" data-lenis-prevent>
                <div v-if="props.item" ref="paneel" class="brand-blad-inhoud">
                    <div data-rij class="flex items-start gap-3 sm:gap-4">
                        <span class="brand-blad-bel" aria-hidden="true">
                            <DienstIcoon :icoon="props.item.icon" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <DialogTitle class="brand-blad-titel">
                                {{ props.item.titel }}
                            </DialogTitle>
                            <DialogDescription class="brand-blad-onder">
                                {{ props.item.samenvatting }}
                            </DialogDescription>
                        </div>

                        <button
                            type="button"
                            class="brand-blad-sluit"
                            :aria-label="$t('Sluiten')"
                            @click="open = false"
                        >
                            <X class="size-4" />
                        </button>
                    </div>

                    <div v-if="props.item.punten.length > 0" data-rij>
                        <p class="brand-blad-kopje">
                            {{ $t('Waar dit over gaat') }}
                        </p>
                        <ul class="brand-dienstkaart-punten mt-2">
                            <li v-for="punt in props.item.punten" :key="punt">
                                {{ punt }}
                            </li>
                        </ul>
                    </div>

                    <!--
                        `brand-blad-tekst` zet zelf de witregels om in
                        alinea's en geeft de tekst op een breed scherm een
                        eigen schuifgebied; zie app.css. Dezelfde klasse
                        als in het venster van de tijdlijn.
                    -->
                    <p
                        v-if="props.item.verhaal"
                        data-rij
                        class="brand-blad-tekst"
                    >
                        {{ props.item.verhaal }}
                    </p>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
