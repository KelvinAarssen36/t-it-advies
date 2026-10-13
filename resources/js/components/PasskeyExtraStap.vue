<script setup lang="ts">
import { ShieldCheck, TriangleAlert } from '@lucide/vue';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import PasskeyStapDialoog from '@/components/PasskeyStapDialoog.vue';
import { Switch } from '@/components/ui/switch';

/**
 * De extra stap na een passkey: aan of uit, de keuze van de eigenaar.
 *
 * Een passkey is één handeling: je vinger, en je bent binnen. Dat is het
 * gemak ervan en tegelijk het verschil met een wachtwoordlogin, waar
 * daarna altijd nog de authenticator komt. Wie dat verschil niet wil, zet
 * deze schakelaar aan.
 *
 * **Het schuifje beweegt pas als de server het heeft omgezet.** Het is
 * gebonden aan `props.aan` en niet aan een eigen waarde: zou het meteen
 * meespringen, dan staat het even iets te tonen wat nog niet waar is --
 * en bij een verkeerde code of een geannuleerd venster moet het weer
 * terug. Een schuifje dat terugspringt leest als een storing.
 *
 * Zie docs/security/extra-stap-na-een-passkey.md.
 */
const props = defineProps<{
    aan: boolean;
    heeftPasskeys: boolean;
}>();

const vensterOpen = ref(false);
const gewenst = ref(false);

const vraag = (wens: boolean): void => {
    gewenst.value = wens;
    vensterOpen.value = true;
};

// Is de stand omgezet, dan is er niets meer te vragen.
watch(
    () => props.aan,
    () => {
        vensterOpen.value = false;
    },
);
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            :title="$t('Extra streng bij een passkey')"
            :description="
                $t('Of je na je passkey ook nog je authenticator-code invult')
            "
        />

        <div class="overflow-hidden rounded-lg border border-border">
            <div
                class="flex flex-wrap items-center justify-between gap-4 p-4 sm:p-5"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-border"
                        :class="
                            props.aan ? 'text-success' : 'text-muted-foreground'
                        "
                        aria-hidden="true"
                    >
                        <ShieldCheck class="size-5" />
                    </span>

                    <div class="min-w-0">
                        <p class="font-medium">
                            {{ $t('Ook je authenticator na een passkey') }}
                        </p>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            {{
                                props.aan
                                    ? $t('Staat aan: passkey én code.')
                                    : $t(
                                          'Staat uit: met je passkey ben je meteen binnen.',
                                      )
                            }}
                        </p>
                    </div>
                </div>

                <!--
                    `model-value` en geen `v-model`: de stand komt van de
                    server. Het schuifje stelt alleen de vraag; het venster
                    en de server bepalen het antwoord.
                -->
                <Switch
                    :model-value="props.aan"
                    :aria-label="$t('Ook je authenticator na een passkey')"
                    @update:model-value="vraag"
                />
            </div>

            <!--
                De waarschuwing hoort bij de stand "uit", want dat is de
                stand waarin er iets ontbreekt. Bij "aan" staat er geen
                vakje: dan is er niets om voor te waarschuwen.
            -->
            <div
                v-if="!props.aan && props.heeftPasskeys"
                class="flex items-start gap-3 border-t border-border bg-muted/40 p-4 text-sm sm:p-5"
            >
                <TriangleAlert
                    class="mt-0.5 size-4 shrink-0 text-warning"
                    aria-hidden="true"
                />
                <p class="text-pretty text-muted-foreground">
                    {{
                        $t(
                            'Je hebt een passkey staan. Die is in zijn eentje genoeg om in je portaal te komen -- je authenticator wordt dan niet gevraagd. Zet deze schakelaar aan als je dat te makkelijk vindt.',
                        )
                    }}
                </p>
            </div>

            <div
                v-else-if="!props.heeftPasskeys"
                class="border-t border-border bg-muted/40 p-4 text-sm text-pretty text-muted-foreground sm:p-5"
            >
                {{
                    $t(
                        'Je hebt nog geen passkey, dus deze keuze doet voorlopig niets. Zet je er later een, dan geldt wat hier staat meteen.',
                    )
                }}
            </div>
        </div>

        <PasskeyStapDialoog v-model:open="vensterOpen" :gewenst="gewenst" />
    </div>
</template>
