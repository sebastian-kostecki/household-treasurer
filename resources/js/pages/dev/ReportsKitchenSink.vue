<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { defineAsyncComponent } from 'vue';
import BudgetMonthPicker from '@/components/BudgetMonthPicker.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ReportCoverageNote from '@/components/ReportCoverageNote.vue';
import ReportEmptyState from '@/components/ReportEmptyState.vue';
import ReportEntryTable from '@/components/ReportEntryTable.vue';
import ReportStatPanel from '@/components/ReportStatPanel.vue';
import ReportTrendSummary from '@/components/ReportTrendSummary.vue';
import ReportTrendTable from '@/components/ReportTrendTable.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { useTranslations } from '@/composables/useTranslations';
import { reportMonthNamesFrom, reportWindowRange } from '@/lib/report-window';
import { reportScenarios } from '@/lib/reports-fixtures';
import type { ReportRange, ReportWindow } from '@/types/reports';

const ReportBarChart = defineAsyncComponent(
    () => import('@/components/ReportBarChart.vue'),
);

const t = useTranslations();

const periodOptions = [
    { value: 'year' as const, label: t.reports.period_year },
    { value: 'month' as const, label: t.reports.period_month },
    { value: 'last-12' as const, label: t.reports.period_last_12 },
    { value: 'trend' as const, label: t.reports.period_trend },
];

const monthNames = reportMonthNamesFrom(t.budget);

function windowRange(window: ReportWindow): ReportRange {
    return reportWindowRange(window, monthNames, t.reports.window_range);
}

function windowLabel(window: ReportWindow): string {
    return windowRange(window).label;
}

function trendMonths(
    months: {
        income_minor_units: number | null;
        expense_minor_units: number | null;
    }[],
    key: 'income_minor_units' | 'expense_minor_units',
): (number | null)[] {
    return months.map((month) => month[key]);
}

function monthUrl(month: number): string {
    return `#month-${month}`;
}

const stateMatrix = [
    {
        state: 'default',
        where: 'Scenariusze year-in-progress, month-closed, archived-in-group, refund-negative-share, non-positive-expenses poniżej.',
    },
    {
        state: 'comparison',
        where: 'Scenariusze compared-year-in-progress (z notką), compared-month-closed, compared-one-side-only, compared-empty-current i compared-phone (dwie linie wiersza); scenariusze bez przedrostka compared- to raport bez porównania.',
    },
    {
        state: 'trend',
        where: 'Scenariusze trend-in-progress (miesiąc „w toku”, miesiące przyszłe z „—”), trend-closed, trend-irregular-and-zero (kategoria nieregularna, netto 0, ujemna komórka), trend-negative-result, trend-archived-in-group, trend-no-operations i trend-phone (przewijanie z przypiętą nazwą). Grupy startują zwinięte.',
    },
    {
        state: 'last-12',
        where: 'Scenariusze last-12-full (pełne pokrycie), last-12-partial (notka, brak na początku), last-12-partial-end (notka, brak na końcu), last-12-no-coverage (stan pusty), last-12-no-operations i last-12-phone (cztery segmenty w 22rem). Okno bez paska miesięcy, bez porównania i bez Trendu.',
    },
    {
        state: 'empty',
        where: 'Scenariusze no-operations, trend-no-operations, last-12-no-coverage, last-12-no-operations, draft i no-budget poniżej.',
    },
    {
        state: 'loading',
        where: 'N/A: strona nie ma deferred props; zmiana okresu to zwykła wizyta Inertia z paskiem postępu.',
    },
    {
        state: 'error',
        where: 'N/A: strona tylko czyta, nie ma formularzy ani żądań w tle; błąd ładowania obsługuje strona błędu Inertia.',
    },
    {
        state: 'disabled',
        where: 'N/A: przełącznik okresu i wiersze grup nie mają stanu wyłączonego.',
    },
    {
        state: 'hover',
        where: 'Nagłówek grupy i przyciski miesięcy w scenariuszach poniżej (najechanie myszą).',
    },
    {
        state: 'focus-visible',
        where: 'Kolejność Tab w scenariuszach: przełącznik okresu, miesiące, przyciski zwijania grup.',
    },
];
</script>

