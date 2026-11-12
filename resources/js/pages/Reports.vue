<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, defineAsyncComponent } from 'vue';
import BudgetMonthPicker from '@/components/BudgetMonthPicker.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ReportEmptyState from '@/components/ReportEmptyState.vue';
import ReportEntryTable from '@/components/ReportEntryTable.vue';
import ReportStatPanel from '@/components/ReportStatPanel.vue';
import ReportTrendSummary from '@/components/ReportTrendSummary.vue';
import ReportTrendTable from '@/components/ReportTrendTable.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { useTranslations } from '@/composables/useTranslations';
import { reports as reportsRoute } from '@/routes';
import type { ReportsPageProps } from '@/types/reports';

const ReportBarChart = defineAsyncComponent(
    () => import('@/components/ReportBarChart.vue'),
);

const props = defineProps<ReportsPageProps>();

const t = useTranslations();

function trendUrl(): string {
    return reportsRoute({ query: { view: 'trend' } }).url;
}

function monthUrl(month: number): string {
    return reportsRoute({ query: { month } }).url;
}

const periodKind = computed({
    get: () => props.period.kind,
    set: (kind: 'year' | 'month' | 'trend') => {
        if (kind === props.period.kind) {
            return;
        }

        router.get(
            kind === 'year'
                ? reportsRoute().url
                : kind === 'trend'
                  ? trendUrl()
                  : monthUrl(props.period.default_month),
            {},
            { preserveScroll: true },
        );
    },
});

const periodOptions = computed(() => [
    { value: 'year' as const, label: t.reports.period_year },
    { value: 'month' as const, label: t.reports.period_month },
    { value: 'trend' as const, label: t.reports.period_trend },
]);

const trendMonths = (
    key: 'income_minor_units' | 'expense_minor_units',
): (number | null)[] => props.trend?.months.map((month) => month[key]) ?? [];

const title = computed(() => {
    if (props.budgetYear === null) {
        return t.reports.title;
    }

    if (props.period.kind === 'trend') {
        return t.reports.title_trend.replace(':year', String(props.budgetYear));
    }

    if (props.period.kind === 'month' && props.period.month !== null) {
        const monthName =
            t.budget[
                `month_name_${props.period.month}` as keyof typeof t.budget
            ];

        return t.reports.title_month
            .replace(':month', monthName)
            .replace(':year', String(props.budgetYear));
    }

    return t.reports.title_year.replace(':year', String(props.budgetYear));
});
</script>

<template>
    <Head :title="t.reports.title" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:gap-5">
        <ReportEmptyState v-if="budgetYear === null" kind="no-budget" />

        <ReportEmptyState
            v-else-if="budgetPhase !== 'execution'"
            kind="draft"
        />

        <template v-else>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="page-title">{{ title }}</h1>
                <SegmentedControl
                    v-model="periodKind"
                    :options="periodOptions"
                    :ariaLabel="t.reports.period_label"
                    tone="neutral"
                />
            </div>

            <template v-if="period.kind === 'trend' && trend !== null">
                <ReportStatPanel
                    :income-minor-units="trend.income_minor_units"
                    :expense-minor-units="trend.expense_minor_units"
                    :result-minor-units="trend.result_minor_units"
                    period-kind="year"
                    :is-in-progress="period.is_in_progress"
                />

                <IndexEmptyState
                    v-if="!trend.has_operations"
                    :title="t.reports.no_operations_title"
                    :description="t.reports.no_operations_description"
                />

                <template v-else>
                    <ReportTrendSummary :trend="trend" />
                    <ReportTrendTable
                        type="expense"
                        :title="t.reports.expense"
                        :entries="trend.expense"
                        :total-months="trendMonths('expense_minor_units')"
                        :total-minor-units="trend.expense_minor_units"
                        :in-progress-month="trend.in_progress_month"
                        :budget-year="budgetYear"
                    />
                    <ReportTrendTable
                        type="income"
                        :title="t.reports.income"
                        :entries="trend.income"
                        :total-months="trendMonths('income_minor_units')"
                        :total-minor-units="trend.income_minor_units"
                        :in-progress-month="trend.in_progress_month"
                        :budget-year="budgetYear"
                    />
                </template>
            </template>

            <template v-else-if="report !== null">
                <BudgetMonthPicker
                    v-if="period.kind === 'month' && period.month !== null"
                    :budget-year="budgetYear"
                    :viewed-month="period.month"
                    :month-url="monthUrl"
                />

                <ReportStatPanel
                    :income-minor-units="report.income_minor_units"
                    :expense-minor-units="report.expense_minor_units"
                    :result-minor-units="report.result_minor_units"
                    :period-kind="period.kind === 'month' ? 'month' : 'year'"
                    :is-in-progress="period.is_in_progress"
                    :comparison="report.comparison"
                />

                <IndexEmptyState
                    v-if="!report.has_operations"
                    :title="t.reports.no_operations_title"
                    :description="t.reports.no_operations_description"
                />

                <template v-else>
                    <ReportBarChart
                        type="expense"
                        :entries="report.expense"
                        :budget-year="budgetYear"
                        :comparison-year="report.comparison?.year"
                    />
                    <ReportEntryTable
                        type="expense"
                        :title="t.reports.expense"
                        :entries="report.expense"
                        :total-minor-units="report.expense_minor_units"
                        :budget-year="budgetYear"
                        :month="period.kind === 'month' ? period.month : null"
                        :comparison-year="report.comparison?.year"
                        :previous-total-minor-units="
                            report.comparison?.expense_minor_units
                        "
                        :difference-total-minor-units="
                            report.comparison?.expense_difference_minor_units
                        "
                    />
                    <ReportBarChart
                        type="income"
                        :entries="report.income"
                        :budget-year="budgetYear"
                        :comparison-year="report.comparison?.year"
                    />
                    <ReportEntryTable
                        type="income"
                        :title="t.reports.income"
                        :entries="report.income"
                        :total-minor-units="report.income_minor_units"
                        :budget-year="budgetYear"
                        :month="period.kind === 'month' ? period.month : null"
                        :comparison-year="report.comparison?.year"
                        :previous-total-minor-units="
                            report.comparison?.income_minor_units
                        "
                        :difference-total-minor-units="
                            report.comparison?.income_difference_minor_units
                        "
                    />
                </template>
            </template>
        </template>
    </div>
</template>
