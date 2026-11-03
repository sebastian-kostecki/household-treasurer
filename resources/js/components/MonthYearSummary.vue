<script setup lang="ts">
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoney } from '@/lib/format';
import type { MonthYearComparison } from '@/types/month-plan';

const props = defineProps<{
    comparison: MonthYearComparison;
    type: 'income' | 'expense';
}>();

const t = useTranslations();

const remainingClass = computed(() => {
    if (props.comparison.remaining_minor_units >= 0) {
        return '';
    }

    return props.type === 'expense' ? 'text-destructive' : 'text-success';
});

const remainingLabel = computed(() => {
    if (props.comparison.remaining_minor_units >= 0) {
        return t.month_plan.year_remaining;
    }

    return props.type === 'expense'
        ? t.month_plan.year_over_expense
        : t.month_plan.year_over_income;
});
</script>

<template>
    <span class="tabular-nums">
        {{ formatMoney(comparison.year_minor_units) }}
        {{ t.month_plan.year_of }}
        {{ formatMoney(comparison.annual_plan_minor_units) }}
        ·
        <span :class="remainingClass">
            {{ remainingLabel }}
            {{ formatMoney(Math.abs(comparison.remaining_minor_units)) }}
        </span>
    </span>
</template>
