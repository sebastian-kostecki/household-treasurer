<script setup lang="ts">
import { AlertTriangle } from '@lucide/vue';
import { computed } from 'vue';
import StatPanel, { type StatPanelMetric } from '@/components/StatPanel.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoney } from '@/lib/format';
import { monthElapsedPercent } from '@/lib/month-pace-calculator';

const props = defineProps<{
    toSpendMinorUnits: number;
    incomeMinorUnits: number;
    expenseMinorUnits: number;
    toSpendLabel: 'live' | 'final' | 'default';
    /** Null for a month that has not started: there is no pace to read. */
    pace: {
        percent: number | null;
        isAhead: boolean;
        elapsedDay: number;
        daysInMonth: number;
    } | null;
}>();

const t = useTranslations();

const toSpendTitle = computed(() => {
    if (props.toSpendLabel === 'final') {
        return t.month_plan.to_spend_final;
    }

    if (props.toSpendLabel === 'live') {
        return `${t.month_plan.to_spend} · ${t.month_plan.to_spend_live}`;
    }

    return t.month_plan.to_spend;
});

const paceCaption = computed(() => {
    if (props.pace === null) {
        return t.month_plan.pace_not_started;
    }

    if (props.pace.percent === null) {
        return t.month_plan.pace_none;
    }

    return t.month_plan.pace_days
        .replace(':days', String(props.pace.daysInMonth))
        .replace(':day', String(props.pace.elapsedDay))
        .replace(
            ':elapsed',
            String(
                monthElapsedPercent(
                    props.pace.elapsedDay,
                    props.pace.daysInMonth,
                ),
            ),
        );
});

const metrics = computed<StatPanelMetric[]>(() => [
    {
        label: toSpendTitle.value,
        value: formatMoney(props.toSpendMinorUnits),
        caption: t.month_plan.to_spend_caption,
        toneClass: props.toSpendMinorUnits < 0 ? 'text-destructive' : undefined,
    },
    {
        label: t.month_plan.income_metric,
        value: formatMoney(props.incomeMinorUnits),
        toneClass: 'text-success',
    },
    {
        label: t.month_plan.expense_metric,
        value: formatMoney(props.expenseMinorUnits),
        caption: t.month_plan.expense_caption,
    },
    {
        label: t.month_plan.pace,
        value:
            props.pace === null || props.pace.percent === null
                ? '—'
                : `${props.pace.percent}%`,
        caption: paceCaption.value,
        toneClass: props.pace?.isAhead ? 'text-warning' : undefined,
    },
]);
</script>

<template>
    <div class="grid gap-3">
        <StatPanel :metrics="metrics" />
        <Alert v-if="pace?.isAhead" variant="note">
            <AlertTriangle aria-hidden="true" />
            <AlertDescription>
                {{ t.month_plan.pace_ahead }}
            </AlertDescription>
        </Alert>
    </div>
</template>
