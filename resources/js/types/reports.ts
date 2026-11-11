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

export type ReportPeriod = {
    kind: 'year' | 'month';
    month: number | null;
    default_month: number;
    is_in_progress: boolean;
};

export type ReportsPageProps = {
    budgetYear: number | null;
    budgetPhase: 'draft' | 'execution' | null;
    period: ReportPeriod;
    report: PeriodReportData | null;
};