<template>
    <Head title="Reports kitchen sink" />

    <div class="flex flex-1 flex-col gap-8 p-4">
        <header class="space-y-1">
            <h1 class="page-title">Reports kitchen sink</h1>
            <p class="text-muted-foreground text-sm">
                Strona deweloperska (tylko środowisko local). Komponenty strony
                Raporty w każdym scenariuszu danych z fixture.
            </p>
        </header>

        <section class="space-y-3" data-section="state-matrix">
            <h2 class="section-title border-b pb-1">Macierz stanów</h2>
            <table class="w-full text-left text-sm">
                <thead>
                    <tr>
                        <th class="table-header pb-2.5 text-left">Stan</th>
                        <th class="table-header pb-2.5 text-left">
                            Gdzie widoczny
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in stateMatrix"
                        :key="row.state"
                        class="border-t align-top"
                    >
                        <td class="py-2 pr-4 font-mono">{{ row.state }}</td>
                        <td class="py-2">{{ row.where }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section
            v-for="scenario in reportScenarios"
            :key="scenario.key"
            :data-scenario="scenario.key"
            class="space-y-2"
        >
            <h2 class="section-title border-b pb-1">
                {{ scenario.key }}
            </h2>
            <p class="text-muted-foreground text-sm">{{ scenario.caption }}</p>

            <div
                class="flex flex-col gap-4"
                :class="{ 'report-phone-frame': scenario.phone }"
            >
                <ReportEmptyState
                    v-if="scenario.kind === 'no-budget'"
                    kind="no-budget"
                />

                <ReportEmptyState
                    v-else-if="scenario.kind === 'draft'"
                    kind="draft"
                />

                <template
                    v-else-if="
                        scenario.kind === 'last-12' &&
                        scenario.period.window !== null &&
                        scenario.report !== null
                    "
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div class="grid gap-1">
                            <p class="page-title">
                                {{ t.reports.title_last_12 }}
                            </p>
                            <p class="meta text-muted-foreground">
                                {{ windowLabel(scenario.period.window) }}
                            </p>
                        </div>
                        <SegmentedControl
                            model-value="last-12"
                            :options="periodOptions"
                            :ariaLabel="t.reports.period_label"
                            tone="neutral"
                        />
                    </div>

                    <IndexEmptyState
                        v-if="scenario.period.window.covered_month_count === 0"
                        :title="t.reports.no_coverage_title"
                        :description="t.reports.no_coverage_description"
                    />

                    <template v-else>
                        <ReportCoverageNote :window="scenario.period.window" />

                        <ReportStatPanel
                            :income-minor-units="
                                scenario.report.income_minor_units
                            "
                            :expense-minor-units="
                                scenario.report.expense_minor_units
                            "
                            :result-minor-units="
                                scenario.report.result_minor_units
                            "
                            period-kind="last-12"
                            :is-in-progress="false"
                        />

                        <IndexEmptyState
                            v-if="!scenario.report.has_operations"
                            :title="t.reports.no_operations_title"
                            :description="t.reports.no_operations_description"
                        />

                        <template v-else>
                            <ReportBarChart
                                type="expense"
                                :entries="scenario.report.expense"
                                :budget-year="2026"
                            />
                            <ReportEntryTable
                                type="expense"
                                :title="t.reports.expense"
                                :entries="scenario.report.expense"
                                :total-minor-units="
                                    scenario.report.expense_minor_units
                                "
                                :budget-year="2026"
                                :month="null"
                                :range="windowRange(scenario.period.window)"
                            />
                            <ReportBarChart
                                type="income"
                                :entries="scenario.report.income"
                                :budget-year="2026"
                            />
                            <ReportEntryTable
                                type="income"
                                :title="t.reports.income"
                                :entries="scenario.report.income"
                                :total-minor-units="
                                    scenario.report.income_minor_units
                                "
                                :budget-year="2026"
                                :month="null"
                                :range="windowRange(scenario.period.window)"
                            />
                        </template>
                    </template>
                </template>

                <template
                    v-else-if="
                        scenario.kind === 'trend' && scenario.trend !== null
                    "
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <p class="page-title">
                            {{ t.reports.title_trend.replace(':year', '2026') }}
                        </p>
                        <SegmentedControl
                            model-value="trend"
                            :options="periodOptions"
                            :ariaLabel="t.reports.period_label"
                            tone="neutral"
                        />
                    </div>

                    <ReportStatPanel
                        :income-minor-units="scenario.trend.income_minor_units"
                        :expense-minor-units="
                            scenario.trend.expense_minor_units
                        "
                        :result-minor-units="scenario.trend.result_minor_units"
                        period-kind="year"
                        :is-in-progress="scenario.period.is_in_progress"
                    />

                    <IndexEmptyState
                        v-if="!scenario.trend.has_operations"
                        :title="t.reports.no_operations_title"
                        :description="t.reports.no_operations_description"
                    />

                    <template v-else>
                        <ReportTrendSummary :trend="scenario.trend" />
                        <ReportTrendTable
                            type="expense"
                            :title="t.reports.expense"
                            :entries="scenario.trend.expense"
                            :total-months="
                                trendMonths(
                                    scenario.trend.months,
                                    'expense_minor_units',
                                )
                            "
                            :total-minor-units="
                                scenario.trend.expense_minor_units
                            "
                            :in-progress-month="
                                scenario.trend.in_progress_month
                            "
                            :budget-year="2026"
                        />
                        <ReportTrendTable
                            type="income"
                            :title="t.reports.income"
                            :entries="scenario.trend.income"
                            :total-months="
                                trendMonths(
                                    scenario.trend.months,
                                    'income_minor_units',
                                )
                            "
                            :total-minor-units="
                                scenario.trend.income_minor_units
                            "
                            :in-progress-month="
                                scenario.trend.in_progress_month
                            "
                            :budget-year="2026"
                        />
                    </template>
                </template>

                <template v-else-if="scenario.report !== null">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <p class="page-title">
                            {{
                                scenario.period.kind === 'year'
                                    ? t.reports.title_year.replace(
                                          ':year',
                                          '2026',
                                      )
                                    : t.reports.title
                            }}
                        </p>
                        <SegmentedControl
                            :model-value="scenario.period.kind"
                            :options="periodOptions"
                            :ariaLabel="t.reports.period_label"
                            tone="neutral"
                        />
                    </div>

                    <BudgetMonthPicker
                        v-if="
                            scenario.period.kind === 'month' &&
                            scenario.period.month !== null
                        "
                        :budget-year="2026"
                        :viewed-month="scenario.period.month"
                        :month-url="monthUrl"
                    />

                    <ReportStatPanel
                        :income-minor-units="scenario.report.income_minor_units"
                        :expense-minor-units="
                            scenario.report.expense_minor_units
                        "
                        :result-minor-units="scenario.report.result_minor_units"
                        :period-kind="
                            scenario.period.kind === 'month' ? 'month' : 'year'
                        "
                        :is-in-progress="scenario.period.is_in_progress"
                        :comparison="scenario.report.comparison"
                    />

                    <IndexEmptyState
                        v-if="!scenario.report.has_operations"
                        :title="t.reports.no_operations_title"
                        :description="t.reports.no_operations_description"
                    />

                    <template v-else>
                        <ReportBarChart
                            type="expense"
                            :entries="scenario.report.expense"
                            :budget-year="2026"
                            :comparison-year="scenario.report.comparison?.year"
                        />
                        <ReportEntryTable
                            type="expense"
                            :title="t.reports.expense"
                            :entries="scenario.report.expense"
                            :total-minor-units="
                                scenario.report.expense_minor_units
                            "
                            :budget-year="2026"
                            :month="
                                scenario.period.kind === 'month'
                                    ? scenario.period.month
                                    : null
                            "
                            :comparison-year="scenario.report.comparison?.year"
                            :previous-total-minor-units="
                                scenario.report.comparison?.expense_minor_units
                            "
                            :difference-total-minor-units="
                                scenario.report.comparison
                                    ?.expense_difference_minor_units
                            "
                        />
                        <ReportBarChart
                            type="income"
                            :entries="scenario.report.income"
                            :budget-year="2026"
                            :comparison-year="scenario.report.comparison?.year"
                        />
                        <ReportEntryTable
                            type="income"
                            :title="t.reports.income"
                            :entries="scenario.report.income"
                            :total-minor-units="
                                scenario.report.income_minor_units
                            "
                            :budget-year="2026"
                            :month="
                                scenario.period.kind === 'month'
                                    ? scenario.period.month
                                    : null
                            "
                            :comparison-year="scenario.report.comparison?.year"
                            :previous-total-minor-units="
                                scenario.report.comparison?.income_minor_units
                            "
                            :difference-total-minor-units="
                                scenario.report.comparison
                                    ?.income_difference_minor_units
                            "
                        />
                    </template>
                </template>
            </div>
        </section>
    </div>
</template>
