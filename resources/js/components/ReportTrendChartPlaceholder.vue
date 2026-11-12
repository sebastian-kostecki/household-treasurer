<script setup lang="ts">
import { computed } from 'vue';
import ReportChartLegend from '@/components/ReportChartLegend.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { useTranslations } from '@/composables/useTranslations';
import { reportTrendChartBars } from '@/lib/report-trend-chart';
import type { ReportTrendMonth } from '@/types/reports';

/**
 * Holds the place of `ReportTrendChart` while its lazy fragment loads: the
 * same props, the same legend and a block of the chart's height.
 */
const props = defineProps<{
    months: ReportTrendMonth[];
    inProgressMonth: number | null;
}>();

const t = useTranslations();

const hasBars = computed(() => reportTrendChartBars(props.months).length > 0);

const legend = computed(() => [
    { key: 'income', color: 'var(--success)', label: t.reports.income },
    { key: 'expense', color: 'var(--chart-1)', label: t.reports.expense },
]);

const inProgressNote = computed(() =>
    props.inProgressMonth === null
        ? null
        : t.reports.trend_legend_in_progress.replace(
              ':month',
              t.budget[
                  `month_name_${props.inProgressMonth}` as keyof typeof t.budget
              ],
          ),
);
</script>

<template>
    <div v-if="hasBars" class="p-4" aria-hidden="true">
        <ReportChartLegend
            :items="legend"
            :note="inProgressNote"
            test-id="report-trend-chart-legend"
        />
        <Skeleton class="h-64" data-testid="report-trend-chart-placeholder" />
    </div>
</template>
