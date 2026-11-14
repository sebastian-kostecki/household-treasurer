<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { useElementSize } from '@vueuse/core';
import { computed, onMounted, ref, watch } from 'vue';
import BudgetMonthPicker from '@/components/BudgetMonthPicker.vue';
import IndexEmptyState from '@/components/IndexEmptyState.vue';
import ReportCoverageNote from '@/components/ReportCoverageNote.vue';
import ReportCategoryPanel from '@/components/ReportCategoryPanel.vue';
import ReportEmptyState from '@/components/ReportEmptyState.vue';
import ReportRanking from '@/components/ReportRanking.vue';
import ReportStatPanel from '@/components/ReportStatPanel.vue';
import ReportTrendSummary from '@/components/ReportTrendSummary.vue';
import ReportTrendTable from '@/components/ReportTrendTable.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { useTranslations } from '@/composables/useTranslations';
import {
    remTokenToPixels,
    reportPanelLayout,
    resolveSelectedCategory,
} from '@/lib/report-category-panel';
import { rankReportCategories } from '@/lib/report-ranking';
import { reportMonthNamesFrom, reportWindowRange } from '@/lib/report-window';
import { reports as reportsRoute } from '@/routes';
import type {
    ReportCategoryPanelData,
    ReportRange,
    ReportRankingMode,
    ReportsPageProps,
} from '@/types/reports';

const props = defineProps<ReportsPageProps>();

const t = useTranslations();
const page = usePage();

/** The Ranking mode travels through the period switch (the Trend has none). */
function rankingQuery(): { ranking?: 'groups' } {
    return props.rankingMode === 'groups' ? { ranking: 'groups' } : {};
}

function yearUrl(): string {
    return reportsRoute({ query: rankingQuery() }).url;
}

function trendUrl(): string {
    return reportsRoute({ query: { view: 'trend' } }).url;
}

function lastTwelveUrl(): string {
    return reportsRoute({ query: { view: 'last-12', ...rankingQuery() } }).url;
}

function monthUrl(month: number): string {
    return reportsRoute({ query: { month, ...rankingQuery() } }).url;
}

/** The query of the current address, so a visit of the client keeps every parameter. */
function currentQuery(): Record<string, string> {
    const query: Record<string, string> = {};

    new URL(page.url, 'http://localhost').searchParams.forEach((value, key) => {
        query[key] = value;
    });

    return query;
}

/** Changes the address and the props in place (a visit of the client, no request, no history entry). */
function replaceInPlace(
    query: Record<string, string>,
    update: (current: ReportsPageProps) => Partial<ReportsPageProps>,
): void {
    router.replace({
        url: reportsRoute({ query }).url,
        props: (current) => ({
            ...current,
            ...update(current as unknown as ReportsPageProps),
        }),
        preserveScroll: true,
        preserveState: true,
        preserveFlash: true,
    });
}

/** Switches the Ranking mode in place: the same address with `ranking` changed, no request. */
function setRankingMode(mode: ReportRankingMode): void {
    if (mode === props.rankingMode) {
        return;
    }

    const query = currentQuery();

    if (mode === 'groups') {
        query.ranking = 'groups';
    } else {
        delete query.ranking;
    }

    replaceInPlace(query, () => ({ rankingMode: mode }));
}

const PANEL_ID = 'report-category-panel';

const shell = ref<HTMLElement | null>(null);
const { width: shellWidth } = useElementSize(shell);
const breakpointPx = ref(960);

/** The breakpoint is the token of `app.css`, the same value the `@container` rule repeats. */
onMounted(() => {
    const styles = getComputedStyle(document.documentElement);
    const px = remTokenToPixels(
        styles.getPropertyValue('--report-panel-breakpoint'),
        parseFloat(styles.fontSize),
    );

    if (px !== null) {
        breakpointPx.value = px;
    }
});

const panelLayout = computed(() =>
    reportPanelLayout(shellWidth.value, breakpointPx.value),
);

const rankedExpense = computed(() =>
    rankReportCategories(props.report?.expense ?? []),
);
const rankedIncome = computed(() =>
    rankReportCategories(props.report?.income ?? []),
);

const hasPanel = computed(
    () =>
        props.period.kind === 'year' &&
        props.categoryPanel !== null &&
        props.report !== null,
);

