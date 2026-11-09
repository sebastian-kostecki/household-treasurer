<script setup lang="ts">
withDefaults(
    defineProps<{
        message?: string;
        id?: string;
        size?: 'sm' | 'xs';
    }>(),
    { size: 'sm' },
);
</script>

<template>
    <p
        v-if="message"
        :id="id"
        role="alert"
        class="text-destructive"
        :class="size === 'xs' ? 'text-xs/4' : 'text-sm'"
    >
        {{ message }}
    </p>
</template>
