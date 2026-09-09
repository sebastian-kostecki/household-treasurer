<script setup lang="ts">
import CategoryIcon from '@/components/CategoryIcon.vue';
import { categoryTileStyle } from '@/lib/category-appearance';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        name: string;
        icon: string;
        color: string;
        size?: 'sm' | 'md';
        showName?: boolean;
    }>(),
    {
        size: 'md',
        showName: true,
    },
);

const tileSizeClass = computed(() =>
    props.size === 'sm' ? 'size-6' : 'size-8',
);

const tileRadiusClass = computed(() =>
    props.size === 'sm' ? 'rounded-md' : 'rounded-lg',
);

const iconSizeClass = computed(() =>
    props.size === 'sm' ? 'size-3.5' : 'size-4',
);

const tileStyle = computed(() => categoryTileStyle(props.color));
</script>

<template>
    <span class="inline-flex min-w-0 max-w-full items-center gap-2">
        <span
            class="inline-flex shrink-0 items-center justify-center"
            :class="[tileSizeClass, tileRadiusClass]"
            :style="tileStyle"
            aria-hidden="true"
        >
            <CategoryIcon :name="icon" :class="iconSizeClass" />
        </span>
        <span
            v-if="showName"
            class="truncate text-sm font-medium"
            :class="size === 'sm' ? 'text-xs' : ''"
        >
            {{ name }}
        </span>
    </span>
</template>
