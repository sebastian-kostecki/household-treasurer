<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import ListSurface from '@/components/ListSurface.vue';
import ReportTrendRow from '@/components/ReportTrendRow.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { ReportTrendData } from '@/types/reports';

const ReportTrendChart = defineAsyncComponent(
    () => import('@/components/ReportTrendChart.vue'),
);

const props = defineProps<{
    trend: ReportTrendData;
}>();

const t = useTranslations();

const incomeMonths = computed(() =>
    props.trend.months.map((month) => month.income_minor_units),
);
const expenseMonths = computed(() =>
    props.trend.months.map((month) => month.expense_minor_units),
);
const resultMonths = computed(() =>
    props.trend.months.map((month) => month.result_minor_units),
);

const regionLabel = computed(() =>
    t.reports.trend_table_label.replace(':title', t.reports.trend_months_title),
);
</script>

<template>
    <ListSurface flush data-trend-table="summary">
        <template #header>
            <h2 class="section-title">{{ t.reports.trend_months_title }}</h2>
        </template>

        <ReportTrendChart
            :months="trend.months"
            :in-progress-month="trend.in_progress_month"
        />

        <div
            class="report-trend-scroll"
            role="region"
            tabindex="0"
            :aria-label="regionLabel"
        >
            <div role="table" :aria-label="t.reports.trend_months_title">
                <div role="rowgroup">
                    <ReportTrendRow
                        variant="header"
                        :in-progress-month="trend.in_progress_month"
                    >
                        {{ t.reports.trend_measure }}
                    </ReportTrendRow>
                </div>
                <div role="rowgroup">
                    <ReportTrendRow
                        variant="row"
                        :months="incomeMonths"
                        :total-minor-units="trend.income_minor_units"
                        data-trend-measure="income"
                    >
                        {{ t.reports.income }}
                    </ReportTrendRow>
                    <ReportTrendRow
                        variant="row"
                        :months="expenseMonths"
                        :total-minor-units="trend.expense_minor_units"
                        data-trend-measure="expense"
                    >
                        {{ t.reports.expense }}
                    </ReportTrendRow>
                    <ReportTrendRow
                        variant="total"
                        sign-tone
                        :months="resultMonths"
                        :total-minor-units="trend.result_minor_units"
                        data-trend-measure="result"
                    >
                        <span class="section-title">{{
                            t.reports.result
                        }}</span>
                    </ReportTrendRow>
                </div>
            </div>
        </div>

        <template #footer>
            <p class="meta text-muted-foreground">
                {{ t.reports.amounts_in_zl_rounded }}
            </p>
        </template>
    </ListSurface>
</template>