const selected = computed(() =>
    hasPanel.value
        ? resolveSelectedCategory(
              props.categoryPanel?.requested_category_id ?? null,
              rankedExpense.value,
              rankedIncome.value,
          )
        : null,
);

const selectedItem = computed(() =>
    selected.value === null
        ? undefined
        : props.categoryPanel?.categories.find(
              (item) => item.id === selected.value?.category.id,
          ),
);

const sheetOpen = ref(false);

watch(
    panelLayout,
    (layout) => {
        if (layout !== 'sheet') {
            sheetOpen.value = false;

            return;
        }

        if (props.categoryPanel?.requested_category_id != null) {
            sheetOpen.value = true;
        }
    },
    { immediate: true },
);

/** Without an open sheet in the narrow layout no row is highlighted. */
const highlightedCategoryId = computed(() => {
    if (selected.value === null) {
        return null;
    }

    if (panelLayout.value === 'aside') {
        return selected.value.category.id;
    }

    return panelLayout.value === 'sheet' && sheetOpen.value
        ? selected.value.category.id
        : null;
});

function setRequestedCategory(id: number | null): void {
    const query = currentQuery();

    if (id === null) {
        delete query.category;
    } else {
        query.category = String(id);
    }

    replaceInPlace(query, (current) => ({
        categoryPanel:
            current.categoryPanel === null
                ? null
                : {
                      ...(current.categoryPanel as ReportCategoryPanelData),
                      requested_category_id: id,
                  },
    }));
}

function selectCategory(id: number): void {
    setRequestedCategory(id);

    if (panelLayout.value === 'sheet') {
        sheetOpen.value = true;
    }
}

function onSheetOpenChange(open: boolean): void {
    if (open) {
        return;
    }

    sheetOpen.value = false;
    setRequestedCategory(null);
}

const periodKind = computed({
    get: () => props.period.kind,
    set: (kind: 'year' | 'month' | 'last-12' | 'trend') => {
        if (kind === props.period.kind) {
            return;
        }

        router.get(
            kind === 'year'
                ? yearUrl()
                : kind === 'trend'
                  ? trendUrl()
                  : kind === 'last-12'
                    ? lastTwelveUrl()
                    : monthUrl(props.period.default_month),
            {},
            { preserveScroll: true },
        );
    },
});

const periodOptions = computed(() => [
    { value: 'year' as const, label: t.reports.period_year },
    { value: 'month' as const, label: t.reports.period_month },
    { value: 'last-12' as const, label: t.reports.period_last_12 },
    { value: 'trend' as const, label: t.reports.period_trend },
]);

/** The last-12 window as the server resolved it; `null` in the other views. */
const reportWindow = computed(() =>
    props.period.kind === 'last-12' ? props.period.window : null,
);

const range = computed<ReportRange | null>(() =>
    reportWindow.value === null
        ? null
        : reportWindowRange(
              reportWindow.value,
              reportMonthNamesFrom(t.budget),
              t.reports.window_range,
          ),
);

const windowLabel = computed(() => range.value?.label ?? '');

const hasNoCoverage = computed(
    () =>
        reportWindow.value !== null &&
        reportWindow.value.covered_month_count === 0,
);

const trendMonths = (
    key: 'income_minor_units' | 'expense_minor_units',
): (number | null)[] => props.trend?.months.map((month) => month[key]) ?? [];

