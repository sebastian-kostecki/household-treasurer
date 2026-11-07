<script setup lang="ts">
import type { Component } from 'vue';
import AuthLayout from '@/layouts/auth/AuthCardLayout.vue';

const {
    title = '',
    description = '',
    icon,
} = defineProps<{
    title?: string;
    description?: string;
    icon?: Component;
}>();
</script>

<template>
    <AuthLayout :title="title" :description="description" :icon="icon">
        <slot />
    </AuthLayout>
</template>
