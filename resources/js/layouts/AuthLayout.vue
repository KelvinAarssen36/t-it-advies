<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';

const {
    title = '',
    description = '',
    variant = 'merk',
} = defineProps<{
    title?: string;
    description?: string;
    /** Zie AuthSimpleLayout: 'merk' is de voordeur, 'portaal' een onderbreking. */
    variant?: 'merk' | 'portaal';
}>();
</script>

<template>
    <AuthLayout :title="title" :description="description" :variant="variant">
        <slot />
    </AuthLayout>
</template>
