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
    <div v-show="message">
        <p
            :id="id"
            class="text-destructive"
            :class="size === 'xs' ? 'text-xs/4' : 'text-sm'"
        >
            {{ message }}
        </p>
    </div>
</template>
