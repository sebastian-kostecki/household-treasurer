/** Mirror of `App\Support\PeriodReport::toArray()` and the Reports page props. */

export type PeriodReportCategory = {
    /** Id of the list Category (not of the budget line). */
    id: number;
    name: string;
    icon: string | null;
    color: string;
    amount_minor_units: number;
    /** Tenths of a percent of the period's expenses; null for income. */
    share_tenths_percent: number | null;
    is_archived: boolean;
    /** Operations in the current period; false for a row that exists only through the comparison. */
    has_operations: boolean;
    /** Amount in the comparison range; null without a comparison or without operations there. */
    previous_minor_units: number | null;
    /** Current minus previous (no operations count as 0); null only without a comparison. */
    difference_minor_units: number | null;
};

export type PeriodReportGroupEntry = {
    kind: 'group';
    id: number;
    name: string;
    amount_minor_units: number;
    share_tenths_percent: number | null;
    has_operations: boolean;
    previous_minor_units: number | null;
    difference_minor_units: number | null;
    categories: PeriodReportCategory[];
};

export type PeriodReportCategoryEntry = PeriodReportCategory & {
    kind: 'category';
};

export type PeriodReportEntry =
    | PeriodReportGroupEntry
    | PeriodReportCategoryEntry;

export type PeriodReportComparison = {
    /** Year of the compared Budget (the selected year minus one). */
    year: number;
    /** First and last day of the compared range, `Y-m-d`. */
    from: string;
    through: string;
    /** True when a period in progress is cut before the end of its equivalent. */
    is_partial: boolean;
    income_minor_units: number;
    expense_minor_units: number;
    result_minor_units: number;
    income_difference_minor_units: number;
    expense_difference_minor_units: number;
    result_difference_minor_units: number;
};

export type PeriodReportData = {
    income_minor_units: number;
    expense_minor_units: number;
    result_minor_units: number;
    /** The report has at least one row, from either range (unlike a row's `has_operations`, which is the current period only). */
    has_operations: boolean;
    comparison: PeriodReportComparison | null;
    income: PeriodReportEntry[];
    expense: PeriodReportEntry[];
};

/** Mirror of `App\Support\ReportTrend::toArray()` (ADR-0077). Cells are minor units; null is a month without an operation. */

export type ReportTrendCategory = {
    /** Id of the list Category (not of the budget line). */
    id: number;
    name: string;
    icon: string | null;
    color: string;
    is_archived: boolean;
    amount_minor_units: number;
    /** 12 cells, January first. */
    months: (number | null)[];
};

export type ReportTrendGroupEntry = {
    kind: 'group';
    id: number;
    name: string;
    amount_minor_units: number;
    months: (number | null)[];
    categories: ReportTrendCategory[];
};

export type ReportTrendEntry =
    | ReportTrendGroupEntry
    | (ReportTrendCategory & { kind: 'category' });

export type ReportTrendMonth = {
    /** 1 to 12. */
    month: number;
    income_minor_units: number | null;
    expense_minor_units: number | null;
    result_minor_units: number | null;
};

export type ReportTrendData = {
    income_minor_units: number;
    expense_minor_units: number;
    result_minor_units: number;
    has_operations: boolean;
    /** Month of today when the Budget year is the current one. */
    in_progress_month: number | null;
    months: ReportTrendMonth[];
    income: ReportTrendEntry[];
    expense: ReportTrendEntry[];
};

/** Mirror of `App\Support\ReportTwelveMonthWindow::toArray()` (ADR-0081); dates are `Y-m-d`. */
export type ReportWindow = {
    /** First and last day of the 12 closed months. */
    from: string;
    through: string;
    /** The stretch of the window covered by Budgets in execution; null when none is. */
    covered_from: string | null;
    covered_through: string | null;
    /** 0 to 12. */
    covered_month_count: number;
    /** The stretch without a Budget in execution; null when the window is fully covered. */
    missing_from: string | null;
    missing_through: string | null;
};

export type ReportPeriod = {
    kind: 'year' | 'month' | 'last-12' | 'trend';
    month: number | null;
    default_month: number;
    is_in_progress: boolean;
    /** Only for `last-12`, otherwise null. */
    window: ReportWindow | null;
};

export type ReportsPageProps = {
    budgetYear: number | null;
    budgetPhase: 'draft' | 'execution' | null;
    period: ReportPeriod;
    report: PeriodReportData | null;
    trend: ReportTrendData | null;
};
