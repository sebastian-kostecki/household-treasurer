<script setup lang="ts">
import { Orientation } from '@unovis/ts';
import { VisAxis, VisGroupedBar, VisXYContainer } from '@unovis/vue';
import { computed } from 'vue';
import ListSurface from '@/components/ListSurface.vue';
import { ChartContainer } from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { formatMoney } from '@/lib/format';
import { reportChartBars } from '@/lib/report-chart';
import type { ReportChartBar } from '@/lib/report-chart';
import type { PeriodReportEntry } from '@/types/reports';

/**
 * First-level bars of a Period Report (ADR-0071): one bar per row of the
 * table below, in the same order. The chart is decoration for sighted users
 * (aria-hidden); the table next to it is the accessible form of the data.
 */
const props = defineProps<{
    entries: PeriodReportEntry[];
    type: 'income' | 'expense';
}>();

type Bar = ReportChartBar;

/** Empty when no row has a positive amount: then only the table is shown. */
const bars = computed<Bar[]>(() => reportChartBars(props.entries));

const color = computed(() =>
    props.type === 'income' ? 'var(--success)' : 'var(--chart-1)',
);

const config = computed<ChartConfig>(() => ({
    value: { color: color.value },
}));

const maxValue = computed(() =>
    Math.max(1, ...bars.value.map((bar) => bar.value)),
);

const ROW_HEIGHT_REM = 3;
const AXIS_HEIGHT_REM = 2;
const NAME_WIDTH_PX = 110;
const VALUE_TICKS = 3;

const chartStyle = computed(() => ({
    height: `${bars.value.length * ROW_HEIGHT_REM + AXIS_HEIGHT_REM}rem`,
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
                        :y="(bar: Bar) => bar.value"
                        :color="color"
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
