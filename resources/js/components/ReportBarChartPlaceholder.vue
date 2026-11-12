<script setup lang="ts">
import { computed } from 'vue';
import ListSurface from '@/components/ListSurface.vue';
import ReportChartLegend from '@/components/ReportChartLegend.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { useTranslations } from '@/composables/useTranslations';
import { reportChartBars, reportChartHeightRem } from '@/lib/report-chart';
import type { PeriodReportEntry } from '@/types/reports';

/**
 * Holds the place of `ReportBarChart` while its lazy fragment loads: the same
 * props (it receives them from `defineAsyncComponent`), the same legend and a
 * block of the chart's height, so the table below does not jump.
 */
const props = defineProps<{
    entries: PeriodReportEntry[];
    type: 'income' | 'expense';
    budgetYear?: number | null;
    comparisonYear?: number | null;
}>();

const t = useTranslations();

const compared = computed(() => props.comparisonYear != null);

const barCount = computed(
    () => reportChartBars(props.entries, compared.value).length,
);

function legendLabel(year: number | null | undefined): string {
    return t.reports.chart_legend_previous.replace(':year', String(year ?? ''));
}

const legend = computed(() => [
    {
        key: 'current',
        color: props.type === 'income' ? 'var(--success)' : 'var(--chart-1)',
        label: legendLabel(props.budgetYear),
    },
    {
        key: 'previous',
        color: 'var(--chart-2)',
        label: legendLabel(props.comparisonYear),
    },
]);

const skeletonStyle = computed(() => ({
    height: `${reportChartHeightRem(barCount.value, compared.value)}rem`,
}));
</script>

<template>
    <ListSurface v-if="barCount > 0" aria-hidden="true">
        <ReportChartLegend
            v-if="compared"
            :items="legend"
            :test-id="`report-chart-legend-${type}`"
        />
        <Skeleton
            :data-testid="`report-chart-placeholder-${type}`"
            :style="skeletonStyle"
        />
    </ListSurface>
</template>