const title = computed(() => {
    if (props.budgetYear === null) {
        return t.reports.title;
    }

    if (props.period.kind === 'last-12') {
        return t.reports.title_last_12;
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
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="grid gap-1">
                <h1 class="page-title">{{ title }}</h1>
                <p
                    v-if="budgetYear !== null && period.kind === 'last-12'"
                    class="meta text-muted-foreground"
                    data-testid="report-window-range"
                >
                    {{ windowLabel }}
                </p>
            </div>
            <SegmentedControl
                v-if="budgetYear !== null"
                v-model="periodKind"
                :options="periodOptions"
                :ariaLabel="t.reports.period_label"
                tone="neutral"
            />
        </div>

        <ReportEmptyState v-if="budgetYear === null" kind="no-budget" />

        <ReportEmptyState
            v-else-if="budgetPhase !== 'execution' && period.kind !== 'last-12'"
            kind="draft"
        />

        <template v-else>
            <IndexEmptyState
                v-if="hasNoCoverage"
                :title="t.reports.no_coverage_title"
                :description="t.reports.no_coverage_description"
            />

            <template v-else-if="period.kind === 'trend' && trend !== null">
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

                <ReportCoverageNote
                    v-if="reportWindow !== null"
                    :window="reportWindow"
                />

                <ReportStatPanel
                    :income-minor-units="report.income_minor_units"
                    :expense-minor-units="report.expense_minor_units"
                    :result-minor-units="report.result_minor_units"
                    :period-kind="
                        period.kind === 'trend' ? 'year' : period.kind
                    "
                    :is-in-progress="period.is_in_progress"
                    :comparison="report.comparison"
                />

                <IndexEmptyState
                    v-if="!report.has_operations"
                    :title="t.reports.no_operations_title"
                    :description="t.reports.no_operations_description"
                />

                <div v-else ref="shell" class="report-year-shell">
                    <div
                        class="report-year-grid"
                        :class="{ 'report-year-grid-panel': hasPanel }"
                    >
                        <div class="report-year-main">
                            <ReportRanking
                                type="expense"
                                :title="t.reports.ranking_expense"
                                :entries="report.expense"
                                :mode="rankingMode"
                                show-mode-switch
                                :total-minor-units="report.expense_minor_units"
                                :budget-year="budgetYear"
                                :month="
                                    period.kind === 'month'
                                        ? period.month
                                        : null
                                "
                                :range="range"
                                :comparison-year="report.comparison?.year"
                                :previous-total-minor-units="
                                    report.comparison?.expense_minor_units
                                "
                                :difference-total-minor-units="
                                    report.comparison
                                        ?.expense_difference_minor_units
                                "
                                :selectable="hasPanel"
                                :selected-category-id="highlightedCategoryId"
                                :expand-category-id="
                                    selected?.category.id ?? null
                                "
                                :panel-id="PANEL_ID"
                                @update:mode="setRankingMode"
                                @select="selectCategory"
                            />
                            <ReportRanking
                                type="income"
                                :title="t.reports.ranking_income"
                                :entries="report.income"
                                :mode="rankingMode"
                                :total-minor-units="report.income_minor_units"
                                :budget-year="budgetYear"
                                :month="
                                    period.kind === 'month'
                                        ? period.month
                                        : null
                                "
                                :range="range"
                                :comparison-year="report.comparison?.year"
                                :previous-total-minor-units="
                                    report.comparison?.income_minor_units
                                "
                                :difference-total-minor-units="
                                    report.comparison
                                        ?.income_difference_minor_units
                                "
                                :selectable="hasPanel"
                                :selected-category-id="highlightedCategoryId"
                                :expand-category-id="
                                    selected?.category.id ?? null
                                "
                                :panel-id="PANEL_ID"
                                @select="selectCategory"
                            />
                        </div>

                        <aside
                            v-if="
                                panelLayout !== 'sheet' &&
                                selected !== null &&
                                selectedItem !== undefined &&
                                categoryPanel !== null
                            "
                            class="report-year-aside"
                            :aria-label="t.reports.panel_label"
                        >
                            <ReportCategoryPanel
                                :id="PANEL_ID"
                                :category="selected.category"
                                :type="selected.type"
                                :item="selectedItem"
                                :panel="categoryPanel"
                                :budget-year="budgetYear"
                                :comparison-year="report.comparison?.year"
                            />
                        </aside>
                    </div>

                    <Sheet
                        v-if="
                            panelLayout === 'sheet' &&
                            selected !== null &&
                            selectedItem !== undefined &&
                            categoryPanel !== null
                        "
                        :open="sheetOpen"
                        @update:open="onSheetOpenChange"
                    >
                        <SheetContent side="bottom" class="report-panel-sheet">
                            <SheetTitle class="sr-only">{{
                                selected.category.name
                            }}</SheetTitle>
                            <SheetDescription class="sr-only">{{
                                t.reports.panel_label
                            }}</SheetDescription>
                            <ReportCategoryPanel
                                :id="PANEL_ID"
                                in-sheet
                                :category="selected.category"
                                :type="selected.type"
                                :item="selectedItem"
                                :panel="categoryPanel"
                                :budget-year="budgetYear"
                                :comparison-year="report.comparison?.year"
                            />
                            <SheetClose as-child>
                                <Button variant="outline" class="w-full">{{
                                    t.reports.panel_close
                                }}</Button>
                            </SheetClose>
                        </SheetContent>
                    </Sheet>
                </div>
            </template>
        </template>
    </div>
</template>
