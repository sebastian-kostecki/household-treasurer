<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';

const props = defineProps<{
    area: 'plan' | 'initial' | 'current' | 'execution' | 'remaining';
    label: string;
    amount: number;
    valueClass?: HTMLAttributes['class'];
    labelHidden?: boolean;
}>();
</script>

<template>
    <div :class="`budget-category-cell-${props.area}`">
        <span
            :class="
                props.labelHidden
                    ? 'sr-only'
                    : 'text-muted-foreground block text-xs md:sr-only'
            "
        >
            {{ props.label }}
        </span>
        <span
            :class="
                cn(
                    'inline-flex items-baseline justify-end gap-1.5',
                    props.valueClass,
                )
            "
        >
            {{ formatMoney(props.amount) }}
            <slot />
        </span>
    </div>
</template>
