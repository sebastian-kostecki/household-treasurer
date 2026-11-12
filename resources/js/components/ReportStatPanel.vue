<script setup lang="ts">
import { AlertTriangle } from '@lucide/vue';
import { computed } from 'vue';
import StatPanel from '@/components/StatPanel.vue';
import type { StatPanelMetric } from '@/components/StatPanel.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useTranslations } from '@/composables/useTranslations';
import { amountToneClass } from '@/lib/amount-tone';
import { formatDate, formatMoney, formatSignedMoney } from '@/lib/format';
import { planChangeToneClass } from '@/lib/plan-change-direction';
import { reportDifferenceTone } from '@/lib/report-difference';
import type { ReportMeasureKind } from '@/lib/report-difference';
import type { PeriodReportComparison } from '@/types/reports';

const props = defineProps<{
    incomeMinorUnits: number;
    expenseMinorUnits: number;
    resultMinorUnits: number;
    periodKind: 'year' | 'month' | 'last-12';
    /** The period includes today; the result is not final yet. */
    isInProgress: boolean;
    /** The same period a year earlier; the tiles show it only when set. */
    comparison?: PeriodReportComparison | null;
}>();

const t = useTranslations();

const resultLabel = computed(() => {
    if (props.periodKind === 'last-12') {
        return t.reports.result_last_12;
    }

    return props.periodKind === 'year'
        ? t.reports.result_year
        : t.reports.result;
});

const metrics = computed<StatPanelMetric[]>(() => [
    {
        id: 'result',
        label: resultLabel.value,
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

type ComparisonLine = {
    id: ReportMeasureKind;
    previous: number;
    difference: number;
};

const lines = computed<ComparisonLine[]>(() => {
    const comparison = props.comparison;

    if (!comparison) {
        return [];
    }

    return [
        {
            id: 'result',
            previous: comparison.result_minor_units,
            difference: comparison.result_difference_minor_units,
        },
        {
            id: 'income',
            previous: comparison.income_minor_units,
            difference: comparison.income_difference_minor_units,
        },
        {
            id: 'expense',
            previous: comparison.expense_minor_units,
            difference: comparison.expense_difference_minor_units,
        },
    ];
});

function previousText(previous: number): string {
    return t.reports.previous_year_value
        .replace(':year', String(props.comparison?.year))
        .replace(':amount', formatMoney(previous));
}

const partialNote = computed(() =>
    t.reports.comparison_partial_note
        .replace(':year', String(props.comparison?.year))
        .replace(':from', formatDate(props.comparison?.from ?? ''))
        .replace(':through', formatDate(props.comparison?.through ?? '')),
);

function differenceClass(line: ComparisonLine): string {
    const tone = reportDifferenceTone(line.difference, line.id);

    return tone === null ? '' : planChangeToneClass(tone, false);
}
</script>

<template>
    <div class="grid gap-3">
        <StatPanel :metrics="metrics">
            <template
                v-for="line in lines"
                :key="line.id"
                #[`footer-${line.id}`]
            >
                <p class="meta text-muted-foreground">
                    {{ previousText(line.previous) }}
                </p>
                <p class="meta" :class="differenceClass(line)">
                    {{ formatSignedMoney(line.difference) }}
                </p>
            </template>
        </StatPanel>
        <Alert v-if="comparison?.is_partial" variant="note">
            <AlertTriangle aria-hidden="true" />
            <AlertDescription>
                {{ partialNote }}
            </AlertDescription>
        </Alert>
    </div>
</template>
