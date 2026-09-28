<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Bevestig je e-mailadres',
        description:
            'We hebben je een link gestuurd. Klik daarop om je e-mailadres te bevestigen.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head :title="$t('Bevestig je e-mailadres')" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ $t('Er is een nieuwe link naar je e-mailadres gestuurd.') }}
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            {{ $t('Stuur de link opnieuw') }}
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            {{ $t('Uitloggen') }}
        </TextLink>
    </Form>
</template>
