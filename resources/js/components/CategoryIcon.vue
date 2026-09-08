<script setup lang="ts">
import { resolveCategoryIcon } from '@/lib/category-appearance';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        name: string;
        class?: string;
        strokeWidth?: number;
    }>(),
    {
        strokeWidth: 1.7,
    },
);

const iconComponent = computed(() => resolveCategoryIcon(props.name));
</script>

<template>
    <component
        :is="iconComponent"
        :class="props.class"
        :stroke-width="strokeWidth"
        aria-hidden="true"
    />
</template>
