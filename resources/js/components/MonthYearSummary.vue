<script setup lang="ts">
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoneyAmount } from '@/lib/format';
import type { MonthYearComparison } from '@/types/month-plan';

const props = defineProps<{
    comparison: MonthYearComparison;
    type: 'income' | 'expense';
}>();

const t = useTranslations();

/** Expense: the server decides (`is_over_year`). Income has no such flag, so a negative remainder is its "over the annual plan". */
const isOver = computed(() =>
    props.type === 'expense'
        ? props.comparison.is_over_year
        : props.comparison.remaining_minor_units < 0,
);

const remainingClass = computed(() => {
    if (!isOver.value) {
        return '';
    }

    return props.type === 'expense' ? 'text-destructive' : 'text-success';
});

const remainingLabel = computed(() => {
    if (!isOver.value) {
        return t.month_plan.year_remaining;
    }

    return props.type === 'expense'
        ? t.month_plan.year_over_expense
        : t.month_plan.year_over_income;
});
</script>

<template>
    <span class="tabular-nums">
        {{ formatMoneyAmount(comparison.year_minor_units) }}
        {{ t.month_plan.year_of }}
        {{ formatMoneyAmount(comparison.annual_plan_minor_units) }}
        ·
        <span :class="remainingClass">
            {{ remainingLabel }}
            {{ formatMoneyAmount(Math.abs(comparison.remaining_minor_units)) }}
        </span>
    </span>
</template>
