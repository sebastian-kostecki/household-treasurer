<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { formatMoneyAmount } from '@/lib/format';
import { cn } from '@/lib/utils';

const props = defineProps<{
    area: 'plan' | 'initial' | 'current' | 'execution' | 'remaining';
    label: string;
    amount: number;
    valueClass?: HTMLAttributes['class'];
    labelHidden?: boolean;
    /** Totals row: the heavier `amount-sum` style. */
    total?: boolean;
}>();
</script>

<template>
    <div :class="`budget-category-cell-${props.area}`">
        <span
            :class="
                props.labelHidden
                    ? 'sr-only'
                    : props.area === 'plan'
                      ? 'text-muted-foreground block text-xs md:sr-only'
                      : 'text-muted-foreground block text-xs xl:sr-only'
            "
        >
            {{ props.label }}
        </span>
        <span
            :class="
                cn(
                    'inline-flex items-baseline justify-end gap-1.5',
                    props.total ? 'amount-sum' : 'amount',
                    props.valueClass,
                )
            "
        >
            <slot />
            {{ formatMoneyAmount(props.amount) }}
        </span>
    </div>
</template>
