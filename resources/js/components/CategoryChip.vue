<script setup lang="ts">
import CategoryIcon from '@/components/CategoryIcon.vue';
import { categoryTileStyle } from '@/lib/category-appearance';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        name: string;
        icon: string;
        color: string;
        /** `lg`: the tile is as tall as the name with its `subtitle` caption. */
        size?: 'sm' | 'md' | 'lg';
        showName?: boolean;
        /** Name size; defaults to `xs` for `sm` and `sm` for `md`. */
        textSize?: 'xs' | 'sm';
    }>(),
    {
        size: 'md',
        showName: true,
        textSize: undefined,
    },
);

const nameSizeClass = computed(() =>
    (props.textSize ?? (props.size === 'sm' ? 'xs' : 'sm')) === 'xs'
        ? 'text-xs'
        : '',
);

const tileSizeClass = computed(() => {
    if (props.size === 'lg') {
        return 'category-chip-lg';
    }

    return props.size === 'sm' ? 'size-6' : 'size-8';
});

const tileRadiusClass = computed(() =>
    props.size === 'sm' ? 'rounded-md' : 'rounded-lg',
);

const iconSizeClass = computed(() =>
    props.size === 'sm' ? 'size-3.5' : 'size-4',
);

const tileStyle = computed(() => categoryTileStyle(props.color));
</script>

<template>
    <span class="inline-flex max-w-full min-w-0 items-center gap-2">
        <span
            class="inline-flex shrink-0 items-center justify-center"
            :class="[tileSizeClass, tileRadiusClass]"
            :style="tileStyle"
            aria-hidden="true"
        >
            <CategoryIcon :name="icon" :class="iconSizeClass" />
        </span>
        <span v-if="showName && size === 'lg'" class="flex min-w-0 flex-col">
            <span class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                <span
                    class="truncate text-sm font-medium"
                    :class="nameSizeClass"
                >
                    {{ name }}
                </span>
                <slot name="badges" />
            </span>
            <slot name="subtitle" />
        </span>
        <span
            v-else-if="showName"
            class="truncate text-sm font-medium"
            :class="nameSizeClass"
        >
            {{ name }}
        </span>
    </span>
</template>
