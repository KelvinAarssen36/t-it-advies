<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * De onzichtbare velden van spatie/laravel-honeypot.
 *
 * Een bot vult elk veld in dat hij tegenkomt; een mens ziet deze niet eens.
 * Het tweede veld bevat een versleutelde tijdstempel, waarmee de server ook
 * inzendingen weigert die onmenselijk snel binnenkomen.
 *
 * De veldnamen wisselen per request en komen daarom uit de gedeelde
 * Inertia-props (zie HandleInertiaRequests).
 *
 * Belangrijk: verberg deze velden met CSS die een bot niet makkelijk leest,
 * en zet er nooit `type="hidden"` op -- dat is juist het signaal waar een
 * slimme bot op filtert.
 */

const page = usePage();

const honeypot = computed(() => page.props.honeypot);
</script>

<template>
    <div
        v-if="honeypot?.enabled"
        :id="honeypot.nameFieldName + '_wrap'"
        style="position: absolute; left: -9999px; top: -9999px"
        aria-hidden="true"
    >
        <input
            :id="honeypot.nameFieldName"
            :name="honeypot.nameFieldName"
            type="text"
            value=""
            tabindex="-1"
            autocomplete="off"
        />
        <input
            :name="honeypot.validFromFieldName"
            type="text"
            :value="honeypot.encryptedValidFrom"
            tabindex="-1"
            autocomplete="off"
        />
    </div>
</template>
