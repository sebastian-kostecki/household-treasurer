<script setup lang="ts">
import { computed } from 'vue';
import StatPanel, { type StatPanelMetric } from '@/components/StatPanel.vue';
import { useTranslations } from '@/composables/useTranslations';
import { amountToneClass } from '@/lib/amount-tone';
import type { DashboardTypeTotals } from '@/lib/dashboard-categories';
import { formatMoney } from '@/lib/format';

const props = defineProps<{
    incomeTotals: DashboardTypeTotals;
    expenseTotals: DashboardTypeTotals;
    plannedBalanceMinorUnits: number;
    expensePercentComplete?: number | null;
    /** Elapsed share of the year; null or omitted hides the execution metric. */
    yearElapsedPercent?: number | null;
}>();

const t = useTranslations();

const metrics = computed<StatPanelMetric[]>(() => {
    const planMetrics: StatPanelMetric[] = [
        {
            label: t.budget.planned_income,
            value: formatMoney(props.incomeTotals.current_plan_minor_units),
        },
        {
            label: t.budget.planned_expense,
            value: formatMoney(props.expenseTotals.current_plan_minor_units),
        },
        {
            label: t.budget.planned_balance,
            value: formatMoney(props.plannedBalanceMinorUnits),
            toneClass: amountToneClass(props.plannedBalanceMinorUnits),
        },
    ];

    if (props.yearElapsedPercent == null) {
        return planMetrics;
    }

    return [
        ...planMetrics,
        {
            label: t.budget.execution_expense_metric,
            value:
                props.expensePercentComplete == null
                    ? '—'
                    : `${props.expensePercentComplete}%`,
            caption: t.budget.year_progress.replace(
                ':percent',
                String(Math.round(props.yearElapsedPercent)),
            ),
        },
    ];
});
</script>

<template>
    <StatPanel :metrics="metrics" />
</template>
