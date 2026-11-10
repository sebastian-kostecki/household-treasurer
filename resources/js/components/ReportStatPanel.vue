<script setup lang="ts">
import { computed } from 'vue';
import StatPanel from '@/components/StatPanel.vue';
import type { StatPanelMetric } from '@/components/StatPanel.vue';
import { useTranslations } from '@/composables/useTranslations';
import { amountToneClass } from '@/lib/amount-tone';
import { formatMoney } from '@/lib/format';

const props = defineProps<{
    incomeMinorUnits: number;
    expenseMinorUnits: number;
    resultMinorUnits: number;
    periodKind: 'year' | 'month';
    /** The period includes today; the result is not final yet. */
    isInProgress: boolean;
}>();

const t = useTranslations();

const metrics = computed<StatPanelMetric[]>(() => [
    {
        id: 'result',
        label:
            props.periodKind === 'year'
                ? t.reports.result_year
                : t.reports.result,
        value: formatMoney(props.resultMinorUnits),
        toneClass: amountToneClass(props.resultMinorUnits),
        caption: props.isInProgress ? t.reports.in_progress : undefined,
    },
    {
        id: 'income',
        label: t.reports.income,
        value: formatMoney(props.incomeMinorUnits),
    },
    {
        id: 'expense',
        label: t.reports.expense,
        value: formatMoney(props.expenseMinorUnits),
    },
]);
</script>

<template>
    <StatPanel :metrics="metrics" />
</template>
