<script setup lang="ts">
import { Orientation } from '@unovis/ts';
import { VisAxis, VisGroupedBar, VisXYContainer } from '@unovis/vue';
import { computed } from 'vue';
import ListSurface from '@/components/ListSurface.vue';
import { ChartContainer } from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { useTranslations } from '@/composables/useTranslations';
import { formatMoney } from '@/lib/format';
import { reportChartBars } from '@/lib/report-chart';
import type { ReportChartBar } from '@/lib/report-chart';
import type { PeriodReportEntry } from '@/types/reports';

/**
 * First-level bars of a Period Report (ADR-0071): one bar per row of the
 * table below, in the same order. The chart is decoration for sighted users
 * (aria-hidden); the table next to it is the accessible form of the data.
 * With a comparison year each row has two bars: the current period above the
 * same range a year earlier, with a legend of the two years.
 */
const props = defineProps<{
    entries: PeriodReportEntry[];
    type: 'income' | 'expense';
    budgetYear?: number | null;
    comparisonYear?: number | null;
}>();

const t = useTranslations();

const compared = computed(() => props.comparisonYear != null);

type Bar = ReportChartBar;

/** Empty when no row has a positive amount: then only the table is shown. */
const bars = computed<Bar[]>(() =>
    reportChartBars(props.entries, compared.value),
);

const color = computed(() =>
    props.type === 'income' ? 'var(--success)' : 'var(--chart-1)',
);

const PREVIOUS_COLOR = 'var(--chart-2)';

const config = computed<ChartConfig>(() => ({
    value: { color: color.value },
}));

const seriesColors = computed(() =>
    compared.value ? [color.value, PREVIOUS_COLOR] : color.value,
);

const valueAccessors = computed(() =>
    compared.value
        ? [(bar: Bar) => bar.value, (bar: Bar) => bar.previousValue ?? 0]
        : (bar: Bar) => bar.value,
);

const maxValue = computed(() =>
    Math.max(
        1,
        ...bars.value.map((bar) => Math.max(bar.value, bar.previousValue ?? 0)),
    ),
);

const legend = computed(() => [
    { color: color.value, year: props.budgetYear, key: 'current' },
    { color: PREVIOUS_COLOR, year: props.comparisonYear, key: 'previous' },
]);

function legendLabel(year: number | null | undefined): string {
    return t.reports.chart_legend_previous.replace(':year', String(year ?? ''));
}

const ROW_HEIGHT_REM = 3;
const COMPARED_ROW_HEIGHT_REM = 4.5;
const AXIS_HEIGHT_REM = 2;
const NAME_WIDTH_PX = 110;
const VALUE_TICKS = 3;

const chartStyle = computed(() => ({
    height: `${bars.value.length * (compared.value ? COMPARED_ROW_HEIGHT_REM : ROW_HEIGHT_REM) + AXIS_HEIGHT_REM}rem`,
}));

const rowIndexes = computed(() => bars.value.map((bar) => bar.index));

function rowName(tick: number | Date): string {
    return typeof tick === 'number' ? (bars.value[tick]?.name ?? '') : '';
}

function valueLabel(tick: number | Date): string {
    return typeof tick === 'number' ? formatMoney(tick) : '';
}
</script>

<template>
    <ListSurface v-if="bars.length > 0" aria-hidden="true">
        <ul
            v-if="compared"
            :data-testid="`report-chart-legend-${type}`"
            class="meta text-muted-foreground mb-3 flex flex-wrap items-center gap-x-4 gap-y-1"
        >
            <li
                v-for="item in legend"
                :key="item.key"
                class="flex items-center gap-1.5"
            >
                <span
                    class="size-2.5 shrink-0 rounded-sm"
                    :style="{ backgroundColor: item.color }"
                />
                {{ legendLabel(item.year) }}
            </li>
        </ul>
        <div :data-testid="`report-chart-${type}`" :style="chartStyle">
            <ChartContainer :config="config" class="aspect-auto">
                <VisXYContainer
                    :data="bars"
                    :y-domain="[-0.5, bars.length - 0.5]"
                    :x-domain="[0, maxValue]"
                    y-direction="south"
                >
                    <VisGroupedBar
                        :x="(bar: Bar) => bar.index"
                        :y="valueAccessors"
                        :color="seriesColors"
                        :orientation="Orientation.Horizontal"
                        :rounded-corners="2"
                    />
                    <VisAxis
                        type="y"
                        :tick-values="rowIndexes"
                        :tick-format="rowName"
                        :tick-text-width="NAME_WIDTH_PX"
                        tick-text-fit-mode="wrap"
                        tick-text-trim-type="end"
                        :tick-line="false"
                        :domain-line="false"
                    />
                    <VisAxis
                        type="x"
                        :num-ticks="VALUE_TICKS"
                        :tick-format="valueLabel"
                        :tick-line="false"
                        :domain-line="false"
                        :grid-line="true"
                    />
                </VisXYContainer>
            </ChartContainer>
        </div>
    </ListSurface>
</template>
