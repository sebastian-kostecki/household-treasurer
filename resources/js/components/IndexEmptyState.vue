<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-4 px-4 py-12 text-center md:py-16"
    >
        <div class="max-w-md space-y-2">
            <h2 class="section-title">{{ title }}</h2>
            <p v-if="description" class="text-muted-foreground text-sm">
                {{ description }}
            </p>
        </div>
        <div
            v-if="$slots.actions"
            class="flex flex-wrap items-center justify-center gap-2"
        >
            <slot name="actions" />
        </div>
    </div>
</template>
