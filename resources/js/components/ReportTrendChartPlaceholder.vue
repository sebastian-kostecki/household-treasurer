<script setup lang="ts">
import { computed } from 'vue';
import ReportChartLegend from '@/components/ReportChartLegend.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { useTranslations } from '@/composables/useTranslations';
import {
    reportTrendChartBars,
    reportTrendInProgressNote,
    reportTrendLegend,
} from '@/lib/report-trend-chart';
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

const legend = computed(() => reportTrendLegend(t.reports));

const inProgressNote = computed(() =>
    reportTrendInProgressNote(props.inProgressMonth, t.reports, t.budget),
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
