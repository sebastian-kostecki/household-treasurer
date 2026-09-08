<script setup lang="ts">
defineProps<{
    name: string;
}>();
</script>

<template>
    <span
        class="inline-flex max-w-full items-center rounded-lg border border-border/60 bg-muted/50 px-2 py-0.5 text-xs font-medium text-foreground"
    >
        <span class="truncate">{{ name }}</span>
    </span>
</template>
