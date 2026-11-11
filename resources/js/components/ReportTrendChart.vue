<script setup lang="ts">
import { Orientation } from '@unovis/ts';
import { VisAxis, VisGroupedBar, VisXYContainer } from '@unovis/vue';
import { computed } from 'vue';
import { ChartContainer } from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { useTranslations } from '@/composables/useTranslations';
import { formatWholeMoney } from '@/lib/format';
import { reportTrendChartBars } from '@/lib/report-trend-chart';
import type { ReportTrendChartBar } from '@/lib/report-trend-chart';
import type { ReportTrendMonth } from '@/types/reports';

/**
 * Month-by-month bars of the Trend (ADR-0076): income and expense per month,
 * the same numbers as the Przychody and Wydatki rows of the measures table
 * below. The chart is decoration for sighted users (aria-hidden); the table is
 * the accessible form of the data.
 */
const props = defineProps<{
    months: ReportTrendMonth[];
    inProgressMonth: number | null;
}>();

const t = useTranslations();

type Bar = ReportTrendChartBar;

/** Empty when no month has a positive amount: then only the table is shown. */
const bars = computed<Bar[]>(() => reportTrendChartBars(props.months));

const INCOME_COLOR = 'var(--success)';
const EXPENSE_COLOR = 'var(--chart-1)';

const config: ChartConfig = {
    income: { color: INCOME_COLOR },
    expense: { color: EXPENSE_COLOR },
};

const legend = computed(() => [
    { key: 'income', color: INCOME_COLOR, label: t.reports.income },
    { key: 'expense', color: EXPENSE_COLOR, label: t.reports.expense },
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

const VALUE_TICKS = 4;

const monthIndexes = computed(() => bars.value.map((_, index) => index));

function monthLabel(tick: number | Date): string {
    const bar = typeof tick === 'number' ? bars.value[tick] : undefined;

    return bar === undefined
        ? ''
        : t.budget[`month_chip_${bar.month}` as keyof typeof t.budget];
}

function valueLabel(tick: number | Date): string {
    return typeof tick === 'number' ? formatWholeMoney(tick) : '';
}
</script>

<template>
    <div v-if="bars.length > 0" class="p-4" aria-hidden="true">
        <ul
            data-testid="report-trend-chart-legend"
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
                {{ item.label }}
            </li>
            <li v-if="inProgressNote !== null">{{ inProgressNote }}</li>
        </ul>
        <div data-testid="report-trend-chart" class="h-64">
            <ChartContainer :config="config" class="aspect-auto">
                <VisXYContainer :data="bars">
                    <VisGroupedBar
                        :x="(_bar: Bar, index: number) => index"
                        :y="[
                            (bar: Bar) => bar.income,
                            (bar: Bar) => bar.expense,
                        ]"
                        :color="[INCOME_COLOR, EXPENSE_COLOR]"
                        :orientation="Orientation.Vertical"
                        :rounded-corners="2"
                    />
                    <VisAxis
                        type="x"
                        :tick-values="monthIndexes"
                        :tick-format="monthLabel"
                        :tick-text-hide-overlapping="true"
                        :tick-line="false"
                        :domain-line="false"
                    />
                    <VisAxis
                        type="y"
                        :num-ticks="VALUE_TICKS"
                        :tick-format="valueLabel"
                        :tick-line="false"
                        :domain-line="false"
                        :grid-line="true"
                    />
                </VisXYContainer>
            </ChartContainer>
        </div>
    </div>
</template>
