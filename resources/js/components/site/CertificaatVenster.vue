<script setup lang="ts">
import { Award, X } from '@lucide/vue';
import {
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { nextTick, ref, watch } from 'vue';
import { gsap, prefersReducedMotion } from '@/lib/motion';
import type { CertificaatOpDeSite } from '@/types/certificaten';

/**
 * Het verhaal achter één certificaat.
 *
 * Zelfde opzet als DienstVenster: er is altijd precies één certificaat,
 * dus `v-model:open` op DialogRoot volstaat en is Escape gewoon sluiten.
 *
 * **Alles wat leeg mag zijn, is hier ook weg.** Geen kopje boven een
 * leeg nummer, geen streepje waar de toelichting had moeten staan. Was
 * alles leeg, dan gaat dit venster niet eens open -- de tegel is dan
 * geen knop. Zie CertificaatTegel.vue.
 *
 * Zie docs/architecture/modules/certificaten.md.
 */
const props = defineProps<{ item: CertificaatOpDeSite | null }>();

const open = defineModel<boolean>('open', { required: true });

const paneel = ref<HTMLElement | null>(null);

/**
 * De inhoud komt regel voor regel binnen, net nadat het venster er is.
 *
 * Het paneel zelf schuift met CSS -- dat is een overgang die de browser
 * zelf kan doen. Deze getrapte beweging is wat het venster het gevoel
 * geeft dat het zich opent in plaats van dat het er ineens staat.
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
                `data-lenis-prevent`: de publieke site draait op Lenis,
                en die vangt het muiswiel af om de pagina zelf soepel te
                laten scrollen -- óók als je met je muis boven dit
                venster hangt. Zonder dit attribuut is een lange tekst
                niet te scrollen.
            -->
            <DialogContent class="brand-blad" data-lenis-prevent>
                <div v-if="props.item" ref="paneel" class="brand-blad-inhoud">
                    <div data-rij class="flex items-start gap-3 sm:gap-4">
                        <span class="brand-blad-bel" aria-hidden="true">
                            <img
                                v-if="props.item.logo"
                                :src="props.item.logo"
                                alt=""
                                class="size-full rounded-[inherit] object-cover"
                            />
                            <Award v-else class="size-5" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <DialogTitle class="brand-blad-titel">
                                {{ props.item.naam }}
                            </DialogTitle>
                            <DialogDescription class="brand-blad-onder">
                                {{ props.item.uitgever }}
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

                    <!--
                        De harde feiten als lijstje: behaald, geldig tot,
                        nummer. Wat er niet is staat er niet; een regel
                        met een streepje erachter is geen informatie.
                    -->
                    <dl data-rij class="brand-certificaat-feiten">
                        <div v-if="props.item.behaald">
                            <dt>{{ $t('Behaald') }}</dt>
                            <dd>{{ props.item.behaald }}</dd>
                        </div>

                        <!--
                            "Geldig tot" staat er alleen als die datum
                            nog moet komen. De server stuurt hem niet
                            eens mee zodra hij voorbij is; zie
                            HomeController. Een datum in het verleden
                            naast "geldig tot" zegt namelijk precies wat
                            hier niet hoort te staan.
                        -->
                        <div v-if="props.item.geldigTot">
                            <dt>{{ $t('Geldig tot') }}</dt>
                            <dd>{{ props.item.geldigTot }}</dd>
                        </div>

                        <div v-if="props.item.nummer">
                            <dt>{{ $t('Certificaatnummer') }}</dt>
                            <dd class="font-mono text-xs break-all">
                                {{ props.item.nummer }}
                            </dd>
                        </div>
                    </dl>

                    <!--
                        `brand-blad-tekst` zet zelf de witregels om in
                        alinea's en geeft de tekst op een breed scherm een
                        eigen schuifgebied; zie app.css.
                    -->
                    <p
                        v-if="props.item.toelichting"
                        data-rij
                        class="brand-blad-tekst"
                    >
                        {{ props.item.toelichting }}
                    </p>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
